<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Managers\ProductManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class CompareProductsBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {

        $this->blockData["model"]["compare"] = array();

        $session = $this->container->get('session');
        $compareProducts = $session->get('compare');

        $attributes = array();
        $products = array();

        /** @var ProductManager $productManager */
        $productManager = $this->container->get("product_manager");

        if (!empty($compareProducts)) {

            foreach ($compareProducts as $productId) {

                /** @var ProductEntity $product */
                $product = $productManager->getProductById($productId);

                if (empty($product)) {
                    continue;
                }

                $products[] = $product;

                $productAttributes = $product->getPreparedProductAttributes("specs");
                if (!empty($productAttributes)) {
                    foreach ($productAttributes as $productAttribute) {
                        $attributes[$productAttribute["attribute"]->getId()]["attribute"] = $productAttribute["attribute"];
                        $attributes[$productAttribute["attribute"]->getId()]["products"][$product->getId()] = $productAttribute["values"];
                    }
                }
            }
        }

        $this->blockData["model"]["warehouses"] = $productManager->getAllWarehouses();
        $this->blockData["model"]["products"] = $products;
        $this->blockData["model"]["attributes"] = $attributes;

        $this->blockData["model"]["product_warehouses_exist"] = false;
        foreach ($this->blockData["model"]["products"] as $product) {
            if (!empty($product->getProductWarehouses())) {
                $this->blockData["model"]["product_warehouses_exist"] = true;
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

        //Check permission
        return true;
    }

}
