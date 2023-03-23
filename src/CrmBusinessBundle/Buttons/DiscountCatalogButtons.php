<?php

namespace CrmBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use CrmBusinessBundle\Entity\DiscountCatalogEntity;
use CrmBusinessBundle\Managers\DiscountRulesManager;

class DiscountCatalogButtons extends AbstractBaseButtons
{
    /** @var DiscountRulesManager $discountRulesManager */
    protected $discountRulesManager;

    public function GetListPageButtons(){

        $buttons[] = array(
            "type" => "button",
            "name" => $this->translator->trans("Recalculate discounts"),
            "class" => "btn-primary btn-red",
            "url" => "recalculate_discounts",
            "action" => "button_default",
            "id" => 1
        );

        return $buttons;
    }

    public function GetFormPageButtons(){

        $data = $this->getData();

        $buttons = Array();

        if(isset($data["id"]) && !empty($data["id"])) {

            if(empty($this->discountRulesManager)){
                $this->discountRulesManager = $this->getContainer()->get("discount_rules_manager");
            }

            /** @var DiscountCatalogEntity $discountCatalog */
            $discountCatalog = $this->discountRulesManager->getDiscountCatalogById($data["id"]);

            if($discountCatalog->getRecalculate()){
                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("Recalculate discounts"),
                    "class" => "btn-primary btn-red",
                    "url" => "recalculate_discounts",
                    "action" => "button_default",
                    "id" => $data["id"]
                );
            }
        }

        $buttons = array_merge($buttons,$this->getDefaultFormButtons());

        return $buttons;
    }
}