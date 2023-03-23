<?php

namespace ScommerceBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use ScommerceBusinessBundle\Entity\SRouteNotFoundEntity;
use ScommerceBusinessBundle\Managers\RouteManager;

class SRouteNotFoundButtons extends AbstractBaseButtons
{
    /** @var RouteManager $routeManager */
    protected $routeManager;

    public function GetListPageButtons()
    {

        $buttons = array();

        $buttons[] = array(
            "type" => "button",
            "name" => $this->translator->trans("Recheck filtered urls"),
            "class" => "btn-primary btn-red btn-custom-yellow",
            "url" => "not_found_recheck",
            "action" => "button_list_filtered",
        );

        $buttons[] = array(
            "type" => "button",
            "name" => $this->translator->trans("Bulk redirect"),
            "class" => "btn-primary btn-red btn-custom-blue",
            "url" => "bulk_redirect_not_found",
            "action" => "button_list_filtered"
        );

        return $buttons;
    }

    public function GetFormPageButtons()
    {
        $data = $this->getData();

        $buttons = Array();

        if(isset($data["id"]) && !empty($data["id"])) {

            if(empty($this->routeManager)){
                $this->routeManager = $this->getContainer()->get("route_manager");
            }

            /** @var SRouteNotFoundEntity $notFoundRoute */
            $notFoundRoute = $this->routeManager->getRoute404ById($data["id"]);

            if(!empty($notFoundRoute->getIsRedirected())){
                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("Recheck url"),
                    "class" => "btn-primary btn-red btn-custom-yellow",
                    "url" => "not_found_url_recheck",
                    "action" => "default_form_button",
                );
            }
        }

        $buttons = array_merge($buttons,$this->getDefaultFormButtons());

        return $buttons;
    }
}