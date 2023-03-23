<?php

// php bin/console scommercehelper:function create_order 287392
// php bin/console scommercehelper:function test_order_email 376673
// php bin/console scommercehelper:function test_register_email
// php bin/console scommercehelper:function generate_sitemap
// php bin/console scommercehelper:function set_product_sort_prices 598166
// php bin/console scommercehelper:function clean_unused_front_blocks
// php bin/console scommercehelper:function set_product_group_levels
// php bin/console scommercehelper:function set_number_of_products_in_product_groups
// php bin/console scommercehelper:function sync_brands
// php bin/console scommercehelper:function sync_brands_products
// php bin/console scommercehelper:function assign_parent_product_groups
// php bin/console scommercehelper:function automatically_fix_rutes
// php bin/console scommercehelper:function assign_promotion_group_to_products_on_discount
// php bin/console scommercehelper:function populate_store_attribute_value product name 5

// php bin/console scommercehelper:function product_export_rule_type export_jeftinije_manager

##ZA SLANJE NARUDŽBI

// php bin/console scommercehelper:function send_order
// php bin/console scommercehelper:function send_order_email 2194
// php bin/console scommercehelper:function cache_warmup
// php bin/console scommercehelper:function invalidate_cache
// php bin/console scommercehelper:function generate_core_product_export 3 15893
// php bin/console scommercehelper:function check_and_delete_duplicate_product_attribute_links 1(delete)|0(return duplicate list)

namespace ScommerceBusinessBundle\Command;

