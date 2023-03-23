<?php

namespace ScommerceBusinessBundle\Controller;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\AccountEntity;
use ScommerceBusinessBundle\Managers\ScommerceHelperManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class DashboardController extends \ScommerceBusinessBundle\Controller\CartController
{
    /** @var ScommerceHelperManager $scommerceHelperManager */
    protected $scommerceHelperManager;

    protected function initialize($request = null)
    {
        parent::initialize($request);
    }

    /**
     * @Route("/dashboard/get_filtered_orders", name="dashboard_get_filtered_orders")
     * @Method("POST")
     */
    public function getFilteredOrdersAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $isEmpty = false;
        if (!array_filter($p)) {
            $isEmpty = true;
        }

        $session = $this->getContainer()->get('session');

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");

        /** @var CoreUserEntity $user */
        $user = $helperManager->getCurrentCoreUser();

        /** @var AccountEntity $account */
        $account = $user->getDefaultAccount();

        if (empty($this->scommerceHelperManager)) {
            $this->scommerceHelperManager = $this->getContainer()->get("scommerce_helper_manager");
        }

        $p["account"] = $account->getId();

        $orders = $this->scommerceHelperManager->getDashboardFilteredOrders($p);

        if (!empty($orders) && $isEmpty) {
            $orders = array_slice($orders, 0, 5, true);
        }

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Dashboard:dashboard_recent_orders_tbody.html.twig", $session->get("current_website_id")), array('orders' => $orders));

        return new JsonResponse(array('error' => false, 'orders' => $html));
    }
}
