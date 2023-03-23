<?php

namespace WikiBusinessBundle\Managers;

use AppBundle\Context\EntityContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use Doctrine\Common\Inflector\Inflector;
use AppBundle\Abstracts\AbstractAutocompleteManager;

class WikiPageAutocompleteManager extends AbstractAutocompleteManager
{
    /** @var EntityContext $entityCtx */
    protected $entityCtx;
    /** @var EntityTypeContext $entityTypeCtx */
    protected $entityTypeCtx;

    public function initialize()
    {
        parent::initialize();
        $this->entityCtx = $this->container->get("entity_context");
        $this->entityTypeCtx = $this->container->get("entity_type_context");
    }

    public function getAutoComplete($term, $attribute, $formData)
    {
        $pagingFilter = new PagingFilter();
        $pagingFilter->setPageNumber(0);
        $pagingFilter->setPageSize(50);
        $filterAttribute = $attribute->getLookupAttribute();

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        if (!empty($term)) {
            $compositeFilter->addFilter(new SearchFilter(Inflector::camelize($filterAttribute->getAttributeCode()), "bw", $term));
        }

        if (array_key_exists("parent_topic_id", $formData)) {
            $compositeFilter->addFilter(new SearchFilter("parentTopicId", "eq", $formData["parent_topic_id"]));
        }

        if (array_key_exists("id", $formData)) {
            $compositeFilter->addFilter(new SearchFilter("id", "ne", $formData["id"]));
        }

        $compositeFilters->addCompositeFilter($compositeFilter);

        $entities = $this->entityManager->getEntitiesByEntityTypeAndFilter($filterAttribute->getEntityType(), $compositeFilters, null, $pagingFilter);
        return $entities;
    }

    public function renderTemplate($data, $template, $attribute)
    {
        $ret = array();

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

    public function createItem($type, $p)
    {
        $factoryManager = $this->container->get("factory_manager");
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

    public function getRenderedItemById($attribute, $id)
    {
        $entity = $this->entityManager->getEntityByEntityTypeAndId($attribute->getLookupEntityType(), $id);

        if (empty($entity)) {
            return false;
        }

        return $this->renderSingleItem($entity, EntityHelper::makeGetter($attribute->getLookupAttribute()->getAttributeCode()));
    }
}