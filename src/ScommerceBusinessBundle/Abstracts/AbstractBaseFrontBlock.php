<?php

namespace ScommerceBusinessBundle\Abstracts;

use AppBundle\Enumerations\php;
use ScommerceBusinessBundle\Entity\SFrontBlockEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Interfaces\FrontBlocks\FrontBlockInterface;
use ScommerceBusinessBundle\Managers\TemplateManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractBaseFrontBlock implements FrontBlockInterface, ContainerAwareInterface
{
    /** @var bool */
    const CACHE_BLOCK_HTML = false;
    /** @var array */
    const CACHE_BLOCK_HTML_TAGS = [];

    /**@var SFrontBlockEntity $block */
    protected $block;

    /**array of data*/
    protected $blockData;

    protected $container;

    protected $isVisible;

    /** @var TemplateManager $templateManager */
    protected $templateManager;

    protected $translator;

    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Ovveride this method to initialize all services you will reqquire
     */
    public function initialize()
    {
        $this->isVisible = true;
        if (empty($this->templateManager)) {
            $this->templateManager = $this->container->get("template_manager");
        }
        if (empty($this->translator)) {
            $this->translator = $this->container->get("translator");
        }
        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }
    }

    public function setBlock(SFrontBlockEntity $block)
    {
        $this->block = $block;
    }

    public function setBlockData($blockData)
    {
        $this->blockData = $blockData;
    }

    public function isVisible()
    {
        return $this->isVisible;
    }

    public function setIsVisible($isVisible)
    {
        $this->isVisible = $isVisible;
    }

    public function GetBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:FrontBlockSettings:abstract_base_block.html.twig';
    }

    public function GetBlockAdminTemplate()
    {
        return 'ScommerceBusinessBundle:FrontBlockSettings:admin_default_block.html.twig';
    }

    public function GetBlockSetingsData()
    {
        $blockTypes = array();
        $services = $this->container->getServiceIds();

        foreach ($services as $service) {
            if (strpos($service, '_front_block') !== false) {
                $blockTypes[str_replace("_front_block", "", $service)] = array(
                    "attribute-set" => true,
                    "content" => true,
                    "is_available_in_block" => 1,
                    "is_available_in_page" => 1,
                );
                ksort($blockTypes);
            }
        }

        return array(
            'entity' => $this->block,
            'block_types' => $blockTypes,
            'parent_id' => null,
            'parent_type' => null,

        );
    }

    /**
     * @param $data
     * @return bool
     */
    protected function prepareBlockSettings($data)
    {
        if (!isset($data["name"]) || empty($data["name"])) {
            return false;
        }
        if (!isset($data["class"]) || empty($data["class"])) {
            $data["class"] = null;
        }
        if (!isset($data["content"]) || empty($data["content"])) {
            $data["content"] = null;
        }
        if (!isset($data["data_attributes"]) || empty($data["data_attributes"])) {
            $data["data_attributes"] = null;
        }

        $this->block->setName($data["name"]);
        $this->block->setClass($data["class"]);
        $this->block->setDataAttributes($data["data_attributes"]);
        $this->block->setContent($data["content"]);

        $active = $data["active"] ?? 1;
        $this->block->setActive($active);

        return true;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function SaveBlockSettings($data)
    {
        $this->prepareBlockSettings($data);

        /** @var TemplateManager $templateManager */
        $templateManager = $this->container->get('template_manager');

        return $templateManager->save($this->block);
    }

    public function GetAdminBlockData()
    {
        return $this->blockData;
    }

    public function GetBlockTemplate()
    {

        $session = $this->container->get("session");

        $template = $this->templateManager->getTemplatePathByBundle('FrontBlock:' . $this->block->getType() . '.html.twig', $session->get("current_website_id"), $this->block->getId());

        return $template;
    }

    public function GetBlockData()
    {
        $this->blockData["model"]["subtitle"] = null;
        $this->blockData["model"]["maintitle"] = null;
        $session = $this->container->get("session");
        $storeId = $session->get("current_store_id");

        $subtitle = $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $this->blockData["block"], "subtitle");
        if ($subtitle) {
            $this->blockData["model"]["subtitle"] = $subtitle;
        }

        $mainTitle = $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $this->blockData["block"], "main_title");
        if ($mainTitle) {
            $this->blockData["model"]["main_title"] = $mainTitle;
        }

        $this->blockData["model"]["show_more"] = array();

        $url = $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $this->blockData["block"], "url");
        if (!empty($url)) {
            $this->blockData["model"]["show_more"] = array(
                "title" => $this->translator->trans($this->getPageUrlExtension->getEntityStoreAttribute($storeId, $this->blockData["block"], "main_title")),
                "url" => $url,
            );
        }

        return $this->blockData;
    }

    public function getBlock()
    {
        return $this->block;
    }

    /**
     * @return string
     */
    public function getPageBuilderTemplate($type = null)
    {
        $session = $this->container->get("session");

        if(empty($type)){
            $type = $this->block->getType();
        }

        return $this->templateManager->getTemplatePathByBundle('PageBuilder/FrontBlock:' . $type . '.html.twig', $session->get("current_website_id"));
    }
}
