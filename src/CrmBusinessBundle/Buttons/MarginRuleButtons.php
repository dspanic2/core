<?php

namespace CrmBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use CrmBusinessBundle\Entity\MarginRuleEntity;
use CrmBusinessBundle\Managers\MarginRulesManager;

class MarginRuleButtons extends AbstractBaseButtons
{
    /** @var MarginRulesManager $marginRulesManager */
    protected $marginRulesManager;

    public function GetListPageButtons(){

        $buttons[] = array(
            "type" => "button",
            "name" => $this->translator->trans("Recalculate margin rules"),
            "class" => "btn-primary btn-red",
            "url" => "recalculate_margin_rules",
            "action" => "button_default",
            "id" => 1
        );

        return $buttons;
    }

    public function GetFormPageButtons(){

        $data = $this->getData();

        $buttons = Array();

        if(isset($data["id"]) && !empty($data["id"])) {

            if(empty($this->marginRulesManager)){
                $this->marginRulesManager = $this->getContainer()->get("margin_rules_manager");
            }

            /** @var MarginRuleEntity $marginRule */
            $marginRule = $this->marginRulesManager->getMarginRuleById($data["id"]);

            if($marginRule->getRecalculate()){
                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("Recalculate margin rules"),
                    "class" => "btn-primary btn-red",
                    "url" => "recalculate_margin_rules",
                    "action" => "button_default",
                    "id" => $data["id"]
                );
            }
        }

        $buttons = array_merge($buttons,$this->getDefaultFormButtons());

        return $buttons;
    }
}