<?php

namespace AppBundle\Providers;

use AppBundle\Entity\Email;
use AppBundle\Entity\ImapConnection;
use AppBundle\Models\MailAddressCollection;
use AppBundle\Models\MailAttachmentCollection;
use AppBundle\Models\MailSender;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;


class SendinblueProvider implements \AppBundle\Interfaces\Providers\IEmailProvider
{
    #/** @var SMTPApi $sendinBlueApi */
    protected $sendinBlueApi;
    protected $container;
    /**@var Logger $logger */
    protected $logger;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function initialize(ImapConnection $connection = null)
    {
        $this->sendinBlueApi = $this->container->get("sendinblue_api");
        $this->logger = $this->container->get('logger');
    }

    /**
     * @param Email $email
     * @return array
     */
    public function sendSingleEmail(Email $email)
    {
        $ret = Array();
        $ret["error"] = true;

        $replayToCollection = new MailAddressCollection();
        $replayToCollection->addMailAddress($email->getReplyTo());

        $emailData = array(
            "to" => $this->prepareRecipients($email->getTo()),
            "cc" => $this->prepareRecipients($email->getCc()),
            "bcc" => $this->prepareRecipients($email->getBcc()),
            "replyto" => $this->prepareRecipients($replayToCollection),
            "from" => array($email->getFrom()->getEmail(), $email->getFrom()->getName()),
            "subject" => $email->getSubject(),
            "text" => $email->getText(),
            "html" => $email->getHtml(),
            "attachment" => $this->prepareAttachments($email->getAttachments()),
            //"headers" => array("Content-Type"=> "text/html; charset=iso-8859-1","X-param1"=> "value1", "X-param2"=> "value2","X-Mailin-custom"=>"my custom value", "X-Mailin-IP"=> "102.102.1.2", "X-Mailin-Tag" => "My tag"),
            //"inline_image" => array("myinlineimage1.png" => "your_png_files_base64_encoded_chunk_data","myinlineimage2.jpg" => "your_jpg_files_base64_encoded_chunk_data")
        );

        $result = null;
        try {
            $result = $this->sendinBlueApi->send_email($emailData);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        if (!isset($result["code"]) || $result["code"] != "success") {
            $this->logger->error($result["code"]);
        }
        else{
            $ret["error"] = false;
        }

        $ret["result"] = $result;
        return $ret;
    }


    /**
     * @param MailAddressCollection $addressCollection
     * @return array
     */
    private function prepareRecipients(MailAddressCollection $addressCollection = null)
    {
        $recipients = array();

        if (!empty($addressCollection) && !empty($addressCollection->getAddresses())) {
            /**@var \AppBundle\Models\MailAddress $address */
            foreach ($addressCollection->getAddresses() as $address) {
                $recipients[$address->getEmail()] = $address->getName();
            }
        }
        return $recipients;
    }

    /**
     * @param MailAttachmentCollection $attachmentCollection
     * @return array
     */
    private function prepareAttachments(MailAttachmentCollection $attachmentCollection)
    {
        $root = $this->container->getParameter('web_path');
        $attachments = array();

        if (!empty($attachmentCollection->getAttachments())) {
            /**@var \AppBundle\Models\MailAttachment $attachment */
            foreach ($attachmentCollection->getAttachments() as $attachment) {
                if ($attachment->getContent() == null) {
                    $attachment_content = chunk_split(base64_encode(file_get_contents($root.$attachment->getUrl())));
                    $attachments[basename($attachment->getUrl())] = $attachment_content;
                } else {
                    $attachment_content = chunk_split(base64_encode($attachment->getContent()));
                    $attachments[$attachment->getFilename()] = $attachment_content;
                }

            }
        }
        return $attachments;
    }

}
