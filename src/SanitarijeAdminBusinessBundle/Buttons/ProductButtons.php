<?php

namespace SanitarijeAdminBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;

class ProductButtons extends AbstractBaseButtons
{
    public function GetFormPageButtons(){

        $data = $this->getData();

        $buttons = Array();

        if(isset($data["id"]) && !empty($data["id"])){

            $buttons[] = array(
                "type" => "button",
                "name" => $this->translator->trans("Sync product"),
                "class" => "btn-primary btn-red",
                "url" => "sync_product",
                "action" => "button_default"
            );

            /*$buttons[] = array(
                "type" => "button",
                "name" => $this->translator->trans("Reset naming"),
                "class" => "btn-primary btn-red",
                "url" => "",
                "action" => "order_generate_pdf"
            );*/
        }

        $buttons = array_merge($buttons,$this->getDefaultFormButtons());

        return $buttons;
    }
}