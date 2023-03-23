<?php

namespace IntegrationBusinessBundle\Controller;

// Do not remove (otherwise an error will occur).
use AppBundle\Interfaces\Entity\IFormEntityInterface;
use AppBundle\Managers\CronJobManager;
use IntegrationBusinessBundle\Managers\GoogleApiManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

// Do not remove (otherwise an error will occur).
use AppBundle\Abstracts\AbstractController;
use IntegrationBusinessBundle\Managers\GoogleAdsManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Google;
use Google_Service_Analytics;

/**
 * Class GoogleAdsController
 * @package IntegrationBusinessBundle\Controller
 */
class GoogleApiTokenController extends AbstractController
{
    /** @var GoogleApiManager $googleApiManager */
    protected $googleApiManager;
    /** @var CronJobManager $cronJobManager */
    protected $cronJobManager;

    /**
     * @Route("/google_oauth2", name="google_oauth2_callback")
     * @Method("GET")
     * @param Request $request
     */
    public function authenticate(Request $request)
    {
        $code = $request->query->get('code');

        if(empty($this->googleApiManager)){
            $this->googleApiManager = $this->getContainer()->get("google_api_manager");
        }

        try{
            $this->googleApiManager->initializeConnection(false);
            $this->googleApiManager->getGoogleToken($code);
        }
        catch (\Exception $e){
            $session = $request->getSession();
            $session->set("error_message",$e->getMessage());
            return $this->redirect("/page/google_api_integration/dashboard", 301);
        }

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }

        $refreshTokenSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("google_refresh_token");

        if(!empty($refreshTokenSettings->getSettingsValue()[$_ENV["DEFAULT_STORE_ID"]])){
            return $this->redirect("/page/google_api_integration/dashboard", 301);
        }

        exit(1);
    }

    /**
     * @Route("/google_remove_tokens", name="google_remove_tokens")
     * @Method("POST")
     * @param Request $request
     */
    public function googleRemoveTokens(Request $request)
    {
        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }

        $tokenSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("google_token");

        $settingsData["settings_value"][$_ENV["DEFAULT_STORE_ID"]] = null;
        $this->applicationSettingsManager->createUpdateSettings($tokenSettings,$settingsData);

        $refreshTokenSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("google_refresh_token");

        $settingsData["settings_value"][$_ENV["DEFAULT_STORE_ID"]] = null;
        $this->applicationSettingsManager->createUpdateSettings($refreshTokenSettings,$settingsData);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Google token removed')));
    }

    /**
     * @Route("/google_api_add_cron_job", name="google_api_add_cron_job")
     * @Method("POST")
     * @param Request $request
     */
    public function googleApiAddCronJob(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is not defined')));
        }

        if(empty($this->cronJobManager)){
            $this->cronJobManager = $this->getContainer()->get("cron_job_manager");
        }

        $data = Array();
        $data["method"] = $p["id"];
        $data["is_active"] = 1;
        $data["run_time"] = 1;
        $data["name"] = "Reset google api limit";
        $data["schedule"] = "5 0 * * *";
        $data["description"] = "Reset google api limit";

        if($p["id"] == "google_search_console:cmd type:run_s_route_not_found_indexed"){
            $data["run_time"] = 40;
            $data["name"] = "Run google api url not found";
            $data["schedule"] = "10 0 * * *";
            $data["description"] = "Run google api url not found";
        }
        elseif($p["id"] == "google_search_console:cmd type:run_s_route_indexed"){
            $data["run_time"] = 40;
            $data["name"] = "Run google api s route";
            $data["schedule"] = "10 3 * * *";
            $data["description"] = "Run google api s route";
        }

        $this->cronJobManager->insertUpdateCronJob($data);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Cron job added')));
    }

}
