<?php

namespace SharedInboxBusinessBundle\Providers;

use AppBundle\Helpers\StringHelper;
use AppBundle\Models\MailAttachment;
use AppBundle\Models\MailAttachmentCollection;
use DateTime;
use SharedInboxBusinessBundle\Abstracts\AbstractEmailProvider;
use SharedInboxBusinessBundle\Constants\SharedInboxConstants;
use SharedInboxBusinessBundle\Entity\EmailEntity;
use SharedInboxBusinessBundle\Entity\SharedInboxConnectionEntity;
use SharedInboxBusinessBundle\Interfaces\IEmailProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IMAPProvider extends AbstractEmailProvider implements IEmailProvider
{
    private $imapParams;

    public function initialize()
    {
        parent::initialize();
        $this->imapParams = array(
            "DISABLE_AUTHENTICATOR" => array(
                "GSSAPI",
                "NTLM",
                "PLAIN"
            )
        );
    }

    /**
     * @param string $host
     * @param int $port
     * @param bool $ssl
     * @param string $name
     * @return false|string
     */
    private function getMailboxPath(string $host, int $port, bool $ssl, string $name)
    {
        $flag = "/notls";
        if ($ssl) {
            $flag = "/ssl/novalidate-cert";
        }

        return "{" . $host . ":" . $port . "/imap" . $flag . "}" . $name;
    }

    /**
     * @param $folder
     * @return bool
     */
    public function getEmails($folder, $filter = null)
    {
        $mailbox = $this->getMailboxPath($this->connection->getHost(), $this->connection->getPort(), $this->connection->getUseSsl(), $folder);
        if (empty($mailbox)) {
            return false;
        }

        $imapStream = imap_open($mailbox, $this->connection->getUsername(), $this->connection->getPassword(), OP_READONLY, 0, $this->imapParams);
        if (empty($imapStream)) {
            return false;
        }

        $emails = imap_search($imapStream, 'SINCE "15 November 2021"');
        if (empty($emails)) {
            return false;
        }

        /** @var SharedInboxConnectionEntity $sharedInboxConnection */
        $sharedInboxConnection = $this->emailManager->getSharedInboxConnectionById($this->connection->getId());

        foreach ($emails as $emailId) {

            $header = imap_headerinfo($imapStream, $emailId);

            $epochTime = 0;
            if (isset($header->udate) && !empty($header->udate)) {
                $epochTime = (int)$header->udate;
            } else if (isset($header->date) && !empty($header->date)) {
                $epochTime = strtotime($header->date);
            }

            if (!$epochTime) {
                continue;
            }

            $structure = imap_fetchstructure($imapStream, $emailId);

            $messageId = trim($header->message_id);

            $existingEmail = $this->emailManager->getEmailByMessageId($messageId);
            if (!empty($existingEmail)) {
                continue;
            }

            $mailAttachmentCollection = new MailAttachmentCollection();

            /** @var EmailEntity $email */
            $email = $this->entityManager->getNewEntityByAttributSetName("email");
            $email->setSharedInboxConnection($this->connection);
            $email->setMailDatetime(DateTime::createFromFormat("U", $epochTime));
            $email->setMessageId($messageId);
            $email->setState(SharedInboxConstants::EMAIL_STATE_UNPROCESSED);

            if (isset($header->cc)) {
                $email->setCc($this->getAddressesFromHeaderArray($header->cc));
            }
            if (isset($header->bcc)) {
                $email->setBcc($this->getAddressesFromHeaderArray($header->bcc));
            }
            if (isset($header->from)) {
                $email->setMailFrom($this->getAddressesFromHeaderArray($header->from));
            }
            if (isset($header->to)) {
                $email->setMailTo($this->getAddressesFromHeaderArray($header->to));
            }
            if (isset($header->subject)) {
                $email->setSubject(StringHelper::removeNonAsciiCharacters(imap_utf8($header->subject)));
            }
            if (isset($header->in_reply_to)) {

                $inReplyTo = trim($header->in_reply_to);
                $email->setInReplyTo($inReplyTo);

                /** @var EmailEntity $parentEmail */
                $parentEmail = $this->emailManager->getEmailByMessageId($inReplyTo);
                if (!empty($parentEmail)) {
                    $email->setParentEmail($parentEmail);
                }
            }
            if (isset($header->references)) {
                $email->setReferencesIds(trim($header->references));
            }

            if (isset($structure->parts)) {
                $flattenedParts = $this->flattenParts($structure->parts);
            } else {
                $flattenedParts = [$structure];
            }
            $email->setSharedInboxConnection($sharedInboxConnection);

            $html = "";
            $plain = "";

            foreach ($flattenedParts as $partNumber => $part) {

                switch ($part->type) {
                    case TYPETEXT:
                        $data = $this->getPart($imapStream, $emailId, $partNumber, $part->encoding);
                        if (!empty($data)) {
                            if (strcasecmp($part->subtype, "plain") == 0) {
                                $plain .= $data;
                            } else {
                                $html .= $data;
                            }
                        }
                        break;
                    case TYPEMULTIPART:
                    case TYPEMESSAGE:
                        break;
                    case TYPEAPPLICATION:
                    case TYPEAUDIO:
                    case TYPEIMAGE:
                    case TYPEVIDEO:
                    case TYPEOTHER:
                        $filename = $this->getFilenameFromPart($part);
                        if ($filename) {
                            $attachment = $this->getPart($imapStream, $emailId, $partNumber, $part->encoding);
                            if ($attachment) {
                                $mailAttachment = new MailAttachment();
                                $mailAttachment->setFilename($filename);
                                $mailAttachment->setContent($attachment);
                                $mailAttachment->setFileType(strtolower($part->subtype));
                                $mailAttachment->setIsEmbedded(false);
                                if ($part->type == TYPEIMAGE && $part->ifid == 1) {
                                    $mailAttachment->setIsEmbedded(true);
                                    $mailAttachment->setCid($this->formatCid($part->id));
                                }
                                $mailAttachmentCollection->addAttachment($mailAttachment);
                            }
                        }
                        break;
                }
            }

            echo $email->getSubject() . "\n";

            $body = $plain;
            if (!empty($html)) {
                $body = StringHelper::removeNonAsciiCharacters($html);
            }

            $email->setBody($body);

            $this->entityManager->saveEntityWithoutLog($email);

            $resaveBody = false;
            if ($this->connection->getSaveAttachments()) {

                /** @var MailAttachment $mailAttachment */
                foreach ($mailAttachmentCollection->getAttachments() as $mailAttachment) {
                    if (!in_array($mailAttachment->getFileType(), $this->allowedFileTypes)) {
                        continue;
                    }
                    $path = $this->saveEmailAttachment($email, $mailAttachment);

                    if($mailAttachment->getIsEmbedded() && !empty($path)){
                        $resaveBody = true;
                        $body = str_ireplace("cid:{$mailAttachment->getCid()}",$path,$body);
                    }
                }
            }

            if($resaveBody){
                $this->entityManager->clearManagerByEntityType($this->entityManager->getEntityTypeByCode("email_attachment"));
                $email->setBody($body);
                $this->entityManager->saveEntityWithoutLog($email);
            }
        }

        /**
         * Flush errors before closing
         */
        imap_errors();
        imap_alerts();
        imap_close($imapStream);

        return true;
    }

    /**
     * @param $sentMimeMessage
     * @return bool
     */
    public function saveEmail($sentMimeMessage)
    {
        $mailbox = $this->getMailboxPath($this->connection->getHost(), $this->connection->getPort(), $this->connection->getUseSsl(), "INBOX.Sent");
        $imapStream = imap_open($mailbox, $this->connection->getUsername(), $this->connection->getPassword(), 0, 0, $this->imapParams);
        $result = imap_append($imapStream, $mailbox, $sentMimeMessage, "\\Seen");

        /**
         * Flush errors before closing
         */
        imap_errors();
        imap_alerts();
        imap_close($imapStream);

        return $result;
    }

    /**
     * @param $messageParts
     * @param array $flattenedParts
     * @param string $prefix
     * @param int $index
     * @param bool $fullPrefix
     * @return array|mixed
     */
    private function flattenParts($messageParts, $flattenedParts = array(), $prefix = '', $index = 1, $fullPrefix = true)
    {
        foreach ($messageParts as $part) {
            $flattenedParts[$prefix . $index] = $part;
            if (isset($part->parts)) {
                if ($part->type == 2) {
                    $flattenedParts = $this->flattenParts($part->parts, $flattenedParts, $prefix . $index . '.', 0, false);
                } elseif ($fullPrefix) {
                    $flattenedParts = $this->flattenParts($part->parts, $flattenedParts, $prefix . $index . '.');
                } else {
                    $flattenedParts = $this->flattenParts($part->parts, $flattenedParts, $prefix);
                }
                unset($flattenedParts[$prefix . $index]->parts);
            }
            $index++;
        }

        return $flattenedParts;
    }

    /**
     * @param $connection
     * @param $messageId
     * @param $partNumber
     * @param $encoding
     * @return string
     */
    private function getPart($connection, $messageId, $partNumber, $encoding)
    {
        $data = $partNumber ?
            imap_fetchbody($connection, $messageId, $partNumber, FT_PEEK) :
            imap_body($connection, $messageId, FT_PEEK);

        switch ($encoding) {
            case ENC7BIT:
                return $data;
            case ENC8BIT:
                return quoted_printable_decode(imap_8bit($data));
            case ENCBINARY:
                return imap_binary($data);
            case ENCBASE64:
                return imap_base64($data);
            case ENCQUOTEDPRINTABLE:
                return utf8_encode(quoted_printable_decode($data));
            case ENCOTHER:
                return $data;
            default:
                return $data;
        }
    }

    /**
     * @param $part
     * @return string
     */
    private function getFilenameFromPart($part)
    {
        $filename = '';

        if ($part->ifdparameters) {
            foreach ($part->dparameters as $object) {
                if (strcasecmp($object->attribute, "filename") == 0) {
                    $filename = $object->value;
                }
            }
        }

        if (!$filename && $part->ifparameters) {
            foreach ($part->parameters as $object) {
                if (strcasecmp($object->attribute, "name") == 0) {
                    $filename = $object->value;
                }
            }
        }

        return $filename;
    }

    /**
     * @param $headerArray
     * @return string
     */
    private function getAddressesFromHeaderArray($headerArray)
    {
        $add = array();

        foreach ($headerArray as $id => $object) {
            if (isset($object->mailbox) && isset($object->host)) {
                array_push($add, $object->mailbox . "@" . $object->host);
            }
        }

        return implode('; ', $add);
    }
}
