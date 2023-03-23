<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\TemplateManager;

class GeneralContactFormBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {
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

    public function SaveBlockSettings($data)
    {

        /** @var TemplateManager $templateManager */
        $templateManager = $this->container->get('template_manager');

        $this->block->setName($data["name"]);
        $this->block->setClass($data["class"]);
        $this->block->setDataAttributes($data["data_attributes"]);

        return $templateManager->save($this->block);
    }

    public function isVisible()
    {
        if (empty($this->blockData["id"])) {
            return false;
        }
        return true;
    }

}