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
    use Aws\Lambda\LambdaClient;
    use Console\App\Config;
    use Console\Utils\AwsConfig;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    class Remove extends Command {
        /**
         * @var Config
         */
        private $config;
        /**
         * @var \Console\Utils\AwsConfig
         */
        private AwsConfig $awsConfig;

        /**
         * Deploy constructor.
         *
         * @param Config    $config
         * @param AwsConfig $awsConfig
         *
         * @internal param Finder $finder
         */
        public function __construct(Config $config, AwsConfig $awsConfig) {
            parent::__construct();

            $this->config = $config;
            $this->awsConfig = $awsConfig;
        }

        protected function configure() {
            $this
                ->setName('remove')
                ->setDescription('Frees all AWS resources created by lambdaphp')
                ->setHelp('This command will delete all lambda functions, api gateways and policies created by lambdaphp');
        }

        protected function execute(InputInterface $input, OutputInterface $output) {
            $debug = function (...$params) use ($output) {
                $output->isVerbose() ? $output->writeln(call_user_func_array('sprintf', $params)) : NULL;
            };

            $awsConfig = $this->awsConfig->getAwsConfig();
            $fnName = sprintf('lambda-puppet-%s', $this->config->getProjectName());
            $lambdaClient = new LambdaClient($awsConfig);

            try {
                $result = $lambdaClient->getFunction(['FunctionName' => $fnName]);
                $lambdaFn = $result->get('Configuration');
            } catch (\Exception $e) {
            }

            try {
                if (!empty($lambdaFn['FunctionArn'])) {
                    $debug("Removing cron jobs");

                    $cloudWatchEventsClient = new CloudWatchEventsClient($awsConfig);
                    $result = $cloudWatchEventsClient->ListRuleNamesByTarget(['TargetArn' => $lambdaFn['FunctionArn'],]);

                    foreach ((array) $result->get('RuleNames') as $ruleName) {
                        $result = $cloudWatchEventsClient->listTargetsByRule(['Rule' => $ruleName]);
                        $targets = array_map(function ($t) { return $t['Id']; }, $result->get('Targets') ?? []);

                        if (!empty($targets))
                            $cloudWatchEventsClient->removeTargets(['Ids' => $targets, 'Rule' => $ruleName]);

                        $cloudWatchEventsClient->deleteRule(['Name' => $ruleName]);
                    }
                }
            } catch (\Exception $e) {
            }

            try {
                $debug("Removing lambda function");
                $lambdaClient->deleteFunction(['FunctionName' => $fnName]);
            } catch (\Exception $e) {
            }

            try {
                $apiClient = new ApiGatewayClient($awsConfig);
                $apiName = "$fnName lambdapuppets";
                $apis = $apiClient->getIterator('GetRestApis');

                foreach ($apis as $api) {
                    if (strcasecmp($api['name'], $apiName) === 0) {
                        $debug('Removing API gateway');
                        $apiClient->deleteRestApi(['restApiId' => $api['id']]);
                    }
                }
            } catch (\Exception $e) {
                $debug("Error removing API gateway (are you using custom domain?)\n" . $e->getMessage());
            }
        }
    }
}
