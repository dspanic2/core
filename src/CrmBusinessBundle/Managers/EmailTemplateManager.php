<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\TemplateManager;

class EmailTemplateManager extends AbstractBaseManager
{
    /** @var TemplateManager $templateManager */
    protected $templateManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $code
     * @return EmailTemplateEntity|null
     */
    public function getEmailTemplateByCode($code)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        /** @var Session $session */
        $session = $this->container->get("session");
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $staticContentEntityType = $this->entityManager->getEntityTypeByCode("email_template");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $code));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var EmailTemplateEntity $emailTemplate */
        $emailTemplate = $this->entityManager->getEntityByEntityTypeAndFilter($staticContentEntityType, $compositeFilters);

        if (empty($emailTemplate)) {

            if (empty($this->applicationSettingsManager)) {
                $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
            }

            $disableWarning = intval($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("disable_email_template_missing_warning", $_ENV["DEFAULT_STORE_ID"]));

            if(!$disableWarning){
                if (empty($this->errorLogManager)) {
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }

                $this->errorLogManager->logErrorEvent("Nedostaje template {$code} na store {$storeId}", null, true);
            }
            return null;
        }
        return $emailTemplate;
    }

    /**
     * @param $entity
     * @param EmailTemplateEntity $emailTemplate
     * @return array|bool
     */
    public function renderEmailTemplate($entity, EmailTemplateEntity $emailTemplate, SStoreEntity $store = null, $customData = array())
    {
        if ($entity->getEntityType()->getEntityTypeCode() != $emailTemplate->getProvidedEntityTypeId()) {
            throw new \Exception("Entity type does not match email template type");
        }

        if (!empty($store)) {
            $storeId = $store->getId();
        } else {
            /** @var Session $session */
            $session = $this->container->get("session");
            $storeId = $session->get("current_store_id");
            if (empty($storeId)) {
                $storeId = $_ENV["DEFAULT_STORE_ID"];
            }

            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }

            /** @var SStoreEntity $store */
            $store = $this->routeManager->getStoreById($storeId);
        }

        /**
         * GENERATE SUBJECT
         */
        $subject = $emailTemplate->getSubject();
        if (isset($subject[$storeId])) {
            $subject = $subject[$storeId];
        } else {
            //throw new \Exception("Missing subject store");
        }
        $subject = str_replace(" }}", "|default('')|raw }}", $subject);
        $subject = str_replace("&#39;", "'", $subject);
        $subject = "{% autoescape 'html' %}{$subject}{% endautoescape %}";

        try {
            $template = twig_template_from_string($this->container->get('twig'), $subject);
            $subject = $template->render(
                [
                    'store_id' => $storeId,
                    $entity->getEntityType()->getEntityTypeCode() => $entity
                ],
                [
                    'ignore_errors' => true
                ]);
        } catch (\Exception $exception) {
            dump($exception->getMessage());
            die;
            $this->logger->error("Notification email create error " . $exception->getMessage());
            return false;
        }

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        /**
         * GENERATE CONTENT
         */
        $content = $emailTemplate->getContent();
        if (isset($content[$storeId])) {
            $content = $content[$storeId];
        } else {
            $content = "";
            /**
             * Uklonjeno jer se ne moze napraviti novi template, puca preview
             */
            //throw new \Exception("Missing content store");
        }

        if(isset($customData["content"])){
            $content = $customData["content"];
        }

        $content = trim(preg_replace('/\s\s+/', ' ', str_replace("\n", " ", $content)));

        /**
         * GENERATE TEMPLATE INCLUDES
         */
        if (stripos($content, "include:") !== false) {
            $content = str_replace("{{ include:", "{{include:", $content);
            $content = str_replace(".html.twig }}", ".html.twig}}", $content);
            $content = str_replace(".html.twig }}", ".html.twig}}", $content);
            preg_match_all('/(?<=(include\:))(.*?)(?=(\.html\.twig))/s', $content, $matches);
            if (!empty($matches)) {

                if (empty($this->templateManager)) {
                    $this->templateManager = $this->container->get("template_manager");
                }

                foreach ($matches[0] as $matchFilename) {
                    $html = $this->twig->render($this->templateManager->getTemplatePathByBundle("Email/EmailTemplatePieces:{$matchFilename}.html.twig", $store->getWebsiteId()), ['entity' => $entity, "custom_data" => $customData]);
                    $content = str_replace("{{include:{$matchFilename}.html.twig}}", $html, $content);
                }
            }
        }

        $includes = "";
        if (stripos($content, "include_secondary:") !== false) {
            $content = str_replace("{{ include_secondary:", "{{include_secondary:", $content);
            $content = str_replace(".html.twig }}", ".html.twig}}", $content);
            $content = str_replace(".html.twig }}", ".html.twig}}", $content);
            preg_match_all('/(?<=(include_secondary\:))(.*?)(?=(\.html\.twig))/s', $content, $matches);
            if (!empty($matches)) {

                if (empty($this->templateManager)) {
                    $this->templateManager = $this->container->get("template_manager");
                }

                foreach ($matches[0] as $matchFilename) {
                    $html = $this->twig->render($this->templateManager->getTemplatePathByBundle("Email/EmailTemplatePieces:{$matchFilename}.html.twig", $store->getWebsiteId()), ['entity' => $entity, "custom_data" => $customData]);
                    $includes .= $html;
                    $content = str_replace("{{include_secondary:{$matchFilename}.html.twig}}", "", $content);
                }
            }
        }

        $content = str_replace(" }}", "|default('')|raw }}", $content);
        $content = str_replace("&#39;", "'", $content);
        $content = "{% autoescape 'html' %}{$content}{% endautoescape %}";

        try {
            $contentTemplate = twig_template_from_string($this->container->get('twig'), $content);

            if (empty($this->templateManager)) {
                $this->templateManager = $this->container->get("template_manager");
            }

            if (empty($this->mailManager)) {
                $this->mailManager = $this->container->get("mail_manager");
            }

            $data = array();
            $data["entity_array"] = $this->entityManager->entityToArray($emailTemplate);
            $data["email_template_entity"] = $emailTemplate;
            $data["subject"] = $subject;
            $data["settings"]["support_email"] = $_ENV["SUPPORT_EMAIL"];
            $data = array_merge($data, $this->mailManager->prepareDataArrayForTemplate($storeId));

            $content = $contentTemplate->render(
                [
                    'store_id' => $storeId,
                    $entity->getEntityType()->getEntityTypeCode() => $entity,
                    'custom_data' => $customData,
                    'data' => $data],
                [
                    'ignore_errors' => true
                ]
            );

            $content = $this->twig->render($this->templateManager->getTemplatePathByBundle("Email:email_template_preview.html.twig", $store->getWebsiteId()), [
                'content' => $content,
                'content_secondary' => $includes,
                'data' => $data
            ]);
        } catch (\Exception $exception) {
            dump($exception->getMessage());
            dump($exception->getTrace());
            die;
            $this->logger->error("Notification email create error " . $exception->getMessage());
            return false;
        }

        return [
            "subject" => $subject,
            "content" => $content,
        ];
    }

    /**
     * @param $templateCode
     * @param $entity
     * @param $storeId
     * @param $toArray
     * @param null $replyTo
     * @param null $ccArray
     * @param null $bccArray
     * @param array $attachments
     * @param null $customData
     * @return bool
     */
    public function sendEmail($templateCode, $entity, $storeId, $toArray, $replyTo = null, $ccArray = null, $bccArray = null, $attachments = [], $customData = [])
    {
        if (empty($this->mailManager)) {
            $this->mailManager = $this->container->get("mail_manager");
        }

        if(empty($this->errorLogManager)){
            $this->errorLogManager = $this->container->get("error_log_manager");
        }

        /** @var EmailTemplateEntity $template */
        $template = $this->getEmailTemplateByCode($templateCode);

        if(empty($template)){
            $this->errorLogManager->logErrorEvent("sendEmail", "Missing email template: {$templateCode}", true);
        }

        $store = null;
        if(!empty($storeId)){
            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }

            /** @var SStoreEntity $store */
            $store = $this->routeManager->getStoreById($storeId);
        }

        try{
            $templateData = $this->renderEmailTemplate($entity, $template, $store, $customData);
        }
        catch (\Exception $e){
            dump($e);
            die;
            $this->errorLogManager->logExceptionEvent("sendEmail", $e, true);
        }

        $templateAttachments = $template->getAttachments();
        if (!empty($templateAttachments)) {
            $attachments = array_merge($attachments, $template->getPreparedAttachments());
        }

        $relatedEntityTypeId = $entity->getEntityType()->getId();
        $relatedEntityId = $entity->getId();
        if(isset($customData["related_entity_type_id"]) && !empty($customData["related_entity_type_id"]) && isset($customData["related_entity_id"]) && !empty($customData["related_entity_id"])){
            $relatedEntityTypeId = $customData["related_entity_type_id"];
            $relatedEntityId = $customData["related_entity_id"];
        }

        $emailData = Array();
        if(isset($customData["email_custom_data"])){
            $emailData = $customData["email_custom_data"];
        }

        return $this->mailManager->sendEmail($toArray, $ccArray, $bccArray, $replyTo, $templateData["subject"], "", null, $emailData, $templateData["content"], $attachments, $storeId, $relatedEntityTypeId, $relatedEntityId);
    }

}
