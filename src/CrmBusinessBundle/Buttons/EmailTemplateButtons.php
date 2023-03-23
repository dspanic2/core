<?php

namespace CrmBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;

class EmailTemplateButtons extends AbstractBaseButtons
{
    public function GetFormPageButtons(){

        $data = $this->getData();

        if(isset($data["id"]) && !empty($data["id"])){

            $buttons = Array();

            $buttons[] = array(
                "type" => "button",
                "name" => $this->translator->trans("Send test email"),
                "class" => "btn-primary btn-red",
                "url" => "send_test_email",
                "action" => "button_default",
                "id" => $data["id"]
            );

            $buttons = array_merge($buttons,$this->getDefaultFormButtons());
        }
        else{
            $buttons = $this->getDefaultFormButtons();
        }

        return $buttons;
    }
}
