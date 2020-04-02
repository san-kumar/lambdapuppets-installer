<?php
/**
 * Created by PhpStorm.
 * User: san
 * Date: 24/10/17
 * Time: 5:42 PM
 */

namespace Console\Command {

    use Aws\ApiGateway\ApiGatewayClient;
    use Aws\CloudWatchEvents\CloudWatchEventsClient;
    use Aws\Exception\AwsException;
    use Aws\Iam\IamClient;
    use Aws\Lambda\LambdaClient;
    use Console\App\Config;
    use Console\Utils\AwsConfig;
    use Console\Utils\ZipMaker;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;
    use function call_user_func_array;
    use function filter_var;
    use function pathinfo;
    use const FILTER_VALIDATE_BOOLEAN;
    use const PATHINFO_FILENAME;

    class Deploy extends Command {
        /**
         * @var Config
         */
        private $config;
        /**
         * @var ZipMaker
         */
        private $zipMaker;
        /**
         * @var \Console\Utils\AwsConfig
         */
        private AwsConfig $awsConfig;

        /**
         * Deploy constructor.
         *
         * @param Config    $config
         * @param ZipMaker  $zipMaker
         * @param AwsConfig $awsConfig
         *
         * @internal param Finder $finder
         */
        public function __construct(Config $config, ZipMaker $zipMaker, AwsConfig $awsConfig) {
            parent::__construct();

            $this->config = $config;
            $this->zipMaker = $zipMaker;
            $this->awsConfig = $awsConfig;
        }

        protected function configure() {
            $this
                ->setName('deploy')
                ->setDescription('Deploy all php files to AWS lambda and generate links')
                ->setHelp('This command will deploy all your PHP files to AWS lambda and create web accessible links for them')
                ->addOption('rebuild', 'r', InputOption::VALUE_OPTIONAL, 'Force rebuild?');
        }

