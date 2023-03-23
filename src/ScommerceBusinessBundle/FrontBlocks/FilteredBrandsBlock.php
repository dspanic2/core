<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\BrandsManager;

class FilteredBrandsBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {
        $this->blockData = parent::GetBlockData();
        $this->blockData["model"]["brands"] = array();

        if (!empty($this->blockData["id"])) {
            /** @var BrandsManager $brandsManager */
            $brandsManager = $this->container->get("brands_manager");

            $data = array();
            if (!empty($this->blockData["block"]->getProductFilterData())) {
                $tmpFilterData = json_decode($this->blockData["block"]->getProductFilterData(), true);
                if (isset($tmpFilterData["pre_filter"])) {
                    $data["pre_filter"] = $tmpFilterData["pre_filter"];
                }
                if (isset($tmpFilterData["filter"])) {
                    $data["filter"] = $tmpFilterData["filter"];
                    $data["get_filter"] = true;
                }
            }
            $data["page_size"] = $this->blockData["block"]->getProductLimit();
            if (!empty($this->blockData["block"]->getProductSortData())) {
                $data["sort"] = $this->blockData["block"]->getProductSortData();
            }

            $this->blockData["model"]["brands"] = $brandsManager->getFilteredBrands($data);
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
