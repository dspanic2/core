<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Constants\ImportManualConstants;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\Entity;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\ImportManualEntity;
use AppBundle\Entity\ImportManualStatusEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\DateTime;

class ImportManualManager extends AbstractBaseManager
{
    /** @var DatabaseContext $databaseContext */
    private $databaseContext;
    /** @var EntityManager $entityManager */
    private $entityManager;
    /** @var AttributeContext $attributeContext */
    private $attributeContext;
    /** @var EntityType $importManualEntityType */
    private $importManualEntityType;
    /** @var FileManager $fileManager */
    private $fileManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    private $crmProcessManager;

    public function initialize()
    {
        parent::initialize();

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }
    }

    /**
     * @param int $numberOfDays
     * @return bool
     */
    public function cleanImportLog($numberOfDays = 300)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "DELETE FROM import_log_entity WHERE created < DATE_SUB(CURRENT_DATE(), INTERVAL {$numberOfDays} DAY);";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param CompositeFilter|null $additionalCompositeFilter
     * @return mixed
     */
    public function getManualImportByFilter(CompositeFilter $additionalCompositeFilter = null)
    {
        if (empty($this->importManualEntityType)) {
            $this->importManualEntityType = $this->entityManager->getEntityTypeByCode("import_manual");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($this->importManualEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getImportManualStatusById($id)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("import_manual_status");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getImportManualById($id)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("import_manual");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param ImportManualEntity $importManual
     * @param $data
     * @return mixed
     */
    public function updateImportManual(ImportManualEntity $importManual, $data)
    {
        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($importManual, $setter)) {
                $importManual->$setter($value);
            }
        }

        $this->entityManager->saveEntity($importManual);
        $this->entityManager->refreshEntity($importManual);

        return $importManual;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function runWatchDog()
    {
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("importManualStatus.id", "eq", ImportManualConstants::STATUS_IN_PROGRESS));

        $manualImports = $this->getManualImportByFilter($compositeFilter);

        if (empty($manualImports)) {
            return false;
        }

        /** @var ImportManualEntity $manualImport */
        foreach ($manualImports as $manualImport) {

            $data = array();

            /** @var ImportManualStatusEntity $statusError */
            $statusError = $this->getImportManualStatusById(ImportManualConstants::STATUS_ERROR);

            $estimatedDuration = intval($manualImport->getImportManualType()->getEstimatedDuration());

            if (empty($estimatedDuration)) {
                $estimatedDuration = ImportManualConstants::DEFAULT_WATCHDOG_MIN;
            }

            $startDate = $manualImport->getDateStarted();
            if (empty($startDate)) {
                $startDate = $manualImport->getCreated();
            }

            $diff = $startDate->diff(new \DateTime());
            $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

            if (intval($minutes) > intval($estimatedDuration)) {

                $data["import_result"] = $this->translator->trans("Manual import killed");
                $data["import_manual_status"] = $statusError;
                $data["date_finished"] = new \DateTime();
                $this->updateImportManual($manualImport, $data);

                $this->errorLogManager->logErrorEvent("Import manual error log - {$manualImport->getImportManualType()->getName()}", $manualImport->getImportResult(), true, null);
            }
        }

        return true;
    }

    /**
     * @param null $importManualId
     * @param false $debug
     * @return array|false
     */
    public function runQueue($importManualId = null, $debug = false)
    {
        if (empty($this->importManualEntityType)) {
            $this->importManualEntityType = $this->entityManager->getEntityTypeByCode("import_manual");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("importManualStatus.id", "eq", ImportManualConstants::STATUS_IN_PROGRESS));

        $manualImports = $this->getManualImportByFilter($compositeFilter);
        if (!empty($manualImports) && empty($importManualId)) {
            return false;
        }

        /**
         * Check if there are new imports
         */
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        if (!empty($importManualId)) {
            $compositeFilter->addFilter(new SearchFilter("id", "eq", $importManualId));
        } else {
            $compositeFilter->addFilter(new SearchFilter("importManualStatus.id", "eq", ImportManualConstants::STATUS_WAITING_IN_QUEUE));
        }

        $manualImports = $this->getManualImportByFilter($compositeFilter);
        if (empty($manualImports)) {
            return false;
        }

        /** @var ImportManualEntity $importManual */
        $importManual = $manualImports[0];

        /**
         * Set status to running
         */
        /** @var ImportManualStatusEntity $statusRunning */
        $statusRunning = $this->getImportManualStatusById(ImportManualConstants::STATUS_IN_PROGRESS);

        $data = [];
        $data["import_manual_status"] = $statusRunning;
        $data["date_started"] = new \DateTime();

        /** @var ImportManualEntity $importManual */
        $importManual = $this->updateImportManual($importManual, $data);

        $data = [];
        $data["error"] = false;
        $data["message"] = null;
        $data["import_result"] = null;
        $data["rows_imported"] = 0;

        $manager = null;

        if (empty($importManual->getImportManualType()->getManagerCode())) {
            $data["message"] = "Manager is empty for {$importManual->getImportManualType()->getName()}";
        } else {
            $manager = $this->getContainer()->get($importManual->getImportManualType()->getManagerCode());
            if (empty($manager)) {
                $data["message"] = "Manager {$importManual->getImportManualType()->getManagerCode()} does not exist";
            } else {
                if (empty($importManual->getImportManualType()->getMethod())) {
                    $data["message"] = "Method is empty for {$importManual->getImportManualType()->getName()}";
                } else if (!EntityHelper::checkIfMethodExists($manager, $importManual->getImportManualType()->getMethod())) {
                    $data["message"] = "Manager {$importManual->getImportManualType()->getManagerCode()} does not have method {$importManual->getImportManualType()->getMethod()}";
                }
            }
        }

        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }

        /** @var Attribute $fileAttribute */
        $fileAttribute = $this->attributeContext->getAttributeByCode("file", $this->importManualEntityType);

        if (empty($this->fileManager)) {
            $this->fileManager = $this->container->get("file_manager");
        }

        $path = $this->fileManager->getTargetPath($fileAttribute->getFolder(), $importManual->getId());
        $fullPath = str_ireplace("//", "/", $_ENV["WEB_PATH"] . $path . $importManual->getFile());

        if (!file_exists($fullPath)) {
            $data["message"] = "File {$fullPath} does not exist";
        }

        if (empty($data["message"])) {
            try {
                if (EntityHelper::checkIfMethodExists($manager, "setDebug")) {
                    $manager->setDebug($debug);
                }
                $ret = $manager->{$importManual->getImportManualType()->getMethod()}($fullPath);
                if (isset($ret["product_ids"]) && !empty($ret["product_ids"])) {
                    if (empty($this->crmProcessManager)) {
                        $this->crmProcessManager = $this->container->get("crm_process_manager");
                    }
                    if (!empty($this->crmProcessManager) && EntityHelper::checkIfMethodExists($this->crmProcessManager, "afterImportCompleted")) {
                        $this->crmProcessManager->afterImportCompleted($ret, "default_manual_import");
                    }
                }
            } catch (\Exception $e) {
                $data["message"] = sprintf("%s @ %s:%d", $e->getMessage(), $e->getFile(), $e->getLine());
                $data["error"] = true;
            }
            if (isset($ret["errors"])) {
                $data["message"] .= $ret["errors"];
            }
            if (isset($ret["rows"])) {
                $data["rows_imported"] = $ret["rows"];
                if ($data["rows_imported"] == 0) {
                    $data["message"] .= "No rows were imported";
                    $data["error"] = true;
                }
            }
        } else {
            $data["error"] = true;
        }

        /**
         * Set new status
         */
        if ($data["error"] == true) {
            /** @var ImportManualStatusEntity $status */
            $status = $this->getImportManualStatusById(ImportManualConstants::STATUS_ERROR);
        } else {
            /** @var ImportManualStatusEntity $status */
            $status = $this->getImportManualStatusById(ImportManualConstants::STATUS_SUCCESS);
        }

        $data["import_manual_status"] = $status;
        $data["date_finished"] = new \DateTime();
        $data["import_result"] = $data["message"];

        $this->updateImportManual($importManual, $data);

        return $data;
    }
}