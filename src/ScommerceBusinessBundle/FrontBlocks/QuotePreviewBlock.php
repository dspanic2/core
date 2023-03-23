<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Helpers\StringHelper;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class QuotePreviewBlock extends AbstractBaseFrontBlock
{
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    protected function prepareBlockData()
    {
        $q = $_GET['q'] ?? null;
        if (empty($q)) {
            $translator = $this->container->get("translator");

            return ["error" => $translator->trans("Missing quote hash")];
        }

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        $q = StringHelper::decrypt($q);

        return $this->quoteManager->getQuotePreviewData($q, $_GET['state'] ?? null);
    }

    public function GetBlockData()
    {
        $translator = $this->container->get("translator");

        $q = $_GET['q'] ?? null;
        if (empty($q)) {

            $this->blockData["model"]["error"] = $translator->trans("Missing quote hash");

            return $this->blockData;
        }

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        $q = StringHelper::decrypt($q);

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteById($q);

        if(empty($quote)){

            $this->blockData["model"]["error"] = $translator->trans("Missing quote");

            return $this->blockData;
        }

        $this->blockData["model"] = $this->quoteManager->getQuotePreviewData($q, $_GET['state'] ?? null);



        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $this->blockData["model"]["payment_data"] = $this->crmProcessManager->getQuoteButtons($quote);

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
