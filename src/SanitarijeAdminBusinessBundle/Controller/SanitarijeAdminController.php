<?php

namespace SanitarijeAdminBusinessBundle\Controller;

use AppBundle\Managers\ErrorLogManager;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Managers\ProductManager;
use SanitarijeBusinessBundle\Managers\SanitarijeHelperManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class SanitarijeAdminController extends AbstractScommerceController
{
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;
    /** @var ProductManager $productManager */
    protected $productManager;
    /** @var SanitarijeHelperManager $sanitarijeHelperManager */
    protected $sanitarijeHelperManager;

    protected function initialize($request = null)
    {
        parent::initialize();
    }

    /**
     * @Route("/sync/sync_product", name="sync_product")
     * @Method("POST")
     */
    public function syncProduct(Request $request)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        $p = $_POST;
        $p = array_map('trim', $p);

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product id is empty')));
        }

        if(empty($this->productManager)){
            $this->productManager = $this->getContainer()->get("product_manager");
        }

        /** @var ProductEntity $product */
        $product = $this->productManager->getProductById($p["id"]);
        if (empty($product)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product is empty')));
        }

        if(empty($this->sanitarijeHelperManager)){
            $this->sanitarijeHelperManager = $this->getContainer()->get("sanitarije_helper_manager");
        }

        try {
            $this->sanitarijeHelperManager->updateProductData($product);
        }
        catch (\Exception $e){
            $ret["error"] = false;
            $ret["title"] = $this->translator->trans("Error");
            $ret["message"] = $e->getMessage();

            return new JsonResponse($ret);
        }

        $ret["error"] = false;
        $ret["title"] = $this->translator->trans("Success");
        $ret["message"] = $this->translator->trans("Product synced");

        return new JsonResponse($ret);
    }
}
