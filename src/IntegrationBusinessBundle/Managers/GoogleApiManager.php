<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\RestManager;
use Google\Service\SearchConsole\Resource\UrlInspectionIndex;
use IntegrationBusinessBundle\Entity\GoogleApiIntegrationEntity;
use ScommerceBusinessBundle\Entity\SWebsiteEntity;

class GoogleApiManager extends AbstractBaseManager
{
    protected $token;
    protected $refreshToken;
    protected $configArray;
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    protected $applicationSettingsManager;

    public function setToken($token){
        $this->token = $token;
    }

    public function setRefreshToken($refreshToken){
        $this->refreshToken = $refreshToken;
    }

    public function setConfigArray($configArray){
        $this->configArray = $configArray;
    }

    public function getToken(){
        return $this->token;
    }

    public function getRefreshToken(){
        return $this->refreshToken;
    }

    public function getConfigArray(){
        return $this->configArray;
    }

    /**
     * @return true
     * @throws \Exception
     */
    public function initializeConnection($checkAll = true){

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }

        if($checkAll){
            $token = $this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("google_token",$_ENV["DEFAULT_STORE_ID"]);
            if(empty($token)){
                throw new \Exception("Missing google token");
            }
            $this->setToken($token);

            $refreshToken = $this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("google_refresh_token",$_ENV["DEFAULT_STORE_ID"]);
            if(empty($refreshToken)){
                throw new \Exception("Missing refresh google token");
            }
            $this->setRefreshToken($refreshToken);
        }

        $configJson = $this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("google_config_json",$_ENV["DEFAULT_STORE_ID"]);
        if(empty($configJson)){
            throw new \Exception("Missing google config json");
        }

        if(empty(json_decode($configJson,true))){
            throw new \Exception("Invalid google config json");
        }

        $this->setConfigArray(json_decode($configJson,true));

        return true;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function refreshGoogleToken(){

        if(empty($this->getConfigArray())){
            throw new \Exception("Missing config json");
        }

        if(empty($this->getRefreshToken())){
            throw new \Exception("Missing refresh token");
        }

        $configArray = $this->getConfigArray();

        $restManager = new RestManager();

        $data = Array();
        $data["client_id"] = $configArray["web"]["client_id"];
        $data["client_secret"] = $configArray["web"]["client_secret"];
        $data["refresh_token"] = $this->getRefreshToken();
        $data["grant_type"] = "refresh_token";

        $restManager->CURLOPT_POST = 1;
        $restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $restManager->CURLOPT_HTTPHEADER = array("Content-Type: application/json");
        $restManager->CURLOPT_POSTFIELDS = json_encode($data);

        $accessToken = $restManager->get("https://www.googleapis.com/oauth2/v4/token");

        if(!isset($accessToken["access_token"])){
            throw new \Exception("Cannot get access token");
        }

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }

        $tokenSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("google_token");
        $settingsValueArray[$_ENV["DEFAULT_STORE_ID"]] = $accessToken["access_token"];
        $tokenSettingsData["settings_value"] = $settingsValueArray;
        $this->applicationSettingsManager->createUpdateSettings($tokenSettings,$tokenSettingsData);

        $this->setToken($accessToken["access_token"]);

