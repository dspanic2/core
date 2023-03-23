<?php

namespace CrmBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use AppBundle\Managers\ApplicationSettingsManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\QuoteManager;

class QuoteButtons extends AbstractBaseButtons
{
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    protected $applicationSettingsManager;

    public function GetFormPageButtons(){

        /** @var Entity\Page $page */

        /**
         * Ovo vise ne bi trebalo biti potrebno
         */
        /*$page = $this->getPage();

        $defaultButtons = '[{"type":"button","name":"Accept quote","class":"btn-primary btn-red hidden","url":"quote_admin_accept","action":"quote_admin_accept"},{"type":"button","name":"View order","class":"btn-default btn-red","url":"","action":"view_order"},{"type":"button","name":"Send to client","class":"btn-primary btn-red","url":"send_to_client_form","action":"send_email"},{"type":"button","name":"Save","class":"btn-primary btn-blue","url":"","action":"return"},{"type":"button","name":"Save and continue","class":"btn-primary btn-blue","url":"","action":"continue"},{"type":"button","name":"Preview","class":"btn-primary btn-blue","url":"","action":"preview"},{"type":"button","name":"Download","class":"btn-primary btn-blue","url":"","action":"/quote-generate-pdf"},{"type":"link","name":"Back","class":"btn-default btn-red","url":"","action":"back"}]';
        $existingButtons = str_replace(array("\n", "\r"), '', $page->getButtons());
        if(!empty($existingButtons) && $existingButtons != $defaultButtons){
            return json_decode($page->getButtons(),true);
        }*/

        $data = $this->getData();

        if(isset($data["id"]) && !empty($data["id"])){

            $buttons = Array();

            if(empty($this->quoteManager)){
                $this->quoteManager = $this->getContainer()->get("quote_manager");
            }

            /** @var QuoteEntity $quote */
            $quote = $this->quoteManager->getQuoteById($data["id"]);

            $hasMandatoryData = false;
            if(!empty($quote->getAccount()) && !empty($quote->getContact()) && !empty($quote->getAccountBillingAddress()) && count($quote->getQuoteItems()) > 0 && !empty($quote->getPaymentType()) && !empty($quote->getDeliveryType())){
                if($quote->getDeliveryType()->getIsDelivery() && empty($quote->getAccountShippingAddress())){
                    $hasMandatoryData = false;
                }
                else{
                    $hasMandatoryData = true;
                }
            }

            if($hasMandatoryData) {
                if (in_array($quote->getQuoteStatusId(), array(CrmConstants::QUOTE_STATUS_NEW, CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT))) {
                    $buttons[] = array(
                        "type" => "button",
                        "name" => $this->translator->trans("Accept quote"),
                        "class" => "btn-primary btn-red",
                        "url" => "quote_admin_accept",
                        "action" => "default_form_button"
                    );
                    $buttons[] = array(
                        "type" => "button",
                        "name" => $this->translator->trans("Reject quote"),
                        "class" => "btn-primary btn-red",
                        "url" => "quote_admin_reject",
                        "action" => "default_form_button"
                    );

                    if (empty($this->applicationSettingsManager)) {
                        $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
                    }

                    $quotePreviewEnabled = intval($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("quote_preview_enable", $_ENV["DEFAULT_STORE_ID"]));

                    if($quotePreviewEnabled){
                        $buttons[] = array(
                            "type" => "button",
                            "name" => $this->translator->trans("Send to client"),
                            "class" => "btn-primary btn-red",
                            "url" => "send_to_client_form",
                            "action" => "send_to_client_form"
                        );
                        $buttons[] = array(
                            "type" => "button",
                            "name" => $this->translator->trans("Preview"),
                            "class" => "btn-primary btn-blue",
                            "url" => "quote_preview",
                            "action" => "quote_preview"
                        );
                    }
                    else{
                        $buttons[] = array(
                            "type" => "button",
                            "name" => $this->translator->trans("Send to client"),
                            "class" => "btn-primary btn-red btn-disabled",
                            "url" => "send_to_client_form",
                            "action" => ""
                        );
                        $buttons[] = array(
                            "type" => "button",
                            "name" => $this->translator->trans("Preview"),
                            "class" => "btn-primary btn-blue btn-disabled",
                            "url" => "quote_preview",
                            "action" => ""
                        );
                    }
                }

                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("PDF"),
                    "class" => "btn-primary btn-blue",
                    "url" => "quote_download",
                    "action" => "quote_download"
                );
            }

            if(in_array($quote->getQuoteStatusId(),Array(CrmConstants::QUOTE_STATUS_ACCEPTED))){
                if(empty($this->orderManager)){
                    $this->orderManager = $this->container->get("order_manager");
                }

                /** @var OrderEntity $order */
                $order = $this->orderManager->getOrderByQuoteId($quote->getId());

                if(!empty($order)){
                    $buttons[] = Array(
                        "type" => "custom_link",
                        "name" => $this->translator->trans("View order"),
                        "class" => "btn-default btn-red",
                        "url" => "/page/order/form/{$order->getId()}",
                        "action" => ""
                    );
                }
            }
            else{
                $buttons[] = Array(
                    "type" => "button",
                    "name" => $this->translator->trans("Save"),
                    "class" => "btn-primary btn-blue",
                    "url" => "",
                    "action" => "return"
                );
                $buttons[] = Array(
                    "type" => "button",
                    "name" => $this->translator->trans("Save and continue"),
                    "class" => "btn-primary btn-blue",
                    "url" => "",
                    "action" => "continue"
                );
            }

            $buttons[] = Array(
                "type" => "link",
                "name" => $this->translator->trans("Back"),
                "class" => "btn-default btn-red",
                "url" => "",
                "action" => "back"
            );
        }
        else{
            $buttons = $this->getDefaultFormButtons();
        }

        return $buttons;
    }
}
