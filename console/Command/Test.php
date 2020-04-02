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
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use function basename;

    class Test extends Command {
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
                ->setName('test')
                ->setDescription('This command will run your puppet locally')
                ->setHelp('llp test -n script_name')
                ->addArgument('n', InputArgument::REQUIRED, 'Name of puppet');
        }

        protected function execute(InputInterface $input, OutputInterface $output) {
            $debug = function (...$params) use ($output) {
                $output->isVerbose() ? $output->writeln(call_user_func_array('sprintf', $params)) : NULL;
            };

            $name = preg_replace('/\.js$/', '', $input->getArgument('n'));

            if ($fPath = realpath(sprintf('%s/puppets/%s.js', $this->config->getBaseDir(), $name))) {
                $testDir = sprintf('%s/test', $this->config->getBaseDir());

                if (!is_dir($testDir))
                    mkdir($testDir, 0777, TRUE);

                if (!is_dir("$testDir/node_modules/puppeteer")) {
                    $debug("Puppeteer not found");

                    copy(__DIR__ . '/../../config/package.json', "$testDir/package.json");
                    $debug("Installing puppeteer in $testDir");
                    system("npm install -C \"$testDir\" || yarn --cwd \"$testDir\" install");
                }

                $debug("Copying runtime files");
                foreach (glob(__DIR__ . '/../../wrapper/*.js') as $file) {
                    copy($file, "$testDir/" . basename($file));
                }

                system(sprintf('node "%s" "%s"', "$testDir/test.js", $fPath));
            } else {
                $output->writeln("Script $name not found in puppets dir");
                exit(0);
            }
        }
    }
}
