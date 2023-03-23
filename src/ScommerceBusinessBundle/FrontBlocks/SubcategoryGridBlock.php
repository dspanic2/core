<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\ProductGroupManager;

class SubcategoryGridBlock extends AbstractBaseFrontBlock
{
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    public function GetBlockData()
    {

        $this->blockData["model"]["categories"] = array();

        if (!empty($this->blockData["id"])) {

            if (empty($this->productGroupManager)) {
                $this->productGroupManager = $this->container->get("product_group_manager");
            }

            $productGroup = $this->productGroupManager->getProductGroupById($this->blockData["id"]);

            if (!empty($productGroup)) {
                $this->blockData["model"]["categories"] = $this->productGroupManager->getChildProductGroups(
                    $productGroup,
                    "intermediarySequencing"
                );
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
