<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\ErrorLogManager;
use Doctrine\Common\Util\Inflector;
use Symfony\Component\Config\Definition\Exception\Exception;

class CriticalErrorMessagesBlock extends AbstractBaseBlock
{
    /** @var  BlockManager $blockManager*/
    protected $blockManager;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    public function GetPageBlockTemplate()
    {
        return ('AppBundle:Block:'.$this->pageBlock->getType().'.html.twig');
    }

    public function GetPageBlockData()
    {
        if(empty($this->errorLogManager)){
            $this->errorLogManager = $this->container->get("error_log_manager");
        }

        $messages = $this->errorLogManager->getCriticalErrorMessages();

        $this->pageBlockData["messages"] = $messages;

        $errorItems = $this->errorLogManager->getCritialErrorStatistics();

        $this->pageBlockData["items"] = $errorItems;

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->getContainer()->get("entity_type_context");
        $entityTypes = $entityTypeContext->getAll();

        return array(
            'entity' => $this->pageBlock,
            'entity_types' => $entityTypes,
        );
    }

    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->getContainer()->get("block_manager");

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
