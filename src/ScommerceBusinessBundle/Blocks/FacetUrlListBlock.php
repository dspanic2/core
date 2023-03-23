<?php

namespace ScommerceBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use ScommerceBusinessBundle\Entity\FacetsEntity;
use ScommerceBusinessBundle\Managers\FacetManager;
use ScommerceBusinessBundle\Managers\RouteManager;

class FacetUrlListBlock extends AbstractBaseBlock
{
    /** @var FacetManager $facetManager */
    protected $facetManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;

    public function GetPageBlockTemplate()
    {
        return ('ScommerceBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
    }

    public function GetPageBlockData()
    {
        $this->pageBlockData["model"]["facet_urls"] = Array();

        if (!empty($this->pageBlockData["id"])) {

            if(empty($this->facetManager)){
                $this->facetManager = $this->getContainer()->get("facet_manager");
            }

            /** @var FacetsEntity $facet */
            $facet = $this->facetManager->getFacetById($this->pageBlockData["id"]);

            /** @var ProductGroupEntity $productGroup */
            $productGroup = $facet->getProductGroup();

            if(empty($this->routeManager)){
                $this->routeManager = $this->getContainer()->get("route_manager");
            }

            $stores = Array();
            foreach ($productGroup->getShowOnStore() as $storeId => $available){
                if($available){
                    $stores[] = $this->routeManager->getStoreById($storeId);
                }
            }

            $urls = Array();

            foreach ($stores as $store){

                $facetConfigurations = $this->facetManager->generateFacetUrls($facet, $store);

                if(empty($facetConfigurations)){
                    continue;
                }

                usort($facetConfigurations, function ($item1, $item2) {
                    return $item2['product_count'] <=> $item1['product_count'];
                });

                $urls[$store->getId()]["base_url"] = $_ENV["SSL"] . "://" . $store->getWebsite()->getBaseUrl() . $this->routeManager->getLanguageUrl($store) . $_ENV["FRONTEND_URL_PORT"];
                $urls[$store->getId()]["configurations"] = $facetConfigurations;
                $urls[$store->getId()]["store"] = $store;
            }

            $this->pageBlockData["model"]["facet_urls"] = $urls;
        }

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
    }


    public function GetPageBlockSetingsData()
    {
        $attributeSetContext = $this->container->get('attribute_set_context');
        $attributeSets = $attributeSetContext->getAll();

        return array(
            'entity' => $this->pageBlock,
            'attribute_sets' => $attributeSets,
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

    public function isVisible()
    {
        if (empty($this->pageBlockData["id"])) {
            return false;
        }

        return true;
    }

}
