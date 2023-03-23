<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Constants\FileSources;
use AppBundle\Context\AttributeContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CkeditorEntity;
use AppBundle\Entity\EntityType;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\FileHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FileManager;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use ImageOptimizationBusinessBundle\Managers\ImageStyleManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Exception;
use ZipArchive;

class FilesController extends AbstractController
{
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var FileManager $fileManager */
    protected $fileManager;
    /** @var ImageStyleManager $imageStyleManager */
    protected $imageStyleManager;

    protected $webPath;

    public function initialize()
    {
        parent::initialize();
        $this->attributeContext = $this->getContainer()->get("attribute_context");
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->fileManager = $this->getContainer()->get("file_manager");
        $this->webPath = $_ENV["WEB_PATH"];
    }

    /**
     * @Route("files/upload", name="file_upload")
     */
    public function uploadAction(Request $request)
    {
        $this->initialize();

        if (empty($_FILES)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("There has been an error, please try again!")));
        }

        $attributeId = $request->get("attribute_id");

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getById($attributeId);

        $targetFile = $_FILES["file"]["name"];
        $sourceFile = $_FILES["file"]["tmp_name"];

        $relatedEntityId = $request->get("related_entity_id");
        $targetDir = $this->fileManager->getTargetPath($attribute->getFolder(), $relatedEntityId);

        $targetPath = $this->webPath . $targetDir;
        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0777, true);
        }

        /**
         * Clean filename
         */
        $basename = $this->helperManager->getFilenameWithoutExtension($targetFile);
        $extension = strtolower($this->helperManager->getFileExtension($targetFile));

        $filename = $this->helperManager->nameToFilename($basename);
        $filename = $filename . "." . $extension;

        /**
         * Increment filename
         */
        $filename = $this->helperManager->incrementFileName($targetPath, $filename);
        $targetFile = $targetPath . $filename;

        if (file_exists($targetFile)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("A file with the same name already exists!")));
        }

        $tempFolder = "tmp/";

        if (!file_exists($tempFolder)) {
            mkdir($tempFolder, 0777, true);
        }

        if (!move_uploaded_file($sourceFile, $targetFile)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("There has been an error, please try again!")));
        }

        $is_image = false;

        if (in_array($extension, array("png", "jpg", "jpeg", "webp", "bmp"))) {
            $is_image = true;
        }

        $webPath = $request->getSchemeAndHttpHost() . "/" . $targetDir . $filename;

        return new JsonResponse(array(
            "error" => false,
            "attributeCode" => $attribute->getAttributeCode(),
            "web_path" => $webPath,
            "path" => $targetFile,
            "is_image" => $is_image,
            "ext" => $extension,
            "message" => $this->translator->trans("Upload success!")
        ));
    }

    /**
     * @Route("files/upload_import", name="file_upload_import")
     */
    public function uploadImportAction(Request $request)
    {
        $this->initialize();

        if (empty($_FILES)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("There has been an error, please try again!")));
        }

        $sourceFile = $_FILES["file"]["tmp_name"];

        $targetPath = $this->webPath . "/imports/";
        $targetFile = $targetPath . $_FILES["file"]["name"];

        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0777, true);
        }

        $tempFolder = "tmp/";

        if (!file_exists($tempFolder)) {
            mkdir($tempFolder, 0777, true);
        }

        if (!move_uploaded_file($sourceFile, $targetFile)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("There has been an error, please try again!")));
        }

        $extension = strtolower($this->helperManager->getFileExtension($targetFile));
        if ($extension != "xlsx") {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Import file must be xlsx!")));
        }

        return new JsonResponse(array(
            "error" => false,
            "path" => $targetFile,
            "ext" => $extension,
            "message" => $this->translator->trans("Uploaded file ready for import")
        ));
    }

    /**
     * @Route("files/create", name="file_create")
     */
    public function uploadAndCreateAction(Request $request)
    {
        $this->initialize();

        if (empty($_FILES)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("There has been an error, please try again!")));
        }

        $attributeId = $request->get("attribute_id");

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getById($attributeId);

        $targetFile = $_FILES["file"]["name"];
        $sourceFile = $_FILES["file"]["tmp_name"];

        $filesize = filesize($sourceFile);
        if (empty($filesize)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("There has been an error, please try again!")));
        }

        $relatedEntityTypeCode = $request->get("related_entity_type");
        $relatedEntityId = $request->get("related_entity_id");

        $targetDir = $this->fileManager->getTargetPath($attribute->getFolder(), $relatedEntityId);
        $filenamePrefix = $this->fileManager->getFilenamePrefix($attribute->getFolder(), $relatedEntityId);

        $targetPath = $this->webPath . $targetDir;
        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0777, true);
        }

        /**
         * Clean filename
         */
        $basename = $this->helperManager->getFilenameWithoutExtension($targetFile);
        $extension = strtolower($this->helperManager->getFileExtension($targetFile));

        if (isset($_ENV["RENAME_FILES_ON_UPLOAD"]) && !empty($_ENV["RENAME_FILES_ON_UPLOAD"])) {
            $renameData = json_decode($_ENV["RENAME_FILES_ON_UPLOAD"], true);
            if (isset($renameData[$attributeId]) && $renameData[$attributeId] == 1) {
                $relatedEntityType = $this->entityManager->getEntityTypeByCode($relatedEntityTypeCode);
                $relatedEntity = $this->entityManager->getEntityByEntityTypeAndId($relatedEntityType, $relatedEntityId);
                if (method_exists($relatedEntity, "getName")) {
                    $name = $relatedEntity->getName();
                    if (is_array($name)) {
                        $name = $name[array_key_first($name)];
                    }
                    $filename = $this->helperManager->nameToFilename($name . "_" . $relatedEntityId);
                } else {
                    $filename = $this->helperManager->nameToFilename($basename);
                }
            } else {
                $filename = $this->helperManager->nameToFilename($basename);
            }
        } else {
            $filename = $this->helperManager->nameToFilename($basename);
        }

        $filename = $filename . "." . $extension;

        /**
         * Increment filename
         */
        $filename = $this->helperManager->incrementFileName($targetPath, $filename);
        $basename = $this->helperManager->getFilenameWithoutExtension($filename);

        $targetFile = $targetPath . $filename;

        if (file_exists($targetFile)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("A file with the same name already exists!")));
        }

        $tempFolder = "tmp/";

        if (!file_exists($tempFolder)) {
            mkdir($tempFolder, 0777, true);
        }

        if (!move_uploaded_file($sourceFile, $targetFile)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("There has been an error, please try again!")));
        }

        $is_image = false;

        if (in_array($extension, array("png", "jpg", "jpeg", "webp", "bmp"))) {
            $is_image = true;
        }

        $webPath = $request->getSchemeAndHttpHost() . "/" . $targetDir . $filename;
        $webPath = str_ireplace("//", "/", $webPath);

        /**
         * Create entity for this file
         */
        $fileEntity = $this->entityManager->getNewEntityByAttributSetName($attribute->getEntityType()->getEntityTypeCode());

        $fileEntity->setFileType($extension);
        $fileEntity->setFilename($basename);
        $fileEntity->setSize(FileHelper::formatSizeUnits($filesize));
        $fileEntity->setFile($filenamePrefix . $filename);
        if (EntityHelper::checkIfMethodExists($fileEntity, "setFileSource")) {
            $fileEntity->setFileSource(FileSources::CRM);
        }

        if (!empty($relatedEntityTypeCode) && !empty($relatedEntityId) &&
            $relatedEntityTypeCode != $attribute->getEntityType()->getEntityTypeCode()) {

            $relatedEntityType = $this->entityManager->getEntityTypeByCode($relatedEntityTypeCode);
            $relatedEntity = $this->entityManager->getEntityByEntityTypeAndId($relatedEntityType, $relatedEntityId);

            /** @var Attribute $relatedAttribute */
            $relatedAttribute = $this->attributeContext->getOneBy(array("entityType" => $attribute->getEntityType(), "lookupEntityType" => $relatedEntityType));
            if (!empty($relatedAttribute)) {

                $setter = EntityHelper::makeSetter(str_replace("_id", "", $relatedAttribute->getAttributeCode()));
                if (!empty($relatedEntity) && method_exists($fileEntity, $setter)) {
                    $fileEntity->{$setter}($relatedEntity);
                }
            }
        }

        $this->entityManager->saveEntity($fileEntity);

        $html = $this->twig->render("AppBundle:Includes:gallery_item.html.twig", array("entity" => $fileEntity, "fileAttributeFolder" => $this->fileManager->getSourcePath($attribute->getFolder())));

        return new JsonResponse(array(
            "error" => false,
            "entity" => $this->entityManager->entityToArray($fileEntity, false),
            "attributeCode" => $attribute->getAttributeCode(),
            "web_path" => $webPath,
            "path" => $filenamePrefix . $filename,
            "is_image" => $is_image,
            "ext" => $extension,
            "html" => $html,
            "message" => $this->translator->trans("Upload success!")
        ));
    }

    /**
     * DEPRECATED, delete on 1.8.2019
     * @Route("files/download/{id}", name="file_download")
     */
    /*public function downloadAction(Request $request, $id)
    {
        $fileContext = $this->getContainer()->get("file_entity_context");
        $this->translator = $this->getContainer()->get("translator");

        $file = $fileContext->getById($id);

        $file->getFileLocation();

        $path = $file->getFileLocation() . '/' . $file->getFilename();
        $content = file_get_contents($path);

        $response = new Response();

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $file->getFilename());

        $response->setContent($content);
        return $response;

    }*/

    /**
     * @Route("files/delete/all", name="delete_all_files")
     */
    public function deleteAllFilesAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["entity_type_code"]) || empty($p["entity_type_code"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Entity type is not defined")));
        }
        if (!isset($p["ids"]) || empty($p["ids"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("List of entities is empty")));
        }

        /**
         * Ako cemo brisat slike skroz
         */
        //$entityType = $this->entityManager->getEntityTypeByCode($p["entity_type_code"]);

        /** @var Attribute $attribute */
        /*$attribute = $attributeContext->getOneBy(Array("entityType" => $entityType, "attributeCode" => "file"));
        if (empty($attribute)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("File attribute is not defined")));
        }*/

        $entityType = $this->entityManager->getEntityTypeByCode($p["entity_type_code"]);

        foreach ($p["ids"] as $id) {
            $entity = $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
            if (empty($entity)) {
                continue;
            }

            $this->entityManager->deleteEntity($entity);
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Delete files"), "message" => $this->translator->trans("All files deleted")));
    }

    /**
     * @Route("/ckeditor/uploader/save", name="ckeditor_uploader_save")
     * @Method("POST")
     */
    public function ckeditorEntitySaveAction(Request $request)
    {
        $this->initialize();
        $p = $_POST;
        if (isset($p["base64image"]) && !empty($p["base64image"])) {
            // Image pasted to CKeditor
            $basename = md5(time());
            $extension = "png";
        } else {
            if (empty($_FILES)) {
                return new JsonResponse(array("error" => true, "message" => $this->translator->trans("There has been an error, please try again!")));
            }

            $targetFile = $_FILES["file"]["name"];
            $sourceFile = $_FILES["file"]["tmp_name"];

            $filesize = filesize($sourceFile);
            if (empty($filesize)) {
                return new JsonResponse(array("error" => true, "message" => $this->translator->trans("There has been an error, please try again!")));
            }

            $basename = $this->helperManager->getFilenameWithoutExtension($targetFile);
            $extension = strtolower($this->helperManager->getFileExtension($targetFile));
        }

        $etCkeditor = $this->entityManager->getEntityTypeByCode("ckeditor");

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getOneBy(array("entityType" => $etCkeditor, "attributeCode" => "file"));

        $relatedEntityId = $request->get("related_entity_id");
        $targetDir = $this->fileManager->getTargetPath($attribute->getFolder(), $relatedEntityId);
        $filenamePrefix = $this->fileManager->getFilenamePrefix($attribute->getFolder(), $relatedEntityId);

        $targetPath = $this->webPath . $targetDir;
        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0777, true);
        }

        /**
         * Clean filename
         */

        $filename = $this->helperManager->nameToFilename($basename);
        $filename = $filename . "." . $extension;

        /**
         * Increment filename
         */
        $filename = $this->helperManager->incrementFileName($targetPath, $filename);
        $basename = $this->helperManager->getFilenameWithoutExtension($filename);

        $targetFile = $targetPath . $filename;

        try {
            if (isset($p["base64image"]) && !empty($p["base64image"])) {
                $data = explode(',', $p["base64image"]);

                // write file
                $ifp = fopen($targetFile, 'wb');
                fwrite($ifp, base64_decode($data[1]));
                fclose($ifp);

                $filesize = filesize($targetFile);
            } else {
                move_uploaded_file($sourceFile, $targetFile);
            }
        } catch (\Exception $e) {
            return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Failed"), "message" => $e->getMessage()));
        }

        $is_image = false;

        if (in_array($extension, array("png", "jpg", "jpeg", "webp", "bmp"))) {
            $is_image = true;
        }

        $webPath = $targetDir . $filename;
        $webPath = str_ireplace("//", "/", $webPath);

        $ckeditorSetting = $_ENV["CKEDITOR_ENTITY_USE_ABSOLUTE_PATH"] ?? 0;
        if (!empty($ckeditorSetting)) {
            $webPath = $request->getSchemeAndHttpHost() . $webPath;
        }

        /**
         * Create entity for this file
         */
        /** @var CkeditorEntity $ckeditorEntity */
        $ckeditorEntity = $this->entityManager->getNewEntityByAttributSetName("ckeditor");

        $ckeditorEntity->setFileType($extension);
        $ckeditorEntity->setFilename($basename);
        $ckeditorEntity->setSize(FileHelper::formatSizeUnits($filesize));
        $ckeditorEntity->setFile($filenamePrefix . $filename);
        if (EntityHelper::checkIfMethodExists($ckeditorEntity, "setFileSource")) {
            $ckeditorEntity->setFileSource(FileSources::CRM);
        }

        $this->entityManager->saveEntity($ckeditorEntity);

        return new JsonResponse(array("error" => false, "src" => $webPath, "is_image" => $is_image));
    }

    /**
     * @Route("files/rotate", name="rotate_image")
     */
    public function rotateImageAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["entity_type_code"]) || empty($p["entity_type_code"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Entity type is not defined")));
        }
        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Image id is empty")));
        }
        if (!isset($p["direction"]) || empty($p["direction"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Rotate direction is empty")));
        }

        /** @var EntityType $entityType */
        $entityType = $this->entityManager->getEntityTypeByCode($p["entity_type_code"]);

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getOneBy(array("entityType" => $entityType, "attributeCode" => "file"));
        if (empty($attribute)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("File attribute is not defined")));
        }

        $entity = $this->entityManager->getEntityByEntityTypeAndId($entityType, $p["id"]);
        if (empty($entity)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Image does not exist")));
        }

        $targetDir = $this->fileManager->getTargetPath($attribute->getFolder(), null);

        $imagePath = $this->webPath . $targetDir . $entity->getFile();
        if (!file_exists($imagePath)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Image file does not exist")));
        }

        $extension = strtolower($entity->getFileType());
        if (!in_array($extension, array("png", "jpg", "jpeg", "webp", "bmp"))) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("This file is not an image")));
        }

        $image = new \Imagick(realpath($imagePath));
        $image->rotateImage(new \ImagickPixel("#00000000"), $p["direction"]);
        $image->setImageFormat($extension);
        $image->writeImage($imagePath);

        if (!file_put_contents($imagePath, $image)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Error saving image")));
        }

        if (empty($this->imageStyleManager)) {
            $this->imageStyleManager = $this->getContainer()->get("image_style_manager");
        }
        $this->imageStyleManager->deleteStyles($targetDir . $entity->getFile());

        $data = array();
        $data["selected"] = false;
        $data["order"] = false;

        $attributes = $this->entityManager->getAttributesOfEntityType($entityType->getEntityTypeCode(), false);
        /** @var Attribute $attribute */
        foreach ($attributes as $attribute) {
            if ($attribute->getAttributeCode() == "ord") {
                $data["order"] = $attribute;
            }
            if ($attribute->getAttributeCode() == "selected") {
                $data["selected"] = $attribute;
            }
        }

        $html = $this->twig->render("AppBundle:Includes:gallery_item.html.twig", array("entity" => $entity, "fileAttributeFolder" => $targetDir, "data" => $data));

        return new JsonResponse(array(
            "error" => false,
            "html" => $html,
            "title" => $this->translator->trans("Rotate image"),
            "message" => $this->translator->trans("Image rotated")
        ));
    }

    /**
     * @Route("/gallery/set_selected", name="gallery_set_selected")
     * @Method("POST")
     */
    public function gallerySetSelectedAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["entity_type_code"]) || empty($p["entity_type_code"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Entity type is not defined")));
        }
        if (!isset($p["image_id"]) || empty($p["image_id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Image id is empty")));
        }
        if (!isset($p["parent_id"]) || empty($p["parent_id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Parent id is empty")));
        }
        if (!isset($p["parent_attribute_id"]) || empty($p["parent_attribute_id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Parent attribute id is empty")));
        }

        $fileEntityType = $this->entityManager->getEntityTypeByCode($p["entity_type_code"]);

        /** @var Attribute $parentAttribute */
        $parentAttribute = $this->attributeContext->getById($p["parent_attribute_id"]);

        $images = $this->fileManager->getRelatedFiles($fileEntityType, $parentAttribute, $p["parent_id"]);
        if (empty($images)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Images missing")));
        }

        foreach ($images as $image) {
            if ($image->getId() == $p["image_id"]) {
                if ($image->getSelected()) {
                    return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("This image is already selected")));
                } else {
                    $image->setSelected(1);
                    try {
                        $image = $this->entityManager->saveEntity($image);
                    } catch (Exception $e) {
                        return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("An error occurred")));
                    }
                }
            } else if ($image->getSelected()) {
                $image->setSelected(0);
                try {
                    $image = $this->entityManager->saveEntity($image);
                } catch (Exception $e) {
                    return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("An error occurred")));
                }
            }
        }

        return new JsonResponse(array("error" => false, "message" => $this->translator->trans("Image set as primary")));
    }

    /**
     * @Route("/gallery/set_sort", name="gallery_set_sort")
     * @Method("POST")
     */
    public function gallerySetSortAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["entity_type_code"]) || empty($p["entity_type_code"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Entity type is not defined")));
        }
        if (!isset($p["data"]) || empty($p["data"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("List of images is empty")));
        }
        if (!isset($p["parent_id"]) || empty($p["parent_id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Parent id is empty")));
        }
        if (!isset($p["parent_attribute_id"]) || empty($p["parent_attribute_id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Parent attribute id is empty")));
        }

        $fileEntityType = $this->entityManager->getEntityTypeByCode($p["entity_type_code"]);

        /** @var Attribute $parentAttribute */
        $parentAttribute = $this->attributeContext->getById($p["parent_attribute_id"]);

        $images = $this->fileManager->getRelatedFiles($fileEntityType, $parentAttribute, $p["parent_id"], true);
        if (empty($images)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Images missing")));
        }

        $imageSaveArray = array();

        foreach ($images as $image) {
            $key = array_search($image->getId(), $p["data"]);
            if (empty($key) && $key !== 0) {
                continue;
            }
            if ($key != $image->getOrd()) {
                $image->setOrd($key);
                $imageSaveArray[] = $image;
            }
        }

        if (!empty($imageSaveArray)) {
            $this->fileManager->saveArray($imageSaveArray, $fileEntityType);
        }

        return new JsonResponse(array("error" => false, "message" => $this->translator->trans("Images reordered")));
    }

    /**
     * @Route("files/download", name="download_files")
     */
    public function downloadFilesAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["entity_type_code"]) || empty($p["entity_type_code"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Entity type is not defined")));
        }
        if (!isset($p["parent_id"]) || empty($p["parent_id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Parent id is empty")));
        }
        if (!isset($p["parent_attribute_id"]) || empty($p["parent_attribute_id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Parent attribute id is empty")));
        }
        if (!isset($p["related_entity_type"]) || empty($p["related_entity_type"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Related entity type is empty")));
        }

        $etFile = $this->entityManager->getEntityTypeByCode($p["entity_type_code"]);

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getOneBy(array("entityType" => $etFile, "attributeCode" => "file"));

        $sourceDir = $this->fileManager->getTargetPath($attribute->getFolder(), $p["parent_attribute_id"]);

        if (empty($sourceDir)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Attribute folder is empty")));
        }

        /** @var Attribute $parentAttribute */
        $parentAttribute = $this->attributeContext->getById($p["parent_attribute_id"]);

        $fileEntities = $this->fileManager->getRelatedFiles($etFile, $parentAttribute, $p["parent_id"]);
        if (empty($fileEntities)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Files are missing")));
        }

        $files = array();

        foreach ($fileEntities as $fileEntity) {
            $files[] = $fileEntity->getFile();
        }

        $filepath = $this->fileManager->addFilesToZip($files, $sourceDir, null, $p["parent_id"], $p["related_entity_type"]);
        if (empty($filepath)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("An error occurred")));
        }

        return new JsonResponse(array("error" => false, "filepath" => $filepath));
    }

    /**
     * @Route("dropbox/add-link", name="add_dropbox_link")
     */
    public function addDropboxLink(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        $dropboxFile = $p["dropboxFile"];

        $attributeId = $request->get("attribute_id");

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getById($attributeId);

        $fileEntity = $this->entityManager->getNewEntityByAttributSetName($attribute->getEntityType()->getEntityTypeCode());

        $filename = $dropboxFile["name"];
        if (!empty($filename)) {
            $basename = $this->helperManager->getFilenameWithoutExtension($filename);
            $extension = strtolower($this->helperManager->getFileExtension($filename));

            $fileEntity->setFileType($extension);
            $fileEntity->setFilename($basename);
            $fileEntity->setSize(FileHelper::formatSizeUnits($dropboxFile["bytes"]));
            $fileEntity->setFile($dropboxFile["link"]);
            $fileEntity->setFileSource(FileSources::DROPBOX);
        }

        $relatedEntityTypeCode = $request->get("related_entity_type");
        $relatedEntityId = $request->get("related_entity_id");

        if (!empty($relatedEntityTypeCode) && !empty($relatedEntityId) && $relatedEntityTypeCode != $attribute->getEntityType()->getEntityTypeCode()) {

            $relatedEntityType = $this->entityManager->getEntityTypeByCode($relatedEntityTypeCode);
            $relatedEntity = $this->entityManager->getEntityByEntityTypeAndId($relatedEntityType, $relatedEntityId);

            /** @var Attribute $relatedAttribute */
            $relatedAttribute = $this->attributeContext->getOneBy(array("entityType" => $attribute->getEntityType(), "lookupEntityType" => $relatedEntityType));
            if (!empty($relatedAttribute)) {
                $setter = EntityHelper::makeSetter(str_replace("_id", "", $relatedAttribute->getAttributeCode()));

                if (!empty($relatedEntity) && method_exists($fileEntity, $setter)) {
                    $fileEntity->{$setter}($relatedEntity);
                }
            }
        }

        $this->entityManager->saveEntity($fileEntity);

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Saved"), "message" => $this->translator->trans("Link saved")));
    }

    /**
     * @Route("/gallery/set_alt", name="gallery_set_alt")
     * @Method("POST")
     */
    public function gallerySetAltAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["entity_type_code"]) || empty($p["entity_type_code"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Entity type is not defined")));
        }
        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Image id is empty")));
        }
        if (!isset($p["value"]) || empty($p["value"])) {
            $p["value"] = "";
        }

        /** @var EntityType $entityType */
        $entityType = $this->entityManager->getEntityTypeByCode($p["entity_type_code"]);

        /** @var ProductImagesEntity $entity */
        $entity = $this->entityManager->getEntityByEntityTypeAndId($entityType, $p["id"]);
        if (empty($entity)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Image does not exist")));
        }

        if (method_exists($entity, "setAlt")) {
            $entity->setAlt($p["value"]);
            $this->entityManager->saveEntity($entity);
        } else {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Alt attribute missing")));
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Image alt"), "message" => $this->translator->trans("Image alt saved")));
    }

    /**
     * @Route("/gallery/set_title", name="gallery_set_title")
     * @Method("POST")
     */
    public function gallerySetTitleAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["entity_type_code"]) || empty($p["entity_type_code"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Entity type is not defined")));
        }
        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Image id is empty")));
        }
        if (!isset($p["value"]) || empty($p["value"])) {
            $p["value"] = "";
        }

        /** @var EntityType $entityType */
        $entityType = $this->entityManager->getEntityTypeByCode($p["entity_type_code"]);

        /** @var ProductImagesEntity $entity */
        $entity = $this->entityManager->getEntityByEntityTypeAndId($entityType, $p["id"]);
        if (empty($entity)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Image does not exist")));
        }

        if (method_exists($entity, "setTitle")) {
            $entity->setTitle($p["value"]);
            $this->entityManager->saveEntity($entity);
        } else {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Title attribute missing")));
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Image title"), "message" => $this->translator->trans("Image title saved")));
    }


}
