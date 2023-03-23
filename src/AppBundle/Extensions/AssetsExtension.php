<?php

namespace AppBundle\Extensions;

use Symfony\Component\Yaml\Yaml;

class AssetsExtension extends \Twig_Extension
{

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('list_assets', array($this, 'listAssets')),
            new \Twig_SimpleFunction('list_files', array($this, 'listFiles')),
            new \Twig_SimpleFunction('file_exists', array($this, 'fileExists')),
            new \Twig_SimpleFunction('merge_files', array($this, 'mergeFiles')),
            new \Twig_SimpleFunction('file_extension', array($this, 'fileExtension')),
        ];
    }

    public function mergeFiles($filesList, $outputFolder, $outputFile)
    {
        $outputFolder = $outputFolder . date("Y") . "-" . date("m") . "/";

        $outputWebPath = $outputFolder . $_ENV["ASSETS_VERSION"] . "_" . $outputFile;
        $outputFilePath = $_ENV["WEB_PATH"] . $outputWebPath;
        if (file_exists($_ENV["WEB_PATH"] . $outputFolder . $_ENV["ASSETS_VERSION"] . "_" . $outputFile)) {
            return $outputFolder . $_ENV["ASSETS_VERSION"] . "_" . $outputFile;
        } else {
            $path = $_ENV["WEB_PATH"] . $outputFolder;
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }

            $data = "";
            foreach ($filesList as $f) {
                $data .= "\n" . file_get_contents($_ENV["WEB_PATH"] . $f);
            }
            $fp = fopen($outputFilePath, 'w');
            if (!$fp)
                die('Could not create / open text file for writing.');
            if (fwrite($fp, $data) === false)
                die('Could not write to text file.');

            return $outputWebPath;
        }
    }

    public function listAssets()
    {
        $webPath = $_ENV["WEB_PATH"];

        $assets = Yaml::parseFile($webPath . "../src/AppBundle/Resources/config/default_assets.yml");

        if (file_exists($webPath . "../app/config/assets.yml")) {
            $projectAssets = Yaml::parseFile($webPath . "../app/config/assets.yml");
            if (!empty($projectAssets)) {
                foreach ($projectAssets as $key => $values) {
                    $assets[$key] = array_unique(array_merge($assets[$key] ?? [], $values ?? []));
                }
            }
        }

        return $assets;
    }

    public function fileExists($relativeWebPath)
    {
        return file_exists($_ENV["WEB_PATH"] . $relativeWebPath);
    }

    public function listFiles($dir, $listSubdirectories = false)
    {
        $webPath = $_ENV["WEB_PATH"];

        if ($listSubdirectories) {
            $files = $this->getDirContents($webPath, $dir);
        } else {
            $files = scandir($dir);
            foreach ($files as $key => $value) {
                if ($value == "." || $value == "..") {
                    unset($files[$key]);
                    continue;
                }
                $files[$key] = $dir . "/" . $value;
            }
        }

        return $files;
    }

    private function getDirContents($webPath, $dir, &$results = array())
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = scandir($dir);
        foreach ($files as $key => $value) {
            if ($value == "." || $value == "..") {
                continue;
            }

            $path = realpath($webPath . $dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path) && is_file($path)) {
                $results[] = $dir . DIRECTORY_SEPARATOR . $value;
            } else {
                $this->getDirContents($webPath, $dir . DIRECTORY_SEPARATOR . $value, $results);
            }
        }

        return $results;
    }

    public function fileExtension($filename)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return $ext;
    }
}
