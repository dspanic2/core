<?php

namespace SanitarijeBusinessBundle\Managers;

use AppBundle\Blocks\EmailTemplate;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\DeliveryPricesEntity;
use CrmBusinessBundle\Entity\DeliveryTypeEntity;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Entity\NewsletterEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Managers\AutomationsManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\EmailTemplateManager;
use CrmBusinessBundle\Managers\OrderManager;
use IntegrationBusinessBundle\Managers\WandApiManager;
use SanitarijeBusinessBundle\Constants\CommerceConstants;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\SproductManager;

class SanitarijeCrmProcessManager extends DefaultCrmProcessManager
{
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var WandApiManager $wandApiManager */
    protected $wandApiManager;
    protected $attributeOptionIds;
    protected $fixedPriceDelivery;
    /** @var AutomationsManager $automationsManager */
    protected $automationsManager;
    /** @var EmailTemplateManager $emailTemplateManager */
    protected $emailTemplateManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    /**
     * @param ProductEntity|null $product
     * @param $account
     * @param $parentProduct
     * @return string
     */
    public function getCalculationMethod(ProductEntity $product = null, $account = null, $parentProduct = null){

        $method = "Vpc";

        return $method;
    }

    /**
     * @param QuoteEntity $quote
     * @param $data
     * @return mixed
     */
    public function getAvailablePaymentTypes(QuoteEntity $quote, $data = [])
    {
        $storeId = $_ENV["DEFAULT_STORE_ID"];
        $websiteId = $_ENV["DEFAULT_WEBSITE_ID"];
        if (!empty($quote) && !empty($quote->getStoreId())) {
            $storeId = $quote->getStoreId();
            $websiteId = $quote->getStore()->getWebsiteId();
        }

        $entityType = $this->entityManager->getEntityTypeByCode("payment_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        if (!empty($quote) && $quote->getBasePriceTotal() > 0) {
            $compositeFilterSub = new CompositeFilter();
            $compositeFilterSub->setConnector("or");
            $compositeFilterSub->addFilter(new SearchFilter("maxCartTotal", "json_ge", json_encode(array($quote->getBasePriceTotal(), '$."' . $websiteId . '"'))));
            $compositeFilterSub->addFilter(new SearchFilter("maxCartTotal", "nu", null));
            $compositeFilterSub->addFilter(new SearchFilter("maxCartTotal", "json_lt", json_encode(array(2, '$."' . $websiteId . '"'))));
            $compositeFilter->addFilter($compositeFilterSub);
        }

        if ($data["delivery_type_id"] == CommerceConstants::DELIVERY_DOSTAVA) {
            $compositeFilter->addFilter(
                new SearchFilter(
                    "id",
                    "ni",
                    implode(",", array(CommerceConstants::PAYMENT_CASH))
                )
            );
        } elseif (in_array($data["delivery_type_id"], array(CommerceConstants::DELIVERY_POSLOVNICA_1, CommerceConstants::DELIVERY_POSLOVNICA_2))) {
            $compositeFilter->addFilter(
                new SearchFilter(
                    "id",
                    "ni",
                    implode(",", array(CommerceConstants::PAYMENT_POUZECE))
                )
            );
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("name", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param ProductEntity $product
     * @param null $data
     * @return bool
     */
    public function recalculateProductPrices(ProductEntity $product, $data = null)
    {

        /**
         * Configurable product does not have price
         */
        if (in_array($product->getProductTypeId(), array(CrmConstants::PRODUCT_TYPE_CONFIGURABLE))) {
            return true;
        }

        $data["product_ids"] = array($product->getId());
        if (!empty($product->getSupplierId())) {
            $data["supplier_ids"] = array($product->getSupplierId());
        }

        $this->recalculateProductsPrices($data);

        return true;
    }

    /**
     * @param null $data
     * @return bool
     */
    public function recalculateProductsPrices($data = null)
    {

        /**
         * Check if product_ids is empty
         */
        if (!isset($data["product_ids"]) || empty($data["product_ids"])) {
            return true;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        /**
         * Ako se koriste marže prvo izvrtiti marže
         */
        /**
         * Nema marži
         */
        /*if (isset($data["supplier_ids"]) && !empty($data["supplier_ids"])) {
            if (empty($this->marginRuleManager)) {
                $this->marginRuleManager = $this->container->get("margin_rules_manager");
            }

            $this->marginRuleManager->recalculateMarginRules($data);
        }*/

        $this->recalculateSecondaryProductsPrices($data);

        /**
         * Paljenje i gasenje proizvoda
         */
        $this->refreshActiveSaleable($data);

        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->container->get("product_group_manager");
        }

        $this->productGroupManager->setNumberOfProductsInProductGroups();

        return true;
    }

    /**
     * @param null $data
     * @return bool
     */
    public function recalculateSecondaryProductsPrices($data = null)
    {
        /**
         * Check if product_ids is empty
         */
        if (!isset($data["product_ids"]) || empty($data["product_ids"])) {
            return true;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        /**
         * Recalculate discounts on products
         */
        /**
         * Popusti dolaze iz wand-a
         */
        /*$where = " id IN (" . implode(",", $data["product_ids"]) . ") AND ";

        $q = "UPDATE product_entity SET discount_price_base = price_base - (price_base * discount_percentage / 100.0), discount_diff_base = price_base - (price_base - (price_base * discount_percentage / 100.0)), discount_price_retail = price_retail - (price_retail * discount_percentage / 100.0), discount_diff = price_retail - (price_retail - (price_retail * discount_percentage / 100.0)) WHERE {$where} discount_percentage > 0 AND (exclude_from_discounts is null or exclude_from_discounts = 0);";
        $this->databaseContext->executeNonQuery($q);*/

        /**
         * Setting qty = fixed_qty on a product if the field 'fixed_qty' > 0 AND if product is active
         */
        #$q = "UPDATE product_entity SET qty = fixed_qty WHERE fixed_qty > 0 AND (qty != fixed_qty OR qty IS NULL) AND active = 1;";
        #$this->databaseContext->executeNonQuery($q);

        /**
         * Izračunava VPC na temelju MPC - PDV
         */
        $where = " p.id IN (" . implode(",", $data["product_ids"]) . ") AND ";
        $q = "UPDATE product_entity AS p INNER JOIN tax_type_entity AS tt ON p.tax_type_id = tt.id SET p.price_base = p.price_retail / (1 + tt.percent / 100) WHERE {$where} tt.percent IS NOT NULL AND p.price_retail > 0 AND p.remote_source = 'wand';";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Calculate discounts ako se importaju iz wand-a
         */
        $q = "UPDATE product_entity AS p INNER JOIN tax_type_entity AS tt ON p.tax_type_id = tt.id SET 
             p.date_discount_base_from = p.date_discount_from,
             p.date_discount_base_to = p.date_discount_to,
             p.discount_type_base = p.discount_type,
             p.discount_percentage_base = p.discount_percentage,
             p.discount_price_base = p.discount_price_retail / (1 + tt.percent / 100) WHERE {$where} tt.percent IS NOT NULL AND p.discount_price_retail > 0 AND p.remote_source = 'wand';";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Recalculate discounts on catalog rule
         */
        /**
         * Popusti dolaze iz wanda
         */
        /*if(count($data["product_ids"]) > 1){
            if (empty($this->discountRulesManager)) {
                $this->discountRulesManager = $this->container->get("discount_rules_manager");
            }
            $this->discountRulesManager->recalculateDiscountRules($data);
        }*/

        /**
         * Remove expired discounts
         * Ovo se ne smije korisiti kada popust na proizvod dolazi iz Wand-a a nije postotni
         */
        //$this->removeOldDiscountOnProducts();

        /**
         * Recalculate bulk price rules
         */
        /*if (empty($this->bulkPriceManager)) {
            $this->bulkPriceManager = $this->container->get("bulk_price_manager");
        }
        $this->bulkPriceManager->recalculateBulkPriceRules($data["product_ids"]);*/

        /** @var SproductManager $sProductManager */
        $sProductManager = $this->getContainer()->get("s_product_manager");
        $sProductManager->setProductSortPrices($data["product_ids"]);

        return true;
    }

    /**
     * @param OrderEntity $order
     * @return bool|null
     * @throws \Exception
     */
    public function afterOrderCreated(OrderEntity $order)
    {
        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        $orderState = $this->orderManager->getOrderStateById(CrmConstants::ORDER_STATE_IN_PROCESS);

        $data = array();
        $data["orderState"] = $orderState;
        $this->orderManager->updateOrder($order, $data);

        /** @var ContactEntity $contact */
        $contact = $order->getContact();

        if (empty($this->mailManager)) {
            $this->mailManager = $this->container->get("mail_manager");
        }

        $bcc = array(
            'email' => $_ENV["ORDER_EMAIL_RECIPIENT"],
            'name' => $_ENV["ORDER_EMAIL_RECIPIENT"],
        );
        $attachments = array();

        if ($order->getPaymentTypeId() == CrmConstants::PAYMENT_TYPE_VIRMAN) {
            if (empty($this->barcodeManager)) {
                $this->barcodeManager = $this->container->get("barcode_manager");
            }

            $targetPath = $this->barcodeManager->generatePDF417Barcode($order);
            $targetPath = str_ireplace(".jpeg", ".pdf", $targetPath);
            $targetPath = str_ireplace("//", "/", $targetPath);

            $webPath = $_ENV["WEB_PATH"];

            if (file_exists($webPath . $targetPath)) {
                unlink($webPath . $targetPath);
            }

            $attachments = array($targetPath);
        }

        if (empty($this->emailTemplateManager)) {
            $this->emailTemplateManager = $this->container->get("email_template_manager");
        }

        /** @var EmailTemplateEntity $template */
        $template = $this->emailTemplateManager->getEmailTemplateByCode("order_confirmation");
        $templateData = $this->emailTemplateManager->renderEmailTemplate($order, $template);
        $this->mailManager->sendEmail(
            array('email' => $contact->getEmail(), 'name' => $contact->getEmail()),
            null,
            $bcc,
            null,
            $templateData["subject"],
            "",
            null,
            [],
            $templateData["content"],
            $attachments,
            $order->getStoreId()
        );

        if ($this->sendOrderWrapper($order)) {

            $komunikatorUcitajNarudzbu = $_ENV["WAND_KOMUNIKATOR_UCITAJ_NARUDZBU"] ?? 0;
            if (!$komunikatorUcitajNarudzbu) {
                if (empty($this->orderManager)) {
                    $this->orderManager = $this->container->get("order_manager");
                }

                $data = array();
                $data["sentToErp"] = 1;
                $data["dateSentToErp"] = new \DateTime();
                $this->orderManager->updateOrder($order, $data);
            }
        }

        return false;
    }

    /**
     * @param OrderEntity $order
     * @return bool
     */
    public function sendOrderWrapper(OrderEntity $order)
    {
        $post = null;

        try {
            $post = $this->prepareOrderPost($order);
        } catch (\Exception $e) {
            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $this->errorLogManager->logExceptionEvent(sprintf("Error preparing order %u for Wand: ", $order->getId()), $e, true);

            return false;
        }

        if ($_ENV["IS_PRODUCTION_ERP"]) {

            if (empty($post)) {
                if (empty($this->errorLogManager)) {
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }

                $this->errorLogManager->logErrorEvent(sprintf("Prepared order %u for Wand is empty: ", $order->getId()), null, true);

                return false;
            }

            if (empty($this->wandOrderManager)) {
                $this->wandOrderManager = $this->container->get("wand_order_manager");
            }

            try {
                $this->wandOrderManager->sendOrder($post);
            } catch (\Exception $e) {
                if (empty($this->errorLogManager)) {
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }

                $this->errorLogManager->logExceptionEvent(sprintf("Error sending order %u to Wand", $order->getId()), $e, true);

                return false;
            }
        }

        return true;
    }

    /**
     * @param OrderEntity $order
     * @return array
     * @throws \Exception
     */
    public function prepareOrderPost(OrderEntity $order)
    {

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        $storeId = $order->getStoreId();
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $klasaDokumenta = "ULN";

        /**
         * TESTNO
         */
        $klasaDokumenta = "PON";


        //$isLegalEntity = $order->getAccount()->getIsLegalEntity();
        $additionalData = $order->getAdditionalData();
        if (!empty($additionalData)) {
            $additionalData = json_decode($additionalData, true);
            if (!isset($additionalData["company_name"])) {
                $additionalData = null;
            }
        } else {
            $additionalData = null;
        }

        $partnerId = $order->getAccountBillingAddress()->getRemoteId();
        if (!empty($order->getAccountShippingAddress())) {
            $partnerId = $order->getAccountShippingAddress()->getRemoteId();
        }

        /** @var AccountEntity $account */
        $account = $order->getAccount();
        $firma = null;
        $oib = 1;

        if ($account->getIsLegalEntity()) {
            $firma = $account->getName();
            $oib = $account->getOib();
        }

        if (!empty($partnerId)) {
            $adresa = $order->getAccountShippingStreet();
            $postBroj = $order->getAccountShippingCity()->getPostalCode();
            $mjesto = $order->getAccountShippingCity()->getName();
        } else {
            $adresa = $order->getAccountBillingStreet();
            $postBroj = $order->getAccountBillingCity()->getPostalCode();
            $mjesto = $order->getAccountBillingCity()->getName();
        }

        /**
         * Override sa additional data ako postoji
         */
        if (!empty($additionalData)) {
            $firma = $additionalData["company_name"];
            $oib = $additionalData["oib"];
            $adresa = $additionalData["street"];
            $postBroj = $additionalData["city_pbr"];
            $mjesto = $additionalData["city_name"];
        }

        /**
         * Provjera da oib nije prazan
         */
        if (empty($oib)) {
            $oib = 1;
        }
        /**
         * Provjera da adresa nije duza od 30 znakova
         */
        if (strlen($adresa) > 30) {
            $adresa = mb_substr($adresa, 0, 30);
        }

        $data = array();
        $data["BrojNarudzbe"] = strval($order->getIncrementId());

        /*if (empty($order->getAccountShippingAddress())) {
            return false;
        }*/

        $data["Partner"] = 0;
        if (!empty($partnerId)) {
            $data["Partner"] = $partnerId;
        }

        /** @var ContactEntity $contact */
        $contact = $order->getContact();

        $data["OsobaID"] = 0;
        $contactRemoteId = $contact->getRemoteId();
        if (!empty($contactRemoteId)) {
            $data["OsobaID"] = $contact->getRemoteId();
        }

        $data["BrojStavki"] = 0;
        $data["Kolicina"] = 0.00;
        $data["DOK_Dokument"] = 0;
        $data["DOK_Status"] = 0;
        $data["Poslana"] = 1;
        $data["Ucitana"] = 0;
        $data["Placena"] = 0;
        $data["Rezervacija"] = 1;

        $data["Osoba"] = $contact->getFullName();
        $data["Firma"] = $firma;
        $data["OIB"] = strval($oib);
        $data["Email"] = $contact->getEmail();
        $data["Telefon"] = $contact->getPhone();
        $data["Fax"] = null;

        $data["Adresa"] = $adresa;
        $data["PostBroj"] = $postBroj;
        $data["Mjesto"] = $mjesto;

        $data["NacinOtpreme"] = $order->getDeliveryType()->getRemoteCode();

        $napomena = "broj narudžbe: " . $order->getIncrementId();

        if (!empty($order->getDeliveryType())) {
            $napomena .= "\r\nnacina dostave: " . $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $order->getDeliveryType(), "name");
        }
        if (!empty($order->getPaymentType())) {
            $napomena .= "\r\nnacina placanja: " . $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $order->getPaymentType(), "name");
        }
        if (!empty($order->getContact())) {
            $napomena .= "\r\nkupac: " . $order->getContact()->getFirstName() . " " . $order->getContact()->getLastName();
            $napomena .= "\r\nemail: " . $order->getContact()->getEmail();
            $napomena .= "\r\ntelefon: " . $order->getContact()->getPhone();
        }
        if (!empty($order->getAccountShippingAddress())) {
            /** @var AddressEntity $shippingAddress */
            $shippingAddress = $order->getAccountShippingAddress();
            $napomena .= "\r\nadresa dostave: " . $shippingAddress->getStreet() . ", " . $shippingAddress->getCity()->getPostalCode() . " " . $shippingAddress->getCity()->getName();
        }
        if (!empty($order->getMessage())) {
            $napomena .= "\r\nnapomena kupca: " . $order->getMessage();
        }

        $data["Napomena"] = $napomena;
        /*if (empty($partnerId) || $partnerId == MakromikroConstants::WAND_ACCOUNT_FIZICKA_OSOBA) {
            $data["Napomena"] .= "\r\n".$order->getAccountShippingStreet().", ".$order->getAccountShippingCity(
                )->getPostalCode()." ".$order->getAccountShippingCity()->getName()."\r\n".$contact->getEmail(
                )."\r\n".$contact->getPhone();
            if ($order->getPaymentTypeId() == MakromikroConstants::PAYMENT_POUZECE) {
                $data["Napomena"] .= "\r\nPouzeće";
            }
        }*/

        $data["KlasaDokumenta"] = null;
        $data["Skladiste"] = "0000116";

        $data["BrutoIznos"] = 0.00;
        $data["VPIznos"] = 0.00;
        $data["MPIznos"] = 0.00;

        $data["DateCreated"] = $order->getCreated()->format("Y-m-d\TH:i:s.u");

        $data["OsobaPassword"] = null;
//        if (empty($contactRemoteId)) {
//            $data["OsobaPassword"] = "@d4ty%Y6W3r";
//        }

        $data["BrojOstvarenihBodovaProgramaVjernosti"] = 0;
        $data["BrojPotrosenihBodovaProgramaVjernosti"] = 0;
        $data["PostotakPopustaProgramaVjernosti"] = 0;
        $data["NacinPlacanja"] = $order->getPaymentType()->getRemoteCode();

        $data["Valuta"] = 0;
        $data["OznakaValute"] = null;
        $data["Tecaj"] = 0.000000;

        $items = $order->getOrderItems();
        /** @var OrderItemEntity $item */
        foreach ($items as $item) {

            /**
             * Check product type
             */
            /** @var ProductEntity $product */
            $product = $item->getProduct();

            if (in_array($product->getProductTypeId(), array(CrmConstants::PRODUCT_TYPE_CONFIGURABLE, CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE))) {
                continue;
            }

            $d = array();

            $d["SifraKosare"] = strval($order->getIncrementId());

            $robaId = $product->getRemoteId();
            if (empty($robaId)) {
                $d["RobaID"] = $_ENV["WAND_DEFAULT_ROBA_ID"];
            } else {
                $d["RobaID"] = $robaId;
            }
            $d["Ident"] = $product->getCatalogCode();
            $d["Naziv"] = $item->getName();
            $d["JM"] = null;
            $d["VPCijena"] = 0.00;
            $d["MPCijena"] = 0.00;
            $d["Kolicina"] = floatval($item->getQty());

            /*if ($isLegalEntity) {
                $d["BrutoIznos"] = number_format(
                    floatval($item->getOriginalBasePriceItemWithoutTax()) * floatval($item->getQty()),
                    2,
                    ",",
                    ""
                );
                if ($isLegalEntity && floatval($item->getOriginalRebate()) == 0) {
                    $d["BrutoIznos"] = number_format($item->getBasePriceWithoutTax(), 2, ".", "");
                }
            } else {*/
            $d["BrutoIznos"] = number_format($item->getBasePriceWithoutTax(), 2, ".", "");
            //}
            $d["Rabat"] = 0.00;
            $d["IznosRabata"] = 0.00;
            $d["VPIznos"] = number_format($item->getBasePriceWithoutTax(), 2, ".", "");
            $d["IznosPDV"] = 0.00;
            $d["Ambalaza"] = 0.00;
            /*if(!empty($item->getPriceReturnTotal()) && $item->getPriceReturnTotal() > 0){
                $d["Ambalaza"] = number_format($item->getPriceReturnTotal(), 2, ",", "");
            }*/
            $d["Trosarina"] = 0.00;
            /*if ($isLegalEntity) {
                $d["MPIznos"] = "0,00";
            } else {*/
            $d["MPIznos"] = number_format($item->getBasePriceTotal(), 2, ".", "");
            //}
            $d["DateCreated"] = $order->getCreated()->format("Y-m-d\TH:i:s.u");
            $d["Izvor"] = 0;
            $d["Napomena"] = null;
            if (empty($robaId)) {
                $d["Napomena"] = $product->getCatalogCode() . " - " . $product->getCode() . " - " . $item->getName();
            }
            $d["PartnerID"] = 0;

            $data["Kosara"][] = $d;
            //}
        }

        if (floatval($order->getBasePriceDeliveryTotal()) > 0) {
            $d = array();

            $d["SifraKosare"] = strval($order->getIncrementId());

            $d["RobaID"] = $_ENV["WAND_DELIVERY_1_ROBA_ID"];
            $d["Ident"] = $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $order->getDeliveryType(), "name");
            $d["Naziv"] = $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $order->getDeliveryType(), "name");
            $d["JM"] = null;
            $d["VPCijena"] = 0.00;
            $d["MPCijena"] = 0.00;
            $d["Kolicina"] = 1.00;
            $d["BrutoIznos"] = number_format($order->getBasePriceDeliveryWithoutTax(), 2, ".", "");
            $d["Rabat"] = 0.00;
            $d["IznosRabata"] = 0.00;
            $d["VPIznos"] = number_format($order->getBasePriceDeliveryWithoutTax(), 2, ".", "");
            $d["IznosPDV"] = 0.00;
            $d["Ambalaza"] = 0.00;
            $d["Trosarina"] = 0.00;
            /*if ($isLegalEntity) {
                $d["MPIznos"] = "0,00";
            } else {*/
            $d["MPIznos"] = number_format($order->getBasePriceDeliveryTotal(), 2, ".", "");
            //}
            $d["DateCreated"] = $order->getCreated()->format("Y-m-d\TH:i:s.u");
            $d["Izvor"] = 0;
            $d["Napomena"] = null;
            $d["PartnerID"] = 0;

            $data["Kosara"][] = $d;
        }

        if (floatval($order->getBasePriceFee()) > 0) {
            $d = array();

            $d["SifraKosare"] = strval($order->getIncrementId());

            $d["RobaID"] = $_ENV["WAND_PAYMENT_FEE_1_ROBA_ID"];
            $d["Ident"] = "Naknada za plaćanje pouzećem";
            $d["Naziv"] = "Naknada za plaćanje pouzećem";
            $d["JM"] = null;
            $d["VPCijena"] = 0.00;
            $d["MPCijena"] = 0.00;
            $d["Kolicina"] = 1.00;
            $d["BrutoIznos"] = number_format($order->getBasePriceFee() / 1.25, 2, ".", "");
            $d["Rabat"] = 0.00;
            $d["IznosRabata"] = 0.00;
            $d["VPIznos"] = number_format($order->getBasePriceFee() / 1.25, 2, ".", "");
            $d["IznosPDV"] = 0.00;
            $d["Ambalaza"] = 0.00;
            $d["Trosarina"] = 0.00;
            /*if ($isLegalEntity) {
                $d["MPIznos"] = "0,00";
            } else {*/
            $d["MPIznos"] = number_format(($order->getBasePriceFee()), 2, ".", "");
            //}
            $d["DateCreated"] = $order->getCreated()->format("Y-m-d\TH:i:s.u");
            $d["Izvor"] = 0;
            $d["Napomena"] = null;
            $d["PartnerID"] = 0;

            $data["Kosara"][] = $d;
        }

        $data = json_encode($data);

        return $data;
    }

