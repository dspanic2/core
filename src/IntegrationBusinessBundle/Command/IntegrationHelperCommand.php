<?php

// php bin/console integration:run wand importDokumenti
// php bin/console integration:run wand importOsobe
// php bin/console integration:run wand importPlacanja
// php bin/console integration:run wand importOtprema

// php bin/console integration:run luceed_erp_import importProducts
// php bin/console integration:run luceed_erp_import importWarehouses
// php bin/console integration:run luceed_erp_import importWarehouseStock
// php bin/console integration:run luceed_erp_import importDiscounts
// php bin/console integration:run luceed_erp_import importProductGroups
// php bin/console integration:run luceed_erp_import importAccounts
// php bin/console integration:run luceed_erp_import getAccountByOib
// php bin/console integration:run luceed_erp_import importPrices
// php bin/console integration:run luceed_erp_import importSupplierWarehouseStock

// php bin/console integration:run luceed_erp getUserByUsername --username=500006
// php bin/console integration:run luceed_erp getUserByUid --uid=141859-3015
// php bin/console integration:run luceed_erp getPartner --uid=1378442-3015
// php bin/console integration:run luceed_erp getOrder --uid=330948-3015
// php bin/console integration:run luceed_erp getMpracData --uid=333386-3015
// php bin/console integration:run luceed_erp getInvoicePdfByUid --uid=565568-3015
// php bin/console integration:run luceed_erp getCityByPostalCode --postal_code=10000
// php bin/console integration:run luceed_erp getCityByCode --postal_code=web_8
// php bin/console integration:run luceed_erp createCity --postal_code=10000

// php bin/console integration:run horizont importCustomers
// php bin/console integration:run horizont importProducts
// php bin/console integration:run horizont sendOrder --id=1

// php bin/console integration:run mailchimp getLists
// php bin/console integration:run mailchimp getMembersList
// php bin/console integration:run mailchimp getSubscriberByEmail --email=hrvoje.rukavina@shipshape-solutions.com
// php bin/console integration:run mailchimp subscribeMember --email=hrvoje.rukavina@shipshape-solutions.com
// php bin/console integration:run mailchimp unsubscribeMember --email=hrvoje.rukavina@shipshape-solutions.com
// php bin/console integration:run mailchimp resubscribeMember --email=hrvoje.rukavina@shipshape-solutions.com
// php bin/console integration:run mailchimp getRecentlyUnsubscribedMembers
// php bin/console integration:run mailchimp getStoresList
// php bin/console integration:run mailchimp getProductsList
// php bin/console integration:run mailchimp syncProducts
// php bin/console integration:run mailchimp syncNewsletters

// php bin/console integration:run pevex_trs importProducts
// php bin/console integration:run pevex_trs importProducts --product_codes=000911,000913 --fast=1
// php bin/console integration:run pevex_trs importWarehouseStock --warehouse_code=0037 --minutes_history=90 --product_codes=000911,000913 --aktivan_za_web=DA
// php bin/console integration:run pevex_trs importWarehouseStock
// php bin/console integration:run pevex_trs importProductPrices --fast=1 --from_date=10.05.2022. --product_codes=000911,000913
// php bin/console integration:run pevex_trs importClassification
// php bin/console integration:run pevex_trs sendOrder --id=1
// php bin/console integration:run pevex_trs cancelOrder --id=1
// php bin/console integration:run pevex_trs getOrderById --id=1
// php bin/console integration:run pevex_trs getPartnerByOib 49864588217
// php bin/console integration:run pevex_trs getLoyaltyUsers
// php bin/console integration:run pevex_trs getLoyaltyPrices
// php bin/console integration:run pevex_trs importLoyaltyPrices
// php bin/console integration:run pevex_trs importLoyaltyUsers
// php bin/console integration:run pevex_trs sendLoyaltyUser

// php bin/console integration:run pro_erp importProducts
// php bin/console integration:run pro_erp importAccounts

