<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\TemplateManager;
use ScommerceBusinessBundle\Managers\SitemapManager;

class SitemapBlock extends AbstractBaseFrontBlock
{
    /** @var RouteManager $routeManager */
    protected $routeManager;

    public function GetBlockData()
    {
        $this->blockData = parent::GetBlockData();
        $session = $this->container->get('session');

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $entity = $this->block->getDataAttributes();

        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $this->blockData["title"] = $this->block->getMainTitle()[$storeId];
        $this->blockData["sitemap"] = $this->routeManager->getEntityDataForSitemapByEntityTypeAndStore($entity, $storeId);

        return $this->blockData;
    }

    /** Nije potrebno dok se ne uvedu zasebni admin template za pojedine blokove */
    /*public function GetBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:BlockSettings:'.$this->block->getType().'.html.twig';
    }*/

    /*public function GetBlockSetingsData()
    {
        return array(
            'entity' => $this->block,
        );
    }*/

    public function SaveBlockSettings($data)
    {

        /** @var TemplateManager $templateManager */
        $templateManager = $this->container->get('template_manager');

        $this->block->setName($data["name"]);
        $this->block->setClass($data["class"]);
        $this->block->setDataAttributes($data["data_attributes"]);

        return $templateManager->save($this->block);
    }

    public function isVisible()
    {
        if (empty($this->blockData["id"])) {
            return false;
        }
        return true;
    }

}
