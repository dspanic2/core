<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Entity\ProductDocumentRuleEntity;
use Doctrine\Common\Util\Inflector;
use ScommerceBusinessBundle\Managers\RouteManager;

class ProductDocumentRulesManager extends ProductAttributeFilterRulesManager
{
    /** @var RouteManager $routeManager */
    protected $routeManager;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $productIds
     * @param null $additionalFilter
     * @return array
     */
    public function getDocumentsForProducts($productIds, $additionalFilter = null){

        $documentRules = $this->getActiveProductDocumentRules($additionalFilter);

        if(!EntityHelper::isCountable($documentRules) || count($documentRules) == 0){
            return Array();
        }

        $ret = Array();

        /** @var ProductDocumentRuleEntity $documentRule */
        foreach ($documentRules as $documentRule){
            if($this->checkIfProductInRule($documentRule,$productIds)){
                $ret[$documentRule->getProductDocumentId()] = $documentRule->getProductDocument();
            }
        }

        return $ret;
    }

    /**
     * @param null $additionalFilter
     * @return mixed
     */
    public function getActiveProductDocumentRules($additionalFilter = null){

        $et = $this->entityManager->getEntityTypeByCode("product_document_rule");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if(!empty($additionalFilter)){
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et,$compositeFilters);
    }

    /**
     * @param ProductDocumentRuleEntity $productDocumentRule
     * @param array $productIds
     * @return mixed[]
     */
    public function getProductIdsForRule($productDocumentRule, $productIds = Array()){

        $ret = Array();

        if(empty($this->attributeContext)){
            $this->attributeContext = $this->container->get("attribute_context");
        }

        $rules = $productDocumentRule->getRules();
        $where = "";
        $join = "";

        /**
         * Used only if rules are regenerated for subset of products
         */
        if(!empty($productIds)){
            $where.= " AND p.id IN (".implode(",",$productIds).") ";
        }

        $rules = json_decode($rules,true);

        if(!empty($rules)){

            $additionaFilter = $this->parseRuleToFilter($rules,$join,$where);
            if(isset($additionaFilter["join"])){
                $join = $additionaFilter["join"];
            }
            if(isset($additionaFilter["where"])){
                $where = $additionaFilter["where"];
            }
        }

        $data = $this->getProductsByRule($join,$where);

        if(!empty($data)){
            $ret = array_column($data,"id");
        }

        return $ret;
    }
}