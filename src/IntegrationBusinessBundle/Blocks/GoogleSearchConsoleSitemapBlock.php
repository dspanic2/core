<?php

namespace IntegrationBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Managers\BlockManager;
use Doctrine\Common\Util\Inflector;
use IntegrationBusinessBundle\Managers\GoogleApiManager;
use ScommerceBusinessBundle\Entity\SWebsiteEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\Config\Definition\Exception\Exception;

class GoogleSearchConsoleSitemapBlock extends AbstractBaseBlock
{
    /** @var  BlockManager $blockManager*/
    protected $blockManager;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /** @var GoogleApiManager $googleApiManager */
    protected $googleApiManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;

    public function GetPageBlockTemplate()
    {
        return ('IntegrationBusinessBundle:Block:'.$this->pageBlock->getType().'.html.twig');
    }

    public function GetPageBlockData()
    {
        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }

        $refreshToken = $this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("google_refresh_token",$_ENV["DEFAULT_STORE_ID"]);

        $this->pageBlockData["sitemaps"] = Array();
        $this->pageBlockData["refresh_token"] = $refreshToken;

        if(!empty($refreshToken)){

            if(empty($this->googleApiManager)){
                $this->googleApiManager = $this->getContainer()->get("google_api_manager");
            }

            if(empty($this->routeManager)){
                $this->routeManager = $this->getContainer()->get("route_manager");
            }

            $websites = $this->routeManager->getWebsites();

            /** @var SWebsiteEntity $website */
            foreach ($websites as $website){
                $this->pageBlockData["sitemaps"][$website->getId()]["sitemap_data"] = $this->googleApiManager->getGoogleSearchConsoleSitemap($website);
                $this->pageBlockData["sitemaps"][$website->getId()]["website"] = $website;
            }
        }

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
