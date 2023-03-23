<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Managers\BlockManager;
use Doctrine\Common\Util\Inflector;
use Symfony\Component\Config\Definition\Exception\Exception;

class MessagesBlock extends AbstractBaseBlock
{
    /** @var  BlockManager $blockManager*/
    protected $blockManager;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;

    public function GetPageBlockTemplate()
    {
        return ('AppBundle:Block:'.$this->pageBlock->getType().'.html.twig');
    }

    public function GetPageBlockData()
    {
        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $messages = $this->crmProcessManager->getMessagesForEntity($this->pageBlockData["model"]["entity"]);

        /**
         * Demo
         */
        /*$messages = Array();

        $messages[] = Array("class" => "info", "content" => "<p>This is an info message</p>");
        $messages[] = Array("class" => "error", "content" => "<p>This is an error message</p>");
        $messages[] = Array("class" => "warning", "content" => "<p>This is a warning message</p>");
        $messages[] = Array("class" => "success", "content" => "<p>This is a success message</p>");*/

        $this->pageBlockData["messages"] = $messages;

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
