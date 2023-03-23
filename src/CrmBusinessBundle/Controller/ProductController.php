<?php

namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SearchFilterHelper;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\FileHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Interfaces\Entity\IEntityValidation;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\FormManager;
use AppBundle\Managers\ListViewManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\ProductConfigurableAttributeEntity;
use CrmBusinessBundle\Entity\ProductConfigurationBundleOptionEntity;
use CrmBusinessBundle\Entity\ProductConfigurationBundleOptionProductLinkEntity;
use CrmBusinessBundle\Entity\ProductConfigurationProductLinkEntity;
use CrmBusinessBundle\Entity\ProductConfiguurationProductLinkEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductLinkTypeEntity;
use CrmBusinessBundle\Entity\ProductProductLinkEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\DefaultImportManager;
use CrmBusinessBundle\Managers\ExportManager;
use CrmBusinessBundle\Managers\ProductAttributeFilterRulesManager;
use CrmBusinessBundle\Managers\ProductManager;
use ScommerceBusinessBundle\Constants\ScommerceConstants;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationEntity;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationOptionsEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\SproductManager;
use ScommerceBusinessBundle\Managers\TemplateManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Doctrine\Common\Inflector\Inflector;

class ProductController extends AbstractController
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var ProductManager $productManager */
    protected $productManager;
    /** @var SProductManager $sProductManager */
    protected $sProductManager;
    /** @var TemplateManager $templateManager */
    protected $templateManager;
    /** @var FormManager $formManager */
    protected $formManager;
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;
    /** @var DefaultImportManager $defaultImportManager */
    protected $defaultImportManager;
    /** @var ProductAttributeFilterRulesManager $productAttributeFilterRulesManager */
    protected $productAttributeFilterRulesManager;
    /** @var ListViewManager $listViewManager */
    protected $listViewManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->productManager = $this->getContainer()->get("product_manager");
        $this->sProductManager = $this->getContainer()->get("s_product_manager");
        $this->templateManager = $this->getContainer()->get("template_manager");
    }

    protected function initializeForm($type)
    {
        $factoryManager = $this->getContainer()->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("/product/save", name="product_save_form")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $type = "product";

        $this->initializeForm($type);

        /**
         * Fallback na starije verzije gdje nema multilang
         */
        $hasMultilang = false;

        if (isset($_POST["show_on_store"])) {
            $hasMultilang = true;
        }

        if(!isset($_POST["name"]) || empty($_POST["name"])){
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Name cannot be empty')));
        }

        if ($hasMultilang) {
            $_POST["name"] = array_map('trim', $_POST["name"]);
            $_POST["name"] = array_filter($_POST["name"]);
            if (empty($_POST["name"])) {
                return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Name cannot be empty')));
            }
            if (empty($_POST["show_on_store_checkbox"])) {
                return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Please add at least one store')));
            }
        }

        /** @var ProductEntity $entity */
        $entity = $this->formManager->saveFormModel($type, $_POST);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('There has been an error')));
        }

        if ($entity->getEntityValidationCollection() != null) {
            /**@var IEntityValidation $firstValidation */
            $firstValidation = $entity->getEntityValidationCollection()[0];

            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans($firstValidation->getTitle()),
                    'message' => $this->translator->trans($firstValidation->getMessage())
                )
            );
        }

        if ($hasMultilang) {
            $this->entityManager->refreshEntity($entity);

            if (empty($this->routeManager)) {
                $this->routeManager = $this->get("route_manager");
            }

            $this->routeManager->insertUpdateDefaultLanguages($entity, $_POST["id"]);
        }

        if (empty($this->sProductManager)) {
            $this->sProductManager = $this->getContainer()->get("s_product_manager");
        }

        $ret = $this->sProductManager->updateSProductAttributeConfiguration($entity, $_POST);
        if (isset($ret["message"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $ret["message"]));
        }

        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $this->crmProcessManager->recalculateProductPrices($entity);
        $this->entityManager->refreshEntity($entity);

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Form has been submitted'), 'entity' => $this->entityManager->entityToArray($entity)));
    }

    /**
     * EXPORT XLS with product_attributes
     * @Route("/product/export_product_attributes_xls", name="export_product_attributes_xls")
     * @Method("POST")
     */
    public function exportProductAttributesXlsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]["product"]) || empty($p["items"]["product"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        if(empty($this->defaultImportManager)){
            $this->defaultImportManager = $this->getContainer()->get("default_import_manager");
        }

        try{
            $xlsPath = $this->defaultImportManager->generateSimpleProductAttributesImportTemplate($p["items"]["product"]);
        }
        catch (\Exception $e){

            if(empty($this->errorLogManager)){
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $this->errorLogManager->logExceptionEvent("exportProductAttributesXlsAction",$e,true);

            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }

        $fullPath = $_ENV["SSL"]."://".$_ENV["BACKEND_URL"].$xlsPath;

        return new JsonResponse(array("error" => false, "file" => $fullPath, "message" => $this->translator->trans("Export generated")));
    }

    /**
     * @Route("/product/list/import_xls_template", name="product_import_xls_template")
     * @Method("POST")
     */
    public function importProductXlsTemplate(Request $request)
    {
        $this->initialize();

        $fileUrl = $_ENV["SHAPE_URL"]."/Documents/import_manual_template/simple_product_import.xlsx";

        return new JsonResponse(array('error' => false, 'filepath' => $fileUrl));
    }

    /**
     * @Route("/product/product_export_default", name="product_export_default")
     * @Method("POST")
     */
    public function productExportDefault(Request $request)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        $p = $_POST;

        if(empty($this->listViewManager)){
            $this->listViewManager = $this->getContainer()->get("list_view_manager");
        }

        $products = $this->listViewManager->getListViewDataModelEntities($p["list_view_id"],$p);

        if(empty($this->defaultImportManager)){
            $this->defaultImportManager = $this->getContainer()->get("default_import_manager");
        }

        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $attributes = $this->crmProcessManager->getProductExportDefaultAttributes();

        try{
            $xlsPath = $this->defaultImportManager->generateSimpleEntityImportTemplate($products,"product",null,$attributes);
        }
        catch (\Exception $e){

            if(empty($this->errorLogManager)){
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $this->errorLogManager->logExceptionEvent("productExportDefault",$e,true);

            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }

        $fullPath = $_ENV["SSL"]."://".$_ENV["BACKEND_URL"].$xlsPath;

        return new JsonResponse(array("error" => false, "file" => $fullPath, "message" => $this->translator->trans("Export generated")));
    }

    /**
     * @Route("/product/product_export_attributes_default", name="product_export_attributes_default")
     * @Method("POST")
     */
    public function productExportAttributesDefault(Request $request)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        $p = $_POST;

        if(empty($this->listViewManager)){
            $this->listViewManager = $this->getContainer()->get("list_view_manager");
        }

        $products = $this->listViewManager->getListViewDataModelEntities($p["list_view_id"],$p);

        if(empty($this->defaultImportManager)){
            $this->defaultImportManager = $this->getContainer()->get("default_import_manager");
        }

        $productIds = Array();
        if(EntityHelper::isCountable($products) && count($products)){
            /** @var ProductEntity $product */
            foreach ($products as $product){
                $productIds[] = $product->getId();
            }
        }

        try{
            $xlsPath = $this->defaultImportManager->generateSimpleProductAttributesImportTemplate($productIds,null);
        }
        catch (\Exception $e){

            if(empty($this->errorLogManager)){
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $this->errorLogManager->logExceptionEvent("productExportAttributesDefault",$e,true);

            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }

        $fullPath = $_ENV["SSL"]."://".$_ENV["BACKEND_URL"].$xlsPath;

        return new JsonResponse(array("error" => false, "file" => $fullPath, "message" => $this->translator->trans("Export generated")));
    }

    /**
     * Related product action
     */

    /**
     * @Route("/product/mass_related", name="mass_related")
     * @Method("POST")
     */
    public function addRelatedProductsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        $keys = array_keys($p["items"]);
        if (empty($keys)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product type code is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent product is not defined')));
        }

        $parentProductEntity = $this->productManager->getProductById($p["parent_entity_id"]);

        $this->productManager->addProductRelations($parentProductEntity, $p["items"][$keys[0]], CrmConstants::PRODUCT_RELATION_TYPE_RELATED);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relations successfully added')));
    }

    /**
     * @Route("/product/add_related", name="add_related")
     * @Method("POST")
     */
    public function addRelatedProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Related product id is not defined')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent product is not defined')));
        }

        /** @var ProductEntity $parentProductEntity */
        $parentProductEntity = $this->productManager->getProductById($p["parent_entity_id"]);

        $this->productManager->addProductRelations($parentProductEntity, [$p["id"]], CrmConstants::PRODUCT_RELATION_TYPE_RELATED);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relation successfully added')));
    }

    /**
     * @Route("/product/mass_upsell", name="mass_upsell")
     * @Method("POST")
     */
    public function addRelatedUpsellProductsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        $keys = array_keys($p["items"]);
        if (empty($keys)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product type code is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent product is not defined')));
        }

        if (empty($this->productManager)) {
            $this->productManager = $this->container->get("product_manager");
        }

        $parentProductEntity = $this->productManager->getProductById($p["parent_entity_id"]);

        $this->productManager->addProductRelations($parentProductEntity, $p["items"][$keys[0]], CrmConstants::PRODUCT_RELATION_TYPE_UPSELL);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relations successfully added')));
    }

    /**
     * @Route("/product/add_upsell", name="add_upsell")
     * @Method("POST")
     */
    public function addRelatedUpsellProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Related product id is not defined')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent product is not defined')));
        }

        /** @var ProductEntity $parentProductEntity */
        $parentProductEntity = $this->productManager->getProductById($p["parent_entity_id"]);

        $this->productManager->addProductRelations($parentProductEntity, [$p["id"]], CrmConstants::PRODUCT_RELATION_TYPE_UPSELL);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relation successfully added')));
    }

    /**
     * @Route("/product/mass_delete_related", name="mass_delete_related")
     * @Method("POST")
     */
    public function deleteRelatedProductsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"]) || !isset($p["items"]["product_product_link"]) || empty($p["items"]["product_product_link"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        foreach ($p["items"]["product_product_link"] as $relation_id) {

            /** @var ProductProductLinkEntity $relation */
            $relation = $this->productManager->getProductRelationById($relation_id);

            if (empty($relation)) {
                continue;
            }

            /** @var ProductProductLinkEntity $relation */
            $relation2 = $this->productManager->getProductRelation($relation->getChildProduct(), $relation->getParentProduct(), $relation->getRelationType());
            if (!empty($relation2)) {
                $this->productManager->deleteProductRelation($relation2);
            }

            $this->productManager->deleteProductRelation($relation);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relations successfully deleted')));
    }

    /**
     * @Route("/product/delete_product_relation", name="delete_product_relation")
     * @Method("POST")
     */
    public function deleteRelatedProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation id is not defined')));
        }

        /** @var ProductProductLinkEntity $relation */
        $relation = $this->productManager->getProductRelationById($p["id"]);

        if (empty($relation)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation does no exist')));
        }

        /** @var ProductProductLinkEntity $relation */
        $relation2 = $this->productManager->getProductRelation($relation->getChildProduct(), $relation->getParentProduct(), $relation->getRelationType());
        if (!empty($relation2)) {
            $this->productManager->deleteProductRelation($relation2);
        }

        $this->productManager->deleteProductRelation($relation);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relation successfully deleted')));
    }

    /**
     * @Route("/api/get_product_export/{secret_key}", name="get_product_export")
     * @Method("POST")
     */
    public function getProductExport(Request $request, $secret_key)
    {

        $p = $_POST;

        $this->initialize();

        if (!isset($p["password"]) && empty($p["password"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Export does not exist')));
        }
        if (empty($secret_key)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Export does not exist')));
        }

        /** @var ExportManager $exportManager */
        $exportManager = $this->getContainer()->get("export_manager");

        $filepath = $exportManager->getExportLocation($secret_key, $p["password"]);

        if (empty($filepath) || !file_exists($filepath)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Export does not exist')));
        }

        header('Content-Description: Product export');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="product_export.xml"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    /**
     * Product attributes block
     */

    /**
     * @Route("/product/add_s_product_attribute_link", name="add_s_product_attribute_link")
     * @Method("POST")
     */
    public function addSProductAttributeLinkAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;
        $p = array_map('trim', $p);

        if (!isset($p["configuration_id"]) || empty($p["configuration_id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Configuration id is incorrect")));
        }

        /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
        $sProductAttributeConfiguration = null;

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("id", "eq", $p["configuration_id"]));

        $sProductAttributeConfigurations = $this->sProductManager->getSproductAttributeConfigurations($compositeFilter);
        if (isset($sProductAttributeConfigurations[$p["configuration_id"]])) {
            $sProductAttributeConfiguration = $sProductAttributeConfigurations[$p["configuration_id"]];
        }

        if (empty($sProductAttributeConfiguration)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Configuration does not exist")));
        }

        $html = "";
        $template = $this->templateManager->getTemplatePathByBundle("Includes:s_product_attributes_group.html.twig");
        if (!empty($template)) {
            $html = $this->renderView($template, array("product_attribute" => array("ord" => $sProductAttributeConfiguration->getOrd(), "attribute" => $sProductAttributeConfiguration, "values" => null)));
        }

        return new JsonResponse(array("error" => false, "html" => $html, "configuration_type_id" => $sProductAttributeConfiguration->getSProductAttributeConfigurationTypeId()));
    }

    /**
     * @Route("/product/get_s_product_autocomplete", name="get_s_product_autocomplete")
     * @Method("GET")
     * @deprecated USE POST METHOD BELOW INSTEAD
     */
    public function getSProductAutocompleteAction(Request $request)
    {
        $this->initialize();

        $ret = array();


        $query = null;
        if (isset($_GET["q"]["term"]) && !empty($_GET["q"]["term"])) {
            $query = $_GET["q"]["term"];
        }

        if (isset($_GET["id"]) && !empty($_GET["id"])) {

            if(StringHelper::startsWith($_GET["id"],"s_")){
                $_GET["id"] = substr($_GET["id"], 2);
            }

            /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
            $sProductAttributeConfiguration = null;

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter("id", "eq", $_GET["id"]));

            $sProductAttributeConfigurations = $this->sProductManager->getSproductAttributeConfigurations($compositeFilter);
            if (isset($sProductAttributeConfigurations[$_GET["id"]])) {
                $sProductAttributeConfiguration = $sProductAttributeConfigurations[$_GET["id"]];
            }

            if (empty($sProductAttributeConfiguration)) {
                return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Configuration does not exist")));
            }

            $options = $sProductAttributeConfiguration->getSproductAttributeConfigurationOptions();

            if (EntityHelper::isCountable($options) && count($options) > 0) {
                /** @var SProductAttributeConfigurationOptionsEntity $option */
                foreach ($options as $option) {
                    if($option->getEntityStateId() == 2){
                        continue;
                    }

                    if (!empty($query)) {
                        if (stripos($option->getConfigurationValue(), $query) === false) {
                            continue;
                        }
                    }
                    $ret[] = array(
                        "id" => $option->getId(),
                        "html" => $option->getConfigurationValue()
                    );
                }
            }
        }

        return new JsonResponse(array("error" => false, "ret" => $ret));
    }

    /**
     * @Route("/product/form/add_field", name="product_attribute_filter_form_field")
     * @Method("POST")
     */
    public function fieldAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;
        if (!isset($p["attribute_id"]) || empty($p["attribute_id"])) {
            return new JsonResponse(array(
                "error" => true,
                "title" => "Error occurred",
                "message" => "Attribute id is missing"
            ));
        }

        if(empty($this->productAttributeFilterRulesManager)){
            $this->productAttributeFilterRulesManager = $this->getContainer()->get("product_attribute_filter_rules_manager");
        }

        $html = $this->productAttributeFilterRulesManager->getRenderedAttributeField($p["attribute_id"], "form", null);

        return new JsonResponse(array(
            "error" => false,
            "html" => $html
        ));
    }

    /**
     * @Route("/product/list/{view}/data", name="get_list_data_product")
     * @Method("POST")
     */
    public function getListDataAction(Request $request, $view)
    {
        /**
         * Change post data from s_product_attribute_link to product ids
         */
        $customData = null;
        if(isset($_POST["custom_data"]) && !empty($_POST["custom_data"])){
            $customData = $_POST["custom_data"];
            $selectedValues = json_decode($customData,true);
            $customDataTmp = Array();
            $customDataSproductAttributeFilter = Array();

            foreach ($selectedValues as $selectedValue){
                if(StringHelper::startsWith($selectedValue["attributeId"],"s_")){
                    $customDataSproductAttributeFilter[] = $selectedValue;
                }
                else{
                    $customDataTmp[] = $selectedValue;
                }
            }

            /**
             * Change s attribute filter to product_ids
             */
            if(!empty($customDataSproductAttributeFilter)){

                $join = "";
                $where = "";

                if(empty($this->sProductManager)){
                    $this->sProductManager = $this->getContainer()->get("s_product_manager");
                }

                foreach ($customDataSproductAttributeFilter as $filter){
                    /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
                    $sProductAttributeConfiguration = $this->sProductManager->getSproductAttributeConfigurationById(substr($filter["attributeId"], 2));

                    $field = "configuration_option";
                    if($sProductAttributeConfiguration->getSProductAttributeConfigurationTypeId() == 3){
                        $field = "attribute_value";
                    }

                    $preparedFilter = SearchFilterHelper::prepareFilter($filter["search_type"],"{$filter["attributeId"]}.",$field,$filter["search"]["value"]);

                    $join.=" LEFT JOIN s_product_attributes_link_entity AS {$filter["attributeId"]} ON p.id = {$filter["attributeId"]}.product_id ";
                    $where.=" AND {$preparedFilter} ";
                }

                if(empty($this->productAttributeFilterRulesManager)){
                    $this->productAttributeFilterRulesManager = $this->getContainer()->get("product_attribute_filter_rules_manager");
                }

                $productData = $this->productAttributeFilterRulesManager->getProductsByRule($join,$where);
                if(!empty($productData)){

                    if(empty($this->attributeContext)){
                        $this->attributeContext = $this->getContainer()->get("attribute_context");
                    }
                    if(empty($this->entityManager)){
                        $this->entityManager = $this->getContainer()->get("entity_manager");
                    }

                    /** @var Attribute $productIdAttribute */
                    $productIdAttribute = $this->attributeContext->getAttributeByCode("id",$this->entityManager->getEntityTypeByCode("product"));

                    $tmp = Array();
                    $tmp["attributeId"] = $productIdAttribute->getId();
                    $tmp["data"] = "id";
                    $tmp["search_type"] = "in";
                    $tmp["search"]["value"] = implode(",",array_column($productData,"id"));

                    $customDataTmp[] = $tmp;
                }
            }

            if(!empty($customDataTmp)){
                $_POST["custom_data"] = json_encode($customDataTmp);
            }
        }

        $this->initialize();
        $pager = new DataTablePager();
        $pager->setFromPost($_POST);

        if ($request->get("id")) {
            $pager->setRequestId($request->get("id"));
        } else {
            $pager->setRequestId(0);
        }

        if ($request->get("ptype")) {
            $pager->setType($request->get("ptype"));
        }

        $p = json_decode($_POST["data"], true);

        if ($p["edit"]) {
            $editable = true;
        } else {
            $editable = false;
        }

        if(empty($this->listViewManager)){
            $this->listViewManager = $this->getContainer()->get("list_view_manager");
        }

        $model = $this->listViewManager->getListViewDataModelHtml($view, $pager, $editable);

        /**
         * Save advanced search to session
         */
        if (isset($customData) && !empty($customData)) {
            $selectedValues = json_decode($customData);
            $selectedValuesArray = array();
            if (isset($selectedValues) && !empty($selectedValues)) {
                foreach ($selectedValues as $field) {

                    $tmpVal = $field->search->value;
                    if ($field->search_type == "ge" || $field->search_type == "le") {
                        $tmpVal = array();
                        if (isset($selectedValuesArray[$field->attributeId])) {
                            $tmpVal = $selectedValuesArray[$field->attributeId];
                        }
                        $tmpVal[$field->search_type] = $field->search->value;
                    } elseif ($field->search_type == "in" || $field->search_type == "ni") {
                        if(empty($this->attributeContext)){
                            $this->attributeContext = $this->getContainer()->get("attribute_context");
                        }
                        /** @var Attribute $attribute */
                        $attribute = $this->attributeContext->getById($field->attributeId);
                        if(!empty($attribute)){
                            if ($attribute->getFrontendInput() == "integer") {
                                $tmpVal = array();
                                if (isset($selectedValuesArray[$field->attributeId])) {
                                    $tmpVal = $selectedValuesArray[$field->attributeId];
                                }
                                $tmpVal[$field->search_type] = $field->search->value;
                            }
                        }
                        else{
                            $tmpVal = array();
                            $tmpVal[$field->search_type] = $field->search->value;
                        }
                    }
                    $selectedValuesArray[$field->attributeId] = $tmpVal;
                }
            }
            $session = $request->getSession();
            $session->set('custom_data_' . $request->get("pageBlockId"), $selectedValuesArray);
        }

        return new JsonResponse($model);
    }

    /**
     * @Route("/product/sAttributeSearchField", name="s_attribute_search_field")
     */
    public function sAttributeSearchFieldAction(Request $request)
    {
        $attribute = $request->get('attribute');
        $value = $request->get('value');
        $search_type = $request->get('search_type');

        if(empty($this->sProductManager)){
            $this->sProductManager = $this->getContainer()->get("s_product_manager");
        }

        /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
        $sProductAttributeConfiguration = $this->sProductManager->getSproductAttributeConfigurationById($attribute);

        $template = "autocomplete";
        if($sProductAttributeConfiguration->getSProductAttributeConfigurationTypeId() == 3){
            $template = "text";
        }

        $options = $sProductAttributeConfiguration->getSproductAttributeConfigurationOptions();

        if ($template == "autocomplete" && !empty($value) && EntityHelper::isCountable($options) && count($options) > 0) {

            $values = explode(",",$value);
            $value = Array();

            /** @var SProductAttributeConfigurationOptionsEntity $option */
            foreach ($options as $option) {
                if($option->getEntityStateId() == 2){
                    continue;
                }

                if(in_array($option->getId(),$values)) {
                    $value[] = array(
                        "option_id" => $option->getId(),
                        "value" => $option->getConfigurationValue()
                    );
                }
            }
        }

        if(empty($this->twig)){
            $this->twig = $this->getContainer()->get("templating");
        }

        $html = $this->twig->render('CrmBusinessBundle:Includes:s_attribute_' . $template . '.html.twig', array('attribute' => $attribute, 'value' => $value, 'search_type' => $search_type));

        return new Response($html);
    }

    /**
     * @Route("/product/get_s_product_autocomplete", name="get_s_product_autocomplete")
     * @Method("POST")
     */
    public function getSProductAutocompletePostAction(Request $request)
    {
        $this->initialize();

        $ret = array();


        $query = null;
        if (isset($_POST["q"]["term"]) && !empty($_POST["q"]["term"])) {
            $query = $_POST["q"]["term"];
        }

        if (isset($_POST["id"]) && !empty($_POST["id"])) {

            if(StringHelper::startsWith($_POST["id"],"s_")){
                $_POST["id"] = substr($_POST["id"], 2);
            }

            /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
            $sProductAttributeConfiguration = null;

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter("id", "eq", $_POST["id"]));

            $sProductAttributeConfigurations = $this->sProductManager->getSproductAttributeConfigurations($compositeFilter);
            if (isset($sProductAttributeConfigurations[$_POST["id"]])) {
                $sProductAttributeConfiguration = $sProductAttributeConfigurations[$_POST["id"]];
            }

            if (empty($sProductAttributeConfiguration)) {
                return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Configuration does not exist")));
            }

            $options = $sProductAttributeConfiguration->getSproductAttributeConfigurationOptions();

            if (EntityHelper::isCountable($options) && count($options) > 0) {
                /** @var SProductAttributeConfigurationOptionsEntity $option */
                foreach ($options as $option) {
                    if($option->getEntityStateId() == 2){
                        continue;
                    }

                    if (!empty($query)) {
                        if (stripos($option->getConfigurationValue(), $query) === false) {
                            continue;
                        }
                    }
                    $ret[] = array(
                        "id" => $option->getId(),
                        "html" => $option->getConfigurationValue()
                    );
                }
            }
        }

        return new JsonResponse(array("error" => false, "ret" => $ret));
    }

    /**
     * Configurable bundle product methods
     */

    /**
     * @Route("/product/mass_configurable_bundle", name="mass_configurable_bundle")
     * @Method("POST")
     */
    public function addConfigurableBundleProductsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        $keys = array_keys($p["items"]);
        if (empty($keys)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product type code is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        /** @var ProductConfigurationBundleOptionEntity $parentEntity */
        $parentEntity = $this->productManager->getProductConfigurationBundleOptionById($p["parent_entity_id"]);

        foreach ($p["items"][$keys[0]] as $product_id) {

            /** @var ProductEntity $childProductEntity */
            $childProductEntity = $this->productManager->getProductById($product_id);

            if (empty($childProductEntity)) {
                continue;
            }

            $relation = $this->productManager->getProductConfigurableBundleOptionLink($parentEntity, $childProductEntity);
            if (empty($relation)) {
                $this->productManager->createProductConfigurableBundleOptionLink($parentEntity, $childProductEntity);
            }
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relations successfully added')));
    }

    /**
     * @Route("/product/add_configurable_bundle", name="add_configurable_bundle")
     * @Method("POST")
     */
    public function addConfigurableBundleProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Related product id is not defined')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent product is not defined')));
        }

        /** @var ProductConfigurationBundleOptionEntity $parentEntity */
        $parentEntity = $this->productManager->getProductConfigurationBundleOptionById($p["parent_entity_id"]);

        /** @var ProductEntity $childProductEntity */
        $childProductEntity = $this->productManager->getProductById($p["id"]);

        if (empty($childProductEntity)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Related product does not exist')));
        }

        $relation = $this->productManager->getProductConfigurableBundleOptionLink($parentEntity, $childProductEntity);
        if (empty($relation)) {
            $this->productManager->createProductConfigurableBundleOptionLink($parentEntity, $childProductEntity);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relation successfully added')));
    }

    /**
     * @Route("/product/mass_delete_configurable_bundle", name="mass_delete_configurable_bundle")
     * @Method("POST")
     */
    public function deleteConfigurableBundleProductsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"]) || !isset($p["items"]["product_configuration_bundle_option_product_link"]) || empty($p["items"]["product_configuration_bundle_option_product_link"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        foreach ($p["items"]["product_configuration_bundle_option_product_link"] as $relation_id) {

            /** @var ProductConfigurationBundleOptionProductLinkEntity $relation */
            $relation = $this->productManager->getProductConfigurableBundleOptionLinkById($relation_id);

            if (empty($relation)) {
                continue;
            }

            $this->productManager->deleteProductConfigurableBundleOptionLink($relation);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relations successfully deleted')));
    }

    /**
     * @Route("/product/delete_product_configurable_bundle", name="delete_product_configurable_bundle")
     * @Method("POST")
     */
    public function deleteConfigurableBundleProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation id is not defined')));
        }

        /** @var ProductConfigurationBundleOptionProductLinkEntity $relation */
        $relation = $this->productManager->getProductConfigurableBundleOptionLinkById($p["id"]);

        if (empty($relation)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation does no exist')));
        }

        $this->productManager->deleteProductConfigurableBundleOptionLink($relation);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relation successfully deleted')));
    }

    /**
     * @Route("/product/mass_configurable_bundle_options_to_product", name="mass_configurable_bundle_options_to_product")
     * @Method("POST")
     */
    public function addConfigurableBundleOptionsToProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        $keys = array_keys($p["items"]);
        if (empty($keys)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product type code is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        /** @var ProductEntity $parentEntity */
        $parentEntity = $this->productManager->getProductById($p["parent_entity_id"]);

        foreach ($p["items"][$keys[0]] as $configurableBundleOptionId) {

            /** @var ProductConfigurationBundleOptionEntity $productConfigurationBundleOption */
            $productConfigurationBundleOption = $this->productManager->getProductConfigurationBundleOptionById($configurableBundleOptionId);

            if (empty($productConfigurationBundleOption)) {
                continue;
            }

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("configurableBundleOption", "eq", $productConfigurationBundleOption->getId()));
            $compositeFilter->addFilter(new SearchFilter("product", "eq", $parentEntity->getId()));

            $relation = $this->productManager->getProductConfigurationProductLink($compositeFilter);
            if (empty($relation)) {

                $data = Array();
                $data["product"] = $parentEntity;
                $data["configurable_bundle_option"] = $productConfigurationBundleOption;

                $this->productManager->createUpdateProductConfigurationProductLink($data);
            }
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relations successfully added')));
    }

    /**
     * @Route("/product/add_configurable_bundle_option_to_product", name="add_configurable_bundle_option_to_product")
     * @Method("POST")
     */
    public function addConfigurableBundleOptionToProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Related product id is not defined')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent product is not defined')));
        }

        /** @var ProductConfigurationBundleOptionEntity $productConfigurationBundleOption */
        $productConfigurationBundleOption = $this->productManager->getProductConfigurationBundleOptionById($p["id"]);

        /** @var ProductEntity $parentEntity */
        $parentEntity = $this->productManager->getProductById($p["parent_entity_id"]);

        if (empty($productConfigurationBundleOption)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Related product does not exist')));
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("configurableBundleOption", "eq", $productConfigurationBundleOption->getId()));
        $compositeFilter->addFilter(new SearchFilter("product", "eq", $parentEntity->getId()));

        $relation = $this->productManager->getProductConfigurationProductLink($compositeFilter);
        if (empty($relation)) {

            $data = Array();
            $data["product"] = $parentEntity;
            $data["configurable_bundle_option"] = $productConfigurationBundleOption;

            $this->productManager->createUpdateProductConfigurationProductLink($data);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relation successfully added')));
    }

    /**
     * @Route("/product/mass_delete_configurable_bundle_options_from_product", name="mass_delete_configurable_bundle_options_from_product")
     * @Method("POST")
     */
    public function deleteConfigurableBundleOptionsFromProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"]) || !isset($p["items"]["product_configuration_product_link"]) || empty($p["items"]["product_configuration_product_link"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        foreach ($p["items"]["product_configuration_product_link"] as $relation_id) {

            /** @var ProductConfigurationProductLinkEntity $relation */
            $relation = $this->productManager->getProductConfigurationProductLinkById($relation_id);

            if (empty($relation)) {
                continue;
            }

            $this->productManager->deleteProductConfigurationProductLink($relation);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relations successfully deleted')));
    }

    /**
     * @Route("/product/delete_configurable_bundle_option_from_product", name="delete_configurable_bundle_option_from_product")
     * @Method("POST")
     */
    public function deleteConfigurableBundleOptionFromProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation id is not defined')));
        }

        /** @var ProductConfigurationProductLinkEntity $relation */
        $relation = $this->productManager->getProductConfigurationProductLinkById($p["id"]);

        if (empty($relation)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation does no exist')));
        }

        $this->productManager->deleteProductConfigurationProductLink($relation);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relation successfully deleted')));
    }

    /**
     * @Route("/product/set_product_configurable_bundle_product_as_default", name="set_product_configurable_bundle_product_as_default")
     * @Method("POST")
     */
    public function setProductConfigurableBundleProductAsDefaultAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation id is not defined')));
        }

        /** @var ProductConfigurationBundleOptionProductLinkEntity $relation */
        $relation = $this->productManager->getProductConfigurableBundleOptionLinkById($p["id"]);

        if (empty($relation)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation does no exist')));
        }

        $this->productManager->setProductConfigurableBundleProductAsDefault($relation);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product set as default')));
    }

    /**
     * CONFIGURABLE PRODUCT METHODS
     */

    /**
     * @Route("/product/mass_configurable_attributes", name="mass_configurable_attributes")
     * @Method("POST")
     */
    public function addConfigurableAttributesAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        $keys = array_keys($p["items"]);
        if (empty($keys)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product type code is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        /** @var ProductEntity $parentEntity */
        $parentEntity = $this->productManager->getProductById($p["parent_entity_id"]);

        foreach ($p["items"][$keys[0]] as $sProductAttributeConfigurationId) {

            /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
            $sProductAttributeConfiguration = $this->sProductManager->getSproductAttributeConfigurationById($sProductAttributeConfigurationId);

            if (empty($sProductAttributeConfiguration)) {
                continue;
            }

            $relation = $this->productManager->getProductConfigurableAttributeByProductAndAttribute($parentEntity, $sProductAttributeConfiguration);
            if (empty($relation)) {
                $this->productManager->createProductConfigurableAttribute($parentEntity, $sProductAttributeConfiguration);
            }
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relations successfully added')));
    }

    /**
     * @Route("/product/add_configurable_attribute", name="add_configurable_attribute")
     * @Method("POST")
     */
    public function addConfigurableAttributeAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Related product id is not defined')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent product is not defined')));
        }

        /** @var ProductEntity $parentEntity */
        $parentEntity = $this->productManager->getProductById($p["parent_entity_id"]);

        /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
        $sProductAttributeConfiguration = $this->sProductManager->getSproductAttributeConfigurationById($p["id"]);

        if (empty($sProductAttributeConfiguration)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Related product does not exist')));
        }

        $relation = $this->productManager->getProductConfigurableAttributeByProductAndAttribute($parentEntity, $sProductAttributeConfiguration);
        if (empty($relation)) {
            $this->productManager->createProductConfigurableAttribute($parentEntity, $sProductAttributeConfiguration);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relation successfully added')));
    }

    /**
     * @Route("/product/mass_delete_configurable_attributes", name="mass_delete_configurable_attributes")
     * @Method("POST")
     */
    public function deleteConfigurableAttributesAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"]) || !isset($p["items"]["product_configurable_attribute"]) || empty($p["items"]["product_configurable_attribute"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        foreach ($p["items"]["product_configurable_attribute"] as $relation_id) {

            /** @var ProductConfigurableAttributeEntity $relation */
            $relation = $this->productManager->getProductConfigurableAttributeById($relation_id);

            if (empty($relation)) {
                continue;
            }

            $this->productManager->deleteProductConfigurableAttribute($relation);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relations successfully deleted')));
    }

    /**
     * @Route("/product/delete_configurable_attribute", name="delete_configurable_attribute")
     * @Method("POST")
     */
    public function deleteConfigurableAttributeAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation id is not defined')));
        }

        /** @var ProductConfigurableAttributeEntity $relation */
        $relation = $this->productManager->getProductConfigurableAttributeById($p["id"]);

        if (empty($relation)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation does no exist')));
        }

        $this->productManager->deleteProductConfigurableAttribute($relation);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product relation successfully deleted')));
    }

    /**
     * @Route("/product/mass_add_products_to_configurable_products", name="mass_add_products_to_configurable_products")
     * @Method("POST")
     */
    public function addChildProductsToConfigurableProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        $keys = array_keys($p["items"]);
        if (empty($keys)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product type code is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        foreach ($p["items"]["product"] as $childProductId) {

            $count = 0;

            try{
                $this->productManager->insertProductConfigurationProductLink($p["parent_entity_id"],$childProductId,true);
                $count++;
            }
            catch (\Exception $e){
                if(empty($this->errorLogManager)){
                    $this->errorLogManager = $this->container->get("error_log_manager");
                }

                $this->errorLogManager->logExceptionEvent("mass_add_products_to_configurable_products", $e, false);
            }
        }

        return new JsonResponse(array('error' => false, 'message' => "{$count} ".$this->translator->trans('Products successfully added to configurable product')));
    }

    /**
     * @Route("/product/add_product_to_configurable_products", name="add_product_to_configurable_products")
     * @Method("POST")
     */
    public function addChildProductToConfigurableProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product id is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        try{
            $this->productManager->insertProductConfigurationProductLink($p["parent_entity_id"],$p["id"],true);
        }
        catch (\Exception $e){
            if(empty($this->errorLogManager)){
                $this->errorLogManager = $this->container->get("error_log_manager");
            }

            $this->errorLogManager->logExceptionEvent("add_product_to_configurable_products", $e, false);

            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Unable to add simple product to configurable product')));
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product successfully added to configurable product')));
    }


    /**
     * @Route("/product/mass_delete_products_from_configurable_products", name="mass_delete_products_from_configurable_products")
     * @Method("POST")
     */
    public function deleteChildProductsFromConfigurableProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        $keys = array_keys($p["items"]);
        if (empty($keys)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product type code is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        foreach ($p["items"]["product_configuration_product_link"] as $relationId) {

            /** @var ProductConfigurationProductLinkEntity $relation */
            $relation = $this->productManager->getProductConfigurationProductLinkById($relationId);
            if (empty($relation)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation does no exist')));
            }
            else{
                $this->productManager->deleteProductConfigurationProductLink($relation);
            }
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Products successfully removed to configurable product')));
    }

    /**
     * @Route("/product/delete_product_from_configurable_products", name="delete_product_from_configurable_products")
     * @Method("POST")
     */
    public function deleteChildProductFromConfigurableProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product id is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        /** @var ProductEntity $parentEntity */
        $parentEntity = $this->productManager->getProductById($p["parent_entity_id"]);
        if (empty($parentEntity)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product id is missing')));
        }

        /** @var ProductConfigurationProductLinkEntity $relation */
        $relation = $this->productManager->getProductConfigurationProductLinkById($p["id"]);

        if (empty($relation)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation does no exist')));
        }
        else{
            $this->productManager->deleteProductConfigurationProductLink($relation);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product successfully removed to configurable product')));
    }

    /**
     * BUNDLE PRODUCT MANAGEMENT
     */
    /**
     * @Route("/product/mass_add_products_to_bundle_products", name="mass_add_products_to_bundle_products")
     * @Method("POST")
     */
    public function addChildProductsToBundleProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        $keys = array_keys($p["items"]);
        if (empty($keys)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product type code is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        /** @var ProductEntity $parentEntity */
        $parentEntity = $this->productManager->getProductById($p["parent_entity_id"]);

        foreach ($p["items"]["product"] as $childProductId) {

            /** @var ProductEntity $childProduct */
            $childProduct = $this->productManager->getProductById($childProductId);

            if (empty($childProduct)) {
                continue;
            }

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("childProduct", "eq", $childProductId));
            $compositeFilter->addFilter(new SearchFilter("product", "eq", $parentEntity->getId()));

            $data = Array();
            $data["product"] = $parentEntity;
            $data["child_product"] = $childProduct;

            /** @var ProductConfigurationProductLinkEntity $relation */
            $relation = $this->productManager->getProductConfigurationProductLink($compositeFilter);
            if (empty($relation)) {
                $this->productManager->createUpdateProductConfigurationProductLink($data);
            }
        }

        /**
         * Check if base product is added to bundle
         */
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("childProduct", "eq", $p["parent_entity_id"]));
        $compositeFilter->addFilter(new SearchFilter("product", "eq", $parentEntity->getId()));

        $data = Array();
        $data["product"] = $parentEntity;
        $data["child_product"] = $parentEntity;
        $data["min_qty"] = 1;

        /** @var ProductConfigurationProductLinkEntity $relation */
        $relation = $this->productManager->getProductConfigurationProductLink($compositeFilter);
        if (empty($relation)) {
            $this->productManager->createUpdateProductConfigurationProductLink($data);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Products successfully added to bundle product')));
    }

    /**
     * @Route("/product/add_product_to_bundle_products", name="add_product_to_bundle_products")
     * @Method("POST")
     */
    public function addChildProductToBundleProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product id is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        /** @var ProductEntity $parentEntity */
        $parentEntity = $this->productManager->getProductById($p["parent_entity_id"]);

        /** @var ProductEntity $childProduct */
        $childProduct = $this->productManager->getProductById($p["id"]);

        if (empty($childProduct)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product id is missing')));
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("childProduct", "eq", $p["id"]));
        $compositeFilter->addFilter(new SearchFilter("product", "eq", $parentEntity->getId()));

        $data = Array();
        $data["product"] = $parentEntity;
        $data["child_product"] = $childProduct;

        /** @var ProductConfigurationProductLinkEntity $relation */
        $relation = $this->productManager->getProductConfigurationProductLink($compositeFilter);
        if (empty($relation)) {
            $this->productManager->createUpdateProductConfigurationProductLink($data);
        }

        /**
         * Check if base product is added to bundle
         */
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("childProduct", "eq", $p["parent_entity_id"]));
        $compositeFilter->addFilter(new SearchFilter("product", "eq", $parentEntity->getId()));

        $data = Array();
        $data["product"] = $parentEntity;
        $data["child_product"] = $parentEntity;
        $data["min_qty"] = 1;

        /** @var ProductConfigurationProductLinkEntity $relation */
        $relation = $this->productManager->getProductConfigurationProductLink($compositeFilter);
        if (empty($relation)) {
            $this->productManager->createUpdateProductConfigurationProductLink($data);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product successfully added to bundle product')));
    }

    /**
     * @Route("/product/mass_delete_products_from_bundle_products", name="mass_delete_products_from_bundle_products")
     * @Method("POST")
     */
    public function deleteChildProductsFromBundleProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        $keys = array_keys($p["items"]);
        if (empty($keys)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product type code is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        /** @var ProductEntity $parentEntity */
        $parentEntity = $this->productManager->getProductById($p["parent_entity_id"]);
        if (empty($parentEntity)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product id is missing')));
        }

        foreach ($p["items"]["product_configuration_product_link"] as $relationId) {

            /** @var ProductConfigurationProductLinkEntity $relation */
            $relation = $this->productManager->getProductConfigurationProductLinkById($relationId);
            if (empty($relation)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation does no exist')));
            }
            elseif ($relation->getChildProduct()->getId() == $parentEntity->getId()){
                continue;
            }
            else{
                $this->productManager->deleteProductConfigurationProductLink($relation);
            }
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Products successfully removed to bundle product')));
    }

    /**
     * @Route("/product/delete_product_from_bundle_products", name="delete_product_from_bundle_products")
     * @Method("POST")
     */
    public function deleteChildProductFromBundleProductAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product id is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        /** @var ProductEntity $parentEntity */
        $parentEntity = $this->productManager->getProductById($p["parent_entity_id"]);
        if (empty($parentEntity)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product id is missing')));
        }

        /** @var ProductConfigurationProductLinkEntity $relation */
        $relation = $this->productManager->getProductConfigurationProductLinkById($p["id"]);

        if (empty($relation)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation does no exist')));
        }
        elseif ($relation->getChildProduct()->getId() == $parentEntity->getId()){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Cannot delete parent from bundle')));
        }
        else{
            $this->productManager->deleteProductConfigurationProductLink($relation);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product successfully removed to configurable product')));
    }

    /**
     * @Route("/product/mass_exclude_from_discounts", name="mass_exclude_from_discounts")
     * @Method("POST")
     */
    public function massExcludeFromDiscountsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"]) || !isset($p["items"]["product"]) || empty($p["items"]["product"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        if(empty($this->productManager)){
            $this->productManager = $this->getContainer()->get("product_manager");
        }

        $this->productManager->setExcludeFromDiscounts($p["items"]["product"],1);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product successfully excluded from discounts')));
    }

    /**
     * @Route("/product/mass_remove_exclude_from_discounts", name="mass_remove_exclude_from_discounts")
     * @Method("POST")
     */
    public function massRemoveExcludeFromDiscountsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"]) || !isset($p["items"]["product"]) || empty($p["items"]["product"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        if(empty($this->productManager)){
            $this->productManager = $this->getContainer()->get("product_manager");
        }

        $this->productManager->setExcludeFromDiscounts($p["items"]["product"],0);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Exclusion from discounts successfully removed')));
    }

    /**
     * @Route("/email_template/send_test_email", name="send_test_email")
     * @Method("POST")
     */
    public function sendTestEmailAction(Request $request){

        $this->initialize();

        $p = $_POST;
        $p = array_map('trim', $p);

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Email template id is empty')));
        }

        if (empty($this->emailTemplateManager)) {
            $this->emailTemplateManager = $this->getContainer()->get("email_template_manager");
        }

        if(empty($this->entityManager)){
            $this->entityManager = $this->getContainer()->get("entity_manager");
        }

        $etEmailTemplate = $this->entityManager->getEntityTypeByCode("email_template");

        /** @var EmailTemplateEntity $emailTemplate */
        $emailTemplate = $this->entityManager->getEntityByEntityTypeAndId($etEmailTemplate, $p["id"]);

        if (empty($emailTemplate)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Email template does not exist')));
        }

        /** @var DatabaseContext $databaseContext */
        $databaseContext = $this->container->get("database_context");

        $tableName = "{$emailTemplate->getProvidedEntityTypeId()}_entity";

        $entityId = $emailTemplate->getEntityIdentifier();
        if(empty($entityId)){
            $q = "SELECT id FROM {$tableName} ORDER BY id ASC LIMIT 1";
            $data = $databaseContext->getSingleEntity($q);

            $entityId = $data["id"];
        }

        // Izuzetci kada entitet ne prati pattern
        if ($emailTemplate->getProvidedEntityTypeId() == "core_user") {
            $tableName = "user_entity";
        }

        $entityId = $emailTemplate->getEntityIdentifier();
        if(empty($entityId)){
            $q = "SELECT id FROM {$tableName} ORDER BY id ASC LIMIT 1";
            $data = $databaseContext->getSingleEntity($q);

            $entityId = $data["id"];
        }

        $entity = $this->entityManager->getEntityByEntityTypeAndId($this->entityManager->getEntityTypeByCode($emailTemplate->getProvidedEntityTypeId()), $entityId);

        /** @var RouteManager $routeManager */
        $routeManager = $this->container->get("route_manager");

        //$websiteData = $routeManager->getWebsiteDataById($_ENV["DEFAULT_WEBSITE_ID"]);

        $templateData = $this->emailTemplateManager->renderEmailTemplate($entity, $emailTemplate);

        if(empty($this->mailManager)){
            $this->mailManager = $this->getContainer()->get("mail_manager");
        }

        $this->mailManager->sendEmail(array('email' => $this->user->getEmail(), 'name' => $this->user->getEmail()), null, null, null, $templateData["subject"], "", null, [], $templateData["content"], $attachments ?? [], $_ENV["DEFAULT_STORE_ID"]);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Test email sent')));
    }
}