        return true;
    }

    public function getGoogleToken($code = null){

        #url to clean and remove access token
        #https://myaccount.google.com/u/0/permissions?continue=https%3A%2F%2Fmyaccount.google.com%2Fu%2F0%2Fsecurity%3Fpli%3D1%26nlr%3D1
        #look into third party applications

        if(empty($this->getConfigArray())){
            throw new \Exception("Missing config json");
        }

        $accessToken = null;
        $refreshToken = null;

        $client = new \Google\Client();

        $client->setAuthConfig($this->getConfigArray());
        $client->setAccessType("offline");
        //$client->setRedirectUri("https://admin.unitrg.shape-dev.com/oauth2");
        $client->setRedirectUri($_ENV["SSL"]."://".$_ENV["BACKEND_URL"]."/"."google_oauth2");

        $client->addScope(\Google\Service\SearchConsole::WEBMASTERS);
        #$client->addScope(Google_Service_Analytics::ANALYTICS_READONLY);

        $auth_url = $client->createAuthUrl();

        // Handle authorization flow from the server.
        if (!isset($code)) {
            header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
        } else {
            $client->authenticate($code);

            if ($client->isAccessTokenExpired()){
                $client->revokeToken();
                header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
            } else {
                $accessToken = $client->getAccessToken();
                $refreshToken = $client->getRefreshToken();
            }
        }

        if (!empty($refreshToken)){

            if (empty($this->applicationSettingsManager)) {
                $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
            }

            $tokenSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("google_token");
            $settingsValueArray[$_ENV["DEFAULT_STORE_ID"]] = $accessToken["access_token"];
            $tokenSettingsData["settings_value"] = $settingsValueArray;
            $this->applicationSettingsManager->createUpdateSettings($tokenSettings,$tokenSettingsData);

            $this->setToken($accessToken["access_token"]);

            $refreshTokenSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("google_refresh_token");
            $settingsValueArray[$_ENV["DEFAULT_STORE_ID"]] = $refreshToken;
            $refreshTokenSettingsData["settings_value"] = $settingsValueArray;
            $this->applicationSettingsManager->createUpdateSettings($refreshTokenSettings,$refreshTokenSettingsData);

            $this->setRefreshToken($refreshToken);

            return true;
        }

        return false;
    }

    public function initialize()
    {
        parent::initialize();

        /**
         * Resources
         */
        #https://github.com/googleapis/google-api-php-client
        #https://github.com/googleapis/google-api-php-client-services/tree/main/src
    }

    /**
     * @param $websiteUrl
     * @return mixed
     * @throws \Exception
     */
    public function listSitemaps($websiteUrl){

        if(empty($this->getToken())){
            throw new \Exception("Missing google token");
        }

        if(empty($this->getRefreshToken())){
            throw new \Exception("Missing google refresh token");
        }

        if(empty($this->getConfigArray())){
            throw new \Exception("Missing config json");
        }

        $this->refreshGoogleToken();

        $client = new \Google\Client();

        $client->setAuthConfig($this->getConfigArray());
        $client->addScope(\Google\Service\SearchConsole::WEBMASTERS);
        $client->setAccessToken($this->getToken());

        $service = new \Google\Service\SearchConsole($client);

        $importLogData = array();
        $importLogData['completed'] = 0;
        $importLogData['name'] = 'Google_Service_SearchConsole_ListSitemaps';
        $importLogData['params'] = $websiteUrl;

        if(empty($this->errorLogManager)){
            $this->errorLogManager = $this->getContainer()->get("error_log_manager");
        }

        try{
            $response = $service->sitemaps->listSitemaps($websiteUrl);
        }
        catch (\Exception $e){

            $importLogData['completed'] = 0;
            $importLogData['error_log'] = $e->getMessage();

            $this->errorLogManager->insertImportLog($importLogData,false);

            throw $e;
        }

        $importLogData['response_data'] = json_encode($response);
        $importLogData['completed'] = 1;

        $this->errorLogManager->insertImportLog($importLogData,false);

        return $response;

        /*Google\Service\SearchConsole\SitemapsListResponse {#6894
          #collection_key: "sitemap"
          #sitemapType: "Google\Service\SearchConsole\WmxSitemap"
          #sitemapDataType: "array"
          #internal_gapi_mappings: []
          #modelData: []
          #processed: []
          +"sitemap": array:1 [
            0 => Google\Service\SearchConsole\WmxSitemap {#6914
              #collection_key: "contents"
              #contentsType: "Google\Service\SearchConsole\WmxSitemapContent"
              #contentsDataType: "array"
              +errors: "0"
              +isPending: false
              +isSitemapsIndex: true
              +lastDownloaded: "2023-03-08T05:01:17.907Z"
              +lastSubmitted: "2023-03-07T16:38:37.051Z"
              +path: "https://www.makromikrogrupa.hr/xml/sitemap_3.xml"
              +type: null
              +warnings: "0"
              #internal_gapi_mappings: []
              #modelData: []
              #processed: []
              +"contents": array:1 [
                0 => Google\Service\SearchConsole\WmxSitemapContent {#6931
                  +indexed: "0"
                  +submitted: "16059"
                  +type: "web"
                  #internal_gapi_mappings: []
                  #modelData: []
                  #processed: []
                }
              ]
            }
          ]
        }*/
    }

    public function getGoogleSearchConsoleInspectUrlIndexRequest($data){

        if(empty($this->getToken())){
            throw new \Exception("Missing google token");
        }

        if(empty($this->getRefreshToken())){
            throw new \Exception("Missing google refresh token");
        }

        if(empty($this->getConfigArray())){
            throw new \Exception("Missing config json");
        }

        $this->refreshGoogleToken();

        $client = new \Google\Client();

        $client->setAuthConfig($this->getConfigArray());
        $client->addScope(\Google\Service\SearchConsole::WEBMASTERS);
        $client->setAccessToken($this->getToken());

        $service = new \Google\Service\SearchConsole($client);

        $query = new \Google_Service_SearchConsole_InspectUrlIndexRequest();
        $query->setSiteUrl($data["site_url"]);
        $query->setInspectionUrl($data["site_inspection_url"]);

        $importLogData = array();
        $importLogData['completed'] = 0;
        $importLogData['name'] = 'Google_Service_SearchConsole_InspectUrlIndexRequest';
        $importLogData['params'] = json_encode($data);

        if(empty($this->errorLogManager)){
            $this->errorLogManager = $this->getContainer()->get("error_log_manager");
        }

        try{
            $response = $service->urlInspection_index->inspect($query);
        }
        catch (\Exception $e){

            $importLogData['completed'] = 0;
            $importLogData['error_log'] = $e->getMessage();

            $this->errorLogManager->insertImportLog($importLogData,false);

            throw $e;
        }

        $importLogData['response_data'] = json_encode($response);
        $importLogData['completed'] = 1;

        $this->errorLogManager->insertImportLog($importLogData,false);

        return $response;

        /**
         * Example response
         */
        /*#inspectionResultType: "Google\Service\SearchConsole\UrlInspectionResult"
          #inspectionResultDataType: ""
          +inspectionResult: Google\Service\SearchConsole\UrlInspectionResult {#6910
            #ampResultType: "Google\Service\SearchConsole\AmpInspectionResult"
            #ampResultDataType: ""
            +ampResult: null
            #indexStatusResultType: "Google\Service\SearchConsole\IndexStatusInspectionResult"
            #indexStatusResultDataType: ""
            +indexStatusResult: Google\Service\SearchConsole\IndexStatusInspectionResult {#6919
              #collection_key: "sitemap"
              +coverageState: "Submitted and indexed"
              +crawledAs: "MOBILE"
              +googleCanonical: "https://www.makromikrogrupa.hr/zebra-novo-u-ponudi"
              +indexingState: "INDEXING_ALLOWED"
              +lastCrawlTime: "2023-03-05T20:34:44Z"
              +pageFetchState: "SUCCESSFUL"
              +referringUrls: []
              +robotsTxtState: "ALLOWED"
              +sitemap: array:1 [
                0 => "https://www.makromikrogrupa.hr/xml/sitemap_3.xml"
              ]
              +userCanonical: "https://www.makromikrogrupa.hr/zebra-novo-u-ponudi"
              +verdict: "PASS"
              #internal_gapi_mappings: []
              #modelData: []
              #processed: []
            }
            +inspectionResultLink: "https://search.google.com/search-console/inspect?resource_id=https://www.makromikrogrupa.hr/&id=Vlgd74yDCJ92HbyAlgIUQg&utm_medium=link&utm_source=api"
            #mobileUsabilityResultType: "Google\Service\SearchConsole\MobileUsabilityInspectionResult"
            #mobileUsabilityResultDataType: ""
            +mobileUsabilityResult: Google\Service\SearchConsole\MobileUsabilityInspectionResult {#6938
              #collection_key: "issues"
              #issuesType: "Google\Service\SearchConsole\MobileUsabilityIssue"
              #issuesDataType: "array"
              +issues: []
              +verdict: "PASS"
              #internal_gapi_mappings: []
              #modelData: []
              #processed: []
            }
            #richResultsResultType: "Google\Service\SearchConsole\RichResultsInspectionResult"
            #richResultsResultDataType: ""
            +richResultsResult: Google\Service\SearchConsole\RichResultsInspectionResult {#6913
              #collection_key: "detectedItems"
              #detectedItemsType: "Google\Service\SearchConsole\DetectedItems"
              #detectedItemsDataType: "array"
              +detectedItems: array:1 [
                0 => Google\Service\SearchConsole\DetectedItems {#6912
                  #collection_key: "items"
                  #itemsType: "Google\Service\SearchConsole\Item"
                  #itemsDataType: "array"
                  +items: array:1 [
                    0 => Google\Service\SearchConsole\Item {#6911
                      #collection_key: "issues"
                      #issuesType: "Google\Service\SearchConsole\RichResultsIssue"
                      #issuesDataType: "array"
                      +issues: []
                      +name: "Unnamed item"
                      #internal_gapi_mappings: []
                      #modelData: []
                      #processed: []
                    }
                  ]
                  +richResultType: "Logos"
                  #internal_gapi_mappings: []
                  #modelData: []
                  #processed: []
                }
              ]
              +verdict: "PASS"
              #internal_gapi_mappings: []
              #modelData: []
              #processed: []
            }
            #internal_gapi_mappings: []
            #modelData: []
            #processed: []
          }
          #internal_gapi_mappings: []
          #modelData: []
          #processed: []
        }*/
    }

    /**
     * @return true
     */
    public function resetGoogleApiLimit(){

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }

        $limitSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("google_api_limit");

        $limitSettingsData["settings_value"][$_ENV["DEFAULT_STORE_ID"]] = 2000;
        $this->applicationSettingsManager->createUpdateSettings($limitSettings,$limitSettingsData);

        return true;
    }

    /**
     * @param null $additionalCompositeFilter
     * @param null $sortFilters
     * @return mixed
     */
    public function getFilteredGoogleApiIntegrationEntity($additionalCompositeFilter = null, $sortFilters = null)
    {
        if(empty($this->entityManager)){
            $this->entityManager = $this->getContainer()->get("entity_manager");
        }

        $et = $this->entityManager->getEntityTypeByCode("google_api_integration");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }


    /**
     * @param SWebsiteEntity $website
     * @return GoogleApiIntegrationEntity|null
     */
    public function getGoogleSearchConsoleSitemap(SWebsiteEntity $website){

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("code", "eq", "google_search_console_sitemap"));
        $compositeFilter->addFilter(new SearchFilter("website", "eq", $website->getId()));

        /** @var GoogleApiIntegrationEntity $googleSearchConsoleSitemap */
        $googleSearchConsoleSitemap = $this->getFilteredGoogleApiIntegrationEntity($compositeFilter);

        $now = new \DateTime();

        if(empty($googleSearchConsoleSitemap) || $googleSearchConsoleSitemap->getDateRefreshed()->diff($now)->days > 1){

            if (empty($this->applicationSettingsManager)) {
                $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
            }

            $limitSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("google_api_limit");
            $limit = intval($limitSettings->getSettingsValue()[$_ENV["DEFAULT_STORE_ID"]]);

            $limitSettingsData["settings_value"][$_ENV["DEFAULT_STORE_ID"]] = $limit-1;
            $this->applicationSettingsManager->createUpdateSettings($limitSettings,$limitSettingsData);

            if($limit <= 0){
                return $googleSearchConsoleSitemap;
            }

            try {
                $this->initializeConnection();

                $websiteUrl = $_ENV["SSL"]."://".$website->getBaseUrl()."/";
                $sitemaps = $this->listSitemaps($websiteUrl);

                $sitemapData = null;
                if(!empty($sitemaps->getSitemap())){
                    $sitemapData = json_encode($sitemaps->getSitemap());
                }

                $googleSearchConsoleSitemapData = Array();
                $googleSearchConsoleSitemapData["name"] = "Google search console sitemap - {$websiteUrl}";
                $googleSearchConsoleSitemapData["code"] = "google_search_console_sitemap";
                $googleSearchConsoleSitemapData["data"] = $sitemapData;
                $googleSearchConsoleSitemapData["date_refreshed"] = new \DateTime();
                $googleSearchConsoleSitemapData["website"] = $website;

                $googleSearchConsoleSitemap = $this->insertUpdateGoogleApiIntegration($googleSearchConsoleSitemapData,$googleSearchConsoleSitemap);
            }
            catch (\Exception $e){
                if(empty($this->errorLogManager)){
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }

                $this->errorLogManager->logExceptionEvent("Error listSitemaps",$e,true);

                return null;
            }
        }

        return $googleSearchConsoleSitemap;
    }

    /**
     * @param $data
     * @param GoogleApiIntegrationEntity|null $entity
     * @param bool $skipLog
     * @return GoogleApiIntegrationEntity|null
     */
    public function insertUpdateGoogleApiIntegration($data, GoogleApiIntegrationEntity $entity = null, $skipLog = true){

        if(empty($this->entityManager)){
            $this->entityManager = $this->getContainer()->get("entity_manager");
        }

        if (empty($entity)) {
            /** @var GoogleApiIntegrationEntity $entity */
            $entity = $this->entityManager->getNewEntityByAttributSetName("google_api_integration");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($entity);
        } else {
            $this->entityManager->saveEntity($entity);
        }
        $this->entityManager->refreshEntity($entity);

        return $entity;
    }
}
