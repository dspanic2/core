<?php

namespace ScommerceBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use CrmBusinessBundle\Entity\ProductEntity;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationEntity;
use ScommerceBusinessBundle\Managers\SproductManager;

class SProductAttributesBlock extends AbstractBaseBlock
{
    /** @var SproductManager $sProductManager */
    protected $sProductManager;

    public function GetPageBlockTemplate()
    {
        return "ScommerceBusinessBundle:Block:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockData()
    {

        /** @var ProductEntity $product */
        $product = $this->pageBlockData["model"]["entity"];

        $productAttributes = $product->getPreparedProductAttributes("specs", false);

        if (empty($this->sProductManager)) {
            $this->sProductManager = $this->container->get("s_product_manager");
        }

        /**
         * Configuration added by product groups
         */
        $configs = $this->sProductManager->getSproductGroupAttributeConfigurations($this->pageBlockData["id"]);

        if (EntityHelper::isCountable($productAttributes) && !empty($productAttributes)) {
            foreach ($productAttributes as $key => $productAttribute) {
                if (isset($configs[$productAttribute["attribute"]->getId()])) {
                    unset($configs[$productAttribute["attribute"]->getId()]);
                }
            }
        }

        if (!empty($configs)) {
            /** @var SProductAttributeConfigurationEntity $config */
            foreach ($configs as $config) {
                $productAttributes[] = array("ord" => $config->getOrd(), "attribute" => $config, "values" => null);
            }
            unset($configs);
        }

        /**
         * Order all by name
         */
        $ord = array();
        $ret = array();

        if (EntityHelper::isCountable($productAttributes) && !empty($productAttributes)) {
            foreach ($productAttributes as $key => $productAttribute) {
                $ord[StringHelper::sanitizeFileName($productAttribute["attribute"]->getName())] = $key;
            }

            ksort($ord);
            foreach ($ord as $o) {
                $ret[$o] = $productAttributes[$o];
            }
        }

        $this->pageBlockData["model"]["product_attributes"] = $ret;

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));

        $this->pageBlockData["model"]["configurations"] = $this->sProductManager->getSproductAttributeConfigurations($compositeFilter);

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return "ScommerceBusinessBundle:BlockSettings:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockSetingsData()
    {
        return array(
            "entity" => $this->pageBlock
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get("block_manager");

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }

    public function isVisible()
    {
        if (empty($this->pageBlockData["id"])) {
            return false;
        }
        return true;
    }
}
