<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\TestimonialsManager;

class TestimonialsBlock extends AbstractBaseFrontBlock
{
    /** @var TestimonialsManager $testimonialsManager */
    protected $testimonialsManager;

    public function GetBlockData()
    {
        if (empty($this->testimonialsManager)) {
            $this->testimonialsManager = $this->container->get("testimonials_manager");
        }

        $this->blockData["model"]["testimonials"] = $this->testimonialsManager->getTestimonials();

        $session = $this->getContainer()->get("session");
        $this->blockData["model"]["subtitle"] = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $this->blockData["block"], "subtitle");

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
}
