<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Managers\BlockManager;

class ImportManualBlock extends AbstractBaseBlock
{
    /**
     * @return string
     */
    public function GetPageBlockTemplate()
    {
        return "AppBundle:Block:" . $this->pageBlock->getType() . ".html.twig";
    }

    /**
     * @return mixed
     */
    public function GetPageBlockData()
    {
        return $this->pageBlockData;
    }

    /**
     * @return string
     */
    public function GetPageBlockSetingsTemplate()
    {
        return "AppBundle:BlockSettings:" . $this->pageBlock->getType() . ".html.twig";
    }

    /**
     * @return array
     */
    public function GetPageBlockSetingsData()
    {
        return [
            "entity" => $this->pageBlock
        ];
    }

    /**
     * @param $data
     * @return \AppBundle\Abstracts\JsonResponse|\AppBundle\Entity\PageBlock|bool
     */
    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->container->get("block_manager");

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }

    /**
     * @return bool
     */
    public function isVisible()
    {
        return true;
    }
}
