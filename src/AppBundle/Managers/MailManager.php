<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\Email;
use AppBundle\Entity\TransactionEmailEntity;
use AppBundle\Interfaces\Providers\IEmailProvider;
use AppBundle\Models\MailAddress;
use AppBundle\Models\MailAddressCollection;
use AppBundle\Models\MailAttachment;
use AppBundle\Models\MailAttachmentCollection;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Lock\Tests\Store\ExpiringStoreTestTrait;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;

class MailManager extends AbstractBaseManager
{
    /** @var TransactionEmailManager $transactionEmailManager */
    protected $transactionEmailManager;
    /** @var IEmailProvider $mailProvider */
    protected $mailProvider;

    public function initialize()
    {
        parent::initialize();
        $this->transactionEmailManager = $this->container->get("transaction_email_manager");
        $this->mailProvider = $this->container->get("mail_provider");
    }

    /**
     * @param $to
     * @param null $cc
     * @param null $bcc
     * @param null $replyto
     * @param $subject
     * @param string $text
     * @param $template
     * @param $data
     * @param null $generated_html
     * @param array $attachments
     * @param null $storeId
     * @return bool
     */
    public function prepareTransactionEmail($to, $cc = null, $bcc = null, $replyto = null, $subject, $text = "", $template, $data, $generated_html = null, $attachments = array(), $storeId = null, $relatedEntityTypeId = null, $relatedEntityId = null)
    {
        if (empty($to)) {
            return false;
        }
        if (empty($subject)) {
            return false;
        }
        if (empty($template) && empty($generated_html)) {
            return false;
        }
        if (!empty($template) && empty($data)) {
            return false;
        }

        $data["subject"] = $subject;
        $data["settings"]["support_email"] = $_ENV["SUPPORT_EMAIL"];

        $cc_mail = array();
        if (!empty($cc)) {
            if (isset($cc["email"])) {
                $cc = [$cc];
            }
            foreach ($cc as $c) {
                $cc_mail[] = array($c["email"] => $c["name"]);
            }
        }

        $bcc_mail = array();
        if (!empty($bcc)) {
            if (isset($bcc["email"])) {
                $bcc = [$bcc];
            }
            foreach ($bcc as $b) {
                $bcc_mail[] = array($b["email"] => $b["name"]);
            }
        }

        $replyto_mail = array($_ENV["NOREPLAY_EMAIL"] => $_ENV["NOREPLAY_EMAIL"]);
        if (!empty($replyto)) {
            $replyto_mail = array($replyto["email"] => $replyto["name"]);
        }

        $html = $generated_html;
        if (!empty($template)) {

            $data = array_merge($data, $this->prepareDataArrayForTemplate($storeId));

            /** @var AppTemplateManager $templateManager */
            $templateManager = $this->container->get('app_template_manager');
            $html = $this->container->get('templating')->render($templateManager->getTemplatePathByBundle("Email:" . $template . ".html.twig", $data["current_website_id"]), [
                'data' => $data,
            ]);
        }

        $fromName = $_ENV["FROM_MAIL"];
        if (isset($_ENV["FROM_MAIL_NAME"]) && !empty($_ENV["FROM_MAIL_NAME"])) {
            $fromName = $_ENV["FROM_MAIL_NAME"];
        }

        $headers = array();
        if (isset($data["headers"])) {
            $headers = $data["headers"];
        }

        $email = [
            "email" => $to["email"],
            "to" => array($to["email"] => $to["name"]),
            "from" => array($fromName, $_ENV["FROM_MAIL"]),
            "subject" => $subject,
            "text" => $text,
            "html" => $html,
            "attachment" => $attachments,
            "headers" => $headers,
            //"headers" => array("Content-Type"=> "text/html; charset=utf-8"),
            //"headers" => array("Content-Type"=> "text/html; charset=iso-8859-1","X-param1"=> "value1", "X-param2"=> "value2","X-Mailin-custom"=>"my custom value", "X-Mailin-IP"=> "102.102.1.2", "X-Mailin-Tag" => "My tag"),
            //"inline_image" => array("myinlineimage1.png" => "your_png_files_base64_encoded_chunk_data","myinlineimage2.jpg" => "your_jpg_files_base64_encoded_chunk_data")
        ];
        if (!empty($cc_mail)) {
            $email["cc"] = $cc_mail;
        }
        if (!empty($bcc_mail)) {
            $email["bcc"] = $bcc_mail;
        }
        if (!empty($replyto_mail)) {
            $email["replyto"] = $replyto_mail;
        }

        return $this->transactionEmailManager->createUpdateTransactionEmail($email,null,$relatedEntityTypeId,$relatedEntityId);
    }

