<?php

namespace IntegrationBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\CronJobManager;
use Doctrine\Common\Util\Inflector;
use Symfony\Component\Config\Definition\Exception\Exception;

class GoogleApiSettingsBlock extends AbstractBaseBlock
{
    /** @var  BlockManager $blockManager*/
    protected $blockManager;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /** @var CronJobManager $cronJobManager */
    protected $cronJobManager;

    public function GetPageBlockTemplate()
    {
        return ('IntegrationBusinessBundle:Block:'.$this->pageBlock->getType().'.html.twig');
    }

    public function GetPageBlockData()
    {
        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }

        $configJson = $this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("google_config_json",$_ENV["DEFAULT_STORE_ID"]);
        $refreshToken = $this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("google_refresh_token",$_ENV["DEFAULT_STORE_ID"]);
        $limit = intval($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("google_api_limit",$_ENV["DEFAULT_STORE_ID"]));

        $this->pageBlockData["settings"] = $configJson;
        $this->pageBlockData["refresh_token"] = $refreshToken;
        $this->pageBlockData["limit"] = $limit;
        $this->pageBlockData["cron_jobs"] = Array();

        if(empty($this->cronJobManager)){
            $this->cronJobManager = $this->getContainer()->get("cron_job_manager");
        }

        $this->pageBlockData["cron_jobs"]["refresh_limit"] = $this->cronJobManager->getCronJobByMethod("google_search_console:cmd type:reset_google_api_limit");
        $this->pageBlockData["cron_jobs"]["run_s_route_not_found"] = $this->cronJobManager->getCronJobByMethod("google_search_console:cmd type:run_s_route_not_found_indexed");
        $this->pageBlockData["cron_jobs"]["run_s_route_indexed"] = $this->cronJobManager->getCronJobByMethod("google_search_console:cmd type:run_s_route_indexed");

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'IntegrationBusinessBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->getContainer()->get("entity_type_context");
        $entityTypes = $entityTypeContext->getAll();

        return array(
            'entity' => $this->pageBlock,
            'entity_types' => $entityTypes,
        );
    }

    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->getContainer()->get("block_manager");

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
