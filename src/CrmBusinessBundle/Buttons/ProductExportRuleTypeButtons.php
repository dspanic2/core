<?php

namespace CrmBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;

class ProductExportRuleTypeButtons extends AbstractBaseButtons
{
    public function GetFormPageButtons(){

        $data = $this->getData();

        $buttons = Array();

        if(isset($data["id"]) && !empty($data["id"])) {

            $buttons[] = array(
                "type" => "button",
                "name" => $this->translator->trans("Generate export"),
                "class" => "btn-primary btn-red",
                "url" => "product_export_generate",
                "action" => "button_default",
                "id" => $data["id"]
            );
        }

        $buttons = array_merge($buttons,$this->getDefaultFormButtons());

        return $buttons;
    }
}