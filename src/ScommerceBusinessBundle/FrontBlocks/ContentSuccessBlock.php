<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\OrderManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class ContentSuccessBlock extends AbstractBaseFrontBlock
{
    /** @var OrderManager $orderManager */
    protected $orderManager;

    public function GetBlockData()
    {

        $session = $this->container->get('session');

        $orderId = $session->get("order_id");

        $this->blockData["model"]["order"] = null;

        if (empty($orderId)) {
            if (isset($_GET["q"]) && !empty($_GET["q"])) {
                $orderId = StringHelper::decrypt($_GET["q"]);
            }
        }

        if (!empty($orderId)) {

            if (empty($this->orderManager)) {
                $this->orderManager = $this->container->get("order_manager");
            }
            /** @var OrderEntity $order */
            $order = $this->orderManager->getOrderById($orderId);

            if (!empty($order)) {
                /** @var QuoteEntity $quote */
                $quote = $order->getQuote();

                $this->blockData["model"]["order"] = $order;
                $this->blockData["model"]["quote"] = $quote;

                $session->set("order_id", null);

                /** @var HelperManager $helperManager */
                $helperManager = $this->container->get("helper_manager");

                /** @var CoreUserEntity $user */
                $user = $helperManager->getCurrentCoreUser();

                if (!empty($user)) {
                    $this->blockData["model"]["user_logged_in"] = true;
                }
            }
        }

        return $this->blockData;
    }
}
