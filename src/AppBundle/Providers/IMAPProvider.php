<?php

namespace AppBundle\Providers;

use AppBundle\Entity\Email;
use AppBundle\Entity\ImapConnection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Providers\IEmailProvider;
use AppBundle\Models\MailAddress;
use AppBundle\Models\MailAttachment;
use AppBundle\Models\MailAttachmentCollection;
use Monolog\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IMAPProvider implements IEmailProvider
{
    /** @var ImapConnection $connection */
    protected $connection;
    /** @var Logger $logger */
    protected $logger;
    protected $container;

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
        if (empty($connection)) {
            $connection = new ImapConnection();
            $connection->setUsername($_ENV["IMAP_USERNAME"]);
            $connection->setPassword($_ENV["IMAP_PASSWORD"]);
            $connection->setHost($_ENV["IMAP_HOST"]);
            $connection->setPort($_ENV["IMAP_PORT"]);
            $connection->setDebug($_ENV["IMAP_DEBUG"]);
            $connection->setAuth($_ENV["IMAP_AUTH"]);
            if (isset($_ENV["IMAP_SECURE"]) && !empty($_ENV["IMAP_SECURE"])) {
                $connection->setSecure($_ENV["IMAP_SECURE"]);
            } else {
                $connection->setSecure(false);
            }
        }
        $this->connection = $connection;
        $this->logger = $this->container->get("logger");
    }

    /**
     * @param Email $email
     * @return mixed
     */
    public function sendSingleEmail(Email $email)
    {
        $mail = new PHPMailer();

        $mail->IsSMTP();
        $mail->SMTPDebug = $this->connection->getDebug();
        $mail->SMTPAuth = $this->connection->getAuth();
        $mail->SMTPSecure = $this->connection->getSecure();
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => false
            )
        );

        $mail->Username = $this->connection->getUsername();
        $mail->Password = $this->connection->getPassword();

        $mail->Host = $this->connection->getHost();
        $mail->Port = $this->connection->getPort();
        $mail->CharSet = 'UTF-8';

        $mail->IsHTML(true);

        $mail->setFrom($email->getFrom()->getName(), $email->getFrom()->getEmail());
        $mail->Subject = $email->getSubject();

        /** @var MailAddress $to */
        foreach ($email->getTo()->getAddresses() as $to) {
            $mail->addAddress($to->getEmail(), $to->getName());
        }

        if (!empty($email->getCc()) && EntityHelper::isCountable($email->getCc()->getAddresses()) && count($email->getCc()->getAddresses()) > 0) {
            /** @var MailAddress $cc */
            foreach ($email->getCc()->getAddresses() as $cc) {
                $mail->AddCC($cc->getEmail(), $cc->getName());
            }
        }

        if (!empty($email->getBcc()) && EntityHelper::isCountable($email->getBcc()->getAddresses()) && count($email->getBcc()->getAddresses()) > 0) {
            /** @var MailAddress $bcc */
            foreach ($email->getBcc()->getAddresses() as $bcc) {
                $mail->AddBCC($bcc->getEmail(), $bcc->getName());
            }
        }

        if (!empty($email->getHeaders())) {
            foreach ($email->getHeaders() as $key => $value) {
                $mail->addCustomHeader($key, $value);
            }
        }

        $body = $this->embedImages($mail, $email->getHtml());

        $mail->msgHTML($body);
        $mail->AltBody = strip_tags($email->getHtml());

        if (!empty($email->getAttachments())) {
            $attachments = $this->prepareAttachments($email->getAttachments());
            foreach ($attachments as $attachment) {
                $mail->AddAttachment($attachment[0], $attachment[1]);
            }
        }

        $ret = array();

        $result = $mail->send();
        if (!$result) {
            $ret["error"] = true;
            $ret["result"] = $mail->ErrorInfo;
        } else {
            $ret["error"] = false;
            $ret["result"] = $mail->getSentMIMEMessage();
        }

        return $ret;
    }

    /**
     * @param PHPMailer $mail
     * @param $body
     * @return string|string[]
     */
    public function embedImages(PHPMailer $mail, $body)
    {
        preg_match_all('/<img.*?>/', $body, $matches);

        if (!isset($matches[0])) {
            return $body;
        }

        $webPath = $this->container->getParameter("web_path");

        foreach ($matches[0] as $key => $img) {

            preg_match('/src="(.*?)"/', $img, $m);
            if (!isset($m[1])) {
                continue;
            }

            $url = parse_url($m[1]);
            if (!isset($url["path"])) {
                continue;
            }

            $cid = "img" . ($key + 1);

            $mail->AddEmbeddedImage($webPath . $url["path"], $cid);
            $body = str_replace($img, '<img alt="" src="cid:' . $cid . '" style="border: none;" />', $body);
        }

        return $body;
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
            /** @var MailAttachment $attachment */
            foreach ($attachmentCollection->getAttachments() as $attachment) {
                if ($attachment->getContent() == null) {
                    //$attachment_content = chunk_split(base64_encode(file_get_contents($root.$attachment->getUrl())));
                    //$attachments[] = Array(basename($attachment->getUrl()), $attachment_content);
                    $attachments[] = array($root . $attachment->getUrl(), basename($attachment->getUrl()));
                } else {
                    //$attachment_content = chunk_split(base64_encode($attachment->getContent()));
                    //$attachments[] = Array($attachment->getFilename(), $attachment_content);
                    $attachments[] = array($root . $attachment->getFilename(), $attachment->getFilename());
                }
            }
        }

        return $attachments;
    }
}