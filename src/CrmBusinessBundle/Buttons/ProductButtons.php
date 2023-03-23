<?php

namespace CrmBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;

class ProductButtons extends AbstractBaseButtons
{
    public function GetListPageButtons(){

        $buttons = Array();

        $buttons[] = array(
            "type" => "button",
            "name" => $this->translator->trans("Filtered product XLS"),
            "class" => "btn-primary btn-red",
            "url" => "product_export_default",
            "action" => "button_list_filtered"
        );

        $buttons[] = array(
            "type" => "button",
            "name" => $this->translator->trans("Filtered product attributes XLS"),
            "class" => "btn-primary btn-red",
            "url" => "product_export_attributes_default",
            "action" => "button_list_filtered"
        );

        return $buttons;
    }


}