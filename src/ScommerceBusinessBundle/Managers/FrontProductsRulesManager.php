<?php

namespace ScommerceBusinessBundle\Managers;

use CrmBusinessBundle\Managers\ProductAttributeFilterRulesManager;
use Doctrine\Common\Util\Inflector;

class FrontProductsRulesManager extends ProductAttributeFilterRulesManager
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $rules
     * @return mixed[]
     */
    public function getProductIdsForRule($rules)
    {

        $ret = array();

        $where = "";
        $join = "";

        $rules = json_decode($rules, true);

        if (!empty($rules)) {
            $additionaFilter = $this->parseRuleToFilter($rules, $join, $where);
            if (isset($additionaFilter["join"])) {
                $join = $additionaFilter["join"];
            }
            if (isset($additionaFilter["where"])) {
                $where = $additionaFilter["where"];
            }
        }

        $data = $this->getProductsByRule($join, $where);

        if (!empty($data)) {
            $ret = array_column($data, "id");
        }

        return $ret;
    }
}