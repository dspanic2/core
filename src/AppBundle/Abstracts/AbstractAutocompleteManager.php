<?php

namespace AppBundle\Abstracts;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Interfaces\Managers\AutocompleteManagerInterface;
use AppBundle\Managers\EntityManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base abstract claas for any manager that will have to be container aware
 */
abstract class AbstractAutocompleteManager implements AutocompleteManagerInterface, ContainerAwareInterface
{
    protected $container;
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /** @var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    protected $twig;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Ovveride this method to initialize all services you will require
     */
    public function initialize()
    {
        $this->entityManager = $this->container->get("entity_manager");
        $this->twig = $this->container->get("templating");
        $this->attributeSetContext = $this->container->get("attribute_set_context");
        $this->attributeContext = $this->container->get("attribute_context");
    }

    /**
     * Ovveride this method
     */
    public function getAutoComplete($term, $attribute, $formData)
    {
    }

    /**
     * Ovveride this method
     */
    public function renderTemplate($data, $template, $request)
    {
    }

    /**
     * Ovveride this method
     */
    public function renderSingleItem($item, $attributeCode)
    {
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

        return $this->renderSingleItem($entity, $attribute->getAttributeCode());
    }

    /**
     * Ovveride this method
     */
    public function getTemplateForAddedEntities()
    {
        return "AppBundle:Form/AutocompleteTemplates:default_added_entities.html.twig";
    }
}
