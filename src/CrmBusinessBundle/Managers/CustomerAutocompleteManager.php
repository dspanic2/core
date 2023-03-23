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
use CrmBusinessBundle\Constants\CrmConstants;
use Doctrine\Common\Inflector\Inflector;
use AppBundle\Managers\EntityManager;

class CustomerAutocompleteManager extends AbstractAutocompleteManager
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
        $filterAttribute = $attribute->getLookupAttribute();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("accountTypes.id", "in", CrmConstants::ACCOUNT_TYPE_CUSTOMER));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($term)) {
            if(stripos($term,"@") !== false){
                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector("or");
                $compositeFilter->addFilter(new SearchFilter("email", "bw", $term));
                $compositeFilters->addCompositeFilter($compositeFilter);
            }
            else{
                $terms = explode(" ", $term);
                foreach ($terms as $term) {
                    if (strlen(trim($term)) < 2) {
                        continue;
                    }
                    $compositeFilter = new CompositeFilter();
                    $compositeFilter->setConnector("or");
                    $compositeFilter->addFilter(new SearchFilter("name", "bw", $term));
                    $compositeFilters->addCompositeFilter($compositeFilter);
                }
            }
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("name", "asc"));

        $entities = $this->entityManager->getEntitiesByEntityTypeAndFilter($filterAttribute->getEntityType(), $compositeFilters, $sortFilters, $pagingFilter);

        return $entities;
    }

    public function renderTemplate($data, $template, $attribute)
    {

        $ret = array();

        if ($template == null) {
            $template = "default";
        }

        $attributeCode = Inflector::camelize($attribute->getLookupAttribute()->getAttributeCode());

        $lookupAttribute = $this->attributeContext->getById($attribute->getLookupAttribute());
        $filterAttribute = $this->attributeContext->getById($lookupAttribute);
        $attributeCode = Inflector::camelize($filterAttribute->getAttributeCode());


        if (!empty($data)) {
            foreach ($data as $key => $d) {
                $ret[$key]["id"] = $d->getId();
                $ret[$key]["html"] = $this->twig->render("CrmBusinessBundle:AutocompleteTemplates:account.html.twig", array('field_data' => $d, 'attribute' => $attributeCode));
            }
        }

        return $ret;
    }

    public function renderSingleItem($item, $attributeCode)
    {
        $ret["id"] = $item->getId();
        $ret["lookup_value"] = $this->twig->render("CrmBusinessBundle:AutocompleteTemplates:account.html.twig", array('field_data' => $item, 'attribute' => $attributeCode));

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
        return "CrmBusinessBundle:AutocompleteTemplates:account.html.twig";
    }

    /**
     * Ovveride this method
     */
    public function getTemplateForAddedEntities()
    {
        return "CrmBusinessBundle:AutocompleteTemplates:account_added_entities.html.twig";
    }
}
