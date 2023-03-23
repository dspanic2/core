<?php

namespace AppBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use AppBundle\Entity\ErrorLogEntity;
use AppBundle\Managers\ErrorLogManager;

class ErrorLogButtons extends AbstractBaseButtons
{
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    public function GetFormPageButtons()
    {
        $data = $this->getData();

        $buttons = Array();

        if(isset($data["id"]) && !empty($data["id"])) {

            if(empty($this->errorLogManager)){
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            /** @var ErrorLogEntity $errorLog */
            $errorLog = $this->errorLogManager->getErrorLogById($data["id"]);

            if(!$errorLog->getResolved()){
                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("Mark resolved"),
                    "class" => "btn-primary btn-red btn-custom-red",
                    "url" => "error_log_mark_resolved",
                    "action" => "default_form_button",
                );
            }
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