// php bin/console integration:run squalomail getLists
// php bin/console integration:run squalomail getSubscriberByEmail --email=hrvoje.rukavina@shipshape-solutions.com

// php bin/console integration:run nubeprint getAuthKey
// php bin/console integration:run nubeprint getInventory
// php bin/console integration:run nubeprint importInventory
// php bin/console integration:run nubeprint getConsumableAlerts
// php bin/console integration:run nubeprint getTechnicalAlerts

// php bin/console integration:run princity getContracts
// php bin/console integration:run princity getInventory
// php bin/console integration:run princity importInventory
// php bin/console integration:run princity getSnmpAlerts
// php bin/console integration:run princity getSupplies
// php bin/console integration:run princity getOrders

// php bin/console integration:run whm getAvailableHostingPlans
// php bin/console integration:run whm getDiskUsage

// php bin/console integration:run city_import hrvatskaPosta 1
// php bin/console integration:run city_import postaSlovenije 1

// php bin/console integration:run minimax importProducts
// php bin/console integration:run minimax getCustomerByCode
// php bin/console integration:run minimax sendCustomer --id=318110
// php bin/console integration:run minimax sendOrder --id=318110

// php bin/console integration:run mailerlite getSortedSubscribers
// php bin/console integration:run mailerlite syncNewsletters

// php bin/console integration:run vasco sendOrder --id=1
// php bin/console integration:run mireo importVehicles
// php bin/console integration:run fitting_box importProductImages
// php bin/console integration:run greencell importProducts
// php bin/console integration:run gath importProducts

// php bin/console integration:run recommend send_recommend --id=dbc34fd9-150c-4b45-bafe-06c3ef0d9ffa


namespace IntegrationBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\OrderManager;
use IntegrationBusinessBundle\Managers\CityImportManager;
use IntegrationBusinessBundle\Managers\FittingBoxApiManager;
use IntegrationBusinessBundle\Managers\GathImportManager;
use IntegrationBusinessBundle\Managers\GreenCellImportManager;
use IntegrationBusinessBundle\Managers\HorizontImportManager;
use IntegrationBusinessBundle\Managers\LuceedErpApiManager;
use IntegrationBusinessBundle\Managers\LuceedErpImportManager;
use IntegrationBusinessBundle\Managers\MailchimpMarketingApiManager;
use IntegrationBusinessBundle\Managers\MailerLiteApiManager;
use IntegrationBusinessBundle\Managers\MinimaxImportManager;
use IntegrationBusinessBundle\Managers\MireoApiManager;
use IntegrationBusinessBundle\Managers\NubeprintApiManager;
use IntegrationBusinessBundle\Managers\PevexTrsImportManager;
use IntegrationBusinessBundle\Managers\PrincityApiManager;
use IntegrationBusinessBundle\Managers\ProErpImportManager;
use IntegrationBusinessBundle\Managers\RecommendIntegrationManager;
use IntegrationBusinessBundle\Managers\SqualomailApiManager;
use IntegrationBusinessBundle\Managers\WandImportManager;
use IntegrationBusinessBundle\Managers\WhmApiManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;

class IntegrationHelperCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('integration:run')
            ->setDescription('Helper functions')
            ->addArgument('vendor', InputArgument::REQUIRED, 'vendor')
            ->addArgument('function', InputArgument::OPTIONAL, 'function')
            ->addArgument('arguments', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'arguments')
            ->addOption('debug', NULL, InputOption::VALUE_REQUIRED, 'debug', 0)
            ->addOption('fast', NULL, InputOption::VALUE_REQUIRED, 'fast', 0)
            ->addOption('uid', NULL, InputOption::VALUE_REQUIRED, 'uid', 0)
            ->addOption('postal_code', NULL, InputOption::VALUE_REQUIRED, 'postal_code', 0)
            ->addOption('id', NULL, InputOption::VALUE_REQUIRED, 'id', 0)
            ->addOption('username', NULL, InputOption::VALUE_REQUIRED, 'username', 0)
            ->addOption('email', NULL, InputOption::VALUE_REQUIRED, 'email', 0)
            ->addOption('from_date', NULL, InputOption::VALUE_REQUIRED, 'from_date', 0)
            ->addOption('warehouse_code', NULL, InputOption::VALUE_OPTIONAL, 'warehouse_code', 0)
            ->addOption('minutes_history', NULL, InputOption::VALUE_OPTIONAL, 'minutes_history', 0)
            ->addOption('aktivan_za_web', NULL, InputOption::VALUE_OPTIONAL, 'aktivan_za_web', 0)
            ->addOption('product_codes', NULL, InputOption::VALUE_OPTIONAL, 'product_codes', 0);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return false
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $request = new Request();
        if (!empty($request->getSession())) {
            $request->getSession()->invalidate();
        }

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");
        $helperManager->loginAnonymus($request, "system");

        $vendor = $input->getArgument("vendor");
        $function = $input->getArgument("function");
        $arguments = $input->getArgument("arguments");
        $debug = $input->getOption("debug");
        $fast = $input->getOption("fast");

        $uid = $input->getOption("uid");
        $postalCode = $input->getOption("postal_code");
        $id = $input->getOption("id");
        $username = $input->getOption("username");
        $email = $input->getOption("email");
        $warehouseCode = $input->getOption("warehouse_code");
        $productCodes = $input->getOption("product_codes");
        $minutesHistory = $input->getOption("minutes_history");
        $aktivanZaWeb = $input->getOption("aktivan_za_web");
        $fromDate = $input->getOption("from_date");

        if ($vendor == "wand") {

            /** @var WandImportManager $wandImportManager */
            $wandImportManager = $this->getContainer()->get("wand_import_manager");
            $wandImportManager->setConsoleOutput($output)
                ->setDebug($debug);

            if ($function == "importDokumenti") {

                $ret = $wandImportManager->importDokumenti();
                dump($ret);
                die;

            } else if ($function == "importOsobe") {

                $ret = $wandImportManager->importOsobe();
                dump($ret);
                die;
            }

        } else if ($vendor == "luceed_erp_import") {

            /** @var LuceedErpImportManager $luceedErpImportManager */
            $luceedErpImportManager = $this->getContainer()->get("luceed_erp_import_manager");
            $luceedErpImportManager->setConsoleOutput($output)
                ->setDebug($debug)
                ->setFast($fast);

            if ($function == "importProducts") {

                $ret = $luceedErpImportManager->importProducts();
                dump($ret);
                die;

            } else if ($function == "importWarehouses") {

                $ret = $luceedErpImportManager->importWarehouses();
                dump($ret);
                die;

            } else if ($function == "importWarehouseStock") {

                $ret = $luceedErpImportManager->importWarehouseStock();
                dump($ret);
                die;

            } else if ($function == "importDiscounts") {

                $ret = $luceedErpImportManager->importDiscounts();
                dump($ret);
                die;

            } else if ($function == "importProductGroups") {

                $ret = $luceedErpImportManager->importProductGroups();
                dump($ret);
                die;

            } else if ($function == "importAccounts") {

                $ret = $luceedErpImportManager->importAccounts();
                dump($ret);
                die;

            } else if ($function == "getAccountByOib") {

                $ret = $luceedErpImportManager->getLuceedAccountByOib("13852622893");
                dump($ret);
                die;

            } else if ($function == "importPrices") {

                $ret = $luceedErpImportManager->importPrices();
                dump($ret);
                die;

            } else if ($function == "importSupplierWarehouseStock") {

                $ret = $luceedErpImportManager->importSupplierWarehouseStock();
                dump($ret);
                die;
            }
        } elseif ($vendor == "recommend") {

            /** @var RecommendIntegrationManager $recommendIntegrationManager */
            $recommendIntegrationManager = $this->getContainer()->get("recommend_integration_manager");

            if ($function == "send_recommend") {
                $recommendIntegrationManager->sendRecommend($id);
            }

        } else if ($vendor == "luceed_erp") {

            /** @var LuceedErpApiManager $luceedErpApiManager */
            $luceedErpApiManager = $this->getContainer()->get("luceed_erp_api_manager");

            if ($function == "getUserByUsername") {

                if (empty($username)) {
                    throw new \Exception("Missing username");
                }

                $ret = $luceedErpApiManager->get("users/username", $username);
                dump($ret);
                die;

            } else if ($function == "getUserByUid") {

                if (empty($uid)) {
                    throw new \Exception("Missing uid");
                }

                $ret = $luceedErpApiManager->get("users/uid", $uid);
                dump($ret);
                die;

            } else if ($function == "getPartner") {

                if (empty($uid)) {
                    throw new \Exception("Missing uid");
                }

                $ret = $luceedErpApiManager->get("partneri/uid", $uid);
                dump($ret);
                die;

            } else if ($function == "getOrder") {

                if (empty($uid)) {
                    throw new \Exception("Missing uid");
                }

                $ret = $luceedErpApiManager->get("NaloziProdaje/uid", $uid);
                dump($ret);
                die;

            } else if ($function == "getMpracData") {

                if (empty($uid)) {
                    throw new \Exception("Missing uid");
                }

                $ret = $luceedErpApiManager->get("mpracuni/nalogprodaje", $uid);
                dump($ret);
                die;

            } else if ($function == "getInvoicePdfByUid") {

                if (empty($uid)) {
                    throw new \Exception("Missing uid");
                }

                $ret = $luceedErpApiManager->getInvoicePdfByUid($uid);
                dump($ret);
                die;

            } else if ($function == "getCityByPostalCode") {

                if (empty($postalCode)) {
                    throw new \Exception("Missing postal code");
                }

                $ret = $luceedErpApiManager->getCityByPostalCode($postalCode);
                dump($ret);
                die;

            } else if ($function == "getCityByCode") {

                if (empty($postalCode)) {
                    throw new \Exception("Missing code");
                }

                $ret = $luceedErpApiManager->getCityByCode($postalCode);
                dump($ret);
                die;

            } else if ($function == "createCity") {

                if (empty($postalCode)) {
                    throw new \Exception("Missing postal code");
                }

                /** @var AccountManager $accountManager */
                $accountManager = $this->getContainer()->get("account_manager");

                $city = $accountManager->getCityByPostalCode($postalCode);
                if (empty($city)) {
                    dump("Missing city");
                    die;
                }

                $ret = $luceedErpApiManager->createCity($city);
                dump($ret);
                die;
            }

        } else if ($vendor == "horizont") {

            /** @var HorizontImportManager $horizontImportManager */
            $horizontImportManager = $this->getContainer()->get("horizont_import_manager");
            $horizontImportManager->setConsoleOutput($output)
                ->setDebug($debug)
                ->setFast($fast);

            if ($function == "importCustomers") {

                $horizontImportManager->importCustomers();

            } else if ($function == "importProducts") {

                $horizontImportManager->importProducts();

            } else if ($function == "sendOrder") {

                if (empty($id)) {
                    throw new \Exception("Missing order id");
                }

                /** @var OrderManager $orderManager */
                $orderManager = $this->getContainer()->get("order_manager");

                /** @var OrderEntity $order */
                $order = $orderManager->getOrderById($id);
                if (empty($order)) {
                    throw new \Exception("Order does not exist");
                }

                $data = $horizontImportManager->prepareOrderPost($order);
                dump($data);

                $data = $horizontImportManager->sendOrder($data);
                dump($data);
            }

        } else if ($vendor == "mailchimp") {

            /** @var MailchimpMarketingApiManager $mailchimpMarketingApiManager */
            $mailchimpMarketingApiManager = $this->getContainer()->get("mailchimp_marketing_api_manager");
            $mailchimpMarketingApiManager->setConsoleOutput($output)
                ->setDebug($debug);

            if ($function == "getLists") {

                $ret = $mailchimpMarketingApiManager->getLists();
                dump($ret);
                die;

            } else if ($function == "getMembersList") {

                $ret = $mailchimpMarketingApiManager->getMembersList(["fields" => "members.id,members.email_address,members.status,members.last_changed,members.web_id"]);
                echo json_encode($ret);
                die;

            } else if ($function == "getSubscriberByEmail") {

                if (empty($email)) {
                    throw new \Exception("Missing email");
                }

                $ret = $mailchimpMarketingApiManager->getSubscriberByEmail($email);
                dump($ret);
                die;

            } else if ($function == "subscribeMember") {

                if (empty($email)) {
                    throw new \Exception("Missing email");
                }

                $ret = $mailchimpMarketingApiManager->subscribeMember($email);
                dump($ret);
                die;

            } else if ($function == "unsubscribeMember") {

                if (empty($email)) {
                    throw new \Exception("Missing email");
                }

                $ret = $mailchimpMarketingApiManager->unsubscribeMember($email);
                dump($ret);
                die;

            } else if ($function == "resubscribeMember") {

                if (empty($email)) {
                    throw new \Exception("Missing email");
                }

                $ret = $mailchimpMarketingApiManager->resubscribeMember($email);
                dump($ret);
                die;

            } else if ($function == "getRecentlyUnsubscribedMembers") {

                $ret = $mailchimpMarketingApiManager->getRecentlyUnsubscribedMembers();
                dump($ret);
                die;

            } else if ($function == "getStoresList") {

                $ret = $mailchimpMarketingApiManager->getStoresList();
                dump($ret);
                die;

            } else if ($function == "getProductsList") {

                $ret = $mailchimpMarketingApiManager->getProductsList();
                dump($ret);
                die;

            } else if ($function == "addProduct" || $function == "updateProduct") {

                if (empty($id)) {
                    throw new \Exception("Missing product id");
                }

                $data = [];
                $data["id"] = (string)$id;
                $data["title"] = "title";
                $data["handle"] = "handle";
                $data["url"] = "url";
                $data["description"] = "description";
                $data["image_url"] = "image_url";

                $data["variants"][] = $data;
                $data["variants"][0]["price"] = (float)9.99;
                $data["variants"][0]["visibility"] = "visible";
                unset($data["variants"][0]["handle"]);

                $ret = $mailchimpMarketingApiManager->$function($data);
                dump($ret);
                die;

            } else if ($function == "syncProducts") {

                $mailchimpMarketingApiManager->syncProducts($mailchimpMarketingApiManager->getExistingProductsForMailchimp());

            } else if ($function == "syncNewsletters") {

                $mailchimpMarketingApiManager->syncNewsletter($mailchimpMarketingApiManager->getExistingNewslettersForMailchimp());
            }

        } else if ($vendor == "mireo") {

            /** @var MireoApiManager $mireoApiManager */
            $mireoApiManager = $this->getContainer()->get("mireo_api_manager");
            $mireoApiManager->setConsoleOutput($output)
                ->setDebug($debug);

            if ($function == "importVehicles") {
                $mireoApiManager->importVehicles();
            }

        } else if ($vendor == "fitting_box") {

            /** @var FittingBoxApiManager $fittingBoxApiManager */
            $fittingBoxApiManager = $this->getContainer()->get("fitting_box_api_manager");
            $fittingBoxApiManager->setConsoleOutput($output)
                ->setDebug($debug)
                ->setFast($fast);

            if ($function == "importProductImages") {

                $args = [
                    "angles" => [50, 51]
                ];

                $fittingBoxApiManager->importProductImages($args);
            }

        } else if ($vendor == "city_import") {

            /** @var CityImportManager $cityImportManager */
            $cityImportManager = $this->getContainer()->get("city_import_manager");
            $cityImportManager->setConsoleOutput($output)
                ->setDebug($debug);

            if ($function == "hrvatskaPosta") {
                $cityImportManager->importHrvatskaPostaCities($arguments[0] ?? false);
            } else if ($function == "postaSlovenije") {
                $cityImportManager->importPostaSlovenijeCities($arguments[0] ?? false);
            }

        } else if ($vendor == "pevex_trs") {

            /** @var PevexTrsImportManager $pevexTrsImportManager */
            $pevexTrsImportManager = $this->getContainer()->get("pevex_trs_import_manager");
            $pevexTrsImportManager->setConsoleOutput($output)
                ->setDebug($debug)
                ->setFast($fast);

            if ($function == "importProducts") {

                $params = [
                    "product_codes" => []
                ];

                if (isset($minutesHistory) && !empty($minutesHistory)) {
                    $params["changes_since_minutes"] = $minutesHistory;
                }
                if (isset($productCodes) && !empty($productCodes)) {
                    $params["product_codes"] = explode(",", $productCodes);
                }

                $ret = $pevexTrsImportManager->importProducts($params);
                dump($ret);
                die;

            } else if ($function == "importWarehouseStock") {

                $params = [
                    "product_codes" => []
                ];

                if (isset($warehouseCode) && !empty($warehouseCode)) {
                    $params["warehouse_code"] = $warehouseCode;
                }
                if (isset($minutesHistory) && !empty($minutesHistory)) {
                    $params["changes_since_minutes"] = $minutesHistory;
                }
                if (isset($productCodes) && !empty($productCodes)) {
                    $params["product_codes"] = explode(",", $productCodes);
                }
                if (isset($aktivanZaWeb) && !empty($aktivanZaWeb) && in_array($aktivanZaWeb, array("DA", "NE"))) {
                    $params["aktivan_za_web"] = $aktivanZaWeb;
                }

                $ret = $pevexTrsImportManager->importWarehouseStock($params);
                dump($ret);
                die;

            } else if ($function == "importProductPrices") {

                $params = [
                    "product_codes" => [],
                    "from_date" => ""
                ];

                if (isset($fromDate) && !empty($fromDate)) {
                    $params["from_date"] = $fromDate;
                }
                if (isset($productCodes) && !empty($productCodes)) {
                    $params["product_codes"] = explode(",", $productCodes);
                }

                $ret = $pevexTrsImportManager->importProductPrices($params);
                //dump($ret);
                die;

            } else if ($function == "importClassification") {

                $ret = $pevexTrsImportManager->importClassification();
                dump($ret);
                die;

            } else if ($function == "getOrderById") {

                if (empty($trsDokumentId)) {
                    throw new \Exception("Missing trs_dokument_id");
                }

                $ret = $pevexTrsImportManager->getOrderById($trsDokumentId);
                dump($ret);
                die;

            } else if ($function == "getPartnerByOib") {

                if (!isset($arguments[0]) || empty($arguments[0])) {
                    throw new \Exception("Oib is empty");
                }

                $ret = $pevexTrsImportManager->getPartnerByOib($arguments[0]);
                dump($ret);
                die;

            } else if ($function == "getLoyaltyUsers") {

                $ret = $pevexTrsImportManager->getLoyaltyUsers();
                dump($ret);
                die;

            } else if ($function == "getLoyaltyPrices") {

                $ret = $pevexTrsImportManager->getLoyaltyPrices();
                dump($ret);
                die;

            } else if ($function == "importLoyaltyPrices") {

                $ret = $pevexTrsImportManager->importLoyaltyPrices();
                dump($ret);
                die;

            } else if ($function == "importLoyaltyUsers") {

                $ret = $pevexTrsImportManager->importLoyaltyUsers();
                dump($ret);
                die;

            } else if ($function == "sendLoyaltyUser") {

                $ret = $pevexTrsImportManager->sendLoyaltyUser();
                dump($ret);
                die;
            }

        } else if ($vendor == "pro_erp") {

            /** @var ProErpImportManager $proErpImportManager */
            $proErpImportManager = $this->getContainer()->get("pro_erp_import_manager");
            $proErpImportManager->setConsoleOutput($output)
                ->setDebug($debug);

            if ($function == "importProducts") {

                $ret = $proErpImportManager->importProducts();
                dump($ret);
                die;

            } else if ($function == "importAccounts") {

                $ret = $proErpImportManager->importAccounts();
                dump($ret);
                die;
            }

        } else if ($vendor == "squalomail") {

            /** @var SqualomailApiManager $squalomailApiManager */
            $squalomailApiManager = $this->getContainer()->get("squalomail_api_manager");
            $squalomailApiManager->setConsoleOutput($output)
                ->setDebug($debug);

            if ($function == "getLists") {

                $ret = $squalomailApiManager->getLists();
                dump($ret);
                die;

            } else if ($function == "getMembersList") {


            } else if ($function == "getSubscriberByEmail") {

                if (empty($email)) {
                    throw new \Exception("Missing email");
                }

                $ret = $squalomailApiManager->getSubscriberByEmail($email);
                dump($ret);
                die;

            } else if ($function == "getRecentlyUnsubscribedMembers") {

                $ret = $squalomailApiManager->getRecentlyUnsubscribedMembers();
                dump($ret);
                die;
            }

        } else if ($vendor == "nubeprint") {

            /** @var NubeprintApiManager $nubeprintApiManager */
            $nubeprintApiManager = $this->getContainer()->get("nubeprint_api_manager");

            if ($function == "getAuthKey") {
                $ret = $nubeprintApiManager->getApiKey();
                dump($ret);
                die;
            } else if ($function == "getInventory") {
                $ret = $nubeprintApiManager->getInventory();
                dump($ret);
                die;
            } else if ($function == "importInventory") {
                $ret = $nubeprintApiManager->importInventory();
                dump($ret);
                die;
            } else if ($function == "getConsumableAlerts") {
                $ret = $nubeprintApiManager->getConsumableAlerts();
                dump($ret);
                die;
            } else if ($function == "getTechnicalAlerts") {
                $ret = $nubeprintApiManager->getTechnicalAlerts();
                dump($ret);
                die;
            }

        } else if ($vendor == "princity") {

            /** @var PrincityApiManager $princityApiManager */
            $princityApiManager = $this->getContainer()->get("princity_api_manager");

            if ($function == "getContracts") {
                $ret = $princityApiManager->getContracts();
                dump($ret);
                die;
            } else if ($function == "getInventory") {
                $ret = $princityApiManager->getInventory("Senso");
                dump($ret);
                die;
            } else if ($function == "importInventory") {
                $ret = $princityApiManager->importInventory();
                dump($ret);
                die;
            } else if ($function == "getSnmpAlerts") {
                $ret = $princityApiManager->getSnmpAlerts("Antunovic-16");
                dump($ret);
                die;
            } else if ($function == "getSupplies") {
                $ret = $princityApiManager->getSupplies("Antunovic-16");
                dump($ret);
                die;
            } else if ($function == "getOrders") {
                $ret = $princityApiManager->getOrders("Senso");
                dump($ret);
                die;
            }

        } else if ($vendor == "greencell") {

            /** @var GreenCellImportManager $greenCellImportManager */
            $greenCellImportManager = $this->getContainer()->get("greencell_import_manager");
            $greenCellImportManager->setConsoleOutput($output)
                ->setDebug($debug)
                ->setStores([6]);

            if ($function == "importProducts") {
                $ret = $greenCellImportManager->importProducts();
                dump($ret);
                die;
            }

        } else if ($vendor == "gath") {

            /** @var GathImportManager $gathImportManager */
            $gathImportManager = $this->getContainer()->get("gath_import_manager");
            $gathImportManager->setConsoleOutput($output)
                ->setDebug($debug);

            if ($function == "importProducts") {
                $ret = $gathImportManager->importProducts();
                dump($ret);
                die;
            }

        } else if ($vendor == "whm") {

            /** @var WhmApiManager $whmApiManager */
            $whmApiManager = $this->getContainer()->get("whm_api_manager");

            if ($function == "getAvailableHostingPlans") {
                $ret = $whmApiManager->getAvailableHostingPlans();
                dump($ret);
                die;
            } else if ($function == "getDiskUsage") {
                $ret = $whmApiManager->getDiskUsage();
                dump($ret);
                die;
            }

        } else if ($vendor == "vasco") {

            if ($function == "sendOrder") {

                if (empty($id)) {
                    throw new \Exception("Missing order id");
                }

                /** @var OrderManager $orderManager */
                $orderManager = $this->getContainer()->get("order_manager");

                /** @var OrderEntity $order */
                $order = $orderManager->getOrderById($id);
                if (empty($order)) {
                    throw new \Exception("Order does not exist");
                }

                $ret = $this->sendVascoOrderWrapper($order);
                dump($ret);
                die;
            }

        } else if ($vendor == "minimax") {

            /** @var MinimaxImportManager $minimaxImportManager */
            $minimaxImportManager = $this->getContainer()->get("minimax_import_manager");
            $minimaxImportManager->setConsoleOutput($output)
                ->setDebug($debug);

            if ($function == "importProducts") {

                $ret = $minimaxImportManager->importProducts();
                dump($ret);
                die;

            } else if ($function == "getCustomerByCode") {

                $ret = $minimaxImportManager->getCustomerByCode("SHIPSHAPE-TEST");
                dump($ret);
                die;

            } else if ($function == "sendCustomer") {

                if (empty($id)) {
                    throw new \Exception("Missing account id");
                }

                /** @var AccountManager $accountManager */
                $accountManager = $this->getContainer()->get("account_manager");

                /** @var AccountEntity $account */
                $account = $accountManager->getAccountById($id);
                if (empty($account)) {
                    throw new \Exception("Account does not exist");
                }

                $data = $minimaxImportManager->getPreparedCustomer($account);
                dump($data);

                $ret = $minimaxImportManager->sendCustomer($data);
                dump($ret);
                die;

            } else if ($function == "sendOrder") {

                if (empty($id)) {
                    throw new \Exception("Missing order id");
                }

                /** @var OrderManager $orderManager */
                $orderManager = $this->getContainer()->get("order_manager");

                /** @var OrderEntity $order */
                $order = $orderManager->getOrderById($id);
                if (empty($order)) {
                    throw new \Exception("Order does not exist");
                }

                $customer = $minimaxImportManager->getCustomerByCode($order->getAccount()->getCode());
                dump($customer);

                if (empty($customer)) {
                    $preparedCustomer = $minimaxImportManager->getPreparedCustomer($order->getAccount());
                    dump($preparedCustomer);

                    $ret = $minimaxImportManager->sendCustomer($preparedCustomer);
                    dump($ret);
                }

                $preparedOrder = $minimaxImportManager->getPreparedOrder($order);
                dump($preparedOrder);

                $ret = $minimaxImportManager->sendOrder($preparedOrder);
                dump($ret);
                die;
            }

        } else if ($vendor == "mailerlite") {

            /** @var MailerLiteApiManager $mailerLiteApiManager */
            $mailerLiteApiManager = $this->getContainer()->get("mailer_lite_api_manager");

            if ($function == "getSortedSubscribers") {

                $ret = $mailerLiteApiManager->getSortedSubscribers();
                dump($ret);
                die;

            } else if ($function == "syncNewsletters") {

                $mailerLiteApiManager->syncNewsletter($mailerLiteApiManager->getExistingNewsletters());
            }

        } else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }

        return false;
    }
}
