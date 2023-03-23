<?php

namespace AppBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\ErrorLogEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Entity\ImportLogEntity;
use Exception;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ErrorLogManager implements ContainerAwareInterface
{
    protected $container;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var ApplicationSettingsManager $settingsManager */
    protected $settingsManager;

    public function initialize()
    {

    }

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param null $additionalFilter
     * @return null
     */
    public function getFilteredErrorLog($additionalFilter = null){

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        $et = $this->entityManager->getEntityTypeByCode("error_log");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if(!empty($additionalFilter)){
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getErrorLogById($id){

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(ErrorLogEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $name
     * @param null $description
     * @param bool $sendEmail
     * @param array $cc
     * @param int $minutesLimit
     * @return bool
     */
    public function logErrorEvent($name, $description = null, $sendEmail = true, $cc = array(), $minutesLimit = 0)
    {
        if($minutesLimit > 0){

            $date = new \DateTime();
            $date->sub(new \DateInterval('PT' . $minutesLimit . 'M'));

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("name", "eq", $name));
            $compositeFilter->addFilter(new SearchFilter("created", "ge", $date->format("Y-m-d H:i:s")));

            /** @var ErrorLogEntity $errorLogEntity */
            $errorLogEntity = $this->getFilteredErrorLog($compositeFilter);

            if(!empty($errorLogEntity)){
                return false;
            }
        }

        if ($sendEmail) {
            if (empty($this->mailManager)) {
                $this->mailManager = $this->container->get("mail_manager");
            }

            $this->mailManager->sendEmail(
                array("email" => $_ENV["SUPPORT_EMAIL"], "name" => $_ENV["SUPPORT_EMAIL"]),
                $cc,
                null,
                null,
                $name,
                "",
                "general_error_log",
                array("generalError" => $description),
                null,
                null,
                null
            );
        }

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        /** @var ErrorLogEntity $errorLog */
        $errorLog = $this->entityManager->getNewEntityByAttributSetName("error_log");

        $errorLog->setName($name);
        $errorLog->setDescription($description);
        $errorLog->setIsHandeled(1);

        $this->entityManager->saveEntity($errorLog);

        return true;
    }

    /**
     * @param $name
     * @param Exception $exception
     * @param bool $sendEmail
     * @param array $cc
     * @param int $minutesLimit
     * @return bool
     */
    public function logExceptionEvent($name, Exception $exception, $sendEmail = true, $cc = array(), $minutesLimit = 0, $emailTo = null, $additionalData = Array())
    {
        if($minutesLimit > 0){

            $date = new \DateTime();
            $date->sub(new \DateInterval('PT' . $minutesLimit . 'M'));

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("name", "eq", $name));
            $compositeFilter->addFilter(new SearchFilter("created", "ge", $date->format("Y-m-d H:i:s")));

            /** @var ErrorLogEntity $errorLog */
            $errorLog = $this->getFilteredErrorLog($compositeFilter);

            if(!empty($errorLog)){
                return false;
            }
        }

        if(isset($additionalData["is_handeled"]) && !$additionalData["is_handeled"]){

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("line", "eq", $exception->getLine()));
            $compositeFilter->addFilter(new SearchFilter("isHandeled", "eq", 0));
            //TODO eventualno mozemo dodati i name ako ce morazlikovati name
            //$compositeFilter->addFilter(new SearchFilter("name", "eq", $name));

            /** @var ErrorLogEntity $errorLogEntity */
            $errorLog = $this->getFilteredErrorLog($compositeFilter);
        }
        else{
            $additionalData["is_handeled"] = 1;
        }

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $now = new \DateTime();
        $openTicket = false;

        if(empty($errorLog)){
            /** @var ErrorLogEntity $errorLog */
            $errorLog = $this->entityManager->getNewEntityByAttributSetName("error_log");

            $errorLog->setName($name);
            $errorLog->setDescription($exception->getMessage());
            $errorLog->setTrace($exception->getTraceAsString());
            $errorLog->setLine($exception->getLine());
            $errorLog->setIsHandeled($additionalData["is_handeled"]);
            $errorLog->setNumberOfRequests(1);
            $errorLog->setResolved(0);
            $errorLog->setDateResolved(null);
            $errorLog->setDateLastRecurrence($now);

            if(isset($additionalData["open_ticket"]) && $additionalData["open_ticket"] == 1){
                $openTicket = true;
            }
        }
        else{
            if(isset($additionalData["open_ticket"]) && $additionalData["open_ticket"] == 1 && $errorLog->getResolved()){
                $openTicket = true;
            }

            $errorLog->setNumberOfRequests(intval($errorLog->getNumberOfRequests())+1);
            $errorLog->setResolved(0);
            $errorLog->setDateResolved(null);
            $errorLog->setDateLastRecurrence($now);
        }

        $errorLog = $this->entityManager->saveEntityWithoutLog($errorLog);

        //ticket_id
        //TODO open ticket
        //TODO povratna veza

        if ($sendEmail || $openTicket) {

            if (empty($this->mailManager)) {
                $this->mailManager = $this->container->get("mail_manager");
            }

            if(empty($emailTo)){
                $emailTo = $_ENV["SUPPORT_EMAIL"];
            }

            $this->mailManager->sendEmail(
                array("email" => $emailTo, "name" => $emailTo),
                $cc,
                null,
                null,
                $name,
                "",
                "exception_error_log",
                array("exception" => $exception, "errorLog" => $errorLog),
                null,
                null,
                null
            );
        }

        return true;
    }

    /**
     * @param $data
     * @param bool $sendEmail
     * @return ImportLogEntity
     */
    public function insertImportLog($data, $sendEmail = true)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        /** @var ImportLogEntity $entity */
        $entity = $this->entityManager->getNewEntityByAttributSetName("import_log");

        $entity->setDateImported(new \DateTime());

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);
            $getter = EntityHelper::makeGetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $getter)) {
                if ($entity->$getter() != $value) {
                    $entity->$setter($value);
                }
            }
        }

        $this->entityManager->saveEntityWithoutLog($entity);

        if (!$entity->getCompleted() && $sendEmail) {

            if (empty($this->mailManager)) {
                $this->mailManager = $this->container->get("mail_manager");
            }

            $this->mailManager->sendEmail(array("email" => $_ENV["SUPPORT_EMAIL"], "name" => $_ENV["SUPPORT_EMAIL"]), null, null, null, "Import error log", "", "general_error_log", array("importLog" => $entity));
        }

        return $entity;
    }

    /**
     * @param $data
     * @param ErrorLogEntity|null $errorLog
     * @param bool $skipLog
     * @return ErrorLogEntity|null
     */
    public function insertUpdateErrorLog($data, ErrorLogEntity $errorLog = null, $skipLog = true){

        if (empty($errorLog)) {
            /** @var ErrorLogEntity $errorLog */
            $errorLog = $this->entityManager->getNewEntityByAttributSetName("error_log");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($errorLog, $setter)) {
                $errorLog->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($errorLog);
        } else {
            $this->entityManager->saveEntity($errorLog);
        }
        $this->entityManager->refreshEntity($errorLog);

        return $errorLog;
    }

    /**
     * @return array
     */
    public function getCriticalErrorMessages(){

        $messages = Array();

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $htaccessContents = true;
        if(!file_exists($_ENV["WEB_PATH"].".htaccess")){
            $messages[] = Array("class" => "error", "content" => "<p>.HTACCESS - Nedostaje .htaccess</p>");
        }
        else{
            $htaccessContents = file_get_contents($_ENV["WEB_PATH"] . ".htaccess");
        }

        /**
         * Check for residue methods in crmProcessManager
         */
        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        if(EntityHelper::checkIfMethodExists($this->crmProcessManager,"calculatePriceItem")){
            $messages[] = Array("class" => "error", "content" => "<p>CRM PROCESS MANAGER - calculatePriceItem više ne smije postojati u crmProcessManageru</p>");
        }
        if(EntityHelper::checkIfMethodExists($this->crmProcessManager,"recalculateQuoteTotals")){
            $messages[] = Array("class" => "error", "content" => "<p>CRM PROCESS MANAGER - recalculateQuoteTotals više ne smije postojati u crmProcessManageru</p>");
        }
        if(EntityHelper::checkIfMethodExists($this->crmProcessManager,"getBulkPricesForProduct")){
            $messages[] = Array("class" => "error", "content" => "<p>CRM PROCESS MANAGER - getBulkPricesForProduct više ne smije postojati u crmProcessManageru</p>");
        }
        if(EntityHelper::checkIfMethodExists($this->crmProcessManager,"getProductPricesOfCombinedProduct")){
            $messages[] = Array("class" => "error", "content" => "<p>CRM PROCESS MANAGER - getProductPricesOfCombinedProduct više ne smije postojati u crmProcessManageru</p>");
        }
        if(EntityHelper::checkIfMethodExists($this->crmProcessManager,"calculateAdminQuoteItem")){
            $messages[] = Array("class" => "error", "content" => "<p>CRM PROCESS MANAGER - calculateAdminQuoteItem više ne smije postojati u crmProcessManageru</p>");
        }
        if(EntityHelper::checkIfMethodExists($this->crmProcessManager,"calculatePriceOrderItem")){
            $messages[] = Array("class" => "error", "content" => "<p>CRM PROCESS MANAGER - calculatePriceOrderItem više ne smije postojati u crmProcessManageru</p>");
        }
        if(EntityHelper::checkIfMethodExists($this->crmProcessManager,"recalculateOrderTotals")){
            $messages[] = Array("class" => "error", "content" => "<p>CRM PROCESS MANAGER - recalculateOrderTotals više ne smije postojati u crmProcessManageru</p>");
        }
        /**
         * End Check for residue methods in crmProcessManager
         */

        /**
         * Check if cron is running
         */
        $q = "SELECT COUNT(id) as count FROM cron_job_history_entity WHERE created >= CURRENT_TIMESTAMP - INTERVAL 5 MINUTE";
        $count = $this->databaseContext->getSingleResult($q);

        if(intval($count) == 0){
            $messages[] = Array("class" => "error", "content" => "<p>CRON JOB - Provjeriti da li je cron postavljen.</p>");
        }
        if($_ENV["ENABLE_OUTGOING_EMAIL"] == 0){
            $messages[] = Array("class" => "warning", "content" => "<p>ENV - Izlazni mailovi ugašeni (ENABLE_OUTGOING_EMAIL).</p>");
        }

        if($_ENV["IS_PRODUCTION"] == 1){

            if(empty($this->routeManager)){
                $this->routeManager = $this->getContainer()->get("route_manager");
            }
            $stores = $this->routeManager->getStores();

            if($_ENV["VALIDATE_RECAPTCHA"] != 1){
                $messages[] = Array("class" => "error", "content" => "<p>ENV - Recaptcha je ugašena (VALIDATE_RECAPTCHA).</p>");
            }
            if($_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"] != 0){
                $messages[] = Array("class" => "warning", "content" => "<p>ENV - Cache je ugašen (DISABLE_FRONT_BLOCK_HTML_CACHE).</p>");
            }
            if($_ENV["IS_PRODUCTION_ERP"] == 0){
                $messages[] = Array("class" => "warning", "content" => "<p>ENV - ERP konekcija ugašena (IS_PRODUCTION_ERP).</p>");
            }
            if(isset($_ENV["REMOTE_URL"])){
                $messages[] = Array("class" => "error", "content" => "<p>ENV - Remote url smije biti postavljen samo na dev (REMOTE_URL).</p>");
            }
            if($_ENV["IMAP_DEBUG"] != 0){
                $messages[] = Array("class" => "error", "content" => "<p>ENV - IMAP_DEBUG je upaljen (IMAP_DEBUG).</p>");
            }


            /**
             * Check robots.txt
             */
            $robotsContents = true;
            if(!file_exists($_ENV["WEB_PATH"]."robots.txt")){
                $messages[] = Array("class" => "error", "content" => "<p>ROBOTS.TXT - Nedostaje robots.txt.</p>");
            }
            else{
                $robotsContents = file_get_contents($_ENV["WEB_PATH"] . "robots.txt");
            }

            if(!empty($robotsContents)){
                if (stripos($robotsContents, "User-agent: *") === false) {
                    $messages[] = Array("class" => "error", "content" => "<p>ROBOTS.TXT - Nedostaje linija: User-agent: *</p>");
                }
                if (stripos($robotsContents, "Allow: /") === false) {
                    $messages[] = Array("class" => "error", "content" => "<p>ROBOTS.TXT - Nedostaje linija: Allow: /</p>");
                }
                if (stripos($robotsContents, "Disallow: *index=0*") === false) {
                    $messages[] = Array("class" => "error", "content" => "<p>ROBOTS.TXT - Nedostaje linija: Disallow: *index=0*</p>");
                }
                if (stripos($robotsContents, "Disallow: *page_size*") === false) {
                    $messages[] = Array("class" => "error", "content" => "<p>ROBOTS.TXT - Nedostaje linija: Disallow: *page_size*</p>");
                }
                if (stripos($robotsContents, "Disallow: *sort=*") === false) {
                    $messages[] = Array("class" => "error", "content" => "<p>ROBOTS.TXT - Nedostaje linija: Disallow: *sort=*</p>");
                }
                if (stripos($robotsContents, "Disallow: *_price=*") === false) {
                    $messages[] = Array("class" => "error", "content" => "<p>ROBOTS.TXT - Nedostaje linija: Disallow: *_price=*</p>");
                }
            }

            /** @var SStoreEntity $store */
            foreach ($stores as $store){

                if(!file_exists($_ENV["WEB_PATH"]."xml/sitemap_{$store->getId()}.xml")){
                    $messages[] = Array("class" => "error", "content" => "<p>SITEMAP - Nedostaje sitemap_{$store->getId()}.xml u direktoriju web/xml</p>");
                }

                if (stripos($robotsContents, "sitemap_{$store->getId()}.xml") === false) {
                    $messages[] = Array("class" => "error", "content" => "<p>ROBOTS.TXT - Nedostaje linija: Sitemap: {$_ENV["SSL"]}://{$store->getWebsite()->getBaseUrl()}/xml/sitemap_{$store->getId()}.xml</p>");
                }
            }

            /*if(!file_exists($_ENV["WEB_PATH"]."sitemap.xml")){
                $messages[] = Array("class" => "error", "content" => "<p>SITEMAP - Nedostaje symlink do sitemap.xml.</p>");
            }*/

            /**
             * Check if dashboard is missing all orders
             */
            $q = "SELECT count(*) as count FROM s_front_block_entity WHERE class LIKE '%dash_all_orders%';";
            $count = $this->databaseContext->getSingleResult($q);
            if(intval($count) == 0){
                $messages[] = Array("class" => "error", "content" => "<p>U dashboardu korisnika nedostaje klasa 'dash_all_orders' koja prikazuje listu narudžbi. (Obratiti se Alenu)</p>");
            }
        }
        else{

            /**
             * Check robots.txt
             */
            $robotsContents = true;
            if(!file_exists($_ENV["WEB_PATH"]."robots.txt")){
                $messages[] = Array("class" => "error", "content" => "<p>ROBOTS.TXT - Nedostaje robots.txt.</p>");
            }
            else{
                $robotsContents = file_get_contents($_ENV["WEB_PATH"] . "robots.txt");
            }

            if(!empty($robotsContents)){
                if (stripos($robotsContents, "User-agent: *") === false) {
                    $messages[] = Array("class" => "error", "content" => "<p>ROBOTS.TXT - Nedostaje linija: User-agent: *</p>");
                }
                if (stripos($robotsContents, "Disallow: /") === false) {
                    $messages[] = Array("class" => "error", "content" => "<p>ROBOTS.TXT - Nedostaje linija: Disallow: /</p>");
                }
            }

            /**
             * Check .htaccess
             */
            if(!empty($htaccessContents)){
                if (
                    stripos($htaccessContents, '#AuthName "Secure Area"') !== false
                    || stripos($htaccessContents, '#AuthUserFile') !== false
                    || stripos($htaccessContents, '#AuthType Basic') !== false
                    || stripos($htaccessContents, '#Require valid-user') !== false
                ) {
                    $messages[] = Array("class" => "error", "content" => "<p>.HTACCESS - Zavjesa NIJE spuštena!!!</p>");
                }
            }
        }

        return $messages;
    }

    /**
     * @return array
     */
    public function getCritialErrorStatistics(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $ret = Array();

        $q = "SELECT count(*) as count FROM error_log_entity WHERE (resolved = 0 or resolved is null) and is_handeled = 0;";
        $count = $this->databaseContext->getSingleResult($q);

        $ret[] = Array(
            "diff" => 0,
            "title" => "Number of distinct exceptions",
            "current" => intval($count)
        );

        $q = "SELECT SUM(number_of_requests) as count FROM error_log_entity WHERE (resolved = 0 or resolved is null) and is_handeled = 0;";
        $count = $this->databaseContext->getSingleResult($q);

        $ret[] = Array(
            "diff" => 0,
            "title" => "Number of unresolved exceptions",
            "current" => intval($count)
        );

        $q = "SELECT count(*) as count FROM `s_route_not_found_entity` WHERE is_redirected = 0 and number_of_requests > 3 and url_type = 'url';;";
        $count = $this->databaseContext->getSingleResult($q);

        $ret[] = Array(
            "diff" => 0,
            "title" => "Number of 404 requests",
            "current" => intval($count)
        );

        return $ret;
    }
}
