<?php

namespace CrmBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Managers\OrderManager;

class OrderButtons extends AbstractBaseButtons
{
    /** @var OrderManager $orderManager */
    protected $orderManager;

    public function GetFormPageButtons(){

        $data = $this->getData();

        if(isset($data["id"]) && !empty($data["id"])){

            $buttons = Array();

            if(empty($this->orderManager)){
                $this->orderManager = $this->getContainer()->get("order_manager");
            }

            /** @var OrderEntity $order */
            $order = $this->orderManager->getOrderById($data["id"]);

            if (in_array($order->getOrderStateId(),Array(CrmConstants::ORDER_STATE_NEW,CrmConstants::ORDER_STATE_IN_PROCESS))) {
                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("Cancel"),
                    "class" => "btn-primary btn-red",
                    "url" => "order_state_canceled",
                    "action" => "default_form_button"
                );
            }
            if (in_array($order->getOrderStateId(),Array(CrmConstants::ORDER_STATE_NEW))) {
                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("Set in process"),
                    "class" => "btn-primary btn-red",
                    "url" => "order_state_in_process",
                    "action" => "default_form_button"
                );
            }
            if (in_array($order->getOrderStateId(),Array(CrmConstants::ORDER_STATE_IN_PROCESS))) {
                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("Completed"),
                    "class" => "btn-primary btn-red",
                    "url" => "order_state_completed",
                    "action" => "default_form_button"
                );
            }
            if (in_array($order->getOrderStateId(),Array(CrmConstants::ORDER_STATE_COMPLETED))) {
                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("Reversal"),
                    "class" => "btn-primary btn-red",
                    "url" => "order_state_reversal",
                    "action" => "default_form_button"
                );
            }

            $buttons[] = array(
                "type" => "button",
                "name" => $this->translator->trans("PDF"),
                "class" => "btn-primary btn-red",
                "url" => "order_generate_pdf",
                "action" => "order_generate_pdf"
            );


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