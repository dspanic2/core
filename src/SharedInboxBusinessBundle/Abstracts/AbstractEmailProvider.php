<?php

namespace SharedInboxBusinessBundle\Abstracts;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Helpers\FileHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Models\MailAttachment;
use SharedInboxBusinessBundle\Constants\SharedInboxConstants;
use SharedInboxBusinessBundle\Entity\EmailAttachmentEntity;
use SharedInboxBusinessBundle\Entity\EmailEntity;
use SharedInboxBusinessBundle\Entity\SharedInboxConnectionEntity;
use SharedInboxBusinessBundle\Interfaces\IEmailProvider;
use SharedInboxBusinessBundle\Managers\EmailManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webklex\PHPIMAP\ClientManager;
use function SharedInboxBusinessBundle\Providers\dump;

abstract class AbstractEmailProvider extends AbstractBaseManager implements IEmailProvider
{
    /** @var SharedInboxConnectionEntity $connection */
    protected $connection;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var EmailManager $emailManager */
    protected $emailManager;
    protected $container;

    protected $attachmentsFolder;
    protected $allowedFileTypes;

    /**
     * @param SharedInboxConnectionEntity $connection
     */
    public function setConnection(SharedInboxConnectionEntity $connection)
    {
        $this->connection = $connection;
    }

    public function initialize()
    {
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->helperManager = $this->getContainer()->get("helper_manager");
        $this->emailManager = $this->getContainer()->get("email_manager");

        $this->attachmentsFolder = $_ENV["WEB_PATH"] . SharedInboxConstants::SHARED_INBOX_ATTACHMENTS_FOLDER;
        if (!file_exists($this->attachmentsFolder)) {
            mkdir($this->attachmentsFolder, 0777, true);
        }
        $this->allowedFileTypes = array(
            "jpg",
            "jpeg",
            "png",
            "zip",
            "pdf",
            "xls",
            "xlsx"
        );
    }

    /**
     * @param EmailEntity|null $email
     * @param $contentHash
     * @return false|EmailAttachmentEntity
     */
    public function getExistingAttachment(EmailEntity $email = null, $contentHash)
    {
        if (empty($email)) {
            return false;
        }

        for ($e = $email; !empty($e); $e = $e->getParentEmail()) {
            $existingAttachments = $e->getAttachments();
            if (!empty($existingAttachments)) {
                /** @var EmailAttachmentEntity $existingAttachment */
                foreach ($existingAttachments->getValues() as $existingAttachment) {
                    if ($contentHash == $existingAttachment->getContentHash()) {
                        return $existingAttachment;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param EmailEntity $email
     * @param MailAttachment $mailAttachment
     * @return bool
     */
    public function saveEmailAttachment(EmailEntity $email, MailAttachment $mailAttachment)
    {
        $contentHash = md5($mailAttachment->getContent());

        /** @var EmailAttachmentEntity $existingEmailAttachment */
        $existingEmailAttachment = $this->getExistingAttachment($email->getParentEmail(), $contentHash);
        if (!empty($existingEmailAttachment)) {

            /** @var EmailAttachmentEntity $emailAttachment */
            $emailAttachment = $this->entityManager->getNewEntityByAttributSetName("email_attachment");

            $emailAttachment->setFileType($existingEmailAttachment->getFileType());
            $emailAttachment->setFilename($existingEmailAttachment->getFilename());
            $emailAttachment->setFile($existingEmailAttachment->getFile());
            $emailAttachment->setSize($existingEmailAttachment->getSize());
            $emailAttachment->setIsEmbedded($existingEmailAttachment->getIsEmbedded());
            $emailAttachment->setContentHash($existingEmailAttachment->getContentHash());
            $emailAttachment->setContentId($mailAttachment->getCid());
            $emailAttachment->setEmail($email);

            $this->entityManager->saveEntityWithoutLog($emailAttachment);

        } else {

            $extension = $mailAttachment->getFileType();
            $filename = $this->helperManager->getFilenameWithoutExtension($mailAttachment->getFilename());
            $filename = $this->helperManager->nameToFilename($filename);
            $filename = $this->helperManager->incrementFileName($this->attachmentsFolder, $filename);

            $targetFile = $this->attachmentsFolder . $filename . "." . $extension;

            $bytes = $this->helperManager->saveRawDataToFile($mailAttachment->getContent(), $targetFile);
            if (empty($bytes)) {
                return false;
            }

            /** @var EmailAttachmentEntity $emailAttachment */
            $emailAttachment = $this->entityManager->getNewEntityByAttributSetName("email_attachment");

            $emailAttachment->setFileType($extension);
            $emailAttachment->setFilename($filename);
            $emailAttachment->setFile($filename . "." . $extension);
            $emailAttachment->setSize(FileHelper::formatSizeUnits($bytes));
            $emailAttachment->setIsEmbedded($mailAttachment->getIsEmbedded());
            $emailAttachment->setContentHash($contentHash);
            $emailAttachment->setContentId($mailAttachment->getCid());
            $emailAttachment->setEmail($email);

            $this->entityManager->saveEntityWithoutLog($emailAttachment);
        }

        $path = "/".SharedInboxConstants::SHARED_INBOX_ATTACHMENTS_FOLDER.$emailAttachment->getFile();

        return $path;
    }

    /**
     * @param $cid
     * @return false|string
     */
    public function formatCid($cid)
    {
        if ($cid[0] == '<' && $cid[strlen($cid) - 1] == '>') {
            $cid = substr($cid, 1, -1);
        }
        return $cid;
    }
}
