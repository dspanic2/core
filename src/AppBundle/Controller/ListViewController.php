<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\ErrorLogEntity;
use AppBundle\Entity\PageBlock;
use AppBundle\Entity\SearchFilter;
use AppBundle\Factory\FactoryManager;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\FileHelper;
use AppBundle\Interfaces\Blocks\BlockInterface;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\ExcelManager;
use AppBundle\Managers\ListViewManager;
use AppBundle\Managers\PageManager;
use Doctrine\ORM\PersistentCollection;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationEntity;
use ScommerceBusinessBundle\Managers\SproductManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ListViewController extends AbstractController
{
    /** @var FactoryManager $factoryManager */
    protected $factoryManager;
    /**@var ListViewManager $listViewManager */
    protected $listViewManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var ExcelManager $excelManager */
    protected $excelManager;
    /** @var BlockManager $blockManager */
    protected $blockManager;
    /** @var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    /** @var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /** @var ListViewContext $listViewContext */
    protected $listViewContext;
    /** @var PageManager $pageManager */
    protected $pageManager;
    /** @var SproductManager $sProductManager */
    protected $sProductManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    protected function initialize()
    {
        parent::initialize();
        $this->factoryManager = $this->getContainer()->get("factory_manager");
        $this->listViewManager = $this->getContainer()->get("list_view_manager");
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->excelManager = $this->getContainer()->get("excel_manager");
        $this->blockManager = $this->getContainer()->get("block_manager");
        $this->attributeSetContext = $this->getContainer()->get("attribute_set_context");
        $this->entityTypeContext = $this->getContainer()->get("entity_type_context");
        $this->attributeContext = $this->getContainer()->get("attribute_context");
        $this->listViewContext = $this->getContainer()->get("list_view_context");
        $this->pageManager = $this->getContainer()->get("page_manager");
    }

    /**
     * @Route("/{type}/list/{view}/data", name="get_list_data")
     * @Method("POST")
     */
    public function getListDataAction(Request $request, $type, $view)
    {
//        dump($type);die;
        $this->initialize();
        $pager = new DataTablePager();
        $pager->setFromRequest($request);

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

        $model = $this->listViewManager->getListViewDataModelHtml($view, $pager, $editable);

        /**
         * Save advanced search to session
         */
        if (isset($_POST["custom_data"]) && !empty($_POST["custom_data"])) {
            $selectedValues = json_decode($_POST["custom_data"]);
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
                        /** @var Attribute $attribute */
                        $attribute = $this->attributeContext->getById($field->attributeId);
                        if ($attribute->getFrontendInput() == "integer") {
                            $tmpVal = array();
                            if (isset($selectedValuesArray[$field->attributeId])) {
                                $tmpVal = $selectedValuesArray[$field->attributeId];
                            }
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
     * Used to generate list view header colums
     * @param Request $request
     * @param $view
     * @return Response
     */
    public function listViewHeaderAction(Request $request, $view)
    {
        $this->initialize();

        $header = $this->listViewManager->getListViewHeader($view);

        return new Response($header);
    }

    /**
     * @Route("/list_view_table", name="get_list_view_table")
     * @Method("POST")
     */
    public function listViewTableAction(Request $request)
    {
        $this->initialize();
        $session = $request->getSession();
        $p = $_POST;

        /**@var ListView $view */
        $view = $this->listViewManager->getListViewModel($p["view_id"]);

        $attributeSet = $view->getAttributeSet();
        $data = array();

        $data["is_modal"] = true;
        $data["parent"]["id"] = "";
        $data["id"] = null;
        if(isset($_GET["pid"]) && !empty($_GET["pid"])){
            $data["parent"]["id"] = $_GET["pid"];
            $data["id"] = $_GET["pid"];
        }
        $data["parent"]["attributeSetCode"] = "";
        if(isset($_GET["pid"]) && !empty($_GET["ptype"])){
            $data["parent"]["attributeSetCode"] = $_GET["ptype"];
        }
        $block_id = $p["block_id"];

        $data["type"] = "list";

        /** @var PageBlock $block */
        $block = $this->blockManager->getBlockById($block_id);
        $block->setRelatedId($p["view_id"]);
        $page = $this->pageManager->loadPageByUid($block->getParent());
        if(isset($_GET["parent_page_uid"]) && !empty($_GET["parent_page_uid"])){
            $page = $this->pageManager->loadPageByUid($_GET["parent_page_uid"]);
        }

        $data["page"] = $page;
        $data["list_view_id"] = $p["view_id"];
        $session->set($p["block_id"], $data["list_view_id"]);


        $html = $this->blockManager->generateBlockHtmlV2($data, $block);

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/massDelete", name="mass_delete")
     * @Method("POST")
     */
    public function massDelete(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("List is empty")));
        }

        $count = 0;

        foreach ($p["items"] as $type => $list) {
            $formManager = $this->factoryManager->loadFormManager($type);

            foreach ($list as $id) {
                $entity = $formManager->deleteFormModel($type, $id);
                if (empty($entity->getEntityValidationCollection())) {
                    $count++;
                }
            }
        }

        if (!$count) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("No items were deleted")));
        } else if ($count != count($p["items"])) {
            return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Delete items"), "message" => $this->translator->trans("Some items were deleted")));
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Delete items"), "message" => $this->translator->trans("Selected items were deleted")));
    }

    /**
     * @param Request $request
     * @param $data
     * @return Response
     */
    public function advancedSearchFormAction(Request $request, $data)
    {
        $this->initialize();

        /**@var BlockInterface $pageBlock */
        $pageBlock = $this->blockManager->getBlock($data["block"], $data);

        $searchAttributesTmp = $this->attributeContext->getBy(array("entityType" => $data["model"]->getEntityType(), "useInAdvancedSearch" => 1));
        $searchAttributes = array();
        $hasSelectedValues = false;

        if (!empty($searchAttributesTmp)) {
            $session = $request->getSession();
            $selectedValues = $session->get('custom_data_' . $data["block"]->getId());
            foreach ($searchAttributesTmp as $searchAttribute) {
                if (!isset($selectedValues[$searchAttribute->getId()])) {
                    $selectedValues[$searchAttribute->getId()] = null;
                } else {
                    $hasSelectedValues = true;
                }
                $searchAttributes[$searchAttribute->getAttributeCode()] = array('type' => 'attribute', 'attribute' => $searchAttribute, 'selectedValue' => $selectedValues[$searchAttribute->getId()]);
            }
        }

        /**
         * Specificno za proizvode ovo se moze naknadno prebaciti na bolje mjesto
         */
        if($data["model"]->getEntityType()->getEntityTypeCode() == "product"){

            if(empty($this->sProductManager)){
                $this->sProductManager = $this->getContainer()->get("s_product_manager");
            }

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("showInAdminFilter", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));

            $sProductConfigurations = $this->sProductManager->getSproductAttributeConfigurations($compositeFilter);
            if(EntityHelper::isCountable($sProductConfigurations) && count($sProductConfigurations)){

                $session = $request->getSession();
                $selectedValuesSProductLink = $session->get('custom_data_' . $data["block"]->getId());

                /** @var SProductAttributeConfigurationEntity $sProductConfiguration */
                foreach ($sProductConfigurations as $sProductConfiguration){

                    $searchType = null;
                    if(isset($selectedValuesSProductLink["s_".$sProductConfiguration->getId()])){
                        foreach ($selectedValuesSProductLink["s_".$sProductConfiguration->getId()] as $key => $val){
                            $searchType = $key;
                            $selectedValues["s_".$sProductConfiguration->getId()] = $val;
                            break;
                        }
                    }
                    else{
                        $selectedValues["s_".$sProductConfiguration->getId()] = null;
                    }

                    $searchAttributes[$sProductConfiguration->getFilterKey()] = array('type' => 's_attribute', 'attribute' => $sProductConfiguration, 'selectedValue' => $selectedValues["s_".$sProductConfiguration->getId()], 'search_type' => $searchType);
                }
            }
        }

        return new Response($this->renderView($pageBlock->GetAdvancedSearchTemplate(), array("searchAttributes" => $searchAttributes, "hasSelectedValues" => $hasSelectedValues)));
    }

    /**
     * @param Request $request
     * @param $data
     * @return Response
     */
    public function quickSearchFormAction(Request $request, $data)
    {
        if (!isset($data["quickSearchQuery"]) || empty($data["quickSearchQuery"])) {
            return new Response();
        }

        $this->initialize();

        $preparedQuery = $this->helperManager->prepareQueryForQuickSearch($data["quickSearchQuery"]);

        if (empty($preparedQuery)) {
            return new Response();
        }

        /**@var BlockInterface $pageBlock */
        $pageBlock = $this->blockManager->getBlock($data["block"], $data);

        $searchAttributesTmp = $this->attributeContext->getBy(array("entityType" => $data["model"]->getEntityType(), "useInQuickSearch" => 1));
        $searchAttributes = array();

        if (!empty($searchAttributesTmp)) {
            foreach ($preparedQuery as $queryPart) {
                foreach ($searchAttributesTmp as $searchAttribute) {
                    $searchAttributes[] = array('attribute' => $searchAttribute, 'selectedValue' => $queryPart);
                }
            }
        } else {
            return new Response();
        }

        return new Response($this->renderView($pageBlock->GetQuickSearchTemplate(), array("searchAttributes" => $searchAttributes)));
    }

    /**
     * @Route("/{type}/list/{view}/export_xls_config", name="export_xls_config")
     * @Method("POST")
     */
    public function exportXlsConfig(Request $request, $type, $view)
    {
        $this->initialize();

        $entityType = $this->entityTypeContext->getOneBy(array("entityTypeCode" => $type));
        $attributes = $this->entityManager->getAttributesListExportConfiguration($entityType);

        $ids = "";
        if (isset($_POST["items"]) && isset($_POST["items"][$type]) && !empty($_POST["items"][$type])) {
            $ids = implode(",", $_POST["items"][$type]);
        }

        $html = $this->renderView("AppBundle:Includes:export.html.twig", array(
            'entityType' => $entityType,
            'attributes' => $attributes,
            'filter' => $_POST["data"],
            'view_id' => $view,
            'ids' => $ids,
            'entity_id' => $request->get("id")
        ));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/{type}/list/export_xls", name="export_xls")
     * @Method("POST")
     */
    public function exportXls(Request $request, $type)
    {
        $this->initialize();

        if (empty($_POST["multiselect"][0]["related_ids"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occurred'), 'message' => $this->translator->trans('Please select at least one attribute to export')));
        }

        $attributes = $_POST["multiselect"][0]["related_ids"];

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);
        $pager->setStart(0);
        $pager->setLenght(50000);
        $pager->setType("raw");

        if ($request->get("entity_id")) {
            $pager->setRequestId($request->get("entity_id"));
        } else {
            $pager->setRequestId(0);
        }

        $view_id = $_POST["view_id"];
        $view = $this->listViewContext->getById($view_id);

        if (!empty($_POST["ids"])) {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");

            /**set filter to get only active*/
            $searchFilter = new SearchFilter();
            $searchFilter->setField("id");
            $searchFilter->setOperation("in");
            $searchFilter->setValue($_POST["ids"]);
            $compositeFilter->addFilter($searchFilter);

            $pager->addFilter($compositeFilter);
        }

        $entities = $this->listViewManager->getListViewDataModel($view, $pager);

        $filepath = $this->excelManager->exportTemplate($attributes, $entities, $type);

        return new JsonResponse(array('error' => false, 'filepath' => $filepath));
    }

    /**
     * @Route("/{type}/list/import_xls_config", name="import_xls_config")
     * @Method("POST")
     */
    public function importXlsConfig(Request $request, $type)
    {
        $this->initialize();

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->attributeSetContext->getOneBy(array("attributeSetCode" => $type));

        $attributes = $this->entityManager->getAttributesOfEntityType($attributeSet->getEntityType()->getEntityTypeCode(), false);

        $html = $this->renderView("AppBundle:Includes:import.html.twig", array('attributeSet' => $attributeSet, 'attributes' => $attributes));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/{type}/list/import_validate_xls", name="import_validate_xls")
     * @Method("POST")
     */
    public function importValidateXls(Request $request, $type)
    {
        $this->initialize();

        $p = $_POST;

        if (empty($p["path"]) || !file_exists($p["path"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Import file is missing')));
        }

        $fileLocation = $p["path"];

        $headers = $this->excelManager->getHeadersFromTable($fileLocation);

        if (count($headers) != 2) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Header is missing')));
        }

        $attributesList = array();
        $attributeMatched = false;

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->attributeSetContext->getOneBy(array("attributeSetCode" => $type));

        $attributes = $this->entityManager->getAttributesOfEntityType($attributeSet->getEntityType()->getEntityTypeCode(), false);

        foreach ($headers[0] as $key => $attribute_code) {

            if(stripos($attribute_code,":") !== false){
                $attribute_code = explode(":",$attribute_code)[1];
            }

            $attributesList[$key]["label"] = $headers[1][$key];
            $attributesList[$key]["attribute"] = null;
            $attributesList[$key]["related_attributes"] = null;

            /** @var Attribute $attribute */
            foreach ($attributes as $attribute) {
                $tmpAttributeCode = $attribute->getAttributeCode();
                if ($attribute->getFrontendType() == "multiselect") {
                    $tmpAttributeCode = $attribute->getAttributeCode() . "." . $attribute->getLookupAttribute()->getLookupAttribute()->getAttributeCode();
                }

                if ($tmpAttributeCode == $attribute_code) {
                    $attributeMatched = true;

                    if ($attribute->getBackendType() == "lookup" && $attribute->getFrontendType() == "multiselect") {
                        $attributesList[$key]["related_attributes"] = $this->entityManager->getAttributesOfEntityType($attribute->getLookupAttribute()->getLookupEntityType()->getEntityTypeCode(), false);
                    } elseif ($attribute->getBackendType() == "lookup") {
                        $attributesList[$key]["related_attributes"] = $this->entityManager->getAttributesOfEntityType($attribute->getLookupEntityType()->getEntityTypeCode(), false);
                    }

                    $attributesList[$key]["attribute"] = $attribute;
                    break;
                }
            }
        }

        // TODO: skip empty fields
        // TODO: better primary key decision

        $html = $this->renderView("AppBundle:Includes:import_list.html.twig", array('attributesList' => $attributesList));

        return new JsonResponse(array('error' => false, 'html' => $html, 'attributeMatched' => $attributeMatched));
    }

    /**
     * @Route("/{type}/list/import_xls", name="import_xls")
     * @Method("POST")
     */
    public function importXls(Request $request, $type)
    {
        $this->initialize();

        $p = $_POST;

        if (empty($p["path"]) || !file_exists($p["path"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Import file is missing')));
        }
        if (empty($p["primary"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Primary key is not defined')));
        }
        if (empty($p["matched"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('List of matched attributes is empty')));
        }

        $fileLocation = $p["path"];
        
        try{
            $entities = $this->excelManager->getEntitiesFromTable($fileLocation, $p["matched"]);

            $results = $this->entityManager->importEntites($type, $entities, $p["primary"], $p["matched"]);
        }
        catch (\Exception $e){
            
            if(empty($this->errorLogManager)){
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }
            
            $this->errorLogManager->logExceptionEvent("List view import",$e,true);
        }

        $html = $this->renderView("AppBundle:Includes:import_results.html.twig", array('results' => $results));

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Import finished'), 'message' => $this->translator->trans('Detailed results displayed above'), 'html' => $html));
    }

    /**
     * @Route("/s_product_attribute_configuration/list/import_xls_template", name="s_product_attribute_configuration_import_xls_template")
     * @Method("POST")
     */
    public function importSProductAttributeConfigurationXlsTemplate(Request $request)
    {
        $this->initialize();

        $type = "s_product_attribute_configuration";

        $fileUrl = $_ENV["SHAPE_URL"]."/Documents/{$type}_default.xlsx";
        if(FileHelper::checkIfRemoteFileExists($_ENV["SHAPE_URL"]."/Documents/{$type}_{$_ENV["FRONTEND_BUNDLE"]}.xlsx")){
            $fileUrl = $_ENV["SHAPE_URL"]."/Documents/{$type}_{$_ENV["FRONTEND_BUNDLE"]}.xlsx";
        }

        return new JsonResponse(array('error' => false, 'filepath' => $fileUrl));
    }

    /**
     * @Route("/s_product_attribute_configuration_options/list/import_xls_template", name="s_product_attribute_configuration_options_import_xls_template")
     * @Method("POST")
     */
    public function importSProductAttributeConfigurationOptionsXlsTemplate(Request $request)
    {
        $this->initialize();

        $type = "s_product_attribute_configuration_options";

        $fileUrl = $_ENV["SHAPE_URL"]."/Documents/{$type}_default.xlsx";
        if(FileHelper::checkIfRemoteFileExists($_ENV["SHAPE_URL"]."/Documents/{$type}_{$_ENV["FRONTEND_BUNDLE"]}.xlsx")){
            $fileUrl = $_ENV["SHAPE_URL"]."/Documents/{$type}_{$_ENV["FRONTEND_BUNDLE"]}.xlsx";
        }

        return new JsonResponse(array('error' => false, 'filepath' => $fileUrl));
    }

    /**
     * @Route("/blog_category/list/import_xls_template", name="blog_category_import_xls_template")
     * @Method("POST")
     */
    public function importBlogCategoryXlsTemplate(Request $request)
    {
        $this->initialize();

        $type = "blog_category";

        $fileUrl = $_ENV["SHAPE_URL"]."/Documents/{$type}_default.xlsx";
        if(FileHelper::checkIfRemoteFileExists($_ENV["SHAPE_URL"]."/Documents/{$type}_{$_ENV["FRONTEND_BUNDLE"]}.xlsx")){
            $fileUrl = $_ENV["SHAPE_URL"]."/Documents/{$type}_{$_ENV["FRONTEND_BUNDLE"]}.xlsx";
        }

        return new JsonResponse(array('error' => false, 'filepath' => $fileUrl));
    }

    /**
     * @Route("/blog_post/list/import_xls_template", name="blog_post_import_xls_template")
     * @Method("POST")
     */
    public function importBlogPostXlsTemplate(Request $request)
    {
        $this->initialize();

        $type = "blog_post";

        $fileUrl = $_ENV["SHAPE_URL"]."/Documents/{$type}_default.xlsx";
        if(FileHelper::checkIfRemoteFileExists($_ENV["SHAPE_URL"]."/Documents/{$type}_{$_ENV["FRONTEND_BUNDLE"]}.xlsx")){
            $fileUrl = $_ENV["SHAPE_URL"]."/Documents/{$type}_{$_ENV["FRONTEND_BUNDLE"]}.xlsx";
        }

        return new JsonResponse(array('error' => false, 'filepath' => $fileUrl));
    }

    /**
     * @Route("/{type}/list/import_xls_template", name="import_xls_template")
     * @Method("POST")
     */
    public function importXlsTemplate(Request $request, $type)
    {
        $this->initialize();

        $attributes = $this->entityManager->getAttributesOfEntityType($type, false);

        try{
            $filepath = $this->excelManager->importTemplate($attributes, $type);
        }
        catch (\Exception $e){
            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }


        return new JsonResponse(array('error' => false, 'filepath' => $filepath));
    }
}
