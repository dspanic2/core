<?php

namespace ScommerceBusinessBundle\Traits;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\DeliveryTypeEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\QuoteManager;

trait CartTrait
{
    /** @var DefaultCrmProcessManager */
    protected $crmProcessManager;
    /** @var CacheManager */
    protected $cacheManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;

    /**
     * @return array
     * @throws \Exception
     */
    protected function prepareCartData()
    {
        $data = [];
        $data["shipping_country_id"] = $_ENV["DEFAULT_COUNTRY"];
        $data["country_id"] = $_ENV["DEFAULT_COUNTRY"];

        $blockData["model"]["quote"] = null;
        $blockData["model"]["contact"] = null;
        $blockData["model"]["account"] = null;
        $blockData["model"]["user_logged_in"] = false;
        $blockData["model"]["payment_types"] = null;
        $blockData["model"]["delivery_types"] = null;
        $blockData["model"]["payment_data"] = null;

        /** Ovo bi trebalo deprecated */
        //$blockData["model"]["default_country"] = null;

        /** Ovo bi trebalo deprecated */
        //$blockData["model"]["default_billing_address"] = null;

        $blockData["model"]["additional_data"] = null;
        $blockData["model"]["default_payment_type"] = null;
        $blockData["model"]["default_delivery_type"] = null;
        $blockData["model"]["billing_city"] = null;
        $blockData["model"]["billing_country"] = null;
        $blockData["model"]["billing_address"] = null;
        $blockData["model"]["shipping_city"] = null;
        $blockData["model"]["shipping_country"] = null;
        $blockData["model"]["shipping_address"] = null;

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->getContainer()->get("quote_manager");
        }
        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);

        if (!empty($quote)) {

            $websiteId = $_ENV["DEFAULT_WEBSITE_ID"];
            if (!empty($quote->getStoreId())) {
                $websiteId = $quote->getStore()->getWebsiteId();
            }

            if (empty($this->helperManager)) {
                $this->helperManager = $this->getContainer()->get("helper_manager");
            }

            /** @var CoreUserEntity $user */
            $user = $this->helperManager->getCurrentCoreUser();

            if (!empty($user)) {
                $contact = $user->getDefaultContact();
                $account = $contact->getAccount();
                $blockData["model"]["user_logged_in"] = true;
            } else {
                $contact = $quote->getContact();
                $account = $quote->getAccount();
            }

            $blockData["model"]["contact"] = $contact;
            $blockData["model"]["account"] = $account;

            /**
             * TODO ovo treba letit van vjerojatno 22.06.2022
             * @deprecated
             */
            /*if (!empty($quote->getContact()) && !empty($quote->getAccount()) && !empty($quote->getAccountBillingAddress()) && EntityHelper::isCountable($quote->getQuoteItems()) && count($quote->getQuoteItems()) > 0) {
                $blockData["model"]["payment_data"] = $this->crmProcessManager->getQuoteButtons($quote);
            }*/

            /**
             * Get adresses
             */
            /** @var AddressEntity $billingAddress */
            $billingAddress = $quote->getAccountBillingAddress();
            if (empty($billingAddress) && !empty($account)) {
                $billingAddress = $account->getBillingAddress();
            }

            /** @var AddressEntity $shippingAddress */
            $shippingAddress = $quote->getAccountShippingAddress();
            if (empty($shippingAddress) && !empty($account)) {
                $shippingAddress = $account->getShippingAddress();
            }

            if (empty($shippingAddress)) {
                $shippingAddress = $billingAddress;
            }

            if (!empty($billingAddress)) {
                $blockData["model"]["billing_city"] = $billingAddress->getCity();
                if (!empty($billingAddress->getCity())) {
                    $blockData["model"]["billing_country"] = $billingAddress->getCity()->getCountry();
                    $data["country_id"] = $billingAddress->getCity()->getCountryId();
                }
                $blockData["model"]["default_billing_address"] = $blockData["model"]["billing_address"] = $billingAddress;
            }

            /**
             * Set default country from settings if empty
             */
            if (empty($blockData["model"]["billing_country"])) {

                if (empty($this->applicationSettingsManager)) {
                    $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
                }

                $defaultCountryId = $this->applicationSettingsManager->getApplicationSettingByCodeAndStore("default_country_id", $quote->getStore());
                if (!empty($defaultCountryId)) {
                    if (empty($this->accountManager)) {
                        $this->accountManager = $this->getContainer()->get("account_manager");
                    }

                    $blockData["model"]["billing_country"] = $this->accountManager->getCountryById($defaultCountryId);
                }
            }

            if (!empty($shippingAddress)) {
                $blockData["model"]["shipping_city"] = $shippingAddress->getCity();
                if (!empty($shippingAddress->getCity())) {
                    $blockData["model"]["shipping_country"] = $shippingAddress->getCity()->getCountry();
                    $data["shipping_country_id"] = $shippingAddress->getCity()->getCountryId();
                }
                $blockData["model"]["shipping_address"] = $shippingAddress;
            }

            if (empty($blockData["model"]["shipping_country"])) {
                $blockData["model"]["shipping_country"] = $blockData["model"]["billing_country"];
            }

            /**
             * Get selected or default delivery type
             */
            /** @var DeliveryTypeEntity $selectedDeliveryType */
            $selectedDeliveryType = null;
            $availableDeliveryTypeIds = array();
            $blockData["model"]["delivery_types"] = $this->crmProcessManager->getAvailableDeliveryTypes($quote, $data);
            /** @var DeliveryTypeEntity $deliveryType */
            foreach ($blockData["model"]["delivery_types"] as $deliveryType) {
                $availableDeliveryTypeIds[] = $deliveryType->getId();
                $useAsDefault = $deliveryType->getUseAsDefault();
                if (isset($useAsDefault[$websiteId]) && $useAsDefault[$websiteId] == 1) {
                    $selectedDeliveryType = $deliveryType;
                }
            }
            /**
             * @deprecated
             * ovo samo smeta
             */
            /*if (empty($selectedDeliveryType)) {
                $selectedDeliveryType = $blockData["model"]["delivery_types"][0];
            }*/

            if (!empty($quote->getDeliveryType())) {
                if (in_array($quote->getDeliveryTypeId(), $availableDeliveryTypeIds)) {
                    $selectedDeliveryType = $quote->getDeliveryType();
                }
            }

            $blockData["model"]["delivery_type"] = $blockData["model"]["default_delivery_type"] = $selectedDeliveryType;
            if (!empty($selectedDeliveryType)) {
                $data["delivery_type_id"] = $selectedDeliveryType->getId();
            }

            /**
             * Get selected or default payment type
             */
            /** @var PaymentTypeEntity $selectedPaymentType */
            $selectedPaymentType = null;
            $availablePaymentTypeIds = array();
            $blockData["model"]["payment_types"] = $this->crmProcessManager->getAvailablePaymentTypes($quote, $data);

            if (!empty($blockData["model"]["payment_types"])) {
                /** @var PaymentTypeEntity $paymentType */
                foreach ($blockData["model"]["payment_types"] as $paymentType) {
                    $availablePaymentTypeIds[] = $paymentType->getId();
                    $useAsDefault = $paymentType->getUseAsDefault();
                    if (isset($useAsDefault[$websiteId]) && $useAsDefault[$websiteId] == 1) {
                        $selectedPaymentType = $paymentType;
                    }
                }
                if (empty($selectedPaymentType)) {
                    $selectedPaymentType = $blockData["model"]["payment_types"][0];
                }

                if (!empty($quote->getPaymentType())) {
                    if (in_array($quote->getPaymentTypeId(), $availablePaymentTypeIds)) {
                        $selectedPaymentType = $quote->getPaymentType();
                    }
                }

                $blockData["model"]["payment_type"] = $blockData["model"]["default_payment_type"] = $selectedPaymentType;
                if (!empty($selectedPaymentType)) {
                    $data["payment_type_id"] = $selectedPaymentType->getId();
                }
            }

            /**
             * Validate coupon and remove it if does not exist
             */
            if (!empty($quote->getDiscountCoupon())) {
                $isValid = $this->crmProcessManager->checkIfDiscountCouponCanBeApplied($quote, $quote->getDiscountCoupon());
                if (!$isValid) {

                    $session = $this->container->get('session');

                    $session->set(
                        "quote_error",
                        $this->translator->trans("Discount coupon cannot be applied or is not valid any more")
                    );

                    $this->crmProcessManager->applyDiscountCoupon($quote, null);
                }
            }

            /**
             * Remove loyalty card from cart
             */
            if(!empty($quote->getLoyaltyCard()) && empty($user)){
                $quoteLoyaltyData["loyalty_card_id"] = null;
                $this->quoteManager->updateQuote($quote,$quoteLoyaltyData);
            }

            /**
             * Calculate preliminary delivery price
             */
            if (!empty($selectedDeliveryType) && !empty($shippingAddress)) {

                $quoteData["delivery_type_id"] = $selectedDeliveryType->getId();
                $quoteData["shipping_address_same"] = 0;
                $quoteData["shipping_city_id"] = $shippingAddress->getCityId();
                $quoteData["shipping_country_id"] = $shippingAddress->getCity()->getCountryId();

                $quote = $this->crmProcessManager->calculateQuoteDeliveryPrice($quote, true, $quoteData);
            }

            /**
             * Failsafe ako se promijeni cijena dok si u cartu
             */
            $quote = $this->quoteManager->recalculateQuoteItems($quote);

            $blockData["model"]["additional_data"] = $this->crmProcessManager->getAdditionalQuoteData($quote);
            $blockData["model"]["quote"] = $quote;
        }

        /**
         * @deprecated
         */
        /*if (empty($blockData["model"]["default_country"])) {

            if (empty($this->accountManager)) {
                $this->accountManager = $this->getContainer()->get("account_manager");
            }

            $blockData["model"]["default_country"] = $this->accountManager->getCountryById($_ENV["DEFAULT_COUNTRY"] ?? 1);
        }*/

        return $blockData;
    }
}
