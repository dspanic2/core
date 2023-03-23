<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractAutocompleteManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use Doctrine\Common\Inflector\Inflector;

class CanonicalAutocompleteManager extends AbstractAutocompleteManager
{
    protected $attributeContext;
    /**@var EntityManager $entityManager */
    protected $entityManager;
    protected $attributeSetContext;

    public function initialize()
    {
        parent::initialize();
        $this->attributeContext = $this->container->get("attribute_context");
        $this->entityManager = $this->container->get("entity_manager");
        $this->attributeSetContext = $this->container->get("attribute_set_context");
    }

    public function getAutoComplete($term, $attribute, $formData)
    {
        $compositeFilters = new CompositeFilterCollection();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        if (isset($formData["store_id"]) && !empty($formData["store_id"])) {
            $compositeFilter->addFilter(new SearchFilter("store", "eq", $formData["store_id"]));
        }
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (isset($formData["show_on_store"]) && !empty($formData["show_on_store"])) {
            $storeCompositeFilter = new CompositeFilter();
            $storeCompositeFilter->setConnector("or");

            foreach ($formData["show_on_store"] as $storeId => $val) {
                $storeCompositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));
            }
            $compositeFilters->addCompositeFilter($storeCompositeFilter);
        }

        if (!empty($term)) {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("or");
            $compositeFilter->addFilter(new SearchFilter(\Doctrine\Common\Util\Inflector::camelize($attribute->getLookupAttribute()->getAttributeCode()), "bw", $term));

            $compositeFilters->addCompositeFilter($compositeFilter);
        }

        $entities = $this->entityManager->getEntitiesByEntityTypeAndFilter($attribute->getLookupEntityType(), $compositeFilters);

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
                $ret[$key]["html"] = $this->twig->render("AppBundle:Form/AutocompleteTemplates:default.html.twig", array('field_data' => $d, 'attribute' => $attributeCode));
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
}
