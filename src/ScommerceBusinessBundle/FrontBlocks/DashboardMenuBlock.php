<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Entity\SMenuEntity;
use ScommerceBusinessBundle\Managers\MenuManager;

class DashboardMenuBlock extends AbstractBaseFrontBlock
{
    /** @var MenuManager $menuManager */
    protected $menuManager;

    public function GetBlockData()
    {
        if (empty($this->menuManager)) {
            $this->menuManager = $this->container->get("menu_manager");
        }

        /** @var SMenuEntity $menu */
        $menu = $this->menuManager->getMenuById(7);

        if (!empty($menu)) {
            $this->blockData["model"]["menu"] = $this->menuManager->getMenuItemsArray($menu);
        }

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
}
