<?php

// php -d memory_limit=-1 bin/console crmhelper:run unlock_quotes //todo 15min
// php -d memory_limit=-1 bin/console crmhelper:run apply_discount_rules //todo 1min
// php -d memory_limit=-1 bin/console crmhelper:run recalculate_discount_rules //todo 5min
// php -d memory_limit=-1 bin/console crmhelper:run apply_product_label_rules //todo 1min
// php -d memory_limit=-1 bin/console crmhelper:run recalculate_product_label_rules //todo 5min
// php -d memory_limit=-1 bin/console crmhelper:run sync_exchange_rates //todo u 2 ujutro

// php bin/console crmhelper:run create_invoice_from_order 294
// php bin/console crmhelper:run fiscalize_invoice 111
// php bin/console crmhelper:run sync_currencies
// php bin/console crmhelper:run sync_exchange_rates
// php bin/console crmhelper:run apply_discount_rules
// php bin/console crmhelper:run recalculate_discount_rules 611683,611684,611685,611686,611687,611688,611689
// php bin/console crmhelper:run apply_product_label_rules
// php bin/console crmhelper:run recalculate_product_label_rules 326368
// php bin/console crmhelper:run generate_payment_slip 372776
// php bin/console crmhelper:run contact_anonymize bane@liberte.co.rs
// php bin/console crmhelper:run contact_generate_user_account bane@liberte.co.rs
// php bin/console crmhelper:run contact_remove_from_newsletter bane@liberte.co.rs
// php bin/console crmhelper:run set_seo_data product name meta_description null false
// php bin/console crmhelper:run set_seo_data product name meta_title null false
// php bin/console crmhelper:run apply_bulk_prices
// php bin/console crmhelper:run recalculate_bulk_prices
// php bin/console crmhelper:run recalculate_margin_rules
// php bin/console crmhelper:run remove_old_discounts_on_products
// php bin/console crmhelper:run apply_loyalty_earnings
// php bin/console crmhelper:run recalculate_loyalty_earnings
// php bin/console crmhelper:run automatic_gdpr_decline
// php bin/console crmhelper:run refresh_active_saleable
// php bin/console crmhelper:run after_order_return_created 11
// php bin/console crmhelper:run import_files product_images product code ord,selected,alt
#Primjer sa custom managerom
// php bin/console crmhelper:run import_files product_document product code '' some_product_helper_manager#prepareDocumentImportEnergetska
// php bin/console crmhelper:run generate_simple_product_import_template 1117
// php bin/console crmhelper:run generate_configurable_product_import_template
// php bin/console crmhelper:run import_simple_products
// php bin/console crmhelper:run import_configurable_products
// php bin/console crmhelper:run import_accounts_contacts_users
// php bin/console crmhelper:run rebuild_configurable_products
// php bin/console crmhelper:run apply_discount_cart_rules
// php bin/console crmhelper:run campaign_ended
// php bin/console crmhelper:run transfer_to_eur 1
// php bin/console crmhelper:run deactivate_expired_coupons

namespace CrmBusinessBundle\Command;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use CrmBusinessBundle\Entity\InvoiceEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\BarcodeManager;
use CrmBusinessBundle\Managers\BulkPriceManager;
use CrmBusinessBundle\Managers\CampaignManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\DefaultImportManager;
use CrmBusinessBundle\Managers\DiscountCouponManager;
use CrmBusinessBundle\Managers\DiscountRulesManager;
use CrmBusinessBundle\Managers\FiscalInvoiceManager;
use CrmBusinessBundle\Managers\HnbApiManager;
use CrmBusinessBundle\Managers\InvoiceManager;
use CrmBusinessBundle\Managers\LoyaltyManager;
use CrmBusinessBundle\Managers\MarginRulesManager;
use CrmBusinessBundle\Managers\NewsletterManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\OrderReturnManager;
use CrmBusinessBundle\Managers\ProductAttributeFilterRulesManager;
use CrmBusinessBundle\Managers\ProductLabelRulesManager;
use CrmBusinessBundle\Managers\ProductManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Entity\OrderReturnEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;

class CrmHelperCommand extends ContainerAwareCommand
{
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var ProductManager $productManager */
    protected $productManager;
    /** @var DiscountCouponManager $discountCouponManager */
    protected $discountCouponManager;

