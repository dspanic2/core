<?php

namespace GLSBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use GLSBusinessBundle\Entity\GlsParcelEntity;
use GLSBusinessBundle\Managers\GLSManager;

class GlsParcelButtons extends AbstractBaseButtons
{
    /** @var GLSManager */
    protected $glsManager;

    public function GetFormPageButtons(){

        $data = $this->getData();

        $buttons = Array();

        if(isset($data["id"]) && !empty($data["id"])){

            if(empty($this->glsManager)){
                $this->glsManager = $this->getContainer()->get("gls_manager");
            }

            /** @var GlsParcelEntity $glsParcel */
            $glsParcel = $this->glsManager->getGlsParcelById($data["id"]);

            if(empty($glsParcel->getParcelNumber())){

                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("Request GLS"),
                    "class" => "btn-primary btn-custom-blue",
                    "url" => "request_gls",
                    "action" => "default_form_button"
                );

                $buttons = array_merge($buttons,$this->getDefaultFormButtons());
            }
        }
        else{
            $buttons = $this->getDefaultFormButtons();
        }


        return $buttons;
    }
}
