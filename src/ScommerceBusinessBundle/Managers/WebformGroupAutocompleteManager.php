<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractAutocompleteManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use Doctrine\Common\Inflector\Inflector;

class WebformGroupAutocompleteManager extends AbstractAutocompleteManager
{

    public function getAutoComplete($term, $attribute, $formData)
    {
        $pagingFilter = new PagingFilter();
        $pagingFilter->setPageNumber(0);
        $pagingFilter->setPageSize(50);
        $filterAttribute = $attribute->getLookupAttribute();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        if (isset($formData["pid"]) && !empty($formData["pid"])) {
            $compositeFilter->addFilter(new SearchFilter("webformId", "eq", $formData["pid"]));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($term)) {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("or");
            $compositeFilter->addFilter(new SearchFilter(\Doctrine\Common\Util\Inflector::camelize($attribute->getLookupAttribute()->getAttributeCode()), "bw", $term));

            $compositeFilters->addCompositeFilter($compositeFilter);
        }

        $entities = $this->entityManager->getEntitiesByEntityTypeAndFilter($filterAttribute->getEntityType(), $compositeFilters, null, $pagingFilter);

        return $entities;
    }

    public function renderTemplate($data, $template, $attribute)
    {
        $ret = [];

        $attributeCode = Inflector::camelize($attribute->getLookupAttribute()->getAttributeCode());

        if ($attribute->getFrontendType() == "multiselect") {
            $lookupAttribute = $this->attributeContext->getById($attribute->getLookupAttribute());
            $filterAttribute = $this->attributeContext->getById($lookupAttribute->getLookupAttribute());
            $attributeCode = Inflector::camelize($filterAttribute->getAttributeCode());
        }

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

    public function getRenderedItemById($attribute, $id)
    {
        $entity = $this->entityManager->getEntityByEntityTypeAndId($attribute->getLookupEntityType(), $id);
        if (empty($entity)) {
            return false;
        }

        return $this->renderSingleItem($entity, EntityHelper::makeGetter($attribute->getLookupAttribute()->getAttributeCode()));
    }

    public function getTemplate()
    {
        return "AppBundle:Form/AutocompleteTemplates:default.html.twig";
    }

    public function getTemplateForAddedEntities()
    {
        return "AppBundle:Form/AutocompleteTemplates:default_added_entities.html.twig";
    }
}
