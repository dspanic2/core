<?php

namespace CrmBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use ScommerceBusinessBundle\Managers\ProductGroupManager;

class ProductGroupButtons extends AbstractBaseButtons
{
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    public function GetFormPageButtons()
    {

        $data = $this->getData();

        $buttons = array();

        if (isset($data["id"]) && !empty($data["id"])) {

            $buttons[] = array(
                "type" => "button",
                "name" => $this->translator->trans("Filtered product XLS"),
                "class" => "btn-primary btn-red",
                "url" => "product_group_product_export_default",
                "action" => "button_default"
            );

            $buttons[] = array(
                "type" => "button",
                "name" => $this->translator->trans("Filtered product attributes XLS"),
                "class" => "btn-primary btn-red",
                "url" => "product_group_product_export_attributes_default",
                "action" => "button_default"
            );
        }

        $buttons = array_merge($buttons, $this->getDefaultFormButtons());

        return $buttons;
    }

    public function GetListPageButtons()
    {

        $buttons = array();

        $buttons[] = array(
            "type" => "button",
            "name" => $this->translator->trans("Filtered product group XLS"),
            "class" => "btn-primary btn-red",
            "url" => "product_group_export_default",
            "action" => "button_list_filtered"
        );

        return $buttons;
    }
}