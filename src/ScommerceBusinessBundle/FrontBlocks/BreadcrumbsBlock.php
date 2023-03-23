<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\BreadcrumbsManager;

class BreadcrumbsBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {

        $this->blockData["model"]["breadcrumbs"] = array();

        if (!empty($this->blockData["id"])) {

            /** @var BreadcrumbsManager $breadcrumbManager */
            $breadcrumbManager = $this->container->get("breadcrumbs_manager");

            $breadcrumbs = $breadcrumbManager->getBreadcrumbForEntity($this->blockData["page"]);

            if (!empty($breadcrumbs)) {
                $this->blockData["model"]["breadcrumbs"] = $breadcrumbs;
            }
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

    public function isVisible()
    {
        if (empty($this->blockData["id"])) {
            return false;
        }

        return true;
    }
}
