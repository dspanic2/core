<?php

namespace CrmBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Managers\AccountManager;

class ContactButtons extends AbstractBaseButtons
{
    /** @var AccountManager $accountManager */
    protected $accountManager;

    public function GetFormPageButtons(){

        $data = $this->getData();

        $buttons = Array();

        if(isset($data["id"]) && !empty($data["id"])){

            if(empty($this->accountManager)){
                $this->accountManager = $this->container->get("account_manager");
            }

            /** @var ContactEntity $contact */
            $contact = $this->accountManager->getContactById($data["id"]);

            $anonymized = false;
            if(strlen($contact->getEmail()) == 60){
                $anonymized = true;
            }

            if(!$anonymized){

                if($contact->getNewsletterSignup() || $contact->getMarketingSignup()){
                    $buttons[] = array(
                        "type" => "button",
                        "name" => $this->translator->trans("Remove from newsletter"),
                        "class" => "btn-primary btn-red",
                        "url" => "contact_remove_from_newsletter",
                        "action" => "default_form_button"
                    );
                }

                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("GDPR anonymize"),
                    "class" => "btn-primary btn-red",
                    "url" => "contact_anonymize",
                    "action" => "default_form_button"
                );

                if(empty($contact->getCoreUser())){
                    $buttons[] = array(
                        "type" => "button",
                        "name" => $this->translator->trans("Create user account"),
                        "class" => "btn-primary btn-red",
                        "url" => "contact_generate_user_account_form",
                        "action" => "default_form_button"
                    );
                }

                $buttons = array_merge($buttons,$this->getDefaultFormButtons());
            }
            else{
                $buttons[] = Array(
                    "type" => "link",
                    "name" => $this->translator->trans("Back"),
                    "class" => "btn-default btn-red",
                    "url" => "",
                    "action" => "back"
                );
            }
        }
        else{
            $buttons = $this->getDefaultFormButtons();
        }


        return $buttons;
    }
}
