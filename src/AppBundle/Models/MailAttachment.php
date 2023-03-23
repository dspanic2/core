<?php

namespace AppBundle\Models;

class MailAttachment
{
    /** @var string $filename */
    private $filename;
    /** @var string $url */
    private $url;
    /** @var string $filePath */
    private $filePath;
    /** @var bool $isEmbedded */
    private $isEmbedded;
    /** @var string $cid */
    private $cid;
    /** @var string $fileType */
    private $fileType;

    private $content;

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param string $filename
     */
    public function setFilename(string $filename)
    {
        $this->filename = $filename;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl(string $url)
    {
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * @param string $filePath
     */
    public function setFilePath(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * @return bool
     */
    public function getIsEmbedded()
    {
        return $this->isEmbedded;
    }

    /**
     * @param bool $isEmbedded
     */
    public function setIsEmbedded(bool $isEmbedded)
    {
        $this->isEmbedded = $isEmbedded;
    }

    /**
     * @return string
     */
    public function getCid()
    {
        return $this->cid;
    }

    /**
     * @param string $cid
     */
    public function setCid(string $cid)
    {
        $this->cid = $cid;
    }

    /**
     * @return string
     */
    public function getFileType()
    {
        return $this->fileType;
    }

    /**
     * @param string $fileType
     */
    public function setFileType(string $fileType)
    {
        $this->fileType = $fileType;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param mixed $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }
}