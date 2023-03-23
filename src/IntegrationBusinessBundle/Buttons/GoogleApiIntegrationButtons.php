<?php

namespace IntegrationBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use CrmBusinessBundle\Managers\AccountManager;

class GoogleApiIntegrationButtons extends AbstractBaseButtons
{
    /** @var AccountManager $accountManager */
    protected $accountManager;

    public function GetDashboardPageButtons(){

        $buttons = Array();

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }

        $refreshTokenSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("google_refresh_token");

        if(empty($refreshTokenSettings->getSettingsValue()[$_ENV["DEFAULT_STORE_ID"]])){
            $buttons[] = array(
                "type" => "link",
                "name" => $this->translator->trans("Authenticate google token"),
                "class" => "btn-primary btn-red btn-custom-blue",
                "url" => "google_oauth2_callback",
            );
        }
        else{
            $buttons[] = array(
                "type" => "button",
                "name" => $this->translator->trans("Remove tokens"),
                "class" => "btn-primary btn-red btn-custom-red",
                "url" => "google_remove_tokens",
                "action" => "default_form_button"
            );
        }

        return $buttons;
    }
}
