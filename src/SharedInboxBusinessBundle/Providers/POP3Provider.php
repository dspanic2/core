<?php

namespace SharedInboxBusinessBundle\Providers;

use SharedInboxBusinessBundle\Abstracts\AbstractEmailProvider;
use SharedInboxBusinessBundle\Entity\EmailEntity;
use SharedInboxBusinessBundle\Models\EmailEntityCollection;
use SharedInboxBusinessBundle\Interfaces\IEmailProvider;

class POP3Provider extends AbstractEmailProvider implements IEmailProvider
{
    public function getEmails($folder, $filter = null)
    {
        // TODO: Implement getEmails() method.
    }

    /**
     * @return EmailEntityCollection
     */
    public function getNewMessages()
    {
        $hostname = $this->connection->getHost();
        $username = $this->connection->getUsername();
        $password = $this->connection->getPassword();

        $date_format = "Y-m-d H:i:s";

        $mbox = imap_open($hostname, $username, $password);
        /* grab emails */
        $msgs = imap_search($mbox, 'UNSEEN');
        $no_of_msgs = $msgs ? count($msgs) : 0;

        $emailMessages = new EmailEntityCollection();
        for ($i = 0; $i < $no_of_msgs; $i++) {

            $email = new EmailEntity();
            $email->setAttachments(new ArrayCollection());
            $email->setAttributeSet($this->emailSet);
            $email->setEntityType($this->emailSet->getEntityType());
            $email->setEntityStateId(1);
            $email->setSharedInboxConnection($this->connection);

            // Get Message Unique ID in case mail box changes
            // in the middle of this operation
            $message_id = imap_uid($mbox, $msgs[$i]);
            $header = imap_header($mbox, $message_id);

            $email->setMailDatetime(\DateTime::createFromFormat($date_format, date($date_format, $header->udate)));

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
                $email->setSubject($header->subject);
            }

            $structure = imap_fetchstructure($mbox, $message_id);
            $body = '';

            if (!empty($structure->parts)) {
                for ($j = 0, $k = count($structure->parts); $j < $k; $j++) {
                    $part = $structure->parts[$j];
                    if ($part->subtype == 'PLAIN') {
                        $body = imap_fetchbody($mbox, $message_id, $j + 1);
                    }
                }
            } else {
                $body = imap_body($mbox, $message_id);
            }

            $body = imap_qprint($body);
            $email->setBody($body);

            $emailMessages->addEmailEntity($email);
            imap_delete($mbox, $message_id);
        }

        imap_expunge($mbox);
        imap_close($mbox);

        return $emailMessages;
    }

    /**
     * @param $headerArray
     * @return string
     */
    private function getAddressesFromHeaderArray($headerArray)
    {
        $add = array();
        foreach ($headerArray as $id => $object) {
            array_push($add, $object->mailbox . "@" . $object->host);
        }

        return implode(',', $add);
    }

    public function initialize()
    {
        parent::initialize();
    }

    public function saveEmail($sentMimeMessage)
    {
        // TODO: Implement saveEmail() method.
    }
}
