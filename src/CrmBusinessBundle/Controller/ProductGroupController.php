<?php

namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Managers\ProductManager;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ProductGroupController extends AbstractController
{

    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var FormManager $formManager */
    protected $formManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    /** @var ProductManager $productManager */
    protected $productManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->productManager = $this->getContainer()->get("product_manager");
    }

    protected function initializeForm($type)
    {
        $factoryManager = $this->getContainer()->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("/product_group/save", name="product_group_save_form")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $type = "product_group";

        $this->initializeForm($type);

        /**
         * Fallback na starije verzije gdje nema multilang
         */
        $hasMultilang = false;

        if(isset($_POST["show_on_store"])){
            $hasMultilang = true;
        }

        if(!isset($_POST["name"]) || empty($_POST["name"])){
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Name cannot be empty')));
        }

        if($hasMultilang){
            $_POST["name"] = array_map('trim', $_POST["name"]);
            $_POST["name"] = array_filter($_POST["name"]);
            if(empty($_POST["name"])){
                return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Name cannot be empty')));
            }
            if(empty($_POST["show_on_store_checkbox"])){
                return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Please add at least one store')));
            }
        }

        /** @var ProductEntity $entity */
        $entity = $this->formManager->saveFormModel($type, $_POST);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('There has been an error')));
        }

        if($hasMultilang){
            $this->entityManager->refreshEntity($entity);

            if(empty($this->routeManager)){
                $this->routeManager = $this->container->get("route_manager");
            }
            $this->routeManager->insertUpdateDefaultLanguages($entity,$_POST["id"]);
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Form has been submitted'), 'entity' => $this->entityManager->entityToArray($entity)));
    }

    /**
     * @Route("/product_group/mass_add_products_to_product_group", name="mass_add_products_to_product_group")
     * @Method("POST")
     */
    public function massAddProductsToProductGroupAction(Request $request)
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

        if(!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product group is not defined')));
        }

        if(empty($this->productGroupManager)){
            $this->productGroupManager = $this->getContainer()->get("product_group_manager");
        }

        /** @var ProductGroupManager $productGroup */
        $productGroup = $this->productGroupManager->getProductGroupById($p["parent_entity_id"]);

        if(empty($productGroup)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product group does not exist')));
        }

        $this->productGroupManager->createProductProductGroups($productGroup,$p["items"][$keys[0]]);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Products successfully added to product group')));
    }

    /**
     * @Route("/product_group/add_product_to_product_group", name="add_product_to_product_group")
     * @Method("POST")
     */
    public function addProductToProductGroupAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if(!isset($p["id"]) || empty($p["id"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Related product id is not defined')));
        }

        if(!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent product is not defined')));
        }

        if(empty($this->productGroupManager)){
            $this->productGroupManager = $this->getContainer()->get("product_group_manager");
        }

        /** @var ProductGroupManager $productGroup */
        $productGroup = $this->productGroupManager->getProductGroupById($p["parent_entity_id"]);

        if(empty($productGroup)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product group does not exist')));
        }

        $this->productGroupManager->createProductProductGroups($productGroup,Array($p["id"]));

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product successfully added to product group')));
    }

    /**
     * @Route("/product_group/mass_delete_products_from_product_group", name="mass_delete_products_from_product_group")
     * @Method("POST")
     */
    public function massDeleteProductsFromProductGroupAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if(!isset($p["items"]) || empty($p["items"]) || !isset($p["items"]["product_product_group_link"]) || empty($p["items"]["product_product_group_link"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        if(empty($this->productGroupManager)){
            $this->productGroupManager = $this->getContainer()->get("product_group_manager");
        }

        $this->productGroupManager->deleteProductProductGroupByIds($p["items"]["product_product_group_link"]);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Products successfully removed to product group')));
    }

    /**
     * @Route("/product_group/delete_product_from_product_group", name="delete_product_from_product_group")
     * @Method("POST")
     */
    public function deleteProductFromProductGroupAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if(!isset($p["id"]) || empty($p["id"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation id is not defined')));
        }

        if(empty($this->productGroupManager)){
            $this->productGroupManager = $this->getContainer()->get("product_group_manager");
        }

        $this->productGroupManager->deleteProductProductGroupByIds(Array($p["id"]));

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Product successfully removed to product group')));
    }


    /**
     * @Route("/product_group/product_group_export_default", name="product_group_export_default")
     * @Method("POST")
     */
    public function productGroupExportDefault(Request $request)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        $p = $_POST;

        if(empty($this->listViewManager)){
            $this->listViewManager = $this->getContainer()->get("list_view_manager");
        }

        $productGroups = $this->listViewManager->getListViewDataModelEntities($p["list_view_id"],$p);

        if(empty($this->defaultImportManager)){
            $this->defaultImportManager = $this->getContainer()->get("default_import_manager");
        }

        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $attributes = $this->crmProcessManager->getProductGroupExportDefaultAttributes();

        try{
            $xlsPath = $this->defaultImportManager->generateSimpleEntityImportTemplate($productGroups,"product_group",null,$attributes);
        }
        catch (\Exception $e){

            if(empty($this->errorLogManager)){
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $this->errorLogManager->logExceptionEvent("productGroupExportDefault",$e,true);

            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }

        $fullPath = $_ENV["SSL"]."://".$_ENV["BACKEND_URL"].$xlsPath;

        return new JsonResponse(array("error" => false, "file" => $fullPath, "message" => $this->translator->trans("Export generated")));
    }

    /**
     * @Route("/product_group/product_group_product_export_default", name="product_group_product_export_default")
     * @Method("POST")
     */
    public function productGroupProductExportDefault(Request $request)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        $p = $_POST;

        if(!isset($p["id"]) || empty($p["id"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation id is not defined')));
        }

        if(empty($this->productGroupManager)){
            $this->productGroupManager = $this->getContainer()->get("product_group_manager");
        }

        /** @var ProductGroupEntity $productGroup */
        $productGroup = $this->productGroupManager->getProductGroupById($p["id"]);

        if(empty($productGroup)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product group does not exist')));
        }

        $products = $productGroup->getProductGroupProducts();

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

            $this->errorLogManager->logExceptionEvent("productGroupProductExportDefault",$e,true);

            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }

        $fullPath = $_ENV["SSL"]."://".$_ENV["BACKEND_URL"].$xlsPath;

        return new JsonResponse(array("error" => false, "file" => $fullPath, "message" => $this->translator->trans("Export generated")));
    }

    /**
     * @Route("/product_group/product_group_product_export_attributes_default", name="product_group_product_export_attributes_default")
     * @Method("POST")
     */
    public function productGroupProductExportAttributesDefault(Request $request)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        $p = $_POST;

        if(!isset($p["id"]) || empty($p["id"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Relation id is not defined')));
        }

        if(empty($this->productGroupManager)){
            $this->productGroupManager = $this->getContainer()->get("product_group_manager");
        }

        /** @var ProductGroupEntity $productGroup */
        $productGroup = $this->productGroupManager->getProductGroupById($p["id"]);

        if(empty($productGroup)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product group does not exist')));
        }

        $products = $productGroup->getProductGroupProducts();

        $productIds = Array();
        if(EntityHelper::isCountable($products) && count($products)){
            /** @var ProductEntity $product */
            foreach ($products as $product){
                $productIds[] = $product->getId();
            }
        }

        if(empty($this->defaultImportManager)){
            $this->defaultImportManager = $this->getContainer()->get("default_import_manager");
        }

        try{
            $xlsPath = $this->defaultImportManager->generateSimpleProductAttributesImportTemplate($productIds,null);
        }
        catch (\Exception $e){

            if(empty($this->errorLogManager)){
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $this->errorLogManager->logExceptionEvent("productGroupProductExportAttributesDefault",$e,true);

            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }

        $fullPath = $_ENV["SSL"]."://".$_ENV["BACKEND_URL"].$xlsPath;

        return new JsonResponse(array("error" => false, "file" => $fullPath, "message" => $this->translator->trans("Export generated")));
    }
}