use AppBundle\Managers\CacheManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\ProductExportRulesManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Managers\BrandsManager;
use ScommerceBusinessBundle\Managers\ExportCoreManager;
use ScommerceBusinessBundle\Managers\ExportGoogleManager;
use ScommerceBusinessBundle\Managers\ExportGoogleXmlManager;
use ScommerceBusinessBundle\Managers\ExportJeftinijeManager;
use ScommerceBusinessBundle\Managers\ExportNabavaManager;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\ScommerceHelperManager;
use ScommerceBusinessBundle\Managers\SitemapManager;
use ScommerceBusinessBundle\Managers\SproductManager;
use ScommerceBusinessBundle\Managers\TestManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class ScommerceHelperCommand extends ContainerAwareCommand
{
    /** @var ProductExportRulesManager $productExportRulesManager */
    protected $productExportRulesManager;
    /** @var ExportCoreManager $exportCoreManager */
    protected $exportCoreManager;

    protected function configure()
    {
        $this->setName('scommercehelper:function')
            ->setDescription('Helper functions')
            ->addArgument('type', InputArgument::OPTIONAL, ' which function ')
            ->addArgument('arg1', InputArgument::OPTIONAL, ' arg1')
            ->addArgument('arg2', InputArgument::OPTIONAL, ' arg2')
            ->addArgument('arg3', InputArgument::OPTIONAL, ' arg3');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return false
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * Start new session for import
         */
        $request = new Request();
        if (!empty($request->getSession())) {
            $request->getSession()->invalidate();
        }

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");
        $helperManager->loginAnonymus($request, "system");
        /**
         * End start new session for import
         */

        $arg1 = $input->getArgument("arg1");
        $arg2 = $input->getArgument("arg2");

        $func = $input->getArgument("type");
        if ($func == "create_order") {

            /** @var QuoteManager $quoteManager */
            $quoteManager = $this->getContainer()->get("quote_manager");

            if (empty($arg1)) {
                throw new \Exception("Missing quote id");
            }

            $quote = $quoteManager->getQuoteById($arg1);
            if (empty($quote)) {
                throw new \Exception("Quote not found");
            }

            $quoteStatus = $quoteManager->getQuoteStatusById(3);

            /** Samo za test */
            $quoteManager->changeQuoteStatus($quote, $quoteStatus);

        }
        else if ($func == "check_and_delete_duplicate_product_attribute_links") {

            if(empty($this->sProductManager)){
                $this->sProductManager = $this->getContainer()->get("s_product_manager");
            }

            $this->sProductManager->checkForDuplicateProductAttributeLinks($arg1);

            return true;
        }
        else if ($func == "clean_product_images") {

            /** @var TestManager $testManager */
            $testManager = $this->getContainer()->get("test_manager");
            $testManager->cleanProductImages();

        } else if ($func == "sync_brands") {

            /** @var BrandsManager $brandManager */
            $brandManager = $this->getContainer()->get("brands_manager");
            $brandManager->syncBrandsWithSProductAttributeConfigurationOptions();

        } else if ($func == "sync_brands_products") {

            /** @var BrandsManager $brandManager */
            $brandManager = $this->getContainer()->get("brands_manager");
            $brandManager->syncBrandsOnProducts();

        } else if ($func == "send_order") {

            if (empty($arg1)) {
                throw new \Exception("Missing order id");
            }

            $debug = !empty($input->getArgument("arg2"));

            /** @var OrderManager $orderManager */
            $orderManager = $this->getContainer()->get("order_manager");

            $order = $orderManager->getOrderById($arg1);
            if (empty($order)) {
                throw new \Exception("Order not found");
            }

            /** @var DefaultCrmProcessManager $crmProcessManager */
            $crmProcessManager = $this->getContainer()->get("crm_process_manager");
            $crmProcessManager->sendOrderWrapper($order);

        } else if ($func == "send_order_email") {

            if (empty($arg1)) {
                throw new \Exception("Missing order id");
            }

            /** @var OrderManager $orderManager */
            $orderManager = $this->getContainer()->get("order_manager");

            $order = $orderManager->getOrderById($arg1);
            if (empty($order)) {
                throw new \Exception("Order not found");
            }

            /** @var DefaultCrmProcessManager $crmProcessManager */
            $crmProcessManager = $this->getContainer()->get("crm_process_manager");
            $crmProcessManager->afterOrderCreated($order);

        } else if ($func == "test_order_email") {

            if (empty($arg1)) {
                throw new \Exception("Missing order id");
            }

            /** @var OrderManager $orderManager */
            $orderManager = $this->getContainer()->get("order_manager");

            $order = $orderManager->getOrderById($arg1);
            if (empty($order)) {
                throw new \Exception("Order not found");
            }

            /** @var \ScommerceBusinessBundle\Managers\TestManager $testManager */
            $testManager = $this->getContainer()->get("test_manager");
            $testManager->testOrderEmail($order);

        } else if ($func == "test_register_email") {

            /** @var TestManager $testManager */
            $testManager = $this->getContainer()->get("test_manager");
            $testManager->testRegisterEmail();

        } else if ($func == "generate_sitemap") {

            /** @var SitemapManager $sitemapManager */
            $sitemapManager = $this->getContainer()->get("sitemap_manager");
            if ($sitemapManager->generateSitemapXML()) {
                echo "success\n";
            } else {
                echo "error\n";
            }

        } else if ($func == "product_export_rule_type") {

            $export_rule_type_code = $input->getArgument("arg1");
            if (empty($export_rule_type_code)) {
                throw new \Exception("Missing export rule type");
            }

            if(empty($this->productExportRulesManager)){
                $this->productExportRulesManager = $this->getContainer()->get("product_export_rules_manager");
            }

            $this->productExportRulesManager->runExportRulesByType($export_rule_type_code);

            return true;
        }
        else if ($func == "set_product_sort_prices") {

            $productIds = [];
            if (!empty($arg1)) {
                $productIds = explode(",", $arg1);
            }

            /** @var SproductManager $sProductManager */
            $sProductManager = $this->getContainer()->get("s_product_manager");
            $sProductManager->setProductSortPrices($productIds);

        } else if ($func == "set_product_group_levels") {

            /** @var ProductGroupManager $productGroupManager */
            $productGroupManager = $this->getContainer()->get("product_group_manager");
            $productGroupManager->setProductGroupLevels();

        } else if ($func == "set_number_of_products_in_product_groups") {

            /** @var ProductGroupManager $productGroupManager */
            $productGroupManager = $this->getContainer()->get("product_group_manager");
            $productGroupManager->setNumberOfProductsInProductGroups();

        } else if ($func == "clean_unused_front_blocks") {

            /** @var ScommerceHelperManager $sCommerceHelperManager */
            $sCommerceHelperManager = $this->getContainer()->get("scommerce_helper_manager");
            $sCommerceHelperManager->cleanUnusedFrontBlocks();

        } else if ($func == "assign_parent_product_groups") {

            /** @var ScommerceHelperManager $sCommerceHelperManager */
            $sCommerceHelperManager = $this->getContainer()->get("scommerce_helper_manager");
            $sCommerceHelperManager->assignParentProductGroups();

        } else if ($func == "automatically_fix_rutes") {

            /** @var RouteManager $routeManager */
            $routeManager = $this->getContainer()->get("route_manager");
            $routeManager->automaticallyFixRutes();

        } else if ($func == "assign_promotion_group_to_products_on_discount") {

            /** @var ProductGroupManager $productGroupManager */
            $productGroupManager = $this->getContainer()->get("product_group_manager");
            $productGroupManager->assignProductPromotionGroupToProductsOnDiscount();

        } else if ($func == "populate_store_attribute_value") {

            $entityTypeCode = $input->getArgument("arg1");
            if (empty($entityTypeCode)) {
                throw new \Exception("Missing entity type code parameter");
            }
            $attributeCode = $input->getArgument("arg2");
            if (empty($attributeCode)) {
                throw new \Exception("Missing entity type code parameter");
            }
            $newStoreId = $input->getArgument("arg3");
            if (empty($newStoreId)) {
                throw new \Exception("Missing new store ID parameter");
            }

            /** @var ScommerceHelperManager $scommerceHelperManager */
            $scommerceHelperManager = $this->getContainer()->get("scommerce_helper_manager");
            $scommerceHelperManager->populateEntityStoreAttributeValue($entityTypeCode, $attributeCode, $newStoreId);

        } else if ($func == "cache_warmup") {

            $request = new Request();
            $_SERVER['HTTP_USER_AGENT'] = "";

            $storage = new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage();
            $session = new \Symfony\Component\HttpFoundation\Session\Session($storage);
            $request->setSession($session);
            $this->getContainer()->get('request_stack')->push($request);

            /** @var ScommerceHelperManager $scommerceHelperManager */
            $scommerceHelperManager = $this->getContainer()->get("scommerce_helper_manager");
            $scommerceHelperManager->cacheWarmupListItems();

        } else if ($func == "invalidate_cache") {

            $tag = $input->getArgument("arg1");
            if (empty($tag)) {
                throw new \Exception("Missing cache tag parameter");
            }

            /** @var CacheManager $cacheManager */
            $cacheManager = $this->getContainer()->get("cache_manager");
            $cacheManager->invalidateCacheByTag($tag);

        } else if ($func == "generate_core_product_export") {

            if(!$_ENV["IS_PRODUCTION"]){
                return true;
            }

            if(empty($this->exportCoreManager)){
                $this->exportCoreManager = $this->getContainer()->get("export_core_manager");
            }

            if(empty($arg1)){
                throw new \Exception("Missing store id for export");
            }

            $this->exportCoreManager->generateExport($arg1,"core_export_{$arg1}",explode(",",$arg2));

            return true;
        }
        else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }

        return false;
    }
}
