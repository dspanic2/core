<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractAutocompleteManager;
use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\StringHelper;
use AppBundle\Helpers\EntityHelper;
use Doctrine\Common\Inflector\Inflector;
use AppBundle\Managers\EntityManager;

class CityAutocompleteManager extends AbstractAutocompleteManager
{
    public function initialize()
    {
        parent::initialize();
    }

    public function getAutoComplete($term, $attribute, $formData)
    {

        /**default limit to number of returned */
        $pagingFilter = new PagingFilter();
        $pagingFilter->setPageNumber(0);
        $pagingFilter->setPageSize(50);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($term)) {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("or");
            $compositeFilter->addFilter(
                new SearchFilter(
                    \Doctrine\Common\Util\Inflector::camelize($attribute->getLookupAttribute()->getAttributeCode()),
                    "bw",
                    $term
                )
            );
            $compositeFilter->addFilter(new SearchFilter("postalCode", "bw", $term));

            $compositeFilters->addCompositeFilter($compositeFilter);
        }

        $sortFilters = new SortFilterCollection();
        if (!empty($term)) {
            if (preg_match('#[^0-9]#', $term)) {
                $sortFilters->addSortFilter(new SortFilter("name", "asc"));
            } else {
                $sortFilters->addSortFilter(new SortFilter("postalCode", "asc"));
            }
        } else {
            $sortFilters->addSortFilter(new SortFilter("postalCode", "asc"));
        }

        $entities = $this->entityManager->getEntitiesByEntityTypeAndFilter(
            $attribute->getLookupEntityType(),
            $compositeFilters,
            $sortFilters,
            $pagingFilter
        );

        return $entities;
    }

    public function renderTemplate($data, $template, $attribute)
    {
        $this->initialize();

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
                $ret[$key]["html"] = $this->twig->render(
                    "CrmBusinessBundle:AutocompleteTemplates:city.html.twig",
                    array('field_data' => $d, 'attribute' => $attributeCode)
                );
            }
        }

        return $ret;
    }

    public function renderSingleItem($item, $attributeCode)
    {
        $this->initialize();

        $ret["id"] = $item->getId();
        $ret["lookup_value"] = $this->twig->render(
            "CrmBusinessBundle:AutocompleteTemplates:city.html.twig",
            array('field_data' => $item, 'attribute' => $attributeCode)
        );

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

        return $this->renderSingleItem(
            $entity,
            EntityHelper::makeGetter($attribute->getLookupAttribute()->getAttributeCode())
        );
    }

    /**
     * Ovveride this method
     */
    public function getTemplate()
    {
        return "CrmBusinessBundle:AutocompleteTemplates:city.html.twig";
    }

    /**
     * Ovveride this method
     */
    public function getTemplateForAddedEntities()
    {
        return "CrmBusinessBundle:AutocompleteTemplates:city_added_entities.html.twig";
    }
}
