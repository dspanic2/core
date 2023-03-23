<?php


namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractAutocompleteManager;
use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
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
use CrmBusinessBundle\Constants\CrmConstants;
use Doctrine\Common\Inflector\Inflector;
use AppBundle\Managers\EntityManager;

class SupplierAutocompleteManager extends AbstractAutocompleteManager
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
        $compositeFilter->addFilter(new SearchFilter("accountTypes.id", "in", CrmConstants::ACCOUNT_TYPE_SUPPLIER));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if ($attribute->getFrontendType() == "multiselect") {
            $filterAttribute = $attribute->getLookupAttribute()->getLookupAttribute();

            $attributes = $this->entityManager->getAttributeCodesAndFrontendTypeByEntityType($filterAttribute->getEntityType());

            if (!empty($term)) {
                $terms = explode(" ", $term);
                foreach ($terms as $term) {
                    if (strlen(trim($term)) < 2) {
                        continue;
                    }
                    $compositeFilter = new CompositeFilter();
                    $compositeFilter->setConnector("or");
                    $compositeFilter->addFilter(new SearchFilter(Inflector::camelize($attribute->getLookupAttribute()->getAttributeCode()), "bw", $term));
                    $compositeFilters->addCompositeFilter($compositeFilter);
                }
            }

            $sortFilters = null;
            if (array_key_exists("name",$attributes)) {
                $sortFilters = new SortFilterCollection();
                if(stripos($attributes["name"],"_store") !== false){
                    $sortFilters->addSortFilter(new SortFilter('["name","$.\"' . $_ENV["DEFAULT_STORE_ID"] . '\""]', "asc"));
                }
                else{
                    $sortFilters->addSortFilter(new SortFilter("name", "asc"));
                }
            }

            $entities = $this->entityManager->getEntitiesByEntityTypeAndFilter($filterAttribute->getEntityType(), $compositeFilters, $sortFilters, $pagingFilter);
        } else {
            $attributes = $this->entityManager->getAttributeCodesAndFrontendTypeByEntityType($attribute->getLookupEntityType());

            if (!empty($term)) {
                $terms = explode(" ", $term);
                foreach ($terms as $term) {
                    if (strlen(trim($term)) < 2) {
                        continue;
                    }
                    $compositeFilter = new CompositeFilter();
                    $compositeFilter->setConnector("or");
                    $compositeFilter->addFilter(new SearchFilter(Inflector::camelize($attribute->getLookupAttribute()->getAttributeCode()), "bw", $term));
                    $compositeFilters->addCompositeFilter($compositeFilter);
                }
            }

            $sortFilters = null;
            if (array_key_exists("name",$attributes)) {
                $sortFilters = new SortFilterCollection();
                if(stripos($attributes["name"],"_store") !== false){
                    $sortFilters->addSortFilter(new SortFilter('["name","$.\"' . $_ENV["DEFAULT_STORE_ID"] . '\""]', "asc"));
                }
                else{
                    $sortFilters->addSortFilter(new SortFilter("name", "asc"));
                }
            }

            $entities = $this->entityManager->getEntitiesByEntityTypeAndFilter($attribute->getLookupEntityType(), $compositeFilters, $sortFilters, $pagingFilter);
        }

        return $entities;
    }

    public function renderTemplate($data, $template, $attribute)
    {

        $ret = array();

        if ($template == null) {
            $template = "default";
        }

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
     * Ovveride this method
     */
    public function getTemplateForAddedEntities()
    {
        return "AppBundle:Form/AutocompleteTemplates:default_added_entities.html.twig";
    }
}
