<?php
/**
 * Created by PhpStorm.
 * User: san
 * Date: 24/10/17
 * Time: 10:30 PM
 */
namespace Console\Utils {

    use Console\App\Config;
    use Symfony\Component\Finder\Finder;
    use ZipArchive;
    use function filesize;

    class ZipMaker {
        /**
         * @var Config
         */
        private $config;
        /**
         * @var \Symfony\Component\Finder\Finder
         */
        private Finder $finder;

        /**
         * ZipMaker constructor.
         *
         * @param Config $config
         * @param Finder $finder
         */
        public function __construct(Config $config, Finder $finder) {
            $this->config = $config;
            $this->finder = $finder;
        }

        public function zip(string $zipFile, array $dirs) {
            //return '/tmp/lambda4iECaH.zip';
            $zip = new ZipArchive();
            $res = $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($res === TRUE) {
                $finder = new Finder();
                $finder->size('< 50M')->ignoreVCS(TRUE);

                /** @var \SplFileInfo $file */
                foreach ($finder->in($dirs) as $file) {
                    if (is_file($file)) {
                        $zip->addFile($file->getRealPath(), $file->getRelativePathname());
                    }
                }

                $zip->close();
            }

            return @filesize($zipFile) > 0 ? $zipFile : FALSE;
        }
    }
}