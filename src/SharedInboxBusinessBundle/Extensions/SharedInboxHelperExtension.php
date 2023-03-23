<?php

namespace SharedInboxBusinessBundle\Extensions;

use SharedInboxBusinessBundle\Constants\SharedInboxConstants;
use SharedInboxBusinessBundle\Entity\EmailAttachmentEntity;
use SharedInboxBusinessBundle\Entity\EmailEntity;

class SharedInboxHelperExtension extends \Twig_Extension
{
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('extract_message', array($this, 'extractMessage')),
            new \Twig_SimpleFunction('embed_images', array($this, 'embedImages')),
        ];
    }

    /**
     * @param null $message
     * @return |null
     */
    public function extractMessage($message = null)
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($message);

        $finder = new \DomXPath($dom);
        $messageTable = $finder->query("//table[contains(@class, 'message')]");;

        if (empty(count($messageTable))) {
            return $message;
        }

        foreach ($messageTable as $match) {
            return $match->ownerDocument->saveHTML($match);
        }

        return $message;
    }

    /**
     * @param EmailEntity $email
     * @return string|string[]
     */
    public function embedImages(EmailEntity $email)
    {
        $body = (string)$email->getBody();

        /** @var EmailAttachmentEntity $emailAttachment */
        foreach ($email->getAttachments()->getValues() as $emailAttachment) {
            if ($emailAttachment->getIsEmbedded()) {
                $body = str_replace(
                    'src="cid:' . $emailAttachment->getContentId() . '"',
                    'src="' . SharedInboxConstants::SHARED_INBOX_ATTACHMENTS_FOLDER . $emailAttachment->getFile() . '"',
                    $body);
            }
        }

        return $body;
    }
}
