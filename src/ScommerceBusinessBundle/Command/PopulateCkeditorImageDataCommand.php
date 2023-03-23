<?php

// php bin/console image:function populate_data product_entity description name

namespace ScommerceBusinessBundle\Command;

use AppBundle\Context\DatabaseContext;
use AppBundle\Managers\HelperManager;
use DOMDocument;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class PopulateCkeditorImageDataCommand extends ContainerAwareCommand
{
    /** @var DatabaseContext */
    protected $databaseContext;

    protected function configure()
    {
        $this->setName('image:function')
            ->SetDescription('Helper image commands')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' which function ')
            ->AddArgument('arg1', InputArgument :: OPTIONAL, ' which arg1 ')
            ->AddArgument('arg2', InputArgument :: OPTIONAL, ' which arg2 ')
            ->AddArgument('arg3', InputArgument :: OPTIONAL, ' which arg3 ');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**@var Logger $logger */
        $logger = $this->getContainer()->get('logger');

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");

        /**
         * Check which function
         */
        $func = $input->getArgument('type');
        if (empty($func)) {
            throw new \Exception('Function not defined');
        }

        $arg1 = $input->getArgument('arg1'); #table
        $arg2 = $input->getArgument('arg2'); #column
        $arg3 = $input->getArgument('arg3'); #populate with column

        if ($func == "populate_data") {

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->getContainer()->get("database_context");
            }

            $query = "SELECT * FROM {$arg1} WHERE {$arg2} LIKE '%<img%'";
            $rows = $this->databaseContext->getAll($query);

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $this->populateImageWithAltAndTitle($arg1, $row, $arg2, $arg3);
                }
            }
        } else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }

        return false;
    }

    private function populateImageWithAltAndTitle($table, $row, $column, $populateWithColumn)
    {
//        if ($row["id"] != 572053) {
//            return;
//        }
        if (!isset($row[$column])) {
            print("Column {$column} not set!");
        }
        if (!isset($row[$populateWithColumn])) {
            print("Column {$populateWithColumn} not set!");
        }

        $names = json_decode($row[$populateWithColumn], true);
        if (json_last_error() == JSON_ERROR_NONE) {

        } else {
            $names = $row[$populateWithColumn];
        }
        $update = false;
        try {
            $data = json_decode($row[$column], true);
            if (json_last_error() == JSON_ERROR_NONE) {
                // Is json
                foreach ($data as $storeId => $html) {
                    $html = str_replace("<elem>", "", $html);
                    $html = str_replace("</elem>", "", $html);
                    $dom = new DOMDocument();
                    $dom->loadHTML('<?xml version="1.0" encoding="utf-8"?><html>' . $html . '</html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    $images = $dom->getElementsByTagName('img');
                    foreach ($images as $image) {
                        if (is_array($names)) {
                            if (isset($names[$storeId])) {
                                $name = $names[$storeId];
                            } else {
                                $name = array_values($names)[0];
                            }
                        } else {
                            $name = $names;
                        }
                        if (empty($image->getAttribute('alt'))) {
                            $image->setAttribute("alt", $name);
                            $update = true;
                        }
                        if (empty($image->getAttribute('title'))) {
                            $image->setAttribute("title", $name);
                            $update = true;
                        }
                        if (!empty($image->getAttribute('src'))) {
                            $url = $image->getAttribute('src');
                            if ($this->isAbsoluteUrl($url)) {
                                echo "\nDownloading image $url";
                                $relativePath = $this->convertAbsoluteImageToRelative($url);
                                $image->setAttribute('src', $relativePath);
                                $update = true;
                            }
                        }
                    }
                    $data[$storeId] = "";
                    foreach ($dom->documentElement->childNodes as $child) {
                        $data[$storeId] .= $dom->saveHTML($child);
                    }
                }
                $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            } else {
                $html = $row[$column];
                $html = str_replace("<elem>", "", $html);
                $html = str_replace("</elem>", "", $html);
                $dom = new DOMDocument();
                $dom->loadHTML('<?xml version="1.0" encoding="utf-8"?><html>' . $html . '</html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                $images = $dom->getElementsByTagName('img');
                foreach ($images as $image) {
                    if (is_array($names)) {
                        $name = array_values($names)[0];
                    } else {
                        $name = $names;
                    }
                    if (empty($image->getAttribute('alt'))) {
                        $image->setAttribute("alt", $name);
                        $update = true;
                    }
                    if (empty($image->getAttribute('title'))) {
                        $image->setAttribute("title", $name);
                        $update = true;
                    }
                    if (!empty($image->getAttribute('src'))) {
                        $url = $image->getAttribute('src');
                        if ($this->isAbsoluteUrl($url)) {
                            $relativePath = $this->convertAbsoluteImageToRelative($url);
                            $image->setAttribute('src', $relativePath);
                            $update = true;
                        }
                    }
                }
                $data = "";
                $dom->saveHTML();
                foreach ($dom->documentElement->childNodes as $child) {
                    $data .= $dom->saveHTML($child);
                }
            }
            if ($update) {
                print "\nUpdating {$table} - {$row["id"]}";
                $data = addslashes($data);
                $query = "UPDATE {$table} SET {$column}='{$data}' WHERE id={$row["id"]};";
                $this->databaseContext->executeNonQuery($query);
            }
        } catch (\Exception $e) {
            print "\n\tFailed to update {$table} - {$row["id"]}: {$e->getMessage()}";
        }
    }

    private function convertAbsoluteImageToRelative($url)
    {
        $dir = $this->getDirectoryPath();

        $filename = basename($url);

        $imgFullPath = "/$dir/$filename";
        file_put_contents($imgFullPath, file_get_contents($url));

        $relativeUrl = str_replace($_ENV["WEB_PATH"], "", $imgFullPath);

        return "/$relativeUrl";
    }

    /**
     * @return string|string[]
     */
    private function getDirectoryPath()
    {
        $path = $_ENV["WEB_PATH"] . "/Documents/relative_ckeditor";
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
        return str_replace("//", "/", $path);
    }

    private function isAbsoluteUrl($url)
    {
        $pattern = "/^(?:ftp|https?|feed)?:?\/\/(?:(?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*
        (?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@)?(?:
        (?:[a-z0-9\-\.]|%[0-9a-f]{2})+|(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\]))(?::[0-9]+)?(?:[\/|\?]
        (?:[\w#!:\.\?\+\|=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})*)?$/xi";

        return (bool)preg_match($pattern, $url);
    }
}