    /**
     * @param null $storeId
     * @return array
     */
    public function prepareDataArrayForTemplate($storeId = null)
    {

        //$websiteId = $_ENV["DEFAULT_WEBSITE_ID"] ?? 1;
        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        if (!empty($storeId)) {
            $store = $this->routeManager->getStoreById($storeId);
            $websiteData = $this->routeManager->getWebsiteDataById($store->getWebsiteId());

            $data["current_store_id"] = $storeId;
            $data["current_website_id"] = $store->getWebsiteId();
            $data["current_language"] = $store->getCoreLanguage()->getCode();
            $data["current_language_url"] = $this->routeManager->getLanguageUrl($store);
            $data["site_base_data"] = $this->routeManager->prepareSiteBaseData($store->getWebsiteId());
            $data["site_base_data"]["site_base_url"] = $_ENV["SSL"] . "://" . $websiteData["base_url"] . "/";
            $data["site_base_data"]["site_base_url_language"] = $_ENV["SSL"] . "://" . $websiteData["base_url"] . $data["current_language_url"] . "/";
            $data["money_transfer_payment_slip"] = $this->routeManager->prepareMoneyTransferPaymentSlip($storeId);

        } else {
            /** @var Session $session */
            $session = $this->container->get("session");

            //TODO ovdje moze eventualno biti problem

            $data["current_store_id"] = $session->get("current_store_id");
            $data["current_website_id"] = $session->get("current_website_id");
            $data["current_language"] = $session->get("current_language");
            $data["current_language_url"] = $session->get("current_language_url");
            $data["site_base_data"] = $session->get("site_base_data");

            $data["money_transfer_payment_slip"] = $this->routeManager->prepareMoneyTransferPaymentSlip($session->get("current_store_id"));
        }

        return $data;
    }

    /**
     * @param TransactionEmailEntity $transactionEmail
     */
    public function sendTransactionEmail(TransactionEmailEntity $transactionEmail)
    {
        $data = json_decode($transactionEmail->getContent(), TRUE);
        if (!isset($data['to'])) {
            throw new MissingMandatoryParametersException("to missing");
        }

        $email = new Email();

        reset($data["to"]);
        $key = key($data["to"]);

        $email->setSubject($transactionEmail->getSubject());
        $email->setText($data["text"] ?? "");
        $email->setHtml($data["html"] ?? null);

        $toAddress = new MailAddress($key, $data["to"][$key]);
        $toAddressCollection = new MailAddressCollection();
        $toAddressCollection->addMailAddress($toAddress);
        $email->setTo($toAddressCollection);

        $fromAddress = new MailAddress($data["from"][0], $data["from"][1]);
        $email->setFrom($fromAddress);

        $addressCollection = null;
        if (!empty($data["cc"])) {
            $addressCollection = new MailAddressCollection();
            foreach ($data["cc"] as $cc) {
                reset($cc);
                $key = key($cc);
                $ccAddress = new MailAddress($key, $cc[$key]);
                $addressCollection->addMailAddress($ccAddress);
            }
        }
        $email->setCc($addressCollection);

        $addressCollection = null;
        if (!empty($data["bcc"])) {
            $addressCollection = new MailAddressCollection();
            foreach ($data["bcc"] as $bcc) {
                reset($bcc);
                $key = key($bcc);
                $bccAddress = new MailAddress($key, $bcc[$key]);
                $addressCollection->addMailAddress($bccAddress);
            }
        }
        $email->setBcc($addressCollection);

        $replyTo = null;
        if (!empty($data["replyto"])) {
            reset($data["replyto"]);
            $key = key($data["replyto"]);
            $replyTo = new MailAddress($key, $data["replyto"][$key]);
        }
        $email->setReplyTo($replyTo);

        $mailAttachments = new MailAttachmentCollection();

        foreach ($data["attachment"] ?? array() as $item) {
            $attachment = new MailAttachment();
            $attachment->setUrl($item);
            $mailAttachments->addAttachment($attachment);
        }

        $email->setAttachments($mailAttachments);
        if (isset($data["headers"])) {
            $email->setHeaders($data["headers"]);
        }

        $this->mailProvider->initialize();
        $result = $this->mailProvider->sendSingleEmail($email);

        if (isset($result["error"]) && $result["error"]) {
            // Sending failed
            $this->transactionEmailManager->setTransactionEmailFailed($transactionEmail, $result["result"]);
        } else {
            // Sending succeeded
            $this->transactionEmailManager->setTransactionEmailSent($transactionEmail);
            $this->transactionEmailManager->dispatchTransactionEmailSent($transactionEmail, $result["result"]);
        }
    }

    /**
     * @param $to
     * @param null $cc
     * @param null $bcc
     * @param null $replyto
     * @param $subject
     * @param string $text
     * @param $template
     * @param $data
     * @param null $generated_html
     * @param array $attachments
     * @param null $storeId
     * @return bool
     */
    public function sendEmail($to, $cc = null, $bcc = null, $replyto = null, $subject, $text = "", $template, $data, $generated_html = null, $attachments = array(), $storeId = null, $relatedEntityTypeId = null, $relatedEntityId = null)
    {
        return $this->prepareTransactionEmail($to, $cc, $bcc, $replyto, $subject, $text, $template, $data, $generated_html, $attachments, $storeId, $relatedEntityTypeId, $relatedEntityId);
    }

}
