<?php

namespace ScommerceBusinessBundle\Controller;

use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\ProductManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;

class PriceController extends AbstractScommerceController
{
    protected $crmProcessManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var ProductManager $productManager */
    protected $productManager;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->initializeTwigVariables($request);
    }

    /**
     * @param ProductEntity $product
     * @param array $options
     * @return JsonResponse
     */
    public function getProductPricesAction(Request $request, ProductEntity $product, $options = array(), ProductEntity $parentProduct = null)
    {
        $this->initialize($request);

        $ret = array();

        /** @var Session $session */
        $session = $this->getContainer()->get("session");

        /** @var AccountEntity $account */
        $account = null;
        if (!empty($session->get("account"))) {
            $account = $session->get("account");
        }

        /**
         * PRODUCT_TYPE_CONFIGURABLE_BUNDLE
         */
        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE) {

            if (empty($this->quoteManager)) {
                $this->quoteManager = $this->getContainer()->get("quote_manager");
            }

            $productIds = array();
            //TODO POTENCIJALNO I OVDJE FALI
            /**
             * IF OPTIONS ARE SENT, USE OPTIONS AS SELECTED PRODUCTS
             */
            if (isset($options["configurable_bundle"]) && !empty($options["configurable_bundle"])) {
                $options["configurable_bundle"] = json_decode($options["configurable_bundle"], true);
                $productIds = array_column($options["configurable_bundle"], "product_id");
                $productIds = array_filter($productIds);
            } /**
             * IF OPTIONS ARE EMPTY USE DEFAULTS
             */
            else {
                if (empty($this->productManager)) {
                    $this->productManager = $this->getContainer()->get("product_manager");
                }

                $configurableBundleProductDetails = $this->productManager->getConfigurableBundleProductDetails($product);

                if (!empty($configurableBundleProductDetails)) {
                    foreach ($configurableBundleProductDetails as $configurableBundleProductDetail) {
                        if (!empty($configurableBundleProductDetail["default"])) {
                            $productIds[] = $configurableBundleProductDetail["default"]->getId();
                        }
                    }
                }
            }

            $ret = $this->quoteManager->getConfigurableBundleProductPrices($product, $account, $productIds);
        } /**
         *  PRODUCT_TYPE_CONFIGURABLE
         */
        elseif ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
            }

            $baseProduct = $product;

            /**
             * IF OPTIONS ARE SENT, USE OPTIONS AS SELECTED PRODUCTS
             */
            //THIS IS NOT USED HERE

            /**
             * IF NO OPTIONS ARE NOT SENT USE DEFAULT
             */
            if (empty($options)) {
                if (empty($this->productManager)) {
                    $this->productManager = $this->getContainer()->get("product_manager");
                }

                $configurableProductDetails = $this->productManager->getConfigurableProductDetails($product);

                if (empty($configurableProductDetails["default_product"])) {
                    $this->logger->error("DEFAULT PRODUCT missing: " . $product->getId());
                } else {
                    $product = $configurableProductDetails["default_product"];
                }
            }

            $ret = $this->crmProcessManager->getProductPrices($product, $account, $parentProduct);
        } /**
         *  PRODUCT_TYPE_BUNDLE
         */
        elseif ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_BUNDLE) {
            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
            }

            $ret = $this->crmProcessManager->getProductPrices($product, $account, $parentProduct);
        } else {
            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
            }

            $ret = $this->crmProcessManager->getProductPrices($product, $account, $parentProduct);
        }

        return new JsonResponse($ret);
    }
}
