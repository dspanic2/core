<?php

namespace SharedInboxBusinessBundle\Providers;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Models\MailAttachment;
use AppBundle\Models\MailAttachmentCollection;
use DateTime;
use IntegrationBusinessBundle\Managers\AzzureManager;
use SharedInboxBusinessBundle\Abstracts\AbstractEmailProvider;
use SharedInboxBusinessBundle\Constants\SharedInboxConstants;
use SharedInboxBusinessBundle\Entity\EmailEntity;
use SharedInboxBusinessBundle\Entity\SharedInboxConnectionEntity;
use SharedInboxBusinessBundle\Interfaces\IEmailProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webklex\PHPIMAP\ClientManager;

class O365Provider extends AbstractEmailProvider implements IEmailProvider
{
    /** @var AzzureManager $azzureManager */
    protected $azzureManager;
    protected $emails;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $folder
     * @return bool
     */
    public function getEmails($folder, $filter = null)
    {
        if(empty($this->errorLogManager)){
            $this->errorLogManager = $this->getContainer()->get("error_log_manager");
        }

        /** @var SharedInboxConnectionEntity $sharedInboxConnection */
        $sharedInboxConnection = $this->emailManager->getSharedInboxConnectionById($this->connection->getId());

        if(empty($this->connection->getAccessToken())){
            throw new \Exception("Missing access token");
        }
        if(empty($this->connection->getRefreshToken())){
            throw new \Exception("Missing access token");
        }

        $cm = new ClientManager();
        $client = $cm->make([
            'host'          => 'outlook.office365.com',
            'port'          => 993,
            'encryption'    => 'ssl',
            'validate_cert' => false,
            'username'      => $this->connection->getUsername(),
            'password'      => $this->connection->getAccessToken(),
            'protocol'      => 'imap',
            'authentication' => "oauth"
        ]);

        try{
            $client->connect();
        }
        catch (\Exception $e){
            if($e->getMessage() == "connection setup failed"){

                if(empty($this->azzureManager)){
                    $this->azzureManager = $this->getContainer()->get("azzure_manager");
                }

                $params = Array(
                    "tenant" => $this->connection->getTenant(),
                    "client_id" => $this->connection->getClientId(),
                    "client_secret" => $this->connection->getClientSecret(),
                    "refresh_token" => $this->connection->getRefreshToken()
                );

                $this->azzureManager->setConnectionParameters($params);

                try{
                    $ret = $this->azzureManager->getAccessTokenWithRefreshToken();
                }
                catch (\Exception $e){
                    $this->errorLogManager->logExceptionEvent($e->getMessage(),$e,true);
                }

                $data = Array();
                $data["access_token"] = $ret["access_token"];
                $data["refresh_token"] = $ret["refresh_token"];

                $sharedInboxConnection = $this->emailManager->insertUpdateSharedInboxConnection($data,$sharedInboxConnection);

                $this->setConnection($sharedInboxConnection);

                $client = $cm->make([
                    'host'          => 'outlook.office365.com',
                    'port'          => 993,
                    'encryption'    => 'ssl',
                    'validate_cert' => false,
                    'username'      => $this->connection->getUsername(),
                    'password'      => $this->connection->getAccessToken(),
                    'protocol'      => 'imap',
                    'authentication' => "oauth"
                ]);

                try{
                    $client->connect();
                }
                catch (\Exception $e){

                    $this->errorLogManager->logExceptionEvent($e->getMessage(),$e,true);

                }
            }
        }

        if(empty($client)){
            return false;
        }

        $this->emails = Array();

        /**
         * Get emails from exchange
         */
        try {
            $folder = $client->getFolder($folder);

            /**
             * Call examples
             */
            /**
             * https://www.php-imap.com/api/query
             * $folder->query()->all()->chunked(function($messages, $chunk){
             * $message = $folder->query()->getMessageByUid(9);
             */


            $days = 20;
            if(isset($filter["days"])){
                $days = intval($filter["days"]);
            }

            $folder->query()->since(\Carbon\Carbon::now()->subDays($days))->all()->chunked(function($messages, $chunk){

                $messages->each(function($message){

                    $email = Array();
                    $email["text_body"] = $message->getTextBody();
                    $email["html_body"] = $message->getHTMLBody();
                    $email["raw_body"] = $message->getRawBody();
                    $email["header"] = $message->getHeader();
                    /**
                     * Ne treba
                     */
                    //$email["attributes"] = $message->getAttributes();
                    $email["attachments"] = Array();
                    $email["date_time"] = (string)$message->get("date");
                    $email["message_id"] = (string)$message->get("message_id");
                    $email["from"] = $message->get("from")->toArray()[0]->mail;
                    $email["to"] = $message->get("to")->toArray()[0]->mail;;
                    $email["subject"] = (string)$message->get("subject");
                    $email["in_reply_to"] = (string)$message->get("in_reply_to");
                    $email["references"] = (string)$message->getHeader()->get("references");
                    $email["cc"] = Array();
                    if(!empty($message->getHeader()->get("cc"))){
                        foreach ($message->getHeader()->get("cc")->toArray() as $cc){
                            $email["cc"][] = $cc->mail;
                        }
                    }
                    $email["cc"] = implode(";",$email["cc"]);
                    $email["bcc"] = Array();
                    if(!empty($message->getHeader()->get("bcc"))){
                        foreach ($message->getHeader()->get("bcc")->toArray() as $cc){
                            $email["bcc"][] = $cc->mail;
                        }
                    }
                    $email["bcc"] = implode(";",$email["bcc"]);
                    $email["attachments"] = Array();
                    if($message->hasAttachments()){
                        $email["attachments"] = $message->getAttachments();
                    }
                    $this->emails[$message->uid] = $email;

                    /*dump($message->uid);
                    if($message->uid == 9){
                        dump($message->getHeader()->get("cc"));
                        die;

                    }*/
                });
            });
        }
        catch (\Exception $e){
            $this->errorLogManager->logExceptionEvent($e->getMessage(),$e,true);
        }

        if(empty($this->emails)) {
            return false;
        }

        $existingEmailsByMessageIdArray = $this->emailManager->getEmailsByMessagesId($this->connection->getId());

        foreach ($this->emails as $emailId => $emailData) {

            if(isset($existingEmailsByMessageIdArray[$emailData["message_id"]])){
                continue;
            }

            $mailAttachmentCollection = new MailAttachmentCollection();

            /** @var EmailEntity $email */
            $email = $this->entityManager->getNewEntityByAttributSetName("email");
            $email->setSharedInboxConnection($this->connection);
            $email->setMailDatetime(DateTime::createFromFormat("Y-m-d H:i:s", $emailData["date_time"]));
            $email->setMessageId($emailData["message_id"]);
            $email->setState(SharedInboxConstants::EMAIL_STATE_UNPROCESSED);
            $email->setUid($emailId);

            if (isset($emailData["cc"])) {
                $email->setCc($emailData["cc"]);
            }
            if (isset($emailData["bcc"])) {
                $email->setBcc($emailData["bcc"]);
            }
            if (isset($emailData["from"])) {
                $email->setMailFrom($emailData["from"]);
            }
            if (isset($emailData["to"])) {
                $email->setMailTo($emailData["to"]);
            }
            if (isset($emailData["subject"])) {
                $email->setSubject(StringHelper::removeNonAsciiCharacters(imap_utf8($emailData["subject"])));
            }
            if (isset($emailData["in_reply_to"]) && !empty($emailData["in_reply_to"])) {

                $emailData["in_reply_to"] = $this->formatCid($emailData["in_reply_to"]);
                $email->setInReplyTo($emailData["in_reply_to"]);

                /** @var EmailEntity $parentEmail */
                $parentEmail = $this->emailManager->getEmailByMessageId($emailData["in_reply_to"]);
                if (!empty($parentEmail)) {
                    $email->setParentEmail($parentEmail);
                    $email->setTicket($parentEmail->getTicket());
                }
            }
            if (isset($emailData["references"])) {
                $email->setReferencesIds($emailData["references"]);
            }


            $html = $emailData["html_body"];
            $plain = $emailData["text_body"];

            if(EntityHelper::isCountable($emailData["attachments"]) && count($emailData["attachments"])){
                foreach ($emailData["attachments"] as $attachment){

                    $mailAttachment = new MailAttachment();
                    $mailAttachment->setFilename($attachment->getName());
                    $mailAttachment->setContent($attachment->getContent());
                    $mailAttachment->setFileType(strtolower($attachment->getExtension()));
                    $mailAttachment->setIsEmbedded(false);
                    if ((string)$attachment->getDisposition() == "inline" && !empty((string)$attachment->getId())) {
                        $mailAttachment->setIsEmbedded(true);
                        $mailAttachment->setCid($this->formatCid((string)$attachment->getId()));
                    }
                    $mailAttachmentCollection->addAttachment($mailAttachment);
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

        return true;
    }

    public function saveEmail($sentMimeMessage)
    {
        // TODO: Implement saveEmail() method.
    }
}
