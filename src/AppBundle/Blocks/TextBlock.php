<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Managers\EntityManager;

class TextBlock extends AbstractBaseBlock
{

    public function GetPageBlockTemplate()
    {
        return 'AppBundle:Block:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockData()
    {
        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        return array(
            'entity' => $this->pageBlock,
        );
    }

    public function SavePageBlockSettings($data)
    {

        $blockManager = $this->container->get('block_manager');

        $this->pageBlock->setAttributeSet();
        $this->pageBlock->setEntityType();
        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);
        $this->pageBlock->setContent($data["content"]);

        return $blockManager->save($this->pageBlock);
    }
}
