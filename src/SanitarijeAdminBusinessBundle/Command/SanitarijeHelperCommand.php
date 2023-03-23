<?php

// php bin/console sanitarijehelper:run wand_import_robe
// php bin/console sanitarijehelper:run wand_import_osobe
// php bin/console sanitarijehelper:run data_import
// php bin/console sanitarijehelper:run api_login
// php bin/console sanitarijehelper:run get_product_data_from_api 21
// php bin/console sanitarijehelper:run update_product_data_from_api 96545
// php bin/console sanitarijehelper:run sync_products

namespace SanitarijeAdminBusinessBundle\Command;

use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use IntegrationBusinessBundle\Managers\WandImportManager;
use SanitarijeBusinessBundle\Managers\SanitarijeHelperManager;
use ScommerceBusinessBundle\Managers\AlgoliaManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use TimnovakBusinessBundle\Managers\TimnovakHelperManager;

class SanitarijeHelperCommand extends ContainerAwareCommand
{
    /** @var WandImportManager $wandImportManager */
    protected $wandImportManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var SanitarijeHelperManager $sanitarijeHelperManager */
    protected $sanitarijeHelperManager;
    /** @var ErrorLogManager $errorLogManager */
    private $errorLogManager;
    /** @var AlgoliaManager $algoliaManager */
    private $algoliaManager;
    /** @var MailManager $mailManager */
    private $mailManager;

    private $isProduction;
    private $isProductionErp;
    private $errorLogEmailRecipient;
    private $defaultStoreId;
    private $algoliaIndexName;

    protected function configure()
    {
        $this->setName("sanitarijehelper:run")
            ->SetDescription("Helper functions")
            ->AddArgument("type", InputArgument::OPTIONAL, " which function ")
            ->AddArgument("arg1", InputArgument::OPTIONAL, " arg1 ");
    }

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

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");
        /**
         * End start new session for import
         */

        $this->isProduction = $_ENV["IS_PRODUCTION"];
        $this->isProductionErp = $_ENV["IS_PRODUCTION_ERP"];
        $this->errorLogEmailRecipient = $_ENV["GENERAL_CONTACT_EMAIL_RECIPIENT"];
        $this->defaultStoreId = $_ENV["DEFAULT_STORE_ID"];
        $this->algoliaIndexName = $_ENV["ALGOLIA_INDEX_NAME"];

