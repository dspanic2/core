<?php

namespace CrmBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use CrmBusinessBundle\Entity\LoyaltyEarningsConfigurationEntity;
use CrmBusinessBundle\Managers\LoyaltyManager;

class LoyaltyEarningsConfigurationButtons extends AbstractBaseButtons
{
    /** @var LoyaltyManager $loyaltyManager */
    protected $loyaltyManager;

    public function GetListPageButtons(){

        $buttons[] = array(
            "type" => "button",
            "name" => $this->translator->trans("Recalculate loyalty earnings configuration"),
            "class" => "btn-primary btn-red",
            "url" => "recalculate_loyalty_earnings",
            "action" => "button_default",
            "id" => 1
        );

        return $buttons;
    }

    public function GetFormPageButtons(){

        $data = $this->getData();

        $buttons = Array();

        if(isset($data["id"]) && !empty($data["id"])) {

            if(empty($this->loyaltyManager)){
                $this->loyaltyManager = $this->getContainer()->get("loyalty_manager");
            }

            /** @var LoyaltyEarningsConfigurationEntity $loyaltyEarningConfigurationEntity */
            $loyaltyEarningConfigurationEntity = $this->loyaltyManager->getLoyaltyEarningConfigurationById($data["id"]);

            if($loyaltyEarningConfigurationEntity->getRecalculate()){
                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("Recalculate loyalty earnings configuration"),
                    "class" => "btn-primary btn-red",
                    "url" => "recalculate_loyalty_earnings",
                    "action" => "button_default",
                    "id" => $data["id"]
                );
            }
        }

        $buttons = array_merge($buttons,$this->getDefaultFormButtons());

        return $buttons;
    }
}