<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractAutocompleteManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use Doctrine\Common\Inflector\Inflector;

class ProductGroupTreeAutocompleteManager extends AbstractAutocompleteManager
{
    /**@var EntityManager $entityManager */
    protected $entityManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    public function getAutoComplete($term, $attribute, $formData)
    {
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($term)) {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("or");
            $compositeFilter->addFilter(new SearchFilter(\Doctrine\Common\Util\Inflector::camelize("name"), "bw", $term));

            $compositeFilters->addCompositeFilter($compositeFilter);
        }

        $entities = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->entityManager->getEntityTypeByCode("product_group"), $compositeFilters);

        return $entities;
    }

    public function renderTemplate($data, $template, $attribute)
    {

        $ret = array();

        if ($template == null) {
            $template = "default";
        }

        $lookupAttribute = $this->attributeContext->getById($attribute->getLookupAttribute());
        $filterAttribute = $this->attributeContext->getById($lookupAttribute);
        $attributeCode = Inflector::camelize($filterAttribute->getAttributeCode());

        if (!empty($data)) {
            foreach ($data as $key => $d) {
                $ret[$key]["id"] = $d->getId();
                $ret[$key]["html"] = $this->getTreeHtml($d, $attributeCode);
//                    $this->twig->render("AppBundle:Form/AutocompleteTemplates:default.html.twig", array('field_data' => $d, 'attribute' => $attributeCode));
            }
        }

        return $ret;
    }

    public function renderSingleItem($item, $attributeCode)
    {
        $ret["id"] = $item->getId();
        $ret["lookup_value"] = $this->twig->render("AppBundle:Form/AutocompleteTemplates:default.html.twig", array('field_data' => $item, 'attribute' => $attributeCode));

        return $ret;
    }

    /**
     * Ovveride this method
     */
    public function getRenderedItemById($attribute, $id)
    {

        $entity = $this->entityManager->getEntityByEntityTypeAndId($attribute->getLookupEntityType(), $id);

        if (empty($entity)) {
            return false;
        }

        return $this->renderSingleItem($entity, EntityHelper::makeGetter($attribute->getLookupAttribute()->getAttributeCode()));
    }

    /**
     * Ovveride this method
     */
    public function getTemplate()
    {
        return "AppBundle:Form/AutocompleteTemplates:default.html.twig";
    }

    /**
     * @param ProductGroupEntity $productGroup
     * @param $attributeCode
     * @return string
     */
    private function getTreeHtml(ProductGroupEntity $productGroup, $attributeCode)
    {
        $html = "";

        if (!empty($productGroup->getProductGroup())) {
            $html .= $this->getTreeHtml($productGroup->getProductGroup(), $attributeCode) . " > ";
        }

        $html .= $this->twig->render("AppBundle:Form/AutocompleteTemplates:default.html.twig", array('field_data' => $productGroup, 'attribute' => "name"));

        return $html;
    }
}
