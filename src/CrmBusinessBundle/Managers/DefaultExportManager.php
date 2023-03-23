<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\ProductProductGroupLinkEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\RouteManager;

class DefaultExportManager extends AbstractBaseManager
{
    protected $storeId;

    /** @var ProductManager $productManager */
    protected $productManager;

    /** @var EntityManager $entityManager */
    protected $entityManager;

    /** @var RouteManager $routeManager */
    protected $routeManager;

    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    /** @var HelperManager $helperManager */
    protected $helperManager;

    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    /** @var string $exportDir */
    protected $exportDir;

    /** @var string $baseUrl */
    protected $baseUrl;
    /** @var string $baseDocumentUrl */
    protected $baseDocumentUrl;

    /** @var string $extension */
    protected $extension;

    public function initialize()
    {
        parent::initialize();

        $this->exportDir = $_ENV["WEB_PATH"] . "/Documents/export/";
        if (!file_exists($this->exportDir)) {
            mkdir($this->exportDir, 0777, true);
        }

        $this->extension = "xml";
    }

    /**
     * @param $productGroups
     * @return array|string|string[]
     */
    public function prepareProductGroupList($productGroups)
    {
        $ret = "";

        if (!EntityHelper::isCountable($productGroups) || count($productGroups) == 0) {
            return $ret;
        }

        $lowestProductGroup = null;
        $lowestProductGroupUrl = null;

        /** @var ProductProductGroupLinkEntity $productGroupLink */
        foreach ($productGroups as $productGroup) {
            $url = $productGroup->getUrlPath($this->storeId);

            if (empty($lowestProductGroup) || strlen($url) > strlen($lowestProductGroupUrl)) {
                $lowestProductGroup = $productGroup;
            }
        }

        if (!empty($lowestProductGroup)) {
            $ret = $this->productGroupManager->getProductGroupNameList($lowestProductGroup, $this->storeId);
        }

        return $ret;
    }

    /**
     * @param $storeId
     * @param null $destinationFilename
     * @param array $productIds
     * @return string
     * @throws \Exception
     */
    public function generateExport($storeId,$destinationFilename = null, $productIds = Array())
    {
        if(empty($destinationFilename)){
            throw new \Exception("Missing export filename");
        }

        $this->storeId = $storeId;

        if (empty($this->productManager)) {
            $this->productManager = $this->getContainer()->get("product_manager");
        }
        if (empty($this->entityManager)) {
            $this->entityManager = $this->getContainer()->get("entity_manager");
        }
        if (empty($this->routeManager)) {
            $this->routeManager = $this->getContainer()->get("route_manager");
        }
        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }
        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->getContainer()->get("product_group_manager");
        }
        if (empty($this->helperManager)) {
            $this->helperManager = $this->getContainer()->get("helper_manager");
        }
        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        /** @var SStoreEntity $store */
        $store = $this->routeManager->getStoreById($this->storeId);

        $this->baseUrl = $_ENV["SSL"] . "://" . $store->getWebsite()->getBaseUrl() . $this->routeManager->getLanguageUrl($store) . $_ENV["FRONTEND_URL_PORT"];
        $this->baseDocumentUrl = $_ENV["SSL"] . "://" . $store->getWebsite()->getBaseUrl() . $_ENV["FRONTEND_URL_PORT"];

        $filepath = $this->exportDir . $destinationFilename . ".".$this->extension;
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("productType", "eq", CrmConstants::PRODUCT_TYPE_SIMPLE));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $this->storeId . '"'))));
        if (isset($_ENV["USE_READY_FOR_WEBSHOP"]) && $_ENV["USE_READY_FOR_WEBSHOP"]) {
            $compositeFilter->addFilter(new SearchFilter("readyForWebshop", "eq", 1));
        }

        $productIds = array_filter($productIds);
        if(!empty($productIds)){
            $productIds = array_unique($productIds);
            $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",",$productIds)));
        }

        /**
         * Get products for export
         */
        $products = $this->productManager->getProductsByFilter($compositeFilter);
        if (empty($products)) {
            throw new \Exception("No products were found for export {$destinationFilename}");
        }

        $filepath = $this->formatExport($filepath, $products);

        $this->entityManager->clearManagerByEntityType($this->entityManager->getEntityTypeByCode("product"));
        $this->entityManager->clearManagerByEntityType($this->entityManager->getEntityTypeByCode("product_group"));
        $this->entityManager->clearManagerByEntityType($this->entityManager->getEntityTypeByCode("facets"));
        $this->entityManager->clearManagerByEntityType($this->entityManager->getEntityTypeByCode("brand"));
        $this->entityManager->clearManagerByEntityType($this->entityManager->getEntityTypeByCode("product_account_group_price"));
        $this->entityManager->clearManagerByEntityType($this->entityManager->getEntityTypeByCode("product_configurable_attribute"));
        $this->entityManager->clearManagerByEntityType($this->entityManager->getEntityTypeByCode("product_configuration_product_link"));

        return $filepath;
    }

}