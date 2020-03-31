<?php

namespace Console\App {

    use Matomo\Ini\IniReader;
    use Matomo\Ini\IniWriter;
    use function basename;
    use function file_exists;

    class Config {
        /**
         * @var string
         */
        private $baseDir;
        /**
         * @var IniReader
         */
        private $reader;
        /**
         * @var IniWriter
         */
        private $writer;

        private $iniFile = 'lambdapuppets.ini';

        /**
         * Config constructor.
         *
         * @param string    $baseDir
         * @param IniReader $reader
         * @param IniWriter $writer
         */
        public function __construct($baseDir, IniReader $reader, IniWriter $writer) {
            $this->baseDir = $baseDir;
            $this->reader = $reader;
            $this->writer = $writer;
        }

        /**
         * @return string
         */
        public function getBaseDir(): string {
            return $this->baseDir;
        }

        /**
         * @param string $section
         * @param null   $default
         *
         * @return array
         */
        public function readSection(string $section, $default = NULL): ?array {
            return $this->readIni($this->getIniFile(), $section, $default);
        }

        public function writeSection(string $section, array $values) {
            return $this->writeIni($this->getIniFile(), $section, $values);
        }

        public function readIni($iniFile, $section, $default = NULL): ?array {
            if (\file_exists($iniFile)) {
                $reader = new IniReader();
                $data = $reader->readFile($iniFile);
                $result = !empty($section) && !empty($data[$section]) ? $data[$section] : $data;
            }

            return !empty($result) ? $result : $default;
        }

        public function writeIni($iniFile, $section, $values): bool {
            echo $iniFile, "\n";
            $data = $this->readIni($iniFile, NULL, []);
            $data[$section] = $values;
            $writer = new IniWriter();
            $writer->writeToFile($iniFile, $data);

            return file_exists($iniFile);
        }

        public function getProjectName() {
            $conf = $this->readSection('default', []);
            return $conf['name'] ?? basename($this->getBaseDir());
        }

        protected function getIniFile() {
            return sprintf("%s/%s", $this->getBaseDir(), $this->iniFile);
        }
    }
}
