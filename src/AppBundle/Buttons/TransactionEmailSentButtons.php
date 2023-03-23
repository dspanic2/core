<?php

namespace AppBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;

class TransactionEmailSentButtons extends AbstractBaseButtons
{
    public function GetFormPageButtons(){

        $data = $this->getData();

        $buttons = Array();

        if(isset($data["id"]) && !empty($data["id"])) {

            $buttons[] = array(
                "type" => "button",
                "name" => $this->translator->trans("Resend"),
                "class" => "btn-primary btn-red",
                "url" => "resend_transaction_email_sent",
                "action" => "button_default",
                "id" => $data["id"]
            );
        }

        $buttons[] = Array(
            "type" => "link",
            "name" => $this->translator->trans("Back"),
            "class" => "btn-default btn-red",
            "url" => "",
            "action" => "back"
        );

        return $buttons;
    }
}