<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractAutocompleteManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use Doctrine\Common\Inflector\Inflector;
use function GuzzleHttp\Psr7\parse_request;

class ProductGroupAutocompleteManager extends AbstractAutocompleteManager
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $term
     * @param $attribute
     * @param $formData
     */
    public function getAutoComplete($term, $attribute, $formData)
    {
        $productEntityType = $this->entityManager->getEntityTypeByCode("product_group");

        /**default limit to number of returned */
        $pagingFilter = new PagingFilter();
        $pagingFilter->setPageNumber(0);
        $pagingFilter->setPageSize(50);

        $compositeFilters = $this->buildCompositeFilters($term, $formData, $productEntityType);

        $sortFilters = $this->buildSortFilters();

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($productEntityType, $compositeFilters, $sortFilters, $pagingFilter);
    }

    /**
     * @param $term
     * @param $formData
     * @param $productEntityType
     * @return CompositeFilterCollection
     */
    protected function buildCompositeFilters($term, $formData, $productEntityType)
    {
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($term)) {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("or");
            $compositeFilter->addFilter(new SearchFilter("productGroupCode", "bw", $term));
            $compositeFilter->addFilter(new SearchFilter("name", "json_bw", $term));
            $compositeFilter->addFilter(new SearchFilter("productGroup.name", "json_bw", $term));

            $compositeFilters->addCompositeFilter($compositeFilter);
        }
        return $compositeFilters;
    }

    /**
     * @return SortFilterCollection
     */
    protected function buildSortFilters()
    {
        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("name", "asc"));
        return $sortFilters;
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
                $ret[$key]["html"] = $this->twig->render("CrmBusinessBundle:AutocompleteTemplates:product_group.html.twig", array('field_data' => $d, 'attribute' => $attributeCode));
            }
        }

        return $ret;
    }

    public function renderSingleItem($item, $attributeCode)
    {
        $ret["id"] = $item->getId();
        $ret["lookup_value"] = $this->twig->render("CrmBusinessBundle:AutocompleteTemplates:product_group.html.twig", array('field_data' => $item, 'attribute' => $attributeCode));

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
        return "CrmBusinessBundle:AutocompleteTemplates:product_group.html.twig";
    }

    /**
     * Ovveride this method
     */
    public function getTemplateForAddedEntities()
    {
        return "CrmBusinessBundle:AutocompleteTemplates:product_group_added_entities.html.twig";
    }
}