        protected function execute(InputInterface $input, OutputInterface $output) {
            if ($dir = $this->config->getBaseDir()) {
                $debug = function (...$params) use ($output) {
                    $output->isVerbose() ? $output->writeln(call_user_func_array('sprintf', $params)) : NULL;
                };

                if (!is_dir($dir = realpath($this->config->getBaseDir() . '/puppets')))
                    die("please create '$dir/puppets' directory first");

                if (empty($awsConfig = $this->awsConfig->getAwsConfig())) {
                    $output->writeln("Please configure AWS credentials before deployment!\n");
                    $command = $this->getApplication()->find('config');
                    $command->run($input, $output);
                }

                $pupFiles = glob("$dir/*.js");
                $config = $this->config->readSection('default', []);

                foreach ((array) $pupFiles as $pupFile) {
                    $pName = pathinfo($pupFile, PATHINFO_FILENAME);
                    $pConfig = (array) $this->config->readSection($pName);
                    $pConfig['enabled'] = filter_var($pConfig['enabled'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

                    $debug("Adding puppet: $pName");

                    if (!empty($pConfig['cron'])) $puppets['cron'][$pName] = $pConfig;
                    elseif ($pConfig['enabled']) $puppets['web'][$pName] = $pConfig;
                }

                $projName = $this->config->getProjectName();
                $zipFile = tempnam(sys_get_temp_dir(), 'lambda') . '.zip';
                $debug("Creating zipFile: $zipFile");

                if ($zipFile = $this->zipMaker->zip($zipFile, [$dir, realpath(__DIR__ . '/../../wrapper')])) {
                    $fnName = sprintf('lambda-puppet-%s', $projName);
                    $lambdaClient = new LambdaClient($awsConfig);

                    try {
                        $result = $lambdaClient->getFunction(['FunctionName' => $fnName]);
                        $lambdaFn = $result->get('Configuration');
                        $debug("Updating lambda function '%s' (%d bytes)", $fnName, filesize($zipFile));

                        $lambdaClient->updateFunctionCode([
                            'FunctionName' => $fnName,
                            'ZipFile'      => file_get_contents($zipFile),
                            'Publish'      => TRUE,
                        ]);
                    } catch (\Throwable $e) {
                        $debug("Creating new lambda function (%s)", $fnName);

                        $iam = new IamClient($awsConfig);
                        $role = "lambdaPuppetsRole";

                        $debug("Setting up IAM permissions");

                        try {
                            $roleObj = $iam->getRole(['RoleName' => $role]);
                            $debug("Found existing IAM role");
                        } catch (\Exception $e) {
                            $debug("Creating new IAM role");
                            $roleObj = $iam->createRole(['RoleName' => $role, 'AssumeRolePolicyDocument' => json_encode(['Version' => '2012-10-17', 'Statement' => [['Effect' => 'Allow', 'Principal' => ['Service' => 'lambda.amazonaws.com',], 'Action' => 'sts:AssumeRole',],],]),]);
                            $iam->putRolePolicy([
                                'PolicyDocument' => $this->awsConfig->getPolicies(), // REQUIRED
                                'PolicyName'     => "lambdaPuppetsPolicy", // REQUIRED
                                'RoleName'       => $role, // REQUIRED
                            ]);

                            $debug("waiting for IAM permissions to propagate (one time only)");
                            sleep(10); //aws bug (https://stackoverflow.com/a/37438525/1031454)
                        }

                        $lambdaFn = $lambdaClient->createFunction([
                            'FunctionName' => $fnName,
                            'Runtime'      => 'nodejs12.x',
                            'Role'         => $roleObj->get('Role')['Arn'],
                            'Handler'      => 'index.handler',
                            'Code'         => ['ZipFile' => file_get_contents($zipFile),],
                            'Layers'       => [$this->awsConfig->getPuppeteerLayer()],
                            'Timeout'      => $config['timeout'] ?? 120,
                            'MemorySize'   => round((($config['ram'] ?? 0) >= 128) ? $config['ram'] : 512),
                            'Environment'  => ['Variables' => ['NODE_PATH' => '/opt/node_modules:/opt/nodejs/node12/node_modules:/opt/nodejs/node_modules:/var/runtime/node_modules:/var/runtime:/var/task']],
                            'Publish'      => TRUE,
                        ]);

                        $debug("Setting permissions for lambda function");

                        $lambdaClient->addPermission([
                            'FunctionName' => $fnName,
                            'StatementId'  => 'ApiInvokeAccess',
                            'Action'       => 'lambda:InvokeFunction',
                            'Principal'    => 'apigateway.amazonaws.com',
                        ]);

                        $lambdaClient->addPermission([
                            'FunctionName' => $fnName,
                            'StatementId'  => "CronInvokeAccess",
                            'Action'       => 'lambda:InvokeFunction',
                            'Principal'    => 'events.amazonaws.com',
                        ]);
                    }

                    $debug("Updating %d cron jobs..", count($puppets['cron'] ?? []));
                    $cloudWatchEventsClient = new CloudWatchEventsClient($awsConfig);
                    $result = $cloudWatchEventsClient->ListRuleNamesByTarget(['TargetArn' => $lambdaFn['FunctionArn'],]);

                    foreach ((array) $result->get('RuleNames') as $ruleName) {
                        $result = $cloudWatchEventsClient->listTargetsByRule(['Rule' => $ruleName]);
                        $targets = array_map(function ($t) { return $t['Id']; }, $result->get('Targets') ?? []);

                        if (!empty($targets))
                            $cloudWatchEventsClient->removeTargets(['Ids' => $targets, 'Rule' => $ruleName]);

                        $cloudWatchEventsClient->deleteRule(['Name' => $ruleName]);
                    }

                    foreach ($puppets['cron'] ?? [] as $pName => $pConfig) {
                        $debug("Creating / Updating cron job for $pName");

                        $cwName = sprintf('lambdapuppets-%s', $pName);
                        try {

                            $rule = $cloudWatchEventsClient->putRule([
                                'Name'               => $cwName, // REQUIRED
                                'ScheduleExpression' => sprintf('cron(%s)', trim($pConfig['cron'])),
                                'State'              => $pConfig['enabled'] ? 'ENABLED' : 'DISABLED',
                            ]);

                            $result = $cloudWatchEventsClient->putTargets([
                                'Rule'    => $cwName,
                                'Targets' => [[
                                    'Arn'   => $lambdaFn['FunctionArn'],
                                    'Id'    => $projName,
                                    'Input' => json_encode(['config' => $pConfig, 'path' => "/$pName", 'cron' => TRUE]),
                                ]],
                            ]);

                            $debug("Successfully added cron job for %s", $pName);
                        } catch (AwsException $e) {
                            $output->writeln("ERROR creating cron job for $pName: " . $e->getAwsErrorMessage());
                        }
                    }

                    $apiClient = new ApiGatewayClient($awsConfig);
                    $apiName = "$fnName lambdapuppets";

                    $apis = $apiClient->getIterator('GetRestApis');

                    foreach ($apis as $api) {
                        if (strcasecmp($api['name'], $apiName) === 0) {
                            $apiId = $api['id'];
                            break;
                        }
                    }

                    $debug("Updating %d web puppets..", count($puppets['web'] ?? []));

                    if (empty($puppets['web']) && !empty($apiId)) {
                        $debug('Removing API gateway %s as 0 web puppets found', $apiId);
                        $apiClient->deleteRestApi(['restApiId' => $apiId]);
                    } elseif (!empty($puppets['web'])) {
                        if (empty($apiId)) {
                            $debug("Creating API gateway access for %d web puppets", count($puppets['web']));

                            $result = $apiClient->createRestApi(['name' => $apiName, 'binaryMediaTypes' => ['*/*']]);
                            $apiId = $result->get('id');

                            $debug('Connecting REST API to Lambda function');

                            $resources = $apiClient->getIterator('GetResources', ['restApiId' => $apiId]);
                            $createMethod = function ($parentId) use ($apiClient, $apiId, $config, $lambdaFn, $debug, $awsConfig) {
                                $debug("Creating API methods for $parentId");

                                $apiClient->putMethod([
                                    'apiKeyRequired'    => FALSE,
                                    'authorizationType' => 'NONE',
                                    'httpMethod'        => 'ANY',
                                    'resourceId'        => $parentId,
                                    'restApiId'         => $apiId,
                                ]);

                                $apiClient->putIntegration([
                                    'httpMethod'            => 'ANY',
                                    'resourceId'            => $parentId,
                                    'restApiId'             => $apiId,
                                    'type'                  => 'AWS_PROXY',
                                    'integrationHttpMethod' => 'POST',
                                    'uri'                   => sprintf('arn:aws:apigateway:%s:lambda:path/2015-03-31/functions/%s/invocations', $awsConfig['region'], $lambdaFn['FunctionArn']),
                                ]);
                            };

                            foreach ($resources as $resource) {
                                if ($resource['path'] == '/') {
                                    $parentId = $resource['id'];

                                    if (empty($resource['resourceMethods'])) {
                                        $createMethod($parentId);
                                    }

                                    break;
                                }
                            }

                            if (!empty($parentId)) {
                                $result = $apiClient->createResource([
                                    'parentId'  => $parentId,
                                    'pathPart'  => '{proxy+}',
                                    'restApiId' => $apiId,
                                ]);

                                $createMethod($result['id']);
                            }

                            $debug("Creating deployment for Rest API");

                            $apiClient->createDeployment(['restApiId' => $apiId, 'stageName' => 'web',]);
                        }

                        $uri = sprintf('https://%s.execute-api.%s.amazonaws.com/%s', $apiId, $awsConfig['region'], 'web');
                        $output->writeln("Your puppets are alive!\n\nTo talk to your puppets just visit these URLs:");

                        foreach ($puppets['web'] as $pName => $pConfig) {
                            $output->writeln(sprintf("%s: %s/%s", $pName, $uri, $pName));
                        }
                    }
                }
            }
        }
    }
}