    protected function configure()
    {
        $this->setName("crmhelper:run")
            ->setDescription("Helper functions")
            ->addArgument("type", InputArgument::OPTIONAL, " which function ")
            ->addArgument("arg1", InputArgument::OPTIONAL, " which export ")
            ->addArgument("arg2", InputArgument::OPTIONAL, " which export ")
            ->addArgument("arg3", InputArgument::OPTIONAL, " which export ")
            ->addArgument("arg4", InputArgument::OPTIONAL, " which export ")
            ->addArgument("arg5", InputArgument::OPTIONAL, " which export ");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
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

        $func = $input->getArgument("type");
        if ($func == "automatic_gdpr_decline") {

            if (empty($this->accountManager)) {
                $this->accountManager = $this->getContainer()->get("account_manager");
            }

            $this->accountManager->automaticGdprDecline();

        } else if ($func == "after_order_return_created") {

            /** @var OrderReturnManager $orderReturnManager */
            $orderReturnManager = $this->getContainer()->get("order_return_manager");

            $orderId = $input->getArgument("arg1");

            /** @var OrderReturnEntity $orderReturn */
            $orderReturn = $orderReturnManager->getOrderReturnById($orderId);
            if (empty($orderReturn)) {
                throw new \Exception("Entity not found");
            }

            /** @var DefaultCrmProcessManager $crmProcessManager */
            $crmProcessManager = $this->getContainer()->get("crm_process_manager");
            $crmProcessManager->afterOrderReturnCreated($orderReturn);

        } else if ($func == "fiscalize_invoice") {

            $invoiceId = $input->getArgument("arg1");

            /** @var InvoiceManager $manager */
            $manager = $this->getContainer()->get("invoice_manager");

            /** @var InvoiceEntity $invoice */
            $invoice = $manager->getInvoiceById($invoiceId);
            if (empty($invoice)) {
                throw new \Exception("Entity not found");
            }

            /** @var FiscalInvoiceManager $fiscalInvoiceManager */
            $fiscalInvoiceManager = $this->getContainer()->get("fiscal_invoice_manager");
            $fiscalInvoiceManager->fiscalizeInvoice($invoice);

        } else if ($func == "sync_currencies") {

            /** @var HnbApiManager $hnbApiManager */
            $hnbApiManager = $this->getContainer()->get("hnb_api_manager");
            $hnbApiManager->syncCurrencies();

        } else if ($func == "sync_exchange_rates") {

            /** @var HnbApiManager $hnbApiManager */
            $hnbApiManager = $this->getContainer()->get("hnb_api_manager");
            $hnbApiManager->syncExchangeRates();

        } else if ($func == "apply_discount_rules") {

            /** @var DiscountRulesManager $discountRulesManager */
            $discountRulesManager = $this->getContainer()->get("discount_rules_manager");
            $discountRulesManager->applyDiscountRules();

        } else if ($func == "recalculate_discount_rules") {

            /** @var DiscountRulesManager $discountRulesManager */
            $discountRulesManager = $this->getContainer()->get("discount_rules_manager");
            if ($discountRulesManager->checkIfDiscountRulesNeedRecalculating()) {
                $discountRulesManager->recalculateDiscountRules();
            }

        } else if ($func == "apply_product_label_rules") {

            /** @var ProductLabelRulesManager $productLabelRulesManager */
            $productLabelRulesManager = $this->getContainer()->get("product_label_rules_manager");
            $productLabelRulesManager->applyProductLabels();


        } else if ($func == "apply_discount_cart_rules") {

            /** @var ProductAttributeFilterRulesManager $productAttributeFilterRulesManager */
            $productAttributeFilterRulesManager = $this->getContainer()->get("product_attribute_filter_rules_manager");
            $productAttributeFilterRulesManager->applyRuleEntities("discount_cart_rule");
        } else if ($func == "recalculate_product_label_rules") {

            $productIds = $input->getArgument("arg1");

            $data = array();
            if (!empty($productIds)) {
                $data["product_ids"] = explode(",", $productIds);
            }

            /** @var ProductLabelRulesManager $productLabelRulesManager */
            $productLabelRulesManager = $this->getContainer()->get("product_label_rules_manager");
            if ($productLabelRulesManager->checkIfProductLabelRulesNeedRecalculating()) {
                $productLabelRulesManager->recalculateProductLabelRules($data);
            }

        } else if ($func == "remove_old_discounts_on_products") {

            /** @var DefaultCrmProcessManager $crmProcessManager */
            $crmProcessManager = $this->getContainer()->get("crm_process_manager");
            $crmProcessManager->removeOldDiscountOnProducts();

        } else if ($func == "apply_bulk_prices") {

            /** @var BulkPriceManager $bulkPriceManager */
            $bulkPriceManager = $this->getContainer()->get("bulk_price_manager");
            $bulkPriceManager->applyBulkPrices();

        } else if ($func == "recalculate_bulk_prices") {

            /** @var BulkPriceManager $bulkPriceManager */
            $bulkPriceManager = $this->getContainer()->get("bulk_price_manager");
            $bulkPriceManager->recalculateBulkPriceRules();

        } else if ($func == "apply_loyalty_earnings") {

            /** @var LoyaltyManager $loyaltyManager */
            $loyaltyManager = $this->getContainer()->get("loyalty_manager");
            $loyaltyManager->applyLoyaltyEarningsConfiguration();

        } else if ($func == "recalculate_loyalty_earnings") {

            /** @var LoyaltyManager $loyaltyManager */
            $loyaltyManager = $this->getContainer()->get("loyalty_manager");
            $loyaltyManager->recalculateLoyaltyEarningsConfiguration();

        } else if ($func == "unlock_quotes") {

            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
            }

            $this->crmProcessManager->unlockQuotes();

            return true;
        } else if ($func == "generate_payment_slip") {

            $orderId = $input->getArgument('arg1');

            /** @var OrderManager $orderManager */
            $orderManager = $this->getContainer()->get("order_manager");

            $order = $orderManager->getOrderById($orderId);
            if (empty($order)) {
                throw new \Exception("Entity not found");
            }

            /** @var BarcodeManager $barcodeManager */
            $barcodeManager = $this->getContainer()->get("barcode_manager");

            $barcodeManager->generatePDF417Barcode($order);

            return true;
        } else if ($func == "set_seo_data") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing entity type code");
            }

            $arg2 = $input->getArgument("arg2");
            if (empty($arg2)) {
                throw new \Exception("Missing from attribute code");
            }

            $arg3 = $input->getArgument("arg3");
            if (empty($arg3)) {
                throw new \Exception("Missing to attribute code");
            }

            $arg4 = $input->getArgument("arg4");
            if ($arg4 == "null") {
                $arg4 = null;
            }
            if (!empty($arg4)) {
                $arg4 = explode(";", $arg4);
            }

            $arg5 = $input->getArgument("arg5");
            if ($arg5 == "null" || $arg5 == "false") {
                $arg5 = null;
            }

            /** @var ProductManager $productManager */
            $productManager = $this->getContainer()->get("product_manager");
            $productManager->setDefaultSeoData($arg1, $arg2, $arg3, $arg4, $arg5);

        } else if ($func == "contact_anonymize") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing email");
            }

            /** @var AccountManager $accountManager */
            $accountManager = $this->getContainer()->get("account_manager");

            /** @var ContactEntity $contact */
            $contact = $accountManager->getContactByEmail($arg1);
            if (empty($contact)) {
                throw new \Exception("Contact not found");
            }

            $accountManager->gdprAnonymize($contact);

        } else if ($func == "contact_remove_from_newsletter") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing email");
            }

            /** @var AccountManager $accountManager */
            $accountManager = $this->getContainer()->get("account_manager");

            /** @var ContactEntity $contact */
            $contact = $accountManager->getContactByEmail($arg1);
            if (empty($contact)) {
                throw new \Exception("Contact not found");
            }

            /** @var NewsletterManager $newsletterManager */
            $newsletterManager = $this->getContainer()->get("newsletter_manager");
            $newsletterManager->removeContactFromNewsletter($contact);

        } else if ($func == "contact_generate_user_account") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing email");
            }

            /** @var AccountManager $accountManager */
            $accountManager = $this->getContainer()->get("account_manager");

            /** @var ContactEntity $contact */
            $contact = $accountManager->getContactByEmail($arg1);
            if (empty($contact)) {
                throw new \Exception("Contact not found");
            }

            $accountManager->createUserForContact($contact);

        } else if ($func == "recalculate_margin_rules") {

            /** @var MarginRulesManager $marginRulesManager */
            $marginRulesManager = $this->getContainer()->get("margin_rules_manager");

            if ($marginRulesManager->checkIfMarginRulesNeedRecalculating()) {
                $marginRulesManager->recalculateMarginRules();
            }
        } else if ($func == "campaign_ended") {

            /** @var CampaignManager $campaignManager */
            $campaignManager = $this->getContainer()->get("campaign_manager");

            $campaignManager->campaignEnded();

        } else if ($func == "import_files") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing file entity type");
            }

            $arg2 = $input->getArgument("arg2");
            if (empty($arg2)) {
                throw new \Exception("Missing base entity type");
            }

            $arg3 = $input->getArgument("arg3");
            if (empty($arg3)) {
                throw new \Exception("Missing unique entity attribute");
            }

            $arg4 = $input->getArgument("arg4");
            if (empty($arg4)) {
                $arg4 = null;
                if ($arg1 == "product_images") {
                    $arg4 = "ord";
                }
            }

            $arg5 = $input->getArgument("arg5");

            /** @var DefaultImportManager $defaultImportManager */
            $defaultImportManager = $this->getContainer()->get("default_import_manager");

            /** @var ErrorLogManager $errorLogManager */
            $errorLogManager = $this->getContainer()->get("error_log_manager");

            try {
                $ret = $defaultImportManager->importFiles($arg1, $arg2, $arg3, $arg4, $arg5);

                if (!empty($ret) && (!isset($ret[0]["error"]) || $ret[0]["error"] == true)) {
                    $errorLogManager->logErrorEvent("Import files error", json_encode($ret), true);
                }
            } catch (\Exception $e) {
                $errorLogManager->logExceptionEvent("Import files exception", $e, true);
            }

            return true;
        } else if ($func == "generate_simple_product_import_template") {

            $arg1 = $input->getArgument("arg1");
            if (!empty($arg1)) {
                $arg1 = explode(",", $arg1);
            }

            /** @var DefaultImportManager $defaultImportManager */
            $defaultImportManager = $this->getContainer()->get("default_import_manager");

            $ret = $defaultImportManager->generateSimpleProductAttributesImportTemplate($arg1);
            dump($ret);

        } else if ($func == "generate_configurable_product_import_template") {

            /** @var DefaultImportManager $defaultImportManager */
            $defaultImportManager = $this->getContainer()->get("default_import_manager");
            $ret = $defaultImportManager->generateConfigurableProductImportTemplate();
            dump($ret);

        } else if ($func == "import_simple_products") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing file path");
            }

            /** @var DefaultImportManager $defaultImportManager */
            $defaultImportManager = $this->getContainer()->get("default_import_manager");
            $ret = $defaultImportManager->importSimpleProducts($arg1);
            dump($ret);

        } else if ($func == "import_configurable_products") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing file path");
            }

            /** @var DefaultImportManager $defaultImportManager */
            $defaultImportManager = $this->getContainer()->get("default_import_manager");
            $ret = $defaultImportManager->importConfigurableProducts($arg1);
            dump($ret);

        } else if ($func == "import_accounts_contacts_users") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing file path");
            }

            /** @var DefaultImportManager $defaultImportManager */
            $defaultImportManager = $this->getContainer()->get("default_import_manager");
            $ret = $defaultImportManager->importAccountsContactsUsers($arg1);
            dump($ret);

        } else if ($func == "refresh_active_saleable") {

            /** @var DefaultCrmProcessManager $crmProcessManager */
            $crmProcessManager = $this->getContainer()->get("crm_process_manager");
            $crmProcessManager->refreshActiveSaleable();

        } else if ($func == "rebuild_configurable_products") {

            $this->productManager = $this->getContainer()->get("product_manager");
            $this->productManager->rebuildConfigurableProducts();

        } else if ($func == "transfer_to_eur") {

            $now = new \DateTime();
            if ($_ENV["IS_PRODUCTION"] && $now->format("Y") < 2023) {
                return true;
            } elseif (!$_ENV["IS_PRODUCTION"] && $now->format("Y") < 2023) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion('It is still not 2023. Are you sure you want to run? [y] ', true);

                if (!$helper->ask($input, $output, $question)) {
                    return;
                }
            }

            $arg1 = $input->getArgument("arg1");

            $crmProcessManager = $this->getContainer()->get("crm_process_manager");
            $crmProcessManager->transferToEur($arg1);

            /**
             * Rebuild triggers
             */
            print "Started building triggers type\r\n";
            $command = $this->getApplication()->find('db_update:helper');

            $arguments = [
                'type' => 'rebuild_procedures_views',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
        } else if ($func == "deactivate_expired_coupons") {

            if (empty($this->discountCouponManager)) {
                $this->discountCouponManager = $this->getContainer()->get("discount_coupon_manager");
            }

            $now = new \DateTime("now");

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter("isTemplate", "eq", 0));
            $compositeFilter->addFilter(new SearchFilter("dateValidTo", "lt", $now->format("Y-m-d H:i:s")));

            $expiredCoupons = $this->discountCouponManager->getFilteredDiscountCoupons($compositeFilter);
            if (EntityHelper::isCountable($expiredCoupons) && count($expiredCoupons) > 0) {
                /** @var DiscountCouponEntity $coupon */
                foreach($expiredCoupons as $coupon){
                    $this->discountCouponManager->deactivateCoupon($coupon);
                }
            }

            return true;
        } else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }

        return false;
    }
}
