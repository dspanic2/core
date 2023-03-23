<?php

namespace AppBundle\Context;

use AppBundle\Abstracts\AbstractEntity;
use AppBundle\DAL\FileEntityDAL;
use AppBundle\Entity\FileEntity;
use AppBundle\Helpers\StringHelper;
use Symfony\Component\Finder\SplFileInfo;

class FileEntityContext extends CoreContext
{
    public function __construct(FileEntityDAL $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }

    /**@var FileEntity $item */
    public function save($item)
    {
        $parent_location = "";

        if ($item->getParentFolder() != null) {
            $parent_location = $item->getParentFolder()->getFileLocation();
        }

        $location = StringHelper::format("{0}\\{1}", $parent_location, $item->getFilename());

        if ($item->getFileType() == "Folder") {
            if ($item->getFileLocation() == "") {
                mkdir($location);
            }
            $item->setFileLocation($location);
        } else {
            if ($item->getFileLocation() != "") {
                $file = new \SplFileInfo($item->getFileLocation());
                $ext = $file->getExtension();

                $item->setFilename($file->getFilename());
                $item->setFileExtension($ext);
                $item->setFileLocation($file->getPath());
            }
        }
        return parent::save($item);
    }

    public function getFolderItems($folder)
    {
        return $this->repository->findBy(array('parent' => $folder));
    }
}
