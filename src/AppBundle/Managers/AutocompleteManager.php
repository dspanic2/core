<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractAutocompleteManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use Doctrine\Common\Inflector\Inflector;

class AutocompleteManager extends AbstractAutocompleteManager
{
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /**@var EntityManager $entityManager */
    protected $entityManager;


    public function initialize()
    {
        parent::initialize();
        $this->attributeContext = $this->container->get("attribute_context");
        ;
        $this->entityManager = $this->container->get("entity_manager");
    }

    public function getAutoComplete($term, $attribute, $formData)
    {
        /**default limit to number of returned */
        $pagingFilter=new PagingFilter();
        $pagingFilter->setPageNumber(0);
        $pagingFilter->setPageSize(50);

        if ($attribute->getFrontendType() == "multiselect") {
            $filterAttribute = $attribute->getLookupAttribute()->getLookupAttribute();

            $attributes = $this->entityManager->getAttributeCodesAndFrontendTypeByEntityType($filterAttribute->getEntityType());

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
            if (!empty($term)) {
                $compositeFilter->addFilter(new SearchFilter(Inflector::camelize($filterAttribute->getAttributeCode()), "bw", $term));
            }
            $compositeFilters = new CompositeFilterCollection();
            $compositeFilters->addCompositeFilter($compositeFilter);

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

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
            if (!empty($term)) {
                $compositeFilter->addFilter(new SearchFilter(Inflector::camelize($attribute->getLookupAttribute()->getAttributeCode()), "bw", $term));
            }
            $compositeFilters = new CompositeFilterCollection();
            $compositeFilters->addCompositeFilter($compositeFilter);

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

    public function createItem($type, $p)
    {
        $factoryManager = $this->container->get('factory_manager');
        $formManager = $factoryManager->loadFormManager($type);

        $entityValidate = $formManager->validateFormModel($type, $p);
        if ($entityValidate->getIsValid() == false) {
            return [
                "error" => true,
                "message" => $entityValidate->getMessage()
            ];
        }

        $entity = $formManager->saveFormModel($type, $p);

        if (empty($entity)) {
            return [
                "error" => true,
                "message" => "Failed to create"
            ];
        }

        return [
            "error" => false,
            "data" => [
                "id" => $entity->getId(),
                "name" => $entity->getName()
            ]
        ];
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
