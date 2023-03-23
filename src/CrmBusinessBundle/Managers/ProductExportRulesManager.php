<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use CrmBusinessBundle\Entity\ProductExportRuleEntity;
use CrmBusinessBundle\Entity\ProductExportRuleTypeEntity;
use Doctrine\Common\Util\Inflector;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;

class ProductExportRulesManager extends ProductAttributeFilterRulesManager
{
    /** @var RouteManager $routeManager */
    protected $routeManager;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param ProductExportRuleTypeEntity $exportRuleType
     * @return bool
     * @throws \Exception
     */
    public function runExportRule(ProductExportRuleTypeEntity $exportRuleType){

        $manager = $this->container->get($exportRuleType->getCode());
        if(empty($manager)){
            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }
            $this->errorLogManager->logErrorEvent("Generate export - missing manager for code {$exportRuleType->getCode()}", "Generate export - missing manager for code {$exportRuleType->getCode()}", true);
            throw new \Exception("Missing manager for code {$exportRuleType->getCode()}");
        }

        $downloadPaths = Array();
        $files = Array();
        $data = Array();

        foreach ($exportRuleType->getShowOnStore() as $storeId => $val){

            $downloadPaths[$storeId] = null;

            if(!$val){
                continue;
            }

            $destinationFilename = StringHelper::convertStringToCode($exportRuleType->getName())."_".$storeId;

            $productExportRules = $exportRuleType->getProductExportRules();

            $productIds = Array();
            if(EntityHelper::isCountable($productExportRules) && count($productExportRules) > 0){
                /** @var ProductExportRuleEntity $productExportRule */
                foreach ($productExportRules as $productExportRule){
                    $productIds = array_merge($productIds,$this->getProductIdsForRule($productExportRule));
                }
            }

            try{
                $filepath = $manager->generateExport($storeId, $destinationFilename, $productIds);
            }
            catch (\Exception $e){
                if (empty($this->errorLogManager)) {
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }
                $this->errorLogManager->logExceptionEvent("Generate export - {$exportRuleType->getName()}", $e, true);
                continue;
            }
            $filepath = str_ireplace("//","/",$filepath);

            if(empty($this->routeManager)){
                $this->routeManager = $this->container->get("route_manager");
            }
            /** @var SStoreEntity $store */
            $store = $this->routeManager->getStoreById($storeId);

            $filenameParts = explode("/",$filepath);

            $downloadPaths[$storeId] = $_ENV["SSL"]."://".$store->getWebsite()->getBaseUrl()."/Documents/export/".end($filenameParts);
            $files[$storeId] = end($filenameParts);

            $data["last_regenerate_date"] = new \DateTime();
        }

        $data["download_path"] = $downloadPaths;
        $data["file"] = $files;

        $this->createUpdateProductExportRuleType($data,$exportRuleType,true);

        return true;
    }

    /**
     * @param $productExportRuleTypeCode
     * @return bool|void
     * @throws \Exception
     */
    public function runExportRulesByType($productExportRuleTypeCode){

        $exportRuleTypes = $this->getRuleTypesByCode($productExportRuleTypeCode);

        if(!EntityHelper::isCountable($exportRuleTypes) || !count($exportRuleTypes)){
            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }
            $this->errorLogManager->logErrorEvent("Generate export - missing export rule types for code {$productExportRuleTypeCode}", "Generate export - missing export rule types for code {$productExportRuleTypeCode}", true);
            throw new \Exception("Missing export rule types for code {$productExportRuleTypeCode}");
        }

        /** @var ProductExportRuleTypeEntity $exportRuleType */
        foreach ($exportRuleTypes as $exportRuleType){
            $this->runExportRule($exportRuleType);
        }

        return true;
    }

    /**
     * @param $data
     * @param ProductExportRuleTypeEntity|null $productExportRuleType
     * @param false $skipLog
     * @return ProductExportRuleTypeEntity|null
     */
    public function createUpdateProductExportRuleType($data, ProductExportRuleTypeEntity $productExportRuleType = null, $skipLog = false)
    {
        if (empty($productExportRuleType)) {
            /** @var ProductExportRuleTypeEntity $productExportRuleType */
            $productExportRuleType = $this->entityManager->getNewEntityByAttributSetName("product_export_rule_type");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($productExportRuleType, $setter)) {
                $productExportRuleType->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($productExportRuleType);
        } else {
            $this->entityManager->saveEntity($productExportRuleType);
        }
        $this->entityManager->refreshEntity($productExportRuleType);

        return $productExportRuleType;
    }

    /**
     * @param $productExportRuleTypeCode
     * @return mixed
     */
    public function getRuleTypesByCode($productExportRuleTypeCode){

        $et = $this->entityManager->getEntityTypeByCode("product_export_rule_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $productExportRuleTypeCode));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et,$compositeFilters);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getRuleTypeById($id){

        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(ProductExportRuleTypeEntity::class);
        return $repository->find($id);
    }

    /**
     * @param ProductExportRuleEntity $productExportRule
     * @param array $productIds
     * @return mixed[]
     */
    public function getProductIdsForRule($productExportRule, $productIds = Array()){

        $ret = Array();

        if(empty($this->attributeContext)){
            $this->attributeContext = $this->container->get("attribute_context");
        }

        $rules = $productExportRule->getRules();
        $where = "";
        $join = "";

        /**
         * Used only if rules are regenerated for subset of products
         */
        if(!empty($productIds)){
            $where.= " AND p.id IN (".implode(",",$productIds).") ";
        }

        $rules = json_decode($rules,true);

        if(!empty($rules)){

            $additionaFilter = $this->parseRuleToFilter($rules,$join,$where);
            if(isset($additionaFilter["join"])){
                $join = $additionaFilter["join"];
            }
            if(isset($additionaFilter["where"])){
                $where = $additionaFilter["where"];
            }
        }

        $data = $this->getProductsByRule($join,$where);

        if(!empty($data)){
            $ret = array_column($data,"id");
        }

        return $ret;
    }
}