    /**
     * @param bool $debug
     * @return bool
     */
    public function triggerErpToPullOrders($debug = false)
    {
        $komunikatorUcitajNarudzbu = $_ENV["WAND_KOMUNIKATOR_UCITAJ_NARUDZBU"] ?? 0;
        if (!$komunikatorUcitajNarudzbu) {
            print "WAND_KOMUNIKATOR_UCITAJ_NARUDZBU je iskljucen";
            return false;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($this->wandApiManager)) {
            $this->wandApiManager = $this->container->get("wand_api_manager");
        }

        $ret = $this->wandApiManager->apiKomunikatorUcitajNarudzbu();

        if (isset($ret["error"]) && $ret["error"] == false) {

            $q = "SELECT id FROM order_entity WHERE sent_to_erp is null or sent_to_erp = 0;";
            $orders = $this->databaseContext->getAll($q);

            if ($debug) {
                dump($orders);
                die;
            }

            if (empty($orders)) {
                return true;
            }

            $ids = array_column($orders, "id");
            $q = "UPDATE order_entity SET sent_to_erp = 1, date_sent_to_erp = NOW() WHERE id in (" . implode(",", $ids) . ");";
            $this->databaseContext->executeNonQuery($q);
        } else {

            /*if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $this->errorLogManager->logErrorEvent("Wand komunikator nije dostupan", null, true);*/
        }

        return $ret;
    }

