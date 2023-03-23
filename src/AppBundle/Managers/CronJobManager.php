<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\CronJobEntity;
use AppBundle\Entity\CronJobHistoryEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\CronSchedule;
use AppBundle\Helpers\EntityHelper;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;

class CronJobManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var CronSchedule $cronScheduleHelper */
    protected $cronScheduleHelper;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;
    protected $testMode;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->setTestMode(false);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getCronJobById($id){
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(CronJobEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $data
     * @param CronJobEntity|null $cronJob
     * @param bool $skipLog
     * @return CronJobEntity|null
     */
    public function insertUpdateCronJob($data, CronJobEntity $cronJob = null, $skipLog = true){

        if (empty($cronJob)) {
            /** @var CronJobEntity $cronJob */
            $cronJob = $this->entityManager->getNewEntityByAttributSetName("cron_job");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($cronJob, $setter)) {
                $cronJob->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($cronJob);
        } else {
            $this->entityManager->saveEntity($cronJob);
        }
        $this->entityManager->refreshEntity($cronJob);

        return $cronJob;
    }

    /**
     * @param int $numberOfDays
     * @return bool
     */
    public function cleanHistoryLog($numberOfDays = 150){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q="DELETE FROM cron_job_history_entity WHERE created < DATE_SUB(CURRENT_DATE(), INTERVAL {$numberOfDays} DAY);";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param $testMode
     */
    public function setTestMode($testMode){
        $this->testMode = $testMode;
    }

    /**
     * @return mixed
     */
    public function getTestMode(){
        return $this->testMode;
    }

    /**
     * @return array|string
     */
    public function getScheduledCronJobs($testMode = false){

        $this->setTestMode($testMode);

        $ret = Array();

        $this->cronWatchdog();

        $entityType = $this->entityManager->getEntityTypeByCode("cron_job");

        $compositeFilters = new CompositeFilterCollection();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("runTime", "asc"));

        $cronJobs = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters,$sortFilters);

        if(!empty($cronJobs)){

            if(empty($this->databaseContext)){
                $this->databaseContext = $this->container->get("database_context");
            }

            $q = "SELECT MAX(cron_batch_id) AS count FROM cron_job_history_entity;";
            $cronBatchId = $this->databaseContext->getSingleResult($q);

            if(empty($cronBatchId)){
                $cronBatchId = 3;
            }
            $cronBatchId++;

            if(empty($this->cronScheduleHelper)){
                $this->cronScheduleHelper = new CronSchedule();
            }

            /** @var CronJobEntity $cronJob */
            foreach ($cronJobs as $key => $cronJob){

                $run = false;

                if($this->checkIfCronJobShouldRun($cronJob)){
                    $run = true;
                }
                else{
                    if($this->checkForMissedCronJob($cronJob)){
                        $run = true;
                    }
                }

                if($run){

                    $ret[$key] = $cronJob->getMethod();

                    $data = array();
                    $data["cron_job"] = $cronJob;
                    $data["name"] = $cronJob->getName();
                    $data["is_running"] = 1;
                    $data["has_error"] = 0;
                    $data["cron_batch_id"] = $cronBatchId;

                    try{
                        $this->insertUpdateCronJobHistory($data);
                    }
                    catch (\Exception $e){
                        unset($ret[$key]);
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * @param CronJobEntity $cronJob
     * @return bool
     * @throws \Exception
     */
    public function checkForMissedCronJob(CronJobEntity $cronJob){

        if(empty($this->cronScheduleHelper)){
            $this->cronScheduleHelper = new CronSchedule();
        }

        /** @var CronSchedule $schedule */
        $schedule = $this->cronScheduleHelper::fromCronString($cronJob->getSchedule());
        $lastRunTimestamp = $schedule->getPreviousDateTime(time(),$schedule);
        $lastRun = new \DateTime();
        $lastRun->setTimestamp($lastRunTimestamp);

        $now = new \DateTime();
        $now->modify("-10 minutes");

        if($lastRun > $now){
            return false;
        }

        $now = new \DateTime();
        $now->modify("-{$cronJob->getRunTime()} minutes");
        if($lastRun > $now){
            return false;
        }

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM cron_job_history_entity WHERE cron_job_id = {$cronJob->getId()} AND date_started > '{$lastRun->format("Y-m-d H:i:s")}';";
        $exists = $this->databaseContext->getAll($q);

        if($exists){
            return false;
        }

        return true;
    }

    /**
     * @param CronJobEntity $cronJob
     * @return bool
     */
    public function checkIfCronJobShouldRun(CronJobEntity $cronJob){

        if(empty($this->cronScheduleHelper)){
            $this->cronScheduleHelper = new CronSchedule();
        }

        /** @var CronSchedule $schedule */
        $schedule = $this->cronScheduleHelper::fromCronString($cronJob->getSchedule());
        $schedule->setVariables($schedule);

        if($this->getTestMode()){
            dump($cronJob->getSchedule());
            dump($schedule->matchByArray(time(),$schedule));
        }

        if(!$schedule->matchByArray(time(),$schedule)){
            return false;
        }

        $seconds = $schedule->getIntervalInSeconds(time(),$schedule);

        if($this->getTestMode()){
            dump($seconds);
        }
        if($seconds < 0){
            return false;
        }

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $diffInSeconds = intval($schedule->getIntervalInSeconds(time(),$schedule));
        /**
         * Namjerno smanjujemo sve sto je vece od 1h
         */
        if($diffInSeconds >= 3600){
            $diffInSeconds = 300;
        }
        else{
            $diffInSeconds = $diffInSeconds - 10;
        }

        $q = "SELECT * FROM cron_job_history_entity WHERE cron_job_id = {$cronJob->getId()} AND (is_running = 1 OR (date_finished is not null AND TIMESTAMPDIFF(SECOND,date_started,NOW()) < {$diffInSeconds})) ORDER BY id DESC LIMIT 1;";
        if($this->getTestMode()){
            dump($q);
        }
        $data = $this->databaseContext->getAll($q);

        if($this->getTestMode()){
            dump($data);
            die;
        }

        if(!empty($data)){
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function cronWatchdog(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT cjh.*, cj.name as cron_job FROM cron_job_history_entity as cjh LEFT JOIN cron_job_entity as cj ON cjh.cron_job_id = cj.id WHERE (cj.run_time is not null and cjh.is_running = 1 and date_started is not null and TIMESTAMPDIFF(MINUTE,date_started,NOW()) > (cj.run_time * 5)) OR 
           (cjh.cron_batch_id IN (
            SELECT cron_batch_id FROM cron_job_history_entity as s1
            WHERE s1.date_finished IS NULL AND s1.cron_batch_id IS NOT NULL AND s1.cron_batch_id NOT IN (SELECT cron_batch_id FROM cron_job_history_entity as s2 WHERE s2.date_finished is not null and TIMESTAMPDIFF(MINUTE,s2.date_finished,NOW()) < 2)
            GROUP BY s1.cron_batch_id
            HAVING COALESCE(MAX(s1.date_started)) IS NULL) AND cjh.date_started IS NULL);";
        $data = $this->databaseContext->getAll($q);

        if(!empty($data)){

            /**
             * Salji mail samo na produkciji
             */
            if($_ENV["IS_PRODUCTION"]){
                if (empty($this->errorLogManager)) {
                    $this->errorLogManager = $this->container->get("mail_manager");
                }

                foreach ($data as $d){
                    $this->errorLogManager->logErrorEvent("Cron job killed error log",$d["cron_job"],true,null);
                }
            }

            $ids = implode(",",array_column($data,"id"));
            $q = "UPDATE cron_job_history_entity SET date_started = IF(date_started IS NULL, NOW(), date_started), date_finished = NOW(), is_running = 0, has_error = 1 WHERE id in ({$ids});";
            $this->databaseContext->executeNonQuery($q);
        }

        return true;
    }

    /**
     * @param $command
     * @return bool
     * @throws \Exception
     */
    public function runCommand($command){



        return true;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getCronJobHistoryById($id){

        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(CronJobHistoryEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $method
     * @return |null
     */
    public function getCronJobByMethod($method){

        $entityType = $this->entityManager->getEntityTypeByCode("cron_job");

        $compositeFilters = new CompositeFilterCollection();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("method", "eq", $method));
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $name
     * @return |null
     */
    public function getCronJobByName($name){

        $entityType = $this->entityManager->getEntityTypeByCode("cron_job");

        $compositeFilters = new CompositeFilterCollection();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("name", "eq", $name));
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $data
     * @return CronJobEntity
     */
    public function addUpdateCronJob($data){

        if(!isset($data["method"])){
            return false;
        }
        if(!isset($data["name"])){
            return false;
        }
        if(!isset($data["schedule"])){
            return false;
        }
        if(!isset($data["description"])){
            return false;
        }
        if(!isset($data["is_active"])){
            return false;
        }
        if(!isset($data["run_time"])){
            return false;
        }

        /** @var CronJobEntity $cronJob */
        $cronJob = $this->getCronJobByMethod($data["method"]);

        if(empty($cronJob)){
            /** @var CronJobEntity $cronJob */
            $cronJob = $this->getCronJobByName($data["name"]);
        }

        if(empty($cronJob)){
            /** @var CronJobEntity $cronJob */
            $cronJob = $this->entityManager->getNewEntityByAttributSetName("cron_job");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($cronJob, $setter)) {
                $cronJob->$setter($value);
            }
        }

        $this->entityManager->saveEntity($cronJob);
        $this->entityManager->refreshEntity($cronJob);

        return $cronJob;
    }

    /**
     * @param $data
     * @return CronJobHistoryEntity|mixed
     * @throws \Exception
     */
    public function insertUpdateCronJobHistory($data, CronJobHistoryEntity $cronJobHistory = null)
    {
        if (empty($cronJobHistory)) {
            /** @var CronJobHistoryEntity $cronJobHistory */
            $cronJobHistory = $this->entityManager->getNewEntityByAttributSetName("cron_job_history");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($cronJobHistory, $setter)) {
                $cronJobHistory->$setter($value);
            }
        }

        $this->entityManager->saveEntityWithoutLog($cronJobHistory);

        if($cronJobHistory->getHasError() && !empty($cronJobHistory->getErrorLog())){

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->container->get("mail_manager");
            }

            $this->errorLogManager->logErrorEvent("Cron job error",$cronJobHistory->getErrorLog(),false,null);
        }

        return $cronJobHistory;
    }

    /**
     * @param null $additionalCompositeFilter
     * @return mixed
     */
    public function getCronJobHistoryByFilter($additionalCompositeFilter = null){

        $et = $this->entityManager->getEntityTypeByCode("cron_job_history");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }


        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);
    }
}
