<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\UUIDHelper;
use AppBundle\Managers\AnalyticsManager;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Abstracts\AbstractImportManager;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Entity\MarketingRulesEntity;
use CrmBusinessBundle\Entity\MarketingRulesResultEntity;
use CrmBusinessBundle\Entity\ProductContactRemindMeEntity;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use Doctrine\Common\Util\Inflector;
use AppBundle\Models\InsertModel;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddConstraintValidatorsPass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AutomationsManager extends AbstractBaseManager
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /**@var HelperManager $helperManager */
    protected $helperManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var ApplicationSettingsManager */
    protected $applicationSettingsManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var DiscountCouponManager $discountCouponManager */
    protected $discountCouponManager;
    /** @var AnalyticsManager $analyticsManager */
    protected $analyticsManager;
    /** @var EmailTemplateManager $emailTemplateManager */
    protected $emailTemplateManager;
    /** @var ProductManager $productManager */
    protected $productManager;

    /** @var AttributeSet $asMarketingRulesResult */
    protected $asMarketingRulesResult;

    protected $products;
    protected $stores;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function sendRemindMeEmails()
    {
        $enableOutgoing = $_ENV["ENABLE_OUTGOING_EMAIL"] ?? 1;

        if(!$enableOutgoing){
            return true;
        }

        $productContactEt = $this->entityManager->getEntityTypeByCode("product_contact_remind_me");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("dateSent", "nu", null));
        $compositeFilter->addFilter(new SearchFilter("product.isSaleable", "eq", 1));
        if(isset($_ENV["USE_READY_FOR_WEBSHOP"]) && $_ENV["USE_READY_FOR_WEBSHOP"]){
            $compositeFilter->addFilter(new SearchFilter("product.readyForWebshop", "eq", 1));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $productContacts = $this->entityManager->getEntitiesByEntityTypeAndFilter($productContactEt,$compositeFilters);

        if (count($productContacts) && $enableOutgoing) {

            $saveArray = Array();

            if (empty($this->emailTemplateManager)) {
                $this->emailTemplateManager = $this->container->get("email_template_manager");
            }

            if (empty($this->mailManager)) {
                $this->mailManager = $this->container->get("mail_manager");
            }

            /** @var EmailTemplateEntity $template */
            $template = $this->emailTemplateManager->getEmailTemplateByCode("product_contact_remind_me");
            $templateAttachments = $template->getAttachments();
            if (!empty($templateAttachments)) {
                $attachments = $template->getPreparedAttachments();
            }

            /** @var ProductContactRemindMeEntity $productContact */
            foreach ($productContacts as $productContact) {

                $productContact->setSent(1);
                $productContact->setDateSent(new \DateTime());
                $saveArray[] = $productContact;

                $emailHtml = null;

                try{
                    $emailHtml = $this->emailTemplateManager->renderEmailTemplate($productContact, $template, $productContact->getStore());
                }
                catch (\Exception $e){
                    //do not catch anything
                }

                if(!empty($emailHtml)){
                    $this->mailManager->sendEmail(array("email" => $productContact->getEmail(), "name" => $productContact->getEmail()), null, null, null, $emailHtml["subject"], "", null, array(), $emailHtml["content"], $attachments ?? []);
                }
            }

            if(!empty($saveArray)){
                $this->entityManager->saveArrayEntities($saveArray,$productContactEt);
            }
        }

        return true;
    }

    /**
     * @param $email
     * @param $templateCode
     * @param $storeId
     * @param array $data
     * @param null $emailTemplate
     * @return DiscountCouponEntity|null
     * @throws \Exception
     */
    public function sendCouponEmail($email, $templateCode, $storeId, $data = array(), $emailTemplate = null)
    {

        $enableOutgoing = $_ENV["ENABLE_OUTGOING_EMAIL"] ?? 1;

        $discountCoupon = null;

        if ($enableOutgoing) {

            if (empty($this->discountCouponManager)) {
                $this->discountCouponManager = $this->container->get("discount_coupon_manager");
            }

            /** @var DiscountCouponEntity $discountCoupon */
            $discountCoupon = $this->discountCouponManager->generateCouponFromTemplate($templateCode);

            if (!empty($discountCoupon)) {

                if (empty($this->mailManager)) {
                    $this->mailManager = $this->container->get("mail_manager");
                }
                $data["discount_coupon"] = $discountCoupon;

                $bcc[] = array(
                    'email' => $_ENV["ORDER_EMAIL_RECIPIENT"],
                    'name' => $_ENV["ORDER_EMAIL_RECIPIENT"],
                );

                if (empty($this->routeManager)) {
                    $this->routeManager = $this->container->get("route_manager");
                }

                if(empty($storeId)){
                    $session = $this->getContainer()->get("session");
                    $storeId = $session->get("current_store_id");
                }

                /** @var SStoreEntity $store */
                $store = $this->routeManager->getStoreById($storeId);

                $data["site_base_data"] = $this->routeManager->prepareSiteBaseData($store->getWebsiteId());
                $data["money_transfer_payment_slip"] = $this->routeManager->prepareMoneyTransferPaymentSlip($store->getId());
                $data["current_store_id"] = $store->getId();

                if (!empty($emailTemplate)) {

                    if (empty($this->emailTemplateManager)) {
                        $this->emailTemplateManager = $this->container->get("email_template_manager");
                    }
                    $emailHtml = $this->emailTemplateManager->renderEmailTemplate($discountCoupon, $emailTemplate, $store);

                    $this->mailManager->sendEmail(array("email" => $email, "name" => $email), null, null, null, $emailHtml["subject"], "", null, array(), $emailHtml["content"], array());
                } else {
                    $this->mailManager->sendEmail(array('email' => $email, 'name' => $email), null, null, null, $this->translator->trans('Newsletter discount coupon'), "", "newsletter_discount_coupon", $data, null, array(), $storeId);
                }
            }
        }

        return $discountCoupon;
    }

    public function sendWarningEmailsForProductsWithLowQty()
    {
        if (empty($this->mailManager)) {
            $this->mailManager = $this->container->get("mail_manager");
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->container->get("application_settings_manager");
        }

        $enableOutgoing = $_ENV["ENABLE_OUTGOING_EMAIL"] ?? 1;
        $activeWebsiteStores = $this->applicationSettingsManager->getApplicationSettingByCode("website_question_email");

        if ($enableOutgoing && !empty($activeWebsiteStores)) {

            $query = "SELECT id, base_url FROM s_website_entity";
            $queryData = $this->databaseContext->getAll($query);

            $websites = array();
            foreach ($queryData as $website) {
                $websites[$website["id"]] = $website["base_url"];
            }

            $productImageEntityType = $this->entityManager->getEntityTypeByCode("product_images");

            foreach ($activeWebsiteStores as $storeId => $storeQuestionsEmail) {

                $query = "SELECT p.id, p.name AS product_name, p.url AS product_url, pi.id AS proudct_image_id, p.show_on_store, p.code
                        FROM product_entity AS p 
                        LEFT JOIN product_images_entity AS pi ON p.id = pi.product_id
                        WHERE p.is_saleable = 1 AND p.qty < JSON_UNQUOTE(JSON_EXTRACT(p.warning_mail_qty, '$.\"{$storeId}\"')) AND JSON_CONTAINS(p.show_on_store, '1', '$.\"{$storeId}\"') = '1';";

                $productsWithLowQty = $this->databaseContext->getAll($query);

                if (!empty($productsWithLowQty)) {

                    if (empty($this->routeManager)) {
                        $this->routeManager = $this->container->get("route_manager");
                    }

                    $store = $this->routeManager->getStoreById($storeId);
                    $websiteId = $store->getWebsiteId();

                    if (!isset(json_decode($_ENV["SITE_BASE_DATA"], true)[$websiteId]) || !isset(json_decode($_ENV["MONEY_TRANSFER_PAYMENT_SLIP"], true)[$storeId])) {
                        continue;
                    }

                    $data = array();
                    $data["site_base_data"] = json_decode($_ENV["SITE_BASE_DATA"], true)[$websiteId];
                    $data["money_transfer_payment_slip"] = json_decode($_ENV["MONEY_TRANSFER_PAYMENT_SLIP"], true)[$storeId];
                    $data["base_url"] = $websites[$websiteId];
                    $data["current_store_id"] = $storeId;
                    $data["web_path"] = $_ENV["WEB_PATH"] . "Documents/Products/";

                    foreach ($productsWithLowQty as $product) {
                        /** @var ProductImagesEntity $productImage */
                        $productImage = $this->entityManager->getEntityByEntityTypeAndId($productImageEntityType, $product["proudct_image_id"]);

                        $data["products"][$product["id"]]["product"] = array("name" => json_decode($product["product_name"], true)[$storeId], "url" => json_decode($product["product_url"], true)[$storeId], "id" => $product["id"], "code" => $product["code"]);
                        $data["products"][$product["id"]]["product_title"] = json_decode($product["product_name"], true)[$storeId];
                        $data["products"][$product["id"]]["image"] = $productImage;
                    }

                    $this->mailManager->sendEmail(array('email' => $storeQuestionsEmail, 'name' => $storeQuestionsEmail), null, null, null, $this->translator->trans('Low product quantity'), "", "low_product_quantity", $data, null, array(), $storeId);
                }
            }
        }
    }

    /**
     * @param $id
     * @return |null
     */
    public function getMarketingRuleById($id)
    {

        $et = $this->entityManager->getEntityTypeByCode("marketing_rules");

        return $this->entityManager->getEntityByEntityTypeAndId($et, $id);
    }

    /**
     * @param $marketingRuleCode
     * @return |null
     */
    public function getMarketingRuleByCode($marketingRuleCode)
    {

        $et = $this->entityManager->getEntityTypeByCode("marketing_rules");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("ruleCode", "eq", $marketingRuleCode));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);
    }

    /**
     * @param $marketingRuleCode
     * @param bool $debug
     * @return array
     */
    public function runMarketingRuleByCode($marketingRuleCode, $debug = false)
    {

        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;

        /** @var MarketingRulesEntity $marketingRule */
        $marketingRule = $this->getMarketingRuleByCode($marketingRuleCode);

        if (empty($marketingRule)) {
            $ret["message"] = "Missing marketing rule";
            return $ret;
        }

        if (!$marketingRule->getIsActive()) {
            $ret["message"] = "Marketing rule is inactive";
            return $ret;
        }

        if (empty($marketingRule->getMarketingRuleType()->getManagerCode())) {
            $ret["message"] = "Manager is empty for {$marketingRule->getMarketingRuleType()->getName()}";
            return $ret;
        } else {
            $manager = $this->getContainer()->get($marketingRule->getMarketingRuleType()->getManagerCode());
            if (empty($manager)) {
                $ret["message"] = "Manager {$marketingRule->getMarketingRuleType()->getManagerCode()} does not exist";
            } else {
                if (empty($marketingRule->getMarketingRuleType()->getMethod())) {
                    $ret["message"] = "Method is empty for {$marketingRule->getMarketingRuleType()->getName()}";
                } elseif (!EntityHelper::checkIfMethodExists($manager, $marketingRule->getMarketingRuleType()->getMethod())) {
                    $ret["message"] = "Manager {$marketingRule->getMarketingRuleType()->getManagerCode()} does not have method {$marketingRule->getMarketingRuleType()->getMethod()};";
                }
            }
        }

        if (!empty($ret["message"])) {
            return $ret;
        }

        /**
         * If debug is on run without try catch
         */
        if ($debug) {

            $tmp = $manager->{$marketingRule->getMarketingRuleType()->getMethod()}($marketingRule, $debug);

            dump($tmp);
            die;
        } else {
            try {
                $tmp = $manager->{$marketingRule->getMarketingRuleType()->getMethod()}($marketingRule, $debug);
            } catch (\Exception $e) {
                $ret["message"] = $e->getMessage();
                return $ret;
            }
        }

        $ret["error"] = false;

        if (isset($tmp["message"])) {
            $ret["message"] = $tmp["message"];
        }

        return $ret;
    }

    /**
     * @param MarketingRulesEntity $marketingRule
     * @param bool $debug
     * @return array
     * @throws \Exception
     */
    public function marketingDefaultAutomation(MarketingRulesEntity $marketingRule, $debug = false)
    {

        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;

        $abstractImportManager = new AbstractImportManager();
        $abstractImportManager->setContainer($this->getContainer());
        $abstractImportManager->initialize();

        $this->asMarketingRulesResult = $this->entityManager->getAttributeSetByCode("marketing_rules_result");
        $insertArray = array("marketing_rules_result_entity" => array());

        $storeIds = $marketingRule->getShowOnStore();
        foreach ($storeIds as $storeId => $storeStatus) {

            if (!$storeStatus) {
                continue;
            }

            if (!empty($marketingRule->getRuleQuery())) {

                if (empty($this->databaseContext)) {
                    $this->databaseContext = $this->container->get("database_context");
                }

                if (empty($this->analyticsManager)) {
                    $this->analyticsManager = $this->container->get("analytics_manager");
                }

                $params = array();
                $params["store_id"] = $storeId;
                $params["group_ids"] = 0;

                $groups = $marketingRule->getMarketingRuleGroups();
                if (EntityHelper::isCountable($groups) && count($groups)) {

                    $groupIds = array();

                    foreach ($groups as $group) {
                        $groupIds[] = $group->getId();
                    }

                    $params["group_ids"] = implode(",", $groupIds);
                }

                $preparedQuery = $this->analyticsManager->prepareAnalyticsQuery($marketingRule->getRuleQuery(), $params);

                $data = $this->databaseContext->getAll($preparedQuery);

                if ($debug) {
                    dump($data);
                    die;
                }
            } else {

                if (empty($marketingRule->getManagerCode())) {
                    $ret["message"] = "Manager is empty for {$marketingRule->getName()}";
                    return $ret;
                } else {
                    $manager = $this->getContainer()->get($marketingRule->getManagerCode());
                    if (empty($manager)) {
                        $ret["message"] = "Manager {$marketingRule->getManagerCode()} does not exist";
                    } else {
                        if (empty($marketingRule->getRuleMethod())) {
                            $ret["message"] = "Method is empty for {$marketingRule->getName()}";
                        } elseif (!EntityHelper::checkIfMethodExists($manager, $marketingRule->getRuleMethod())) {
                            $ret["message"] = "Manager {$marketingRule->getManagerCode()} does not have method {$marketingRule->getRuleMethod()};";
                        }
                    }
                }

                if (!empty($ret["message"])) {
                    return $ret;
                }

                if ($debug) {
                    $data = $manager->{$marketingRule->getRuleMethod()}($storeId, $debug);
                    dump($data);
                    die;
                } else {
                    try {
                        $data = $manager->{$marketingRule->getRuleMethod()}($storeId, $debug);
                    } catch (\Exception $e) {
                        $ret["message"] = $e->getMessage();
                        return $ret;
                    }
                }
            }

            /**
             * Ako nema nicega nastavi dalje
             */
            if (empty($data)) {
                continue;
            }

            foreach ($data as $d) {

                if (empty($d["email"])) {
                    continue;
                }

                $insertValues = (new InsertModel($this->asMarketingRulesResult))->getArray();
                $insertValues["marketing_rule_id"] = $marketingRule->getId();
                $insertValues["uid"] = UUIDHelper::generateUUID();
                $insertValues["store_id"] = $storeId;
                $insertValues["is_sent"] = 0;
                $insertValues["contact_id"] = null;
                if (isset($d["contact_id"])) {
                    $insertValues["contact_id"] = $d["contact_id"];
                }
                $insertValues["first_name"] = null;
                if (isset($d["first_name"])) {
                    $insertValues["first_name"] = $d["first_name"];
                }
                $insertValues["last_name"] = null;
                if (isset($d["last_name"])) {
                    $insertValues["last_name"] = $d["last_name"];
                }
                $insertValues["email"] = $d["email"];
                $insertValues["discount_coupon_id"] = null;
                $insertValues["product_id"] = null;
                if (isset($d["product_id"])) {
                    $insertValues["product_id"] = $d["product_id"];
                }
                $insertValues["quote_id"] = null;
                if (isset($d["quote_id"])) {
                    $insertValues["quote_id"] = $d["quote_id"];
                }

                if (!empty($marketingRule->getDiscountCouponTemplate())) {

                    if (empty($this->discountCouponManager)) {
                        $this->discountCouponManager = $this->container->get("discount_coupon_manager");
                    }

                    /** @var DiscountCouponEntity $discountCoupon */
                    $discountCoupon = $this->discountCouponManager->generateCouponFromTemplateEntity($marketingRule->getDiscountCouponTemplate());

                    if (!empty($discountCoupon)) {
                        $discountCouponData = array();
                        $discountCouponData["email"] = $d["email"];
                        $this->discountCouponManager->updateDiscountCoupon($discountCoupon, $discountCouponData);

                        $insertValues["discount_coupon_id"] = $discountCoupon->getId();

                        //todo ako je product kupon treba fixirati na product id
                    }
                }

                $insertArray["marketing_rules_result_entity"][] = $insertValues;
            }
        }

        $abstractImportManager->executeInsertQuery($insertArray);

        $ret["error"] = false;

        $importLogData = array();
        $importLogData['error'] = $ret["error"];
        $importLogData['completed'] = 1;
        $importLogData['message'] = $ret["message"];
        $importLogData["error_log"] = $importLogData['message'];
        $importLogData["name"] = "Marketing automation - " . $marketingRule->getName();

        $abstractImportManager->insertImportLog($importLogData);

        return $ret;
    }

    /**
     * @param bool $debug
     * @param null $additionalFilter
     * @return bool
     * @throws \Exception
     */
    public function runMarketingResultsQueue($debug = false, $additionalFilter = null)
    {

        $et = $this->entityManager->getEntityTypeByCode("marketing_rules_result");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isSent", "eq", 0));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalFilter)) {
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "asc"));

        $marketingResults = $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters);

        //TODO ovdje ce mozda terebati staviti status neki, da ga sljedeci cron ne uzme

        if ($debug) {
            dump(count($marketingResults));
            die;
        }

        if (empty($marketingResults)) {
            return true;
        }

        if (empty($this->emailTemplateManager)) {
            $this->emailTemplateManager = $this->container->get("email_template_manager");
        }

        if (empty($this->mailManager)) {
            $this->mailManager = $this->container->get("mail_manager");
        }

        $updateIds = array();

        /** @var MarketingRulesResultEntity $marketingResult */
        foreach ($marketingResults as $marketingResult) {

            try{
                $email = $this->emailTemplateManager->renderEmailTemplate($marketingResult,$marketingResult->getMarketingRule()->getEmailTemplate(),$marketingResult->getStore());

                    if(!isset($email["content"]) || empty($email["content"])){
                    continue;
                    //todo rijesiti da se ne vrti u petlji
                }

                $this->mailManager->sendEmail(array("email" => $marketingResult->getEmail(), "name" => $marketingResult->getEmail()), null, null, null, $email["subject"], "", null, array(), $email["content"], array(), $marketingResult->getStoreId());
            }
            catch (\Exception $e){
                //do not catch anything
                //ovo je nuzno jer ako se ne updatea da je poslano, udje u loop
            }

            $updateIds[] = $marketingResult->getId();
        }

        if (!empty($updateIds)) {

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }

            $q = "UPDATE marketing_rules_result_entity SET is_sent = 1, date_sent = NOW() WHERE id in (" . implode(",", $updateIds) . ");";
            $this->databaseContext->executeNonQuery($q);
        }

        return true;
    }
}

?>