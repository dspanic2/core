<?php

namespace AppBundle\Blocks;

use AppBundle\Context\AttributeSetContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\BlockManager;
use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Managers\EntityManager;
use Symfony\Component\Translation\Translator;

class CustomHtmlBlock extends AbstractBaseBlock
{
    function is_base64($s)
    {
        if(stripos($s," ") !== false){
            return false;
        }

        return true;
    }

    public function GetPageBlockTemplate()
    {
        return "AppBundle:Block:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockData()
    {
        $id = $this->pageBlockData["id"];

        $entity = null;
        if ($this->pageBlock->getRelatedId()) {
            /** @var EntityManager $entityManager */
            $entityManager = $this->container->get("entity_manager");

            /** @var AttributeSet $attributeSet */
            $attributeSet = $entityManager->getAttributeSetById($this->pageBlock->getRelatedId());
            $entity = $entityManager->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $id);
        }

        $content = json_decode($this->pageBlock->getContent(), true);

        $this->pageBlockData["model"]["html"] = "";
        if (isset($content["html"])) {
            $html = $this->is_base64($content["html"]) ? base64_decode($content["html"]) : $content["html"];

            $template = $this->container->get("twig")->createTemplate($html);
            $this->pageBlockData["model"]["html"] = $template->render(
                Array(
                    "entity" => $entity
                ),
                Array(
                    "ignore_errors" => false
                )
            );
        }

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return "AppBundle:BlockSettings:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockSetingsData()
    {
        /** @var AttributeSetContext $attributeSetContext */
        $attributeSetContext = $this->container->get("attribute_set_context");
        $attributeSets = $attributeSetContext->getAll();

        $content = json_decode($this->pageBlock->getContent());

        return array(
            "entity" => $this->pageBlock,
            "attribute_sets" => $attributeSets,
            "html" => isset($content->html) ? ($content->html) : "",
        );
    }

    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->container->get("block_manager");

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);
        $this->pageBlock->setRelatedId($data["relatedId"]);

        $content = $this->is_base64($data["customHtml"]) ? $data["customHtml"] : json_encode($data["customHtml"]);
        $this->pageBlock->setContent($content);

        return $blockManager->save($this->pageBlock);
    }

    public function isVisible()
    {
        $id = $this->pageBlockData["id"];
        if ($id == null) {
            return false;
        } else {
            return true;
        }
    }
}