<?php
/**
 * Created by PhpStorm.
 * User: san
 * Date: 24/10/17
 * Time: 10:30 PM
 */
namespace Console\Utils {

    use Console\App\Config;
    use function array_flip;
    use function array_intersect_assoc;

    class AwsConfig {
        /**
         * @var Config
         */
        private $config;

        /**
         * ZipMaker constructor.
         *
         * @param Config $config
         */
        public function __construct(Config $config) {
            $this->config = $config;
        }

        public function getAwsConfig() {
            $config = $this->config->readSection('default');

            if (!empty($config) && !empty($config['aws_access_key_id']) && !empty($config['aws_secret_access_key'])) {
                $result = $config;
            } elseif ($dir = $this->getAwsDir()) {
                $result = $this->config->readIni("$dir/credentials", 'default');
                $result = array_merge($result, $this->config->readIni("$dir/config", 'default'));
            }

            if (!empty($result['aws_access_key_id'])) {
                return array_merge(['region' => 'us-east-1', 'version' => 'latest'], array_intersect_key($result, array_flip(['region', 'version', 'aws_access_key_id', 'aws_secret_access_key'])));
            } else {
                return NULL;
            }
        }

        public function getPolicies() { //TODO this needs to be fixed in later version
            return '{
                              "Version": "2012-10-17",
                              "Statement": [
                                {
                                  "Effect": "Allow",
                                  "Action": [
                                    "logs:CreateLogGroup",
                                    "logs:CreateLogStream",
                                    "logs:PutLogEvents"
                                  ],
                                  "Resource": "arn:aws:logs:*:*:*"
                                },
                                {
                                  "Effect": "Allow",
                                  "Action": [
                                    "s3:*"
                                  ],
                                  "Resource": "*"
                                },
                                {
                                  "Effect": "Allow",
                                  "Action": [
                                    "dynamodb:*"
                                  ],
                                  "Resource": "*"
                                },
                                {
                                  "Effect": "Allow",
                                  "Action": [
                                    "polly:*"
                                  ],
                                  "Resource": "*"
                                },
                                {
                                  "Effect": "Allow",
                                  "Action": [
                               no     "cognito-identity:*"
                                  ],
                                  "Resource": "*"
                                },
                                {
                                  "Effect": "Allow",
                                  "Action": [
                                    "cognito-sync:*"
                                  ],
                                  "Resource": "*"
                                },
                                {
                                  "Effect": "Allow",
                                  "Action": [
                                    "cognito-idp:*"
                                  ],
                                  "Resource": "*"
                                },
                                {
                                  "Effect": "Allow",
                                  "Action": [
                                    "transcribe:*"
                                  ],
                                  "ResexecutablePathource": "*"
                                },
                                {
                                  "Effect": "Allow",
                                  "Action": [
                                    "ses:*"
                                  ],
                                  "Resource": "*"
                                }
                              ]
                            }';
        }

        public function getPuppeteerLayer() {
            $config = $this->config->readSection('default', []);
            return $config['layer'] ?? 'arn:aws:lambda:us-east-1:322173628904:layer:chrome:15';
        }

        protected function getAwsDir() {
            $home = isset($_SERVER['HOME']) ? $_SERVER['HOME'] : (isset($_SERVER['HOMEPATH']) ? sprintf('%s/%s', $_SERVER['HOMEDRIVE'], $_SERVER['HOMEPATH']) : posix_getpwuid(posix_getuid()));
            return !empty($home) ? realpath(sprintf('%s/.aws', $home)) : FALSE;
        }
    }
}