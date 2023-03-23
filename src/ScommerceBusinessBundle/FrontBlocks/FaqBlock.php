<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\FaqManager;

class FaqBlock extends AbstractBaseFrontBlock
{
    /** @var FaqManager $faqManager */
    protected $faqManager;

    public function GetBlockData()
    {
        $session = $this->getContainer()->get("session");
        $this->blockData["model"]["faq"] = array();
        $this->blockData["model"]["subtitle"] = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $this->blockData["block"], "subtitle");

        if (!empty($this->blockData["id"])) {

            if (empty($this->faqManager)) {
                $this->faqManager = $this->container->get("faq_manager");
            }

            $entity = $this->blockData["page"];

            $this->blockData["model"]["faq"] = $this->faqManager->getFaqByRelatedEntityTypeAndId($session->get("current_store_id"), $entity);
        }

        return $this->blockData;
    }
}
