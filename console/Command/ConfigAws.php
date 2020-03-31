<?php
/**
 * Created by PhpStorm.
 * User: san
 * Date: 24/10/17
 * Time: 5:42 PM
 */

namespace Console\Command {

    use Console\App\Config;
    use Console\Utils\AwsConfig;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;

    class ConfigAws extends Command {
        /**
         * @var Config
         */
        private $config;
        /**
         * @var \Console\Utils\AwsConfig
         */
        private AwsConfig $awsConfig;

        /**
         * ConfigAws constructor.
         *
         * @param Config    $config
         * @param AwsConfig $awsConfig
         */
        public function __construct(Config $config, AwsConfig $awsConfig) {
            parent::__construct();
            $this->config = $config;
            $this->awsConfig = $awsConfig;
        }

        protected function configure() {
            $this
                ->setName('config')
                ->setDescription('Configure AWS access settings')
                ->setHelp('This command helps to configure aws settings to access your AWS account (see AWS IAM)')
                ->addOption('key', '', InputOption::VALUE_REQUIRED, 'Your AWS Access Key ID')
                ->addOption('secret', '', InputOption::VALUE_REQUIRED, 'Your AWS Secret Access Key')
                ->addOption('region', '', InputOption::VALUE_REQUIRED, 'Your default region name (us-east-1)', '')
                ->addOption('name', '', InputOption::VALUE_REQUIRED, 'Your project name (or identifier)', '');
        }

        protected function execute(InputInterface $input, OutputInterface $output) {
            $aws = $this->awsConfig->getAwsConfig();
            $keys = ['aws_access_key_id' => 'AWS Access Key ID', 'aws_secret_access_key' => 'AWS Access Key Secret', 'region' => 'AWS default region name', 'name' => 'Project name'];

            $output->writeln("Configure AWS default profile");
            $output->writeln("http://docs.aws.amazon.com/cli/latest/userguide/cli-chap-getting-started.html\n");

            foreach ($keys as $key => $label) {
                $value = $aws[$key] ?? '';
                $output->writeln("Enter $label" . (!empty($value) ? " [$value]:" : ':'));
                $line = fgets(STDIN);
                $conf[$key] = trim($line) ?: $value;
            }

            return !empty($conf['aws_access_key_id']) && !empty($conf['aws_secret_access_key']) ? $this->config->writeSection('default', $conf) : FALSE;
        }
    }
}