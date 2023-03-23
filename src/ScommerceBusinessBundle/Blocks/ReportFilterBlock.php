<?php

namespace ScommerceBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\DatabaseContext;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Entity\SWebsiteEntity;
use ScommerceBusinessBundle\Managers\RouteManager;

class ReportFilterBlock extends AbstractBaseBlock
{

    /**@var DatabaseContext $databaseContext */
    protected $databaseContext;


    public function GetPageBlockTemplate()
    {
        return ('ScommerceBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
    }

    public function GetPageBlockData()
    {
        /** @var RouteManager $routeManager */
        $routeManager = $this->container->get("route_manager");

        $this->pageBlockData["model"]["all_stores"] = [];

        $ret = [];

        /** @var SStoreEntity $store */
        foreach ($routeManager->getStores() as $store) {
            /** @var SWebsiteEntity $website */
            $website = $store->getWebsite();
            if (!isset($ret[$website->getId()])) {
                $ret[$website->getId()] = [
                    "name" => $website->getName(),
                    "ids" => [],
                    "stores" => [],
                ];
            }
            $ret[$website->getId()]["ids"][] = $store->getId();
            $ret[$website->getId()]["stores"][] = [
                "name" => $store->getName(),
                "id" => $store->getId(),
            ];
            $this->pageBlockData["model"]["all_stores"][] = $store->getId();
        }

        $this->pageBlockData["model"]["stores"] = $ret;

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        return array(
            'entity' => $this->pageBlock,
        );
    }


    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