    /**
     * @param $changedProducts
     * @param $importType
     * @return bool
     */
    public function afterImportCompleted($changedProducts, $importType)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE product_entity SET qty_step = 1 WHERE qty_step != 1 and measure IN ('kom','set');";
        $this->databaseContext->executeNonQuery($q);

        if (empty($this->brandsManager)) {
            $this->brandsManager = $this->container->get("brands_manager");
        }

        $changedProductBrandIds = $this->brandsManager->syncBrandsWithSProductAttributeConfigurationOptions();

        if (!empty($changedProductBrandIds)) {
            $changedProducts["product_ids"] = array_merge($changedProducts["product_ids"], $changedProductBrandIds);
            $changedProducts["product_ids"] = array_unique($changedProducts["product_ids"]);
        }

        /**
         * Set new as
         */
        $q = "UPDATE product_entity SET date_new_to = date_add(created,interval 2 week) WHERE date_new_to is null;";
        $this->databaseContext->executeNonQuery($q);
        /**
         * End set new as
         */

        if (!empty($changedProducts)) {
            $this->recalculateProductsPrices($changedProducts);
        }

        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->container->get("product_group_manager");
        }

        $this->productGroupManager->setNumberOfProductsInProductGroups();

        if (empty($this->sitemapManager)) {
            $this->sitemapManager = $this->container->get("sitemap_manager");
        }
        $this->sitemapManager->generateSitemapXML();

        if (!isset($_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"]) || $_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"] != 1) {
            $this->invalidateProductCacheForRecentlyModifiedProducts();
        }

        return true;
    }

    /**
     * @param ProductEntity $product
     * @param QuoteEntity $quote
     * @param $requestedQty
     * @param $add
     * @param QuoteItemEntity|null $quoteItem
     * @return array
     */
    public function validateQuoteItemQty(ProductEntity $product, QuoteEntity $quote, $requestedQty, $add, QuoteItemEntity $quoteItem = null)
    {
        $requestedQty = floatval($requestedQty);

        $ret = array();
        $ret["error"] = false;
        $ret["message"] = null;
        $ret["reload"] = false;
        $ret["qty"] = $requestedQty;
        $ret["add"] = $add;

        if ($product->getQtyStep() > 0 && $product->getQtyStep() != 1) {
            if (fmod($requestedQty, $product->getQtyStep()) != 0) {
                $ret["qty"] = $product->getQtyStep();
                $ret["add"] = false;
            }
        }

        /** Qty of same product in other quote items if exists */
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }
        $additionaWhere = "";
        if (!empty($quoteItem)) {
            /**
             *  Provjera stanja za configurabilne
             */
            if (!empty($quoteItem->getConfigurableProductOptions())) {
                $childItems = $quoteItem->getChildItems();
                if (EntityHelper::isCountable($childItems) && count($childItems)) {
                    $additionaWhere = " AND id != {$childItems[0]->getId()} ";
                }
            } else {
                $additionaWhere = " AND id != {$quoteItem->getId()} ";
            }
        }
        $q = "SELECT SUM(qty) as total_qty FROM quote_item_entity WHERE quote_id = {$quote->getId()} AND product_id = {$product->getId()} {$additionaWhere};";
        $totalCurrentQtyInQuote = $this->databaseContext->getSingleEntity($q);
        if (empty($totalCurrentQtyInQuote)) {
            $totalCurrentQtyInQuote = 0;
        } else {
            $totalCurrentQtyInQuote = $totalCurrentQtyInQuote["total_qty"];
        }

        if (empty($totalCurrentQtyInQuote)) {
            $totalCurrentQtyInQuote = 0;
        }

        $currentQuoteItemQty = 0;
        if (!empty($quoteItem)) {
            $currentQuoteItemQty = $this->prepareQty($quoteItem->getQty(), $product->getQtyStep());
        }

        /**
         * Get product attribute STATUS and defined if can be sold
         */
        $overrideQty = true;

        $status = $this->getIsOnOutlet($product);

        if (!empty($status)) {
            $overrideQty = false;
        }

        /**
         * If product is not saleable return error
         */
        if (!$overrideQty) {
            if (!$product->getIsSaleable()) {
                $ret["qty"] = 0;
                $ret["add"] = false;
                $ret["error"] = true;
                $ret["message"] = $ret["message"] . $this->translator->trans("This product is not available any more");

                return $ret;
            }
        }

        $maxQty = $this->prepareQty($product->getQty(), $product->getQtyStep());

        $availableQty = $maxQty - ($totalCurrentQtyInQuote + $currentQuoteItemQty);
        $availableQty = $this->prepareQty($availableQty, $product->getQtyStep());

        if ($overrideQty) {
            $availableQty = 99999999;
        }

        /**
         * If quote item exists and product is being added
         */
        if (!empty($quoteItem)) {

            /**
             * If we are adding new qty
             */
            if ($add) {
                if ($requestedQty > $availableQty) {
                    if ($availableQty < 0) {
                        $ret["qty"] = $currentQuoteItemQty;
                        $ret["add"] = false;
                        $ret["error"] = true;
                        $ret["message"] = $ret["message"] . $this->translator->trans("Current quantity in cart is not available any more");
                        return $ret;
                    } elseif ($availableQty == 0) {
                        $ret["qty"] = $currentQuoteItemQty;
                        $ret["add"] = false;
                        $ret["error"] = true;
                        $ret["message"] = $ret["message"] . $this->translator->trans("You have already added max quantity") . ". " . $this->translator->trans("Max quantity is") . ": " . $currentQuoteItemQty;
                        return $ret;
                    } else {
                        $ret["qty"] = $availableQty;
                        $ret["add"] = true;
                        $ret["error"] = false;
                        $ret["message"] = $ret["message"] . $this->translator->trans("Requested quantity is not available any more") . ". " . $this->translator->trans("Max quantity is") . ": " . $maxQty;
                        return $ret;
                    }
                } /**
                 * If $availableQty > $requestedQty add requestedQty
                 */
                else {
                    return $ret;
                }
            } /**
             * If we are checking qty
             */
            else {

                if ($availableQty < 0) {
                    $ret["qty"] = $maxQty;
                    $ret["add"] = false;
                    $ret["error"] = true;
                    $ret["message"] = $ret["message"] . $this->translator->trans("Requested quantity is not available any more") . ". " . $this->translator->trans("Max quantity is") . ": " . $maxQty;
                    return $ret;
                } elseif ($requestedQty <= $currentQuoteItemQty) {
                    return $ret;
                } elseif ($availableQty == 0) {
                    $ret["qty"] = $currentQuoteItemQty;
                    $ret["add"] = false;
                    $ret["error"] = true;
                    $ret["message"] = $ret["message"] . $this->translator->trans("You have already added max quantity") . ". " . $this->translator->trans("Max quantity is") . ": " . $currentQuoteItemQty;
                    return $ret;
                } elseif ($requestedQty > $availableQty) {
                    $ret["qty"] = $availableQty;
                    $ret["add"] = true;
                    $ret["error"] = false;
                    $ret["message"] = $ret["message"] . $this->translator->trans("Max quantity is") . ": " . $maxQty;
                    return $ret;
                }

                return $ret;
            }
        } /**
         * if quote item does not exist
         */
        else {
            if ($add) {
                if ($requestedQty > $availableQty) {
                    $ret["qty"] = $availableQty;
                    $ret["message"] = $ret["message"] . $this->translator->trans("Requested quantity is not available any more") . ". " . $this->translator->trans("Max quantity is") . ": " . $availableQty;
                }
            } else {
                throw new Exception("Update quote item on none existing quote");
            }
        }

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @return bool
     */
    public function getIsOnOutlet(ProductEntity $product)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $isOnOutlet = false;

        if (empty($this->attributeOptionIds)) {
            if (empty($this->applicationSettingsManager)) {
                $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
            }
            $this->attributeOptionIds = $this->applicationSettingsManager->getApplicationSettingByCode("outlet_attribute_values")[$_ENV["DEFAULT_STORE_ID"]];
        }

        $q = "SELECT id FROM s_product_attributes_link_entity WHERE s_product_attribute_configuration_id = 5 AND product_id = {$product->getId()} AND configuration_option IN ({$this->attributeOptionIds});";
        $status = $this->databaseContext->getAll($q);

        if (!empty($status)) {
            $isOnOutlet = true;
        }

        return $isOnOutlet;
    }

    /**
     * @param QuoteEntity $quote
     * @param DeliveryTypeEntity $deliveryType
     * @param $countryId
     * @param $postalCode
     * @return \CrmBusinessBundle\Entity\decimal|float|int
     */
    public function calculateDelivery(QuoteEntity $quote, DeliveryTypeEntity $deliveryType, $countryId, $postalCode)
    {

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        $totalBasePriceDeliveryWithoutTax = 0;

        $size = 0;
        $applyFreeDelivery = false;

        /**
         * Apply coupon free delivery if coupon exists
         */
        if (!empty($quote->getDiscountCoupon()) && $quote->getDiscountCoupon()->getForceFreeDelivery()) {
            return $totalBasePriceDeliveryWithoutTax;
        }

        $totalQtyForFixedDeliveryPrice = 0;

        /**
         * If price
         */
        //$size = floatval($quote->getBasePriceItemsTotal()) - floatval($quote->getDiscountLoyaltyBasePriceTotal()) - floatval($quote->getDiscountCouponPriceTotal());
        /**
         * If weight
         */
        $quoteItems = $quote->getQuoteItems();
        if (EntityHelper::isCountable($quoteItems) && count($quoteItems) > 0) {
            foreach ($quoteItems as $quoteItem) {
                if ($quoteItem->getProduct()->getUseFixedDeliveryPrice()) {
                    //$totalQtyForFixedDeliveryPrice = $totalQtyForFixedDeliveryPrice + $quoteItem->getQty();
                    $totalQtyForFixedDeliveryPrice = 1;
                } else {
                    $size = $size + ($quoteItem->getQty() * $quoteItem->getProduct()->getWeight());
                }
            }
        }

        if ($size > 0) {
            /** @var DeliveryPricesEntity $deliveryPrice */
            $deliveryPrice = $this->quoteManager->getDeliveryPrice($deliveryType, $countryId, $postalCode, $size);

            if (!empty($deliveryPrice) && !$applyFreeDelivery) {

                $totalBasePriceDeliveryWithoutTax = $totalBasePriceDeliveryWithoutTax + $deliveryPrice->getPriceBase();

                if ($deliveryPrice->getPriceBaseStep() > 0 && $deliveryPrice->getForEveryNextSize() > 0 && $deliveryPrice->getStepStartsAt() && $deliveryPrice->getStepStartsAt() < floatval($size)) {
                    $size = floatval($size) - floatval($deliveryPrice->getStepStartsAt());

                    $steps = ceil($size / floatval($deliveryPrice->getForEveryNextSize()));

                    $totalBasePriceDeliveryWithoutTax = $totalBasePriceDeliveryWithoutTax + $deliveryPrice->getPriceBaseStep() * $steps;
                }
            }
        }

        /**
         * Add fixed delivery price for other items
         */
        if ($totalQtyForFixedDeliveryPrice > 0 && !$applyFreeDelivery) {
            if (empty($this->fixedPriceDelivery)) {
                if (empty($this->applicationSettingsManager)) {
                    $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
                }
                $this->fixedPriceDelivery = intval($this->applicationSettingsManager->getApplicationSettingByCode("fixed_price_delivery")[$_ENV["DEFAULT_STORE_ID"]]);
            }

            $totalBasePriceDeliveryWithoutTax = $totalBasePriceDeliveryWithoutTax + ($totalQtyForFixedDeliveryPrice * floatval($this->fixedPriceDelivery));
        }

        return $totalBasePriceDeliveryWithoutTax;
    }

    /**
     * @param ProductEntity $product
     * @return \CrmBusinessBundle\Entity\decimal
     */
    public function getCustomProductQty(ProductEntity $product)
    {

        $qty = 99999999;

        $status = $this->getIsOnOutlet($product);

        if (!empty($status)) {
            $qty = $product->getQty();
        }

        return $qty;
    }

    /**
     * @param ProductEntity $product
     * @return bool
     */
    public function getCustomProductIsSaleable(ProductEntity $product)
    {

        $isSaleable = 1;

        $status = $this->getIsOnOutlet($product);

        if (!empty($status)) {
            $isSaleable = $product->getIsSaleable();
        }

        return $isSaleable;
    }

    /**
     * @param NewsletterEntity $subscription
     * @param $isNew
     * @return bool
     */
    public function afterNewsletterSubscriptionChanged(NewsletterEntity $subscription, $isNew)
    {

        if ($isNew) {
            if (empty($this->automationsManager)) {
                $this->automationsManager = $this->container->get("automations_manager");
            }

            $data = array();
            $data["newsletter"] = $subscription;

            if (empty($this->emailTemplateManager)) {
                $this->emailTemplateManager = $this->container->get("email_template_manager");
            }

            /** @var EmailTemplate $emailTemplate */
            $emailTemplate = $this->emailTemplateManager->getEmailTemplateByCode("newsletter_popust");

            if (empty($emailTemplate)) {
                if (empty($this->errorLogManager)) {
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }

                $this->errorLogManager->logErrorEvent("Missinge email template for newsletter_popust", null, true);
            }

            $this->automationsManager->sendCouponEmail($subscription->getEmail(), "newsletter_application", $subscription->getStoreId(), $data, $emailTemplate);
        }

        return true;
    }

    /**
     * @param $data
     */
    public function wandFilterProductsOnImport($data)
    {

        if (intval($data["cjenik7"]) != 1) {
            return array("robaID" => null);
        }

        if (isset($data["jmTezine"]) && !empty($data["jmTezine"])) {
            if ($data["jmTezine"] != "kg") {
                if ($data["jmTezine"] == "g") {
                    $data["tezinaPak"] = floatval($data["tezinaPak"]) / 1000;
                }
            }
        }

        return $data;
    }

    /**
     * @param $qty
     * @param $qtyStep
     * @return int
     */
    public function prepareQty($qty, $qtyStep = null, $item = null)
    {
        if (!empty($qtyStep) && fmod($qtyStep, 1) != 0) {
            return number_format($qty, 2, ".", "");
        }
        return number_format($qty, 0, "", "");
    }
}
