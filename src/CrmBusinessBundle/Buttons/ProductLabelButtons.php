<?php

namespace CrmBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use CrmBusinessBundle\Entity\ProductLabelEntity;
use CrmBusinessBundle\Managers\ProductLabelRulesManager;

class ProductLabelButtons extends AbstractBaseButtons
{
    /** @var ProductLabelRulesManager $productLabelRulesManager */
    protected $productLabelRulesManager;

    public function GetListPageButtons(){

        $buttons[] = array(
            "type" => "button",
            "name" => $this->translator->trans("Recalculate product labels"),
            "class" => "btn-primary btn-red",
            "url" => "recalculate_product_labels",
            "action" => "button_default",
            "id" => 1
        );

        return $buttons;
    }

    public function GetFormPageButtons(){

        $data = $this->getData();

        $buttons = Array();

        if(isset($data["id"]) && !empty($data["id"])) {

            if(empty($this->productLabelRulesManager)){
                $this->productLabelRulesManager = $this->container->get("product_label_rules_manager");
            }

            /** @var ProductLabelEntity $productLabel */
            $productLabel = $this->productLabelRulesManager->getProductLabelById($data["id"]);

            if($productLabel->getRecalculate()){
                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("Recalculate product labels"),
                    "class" => "btn-primary btn-red",
                    "url" => "recalculate_product_labels",
                    "action" => "button_default",
                    "id" => $data["id"]
                );
            }
        }

        $buttons = array_merge($buttons,$this->getDefaultFormButtons());

        return $buttons;
    }
}