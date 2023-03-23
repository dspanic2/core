<?php

namespace ScommerceBusinessBundle\Controller;

use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Managers\ProductManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class ProductController extends AbstractScommerceController
{
    protected function initialize($request = null)
    {
        parent::initialize();
        $this->initializeTwigVariables($request);
    }

    /**
     * @Route("/product/get_product_details", name="product_details")
     * @Method("POST")
     */
    public function getProductDetailsAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (!isset($p["pid"]) || empty($p["pid"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product is missing")));
        }
        if (!isset($p["configurable"])) {
            $p["configurable"] = array();
        }
        if (!isset($p["configurable_bundle"])) {
            $p["configurable_bundle"] = array();
        }

        /** @var ProductManager $productManager */
        $productManager = $this->getContainer()->get("product_manager");

        /** @var ProductEntity $product */
        $product = $productManager->getProductById($p["pid"]);

        if (empty($product)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product not found")));
        }

        /** @var DefaultScommerceManager $scommerceManager */
        $scommerceManager = $this->getContainer()->get("scommerce_manager");
        $ret = $scommerceManager->replaceProductDetails($product, $p);

        return new JsonResponse($ret);
    }

    /**
     * @Route("/product/get_product_configurable_modal", name="product_configurable_modal")
     * @Method("POST")
     */
    public function getProductConfigurableModalAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (!isset($p["pid"]) || empty($p["pid"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product is missing")));
        }

        /** @var ProductManager $productManager */
        $productManager = $this->container->get("product_manager");

        /** @var ProductEntity $product */
        $product = $productManager->getProductById($p["pid"]);

        if (empty($product)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product not found")));
        }

        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE) {
            $modalHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product/ListItem/Modals:configurable_bundle_modal.html.twig"), ['product' => $product]);
            return new JsonResponse(array('error' => false, 'modal_html' => $modalHtml));
        } elseif ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
            $modalHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product/ListItem/Modals:configurable_modal.html.twig"), ['product' => $product]);
            return new JsonResponse(array('error' => false, 'modal_html' => $modalHtml));
        } else {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product type unknown")));
        }
    }

    /**
     * @Route("/product/get_bundle_saving_prices", name="product_bundle_saving_prices")
     * @Method("POST")
     */
    public function getBundleSavingPricesAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (!isset($p["pid"]) || empty($p["pid"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product is missing")));
        }

        /** @var ProductManager $productManager */
        $productManager = $this->container->get("product_manager");

        /** @var ProductEntity $product */
        $product = $productManager->getProductById($p["pid"]);

        $modalHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:bundle_savings.html.twig"), [
            'parent_product' => $product,
            'include' => $p["include"] ?? [],
        ]);
        return new JsonResponse(array('error' => false, 'savings_html' => $modalHtml));
    }
}
