<?php

namespace CrmBusinessBundle\Buttons;

use AppBundle\Abstracts\AbstractBaseButtons;
use CrmBusinessBundle\Entity\BulkPriceEntity;
use CrmBusinessBundle\Managers\BulkPriceManager;

class BulkPriceButtons extends AbstractBaseButtons
{
    /** @var BulkPriceManager $bulkPriceManager */
    protected $bulkPriceManager;

    public function GetListPageButtons(){

        $buttons[] = array(
            "type" => "button",
            "name" => $this->translator->trans("Recalculate bulk prices"),
            "class" => "btn-primary btn-red",
            "url" => "recalculate_bulk_prices",
            "action" => "button_default",
            "id" => 1
        );

        return $buttons;
    }

    public function GetFormPageButtons(){

        $data = $this->getData();

        $buttons = Array();

        if(isset($data["id"]) && !empty($data["id"])) {

            if(empty($this->bulkPriceManager)){
                $this->bulkPriceManager = $this->getContainer()->get("bulk_price_manager");
            }

            /** @var BulkPriceEntity $bulkPrice */
            $bulkPrice = $this->bulkPriceManager->getBulkPriceById($data["id"]);

            if($bulkPrice->getRecalculate()){
                $buttons[] = array(
                    "type" => "button",
                    "name" => $this->translator->trans("Recalculate bulk prices"),
                    "class" => "btn-primary btn-red",
                    "url" => "recalculate_bulk_prices",
                    "action" => "button_default",
                    "id" => $data["id"]
                );
            }
        }

        $buttons = array_merge($buttons,$this->getDefaultFormButtons());

        return $buttons;
    }
}