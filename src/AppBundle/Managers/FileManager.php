<?php

namespace AppBundle\Managers;

use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\FileHelper;
use Doctrine\Common\Util\Inflector;
use Knp\Bundle\SnappyBundle\KnpSnappyBundle;
use Knp\Snappy\GeneratorInterface;
use ZipArchive;

class FileManager extends FormManager
{
    /** @var HelperManager $helperManager */
    protected $helperManager;
    protected $webPath;

    public function initialize()
    {
        parent::initialize();
        $this->helperManager = $this->getContainer()->get("helper_manager");
        $this->webPath = $_ENV["WEB_PATH"];
    }

    /**
     * @param $filename
     * @param $addHash
     * @param $html
     * @param $headerHtml
     * @param $footerHtml
     * @param null $fileEntity
     * @param null $documentEntityFileAttribute
     * @param null $parentEntity
     * @param bool $returnWebPath
     * @param string $orientation -> Landscape | Portrait
     * @return bool|mixed|string|null
     */
    public function saveFileWithPDF($filename,
                                    $addHash,
                                    $html,
                                    $headerHtml = null,
                                    $footerHtml = null,
                                    $fileEntity = null,
                                    $documentEntityFileAttribute = null,
                                    $parentEntity = null,
                                    $returnWebPath = false,
                                    $orientation = "Portrait")
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        /** @var GeneratorInterface $snappy */
        $snappy = $this->container->get("knp_snappy.pdf");

        if (is_string($fileEntity)) {
            $fileEntity = $this->entityManager->getNewEntityByAttributSetName($fileEntity);
            if (empty($fileEntity)) {
                return false;
            }
        } else if (is_object($fileEntity)) {
            if (empty($fileEntity->getId())) {
                return false;
            }
        }

        if (!empty($documentEntityFileAttribute)) {
            $relatedEntityId = null;
            if(!empty($parentEntity)){
                $relatedEntityId = $parentEntity->getId();
            }

            $folder = $this->getTargetPath($documentEntityFileAttribute->getFolder(),$relatedEntityId);
        } else {
            $folder = "/tmp/";
        }

        $targetPath = $this->webPath . $folder;

        $filename = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $filename . ".pdf");
        $filename = mb_ereg_replace("([\.]{2,})", '', $filename);
        $filename = FileHelper::generateFilenameFromString($filename);
        if ($addHash) {
            $filename = FileHelper::addHashToFilename($filename);
        }

        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0777, true);
        }

        $targetFile = $targetPath . $filename;
        if (file_exists($targetFile)) {
            unlink($targetFile);
        }

        if (stripos($targetFile, "//")) {
            $targetFile = str_ireplace("//", "/", $targetFile);
        }

        //$snappy->setOption("footer-right", "[page]");
        $snappy->generateFromHtml(
            $html,
            $targetFile,
            array(
                "header-html" => $headerHtml,
                "footer-html" => $footerHtml,
                "orientation" => $orientation,
                "page-size" => "A4",
                "disable-smart-shrinking" => true,
                "enable-local-file-access" => true
            )
        );

        $extension = $this->helperManager->getFileExtension($filename);
        $name = $this->helperManager->getFilenameWithoutExtension($filename);
        $fileSize = filesize($targetFile);

        if (!empty($fileEntity)) {
            $fileEntity->setFileType($extension);
            $fileEntity->setFilename($name);
            $fileEntity->setSize(FileHelper::formatSizeUnits($fileSize));
            $fileEntity->setFile($filename);
            if (!empty($parentEntity)) {
                $setter = EntityHelper::makeSetter($parentEntity->getEntityType()->getEntityTypeCode());
                $fileEntity->{$setter}($parentEntity);
            }
            $this->entityManager->saveEntity($fileEntity);
        }

        if ($returnWebPath) {
            return $folder . $filename;
        }

        if (!empty($fileEntity)) {
            return $fileEntity;
        }

        return $folder . $filename;
    }

    /**
     * @param EntityType $fileEntityType
     * @param Attribute $parentAttribute
     * @param $parentId
     * @param bool $order
     * @return mixed
     */
    public function getRelatedFiles(EntityType $fileEntityType, Attribute $parentAttribute, $parentId, $order = false)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter(EntityHelper::makeAttributeName($parentAttribute->getAttributeCode()), "eq", $parentId));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = null;

        if ($order) {
            $sortFilters = new SortFilterCollection();
            $sortFilters->addSortFilter(new SortFilter("ord", "asc"));
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($fileEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param $images
     * @param EntityType $entityType
     * @return bool
     */
    public function saveArray($images, EntityType $entityType)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $this->entityManager->saveArrayEntities($images, $entityType);

        return true;
    }

    /**
     * @param $files
     * @param $sourceDir
     * @param $targetDir
     * @param $parentId
     * @param $relatedEntityType
     * @return bool
     */
    public function addFilesToZip($files, $sourceDir, $targetDir, $parentId, $relatedEntityType)
    {
        $zip = new ZipArchive();

        if (!empty($relatedEntityType)) {
            $targetDir = "/Documents/export/" . $relatedEntityType . "/";
        }

        $zipName = sha1(strval($parentId) . time());
        $zipPath = $this->webPath . $targetDir . $zipName . ".zip";

        if (!file_exists($this->webPath . $targetDir)) {
            mkdir($this->webPath . $targetDir, 0777, true);
        }

        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            foreach ($files as $file) {
                $filePath = $this->webPath . $sourceDir . $file;
                $file = ltrim($file, '/');
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $file);
                }
            }

            $count = $zip->numFiles;
            if ($zip->close() === true) {
                if ($count == count($files)) {
                    return $targetDir . $zipName . ".zip";
                }
            }
        }

        return false;
    }

    /**
     * @param $folder
     * @param $destinationId
     * @return mixed
     */
    public function getTargetPath($folder,$destinationId){

        $folder = str_replace("{id}", $destinationId, $folder);
        return $folder;
    }

    public function getFilenamePrefix($folder,$destinationId){

        if(stripos($folder,"{id}") !== false && !empty($destinationId)){
            return $destinationId."/";
        }

        return null;
    }

    /**
     * @param $folder
     * @return string|string[]|null
     */
    public function getSourcePath($folder){
        return preg_replace('/\{.*/', '', $folder);
    }
}