        $func = $input->getArgument("type");
        if ($func == "wand_import_osobe") {

            if (empty($this->wandImportManager)) {
                $this->wandImportManager = $this->getContainer()->get("wand_import_manager");
            }

            $data = [];
            $data["completed"] = 0;
            $data["error_log"] = "";
            $data["name"] = "wand_import_osobe";

            try {
                $this->wandImportManager->importPartneri();
                $this->wandImportManager->importOsobe();
                if ($this->isProductionErp) {
                    $this->wandImportManager->generateCoreUsers();
                }
                $data["completed"] = 1;
            } catch (\Exception $e) {

                if (empty($this->errorLogManager)) {
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }
                $this->errorLogManager->logExceptionEvent("Wand import osobe error", $e, true);
                $data["error_log"] = $e->getMessage();
                $data["exception"] = $e;
            }

            $this->wandImportManager->insertImportLog($data);

            $errors = $this->wandImportManager->getErrors();
            if (!empty($errors) && !empty($this->errorLogEmailRecipient)) {
                $to = [
                    "email" => $this->errorLogEmailRecipient,
                    "name" => $this->errorLogEmailRecipient
                ];
                if (empty($this->mailManager)) {
                    $this->mailManager = $this->getContainer()->get("mail_manager");
                }
                $this->mailManager->sendEmail($to, null, null, null, "Wand import osobe report", "", "wand_report", ["errors" => $errors], "", [], $this->defaultStoreId);
            }
        }
        elseif ($func == "wand_import_robe") {

            if (empty($this->wandImportManager)) {
                $this->wandImportManager = $this->getContainer()->get("wand_import_manager");
            }

            $data = [];
            $data["completed"] = 0;
            $data["error_log"] = "";
            $data["name"] = "wand_import";

            try {
                $this->wandImportManager->updateProductGroupRemoteIds();

                $changedProducts = array("product_ids" => array());


                $changedProductIds = $this->wandImportManager->importRobe();
                /**
                 * Dodano
                 */
                if(isset($changedProductIds["product_ids"]) && !empty($changedProductIds["product_ids"])){
                    $changedProducts["product_ids"] = array_merge($changedProducts["product_ids"],$changedProductIds["product_ids"]);
                }

                $this->wandImportManager->importSkladista();
                $changedProductIds = $this->wandImportManager->importStanja();
                /**
                 * Dodano
                 */
                if(!empty($changedProductIds)){
                    $changedProducts["product_ids"] = array_merge($changedProducts["product_ids"],$changedProductIds);
                }

                if(empty($this->sanitarijeHelperManager)){
                    $this->sanitarijeHelperManager = $this->getContainer()->get("sanitarije_helper_manager");
                }
                $this->sanitarijeHelperManager->updateProductTotalQty("wand");

                $changedProductIds = $this->wandImportManager->importRabati();
                /**
                 * Dodano
                 */
                if(!empty($changedProductIds)){
                    $changedProducts["product_ids"] = array_merge($changedProducts["product_ids"],$changedProductIds);
                }

                if (empty($this->crmProcessManager)) {
                    $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
                }

                /**
                 * Dodano
                 */
                $changedProducts["product_ids"] = array_unique($changedProducts["product_ids"]);

                $this->crmProcessManager->afterImportCompleted($changedProducts, "wand");

                if ($this->isProduction) {
                    if (empty($this->algoliaManager)) {
                        $this->algoliaManager = $this->getContainer()->get("algolia_manager");
                    }
                    $data = $this->algoliaManager->getAlgoliaRecords($this->defaultStoreId);
                    if (!empty($data)) {
                        $this->algoliaManager->createUpdateIndex($this->algoliaIndexName, $data);
                    }
                }

                $data["completed"] = 1;

            } catch (\Exception $e) {
                $data["error_log"] = $e->getMessage();
                $data["exception"] = $e;
            }

            $this->wandImportManager->insertImportLog($data);
        }
        elseif ($func == "data_import") {

            if(empty($this->sanitarijeHelperManager)){
                $this->sanitarijeHelperManager = $this->getContainer()->get("sanitarije_helper_manager");
            }

            $this->sanitarijeHelperManager->oldDataImport();
        }
        elseif ($func == "api_login") {

            if(empty($this->sanitarijeHelperManager)){
                $this->sanitarijeHelperManager = $this->getContainer()->get("sanitarije_helper_manager");
            }

            $this->sanitarijeHelperManager->apiLogin();
        }
        elseif ($func == "get_product_data_from_api") {

            if(empty($this->sanitarijeHelperManager)){
                $this->sanitarijeHelperManager = $this->getContainer()->get("sanitarije_helper_manager");
            }

            $remoteId = $input->getArgument("arg1");

            $this->sanitarijeHelperManager->getProductDataFromApi($remoteId);
        }
        elseif ($func == "update_product_data_from_api") {

            if(empty($this->sanitarijeHelperManager)){
                $this->sanitarijeHelperManager = $this->getContainer()->get("sanitarije_helper_manager");
            }

            $id = $input->getArgument("arg1");

            if(empty($this->productManager)){
                $this->productManager = $this->getContainer()->get("product_manager");
            }

            /** @var ProductEntity $product */
            $product = $this->productManager->getProductById($id);

            $this->sanitarijeHelperManager->updateProductData($product);
        }
        elseif ($func == "sync_products") {

            if(empty($this->sanitarijeHelperManager)){
                $this->sanitarijeHelperManager = $this->getContainer()->get("sanitarije_helper_manager");
            }

            $this->sanitarijeHelperManager->syncProducts();
        }
        else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }

        return 0;
    }
}
