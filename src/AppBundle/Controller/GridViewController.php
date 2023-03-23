<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\PageBlock;
use AppBundle\Entity\SearchFilter;
use AppBundle\Factory\FactoryEntityType;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Entity\KanbanColumnEntity;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use AppBundle\Managers\EntityManager;
use Symfony\Component\Config\Definition\Exception\Exception;

class GridViewController extends AbstractController
{
    /** @var  EntityManager $entityManager */
    protected $entityManager;
    /** @var  PageBlockContext $pageBlockContext */
    protected $pageBlockContext;
    /**@var AttributeContext $attributeContext */
    protected $attributeContext;

    protected function initialize()
    {
        parent::initialize();
        $this->pageBlockContext = $this->getContainer()->get('page_block_context');
        $this->attributeContext = $this->getContainer()->get("attribute_context");
        $this->entityManager = $this->getContainer()->get("entity_manager");
    }

    /**
     * @Route("/gridview/filter", name="gridview_filter")
     */
    public function getFilteredData(Request $request)
    {
        $p = $_POST;

        $this->initialize();

        if (!isset($p["blockId"]) || empty($p["blockId"])) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Block id is not defined"))
            );
        }

        /** @var PageBlock $block */
        $block = $this->pageBlockContext->getById($p["blockId"]);
        $blockContent = json_decode($block->getContent(), true);

        $compositeFilters = new CompositeFilterCollection();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters->addCompositeFilter($compositeFilter);

        if (isset($blockContent["filter"]) && !empty(trim($blockContent["filter"]))) {
            $decodedFilter = (array)json_decode(trim($blockContent["filter"]));
            $currentDate = new \DateTime();

            foreach ($decodedFilter->filters as $filter) {
                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector($decodedFilter->connector);

                $searchFilter = new SearchFilter();
                $filter->value = str_replace("{id}", $request->getRequestId(), $filter->value);
                $filter->value = str_replace("{now}", $currentDate->format("Y-m-d H:i:s"), $filter->value);
                $filter->value = str_replace("{user_id}", $this->user->getId(), $filter->value);
                $searchFilter->setFromArray($filter);
                $compositeFilter->addFilter($searchFilter);
            }

            $compositeFilters->addCompositeFilter($compositeFilter);
        }

        $categoryAttr = false;
        $categoryAttributeSet = false;
        if (isset($blockContent["category"]) && !empty($blockContent["category"])) {
            if ($blockContent["category"] == "attribute_set") {
                $categoryAttributeSet = true;
            } else {
                /** @var Attribute $categoryAttr */
                $categoryAttr = $this->attributeContext->getOneBy(
                    array('attributeCode' => $blockContent["category"], 'entityType' => $block->getEntityType())
                );
                if (empty($categoryAttr)) {
                    $categoryAttr = false;
                }
            }
        }

        if (isset($p["categoryVal"]) && !empty($p["categoryVal"])) {
            // Filter by attribute set
            if ($categoryAttributeSet) {
                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector("and");
                $compositeFilter->addFilter(new SearchFilter("attributeSet.id", "eq", $p["categoryVal"]));

                $compositeFilters->addCompositeFilter($compositeFilter);
            }
            // Filter by attribute
            if ($categoryAttr) {
                $attributeName = EntityHelper::makeAttributeName($categoryAttr->getAttributeCode());
                $attributeName = str_replace("Id", ".id", $attributeName);

                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector("and");
                $compositeFilter->addFilter(new SearchFilter($attributeName, "eq", $p["categoryVal"]));

                $compositeFilters->addCompositeFilter($compositeFilter);
            }
        }

        if (isset($p["keywordVal"]) && !empty(trim($p["keywordVal"]))) {
            $attributes = $this->entityManager->getAttributeCodesByEntityType($block->getEntityType());

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("or");
            if (in_array("name", $attributes)) {
                $compositeFilter->addFilter(
                    new SearchFilter(EntityHelper::makeAttributeName("name"), "bw", $p["keywordVal"])
                );
            }
            if (in_array("ean", $attributes)) {
                $compositeFilter->addFilter(
                    new SearchFilter(EntityHelper::makeAttributeName("ean"), "bw", $p["keywordVal"])
                );
            }
            if (in_array("code", $attributes)) {
                $compositeFilter->addFilter(
                    new SearchFilter(EntityHelper::makeAttributeName("code"), "bw", $p["keywordVal"])
                );
            }

            $compositeFilters->addCompositeFilter($compositeFilter);
        }

        $allItems = $this->entityManager->getEntitiesByEntityTypeAndFilter($block->getEntityType(), $compositeFilters);

        $attributeSetDefinitions = array();

        if (isset($blockContent["attribute_set_definition"]) && !empty($blockContent["attribute_set_definition"])) {
            foreach ($blockContent["attribute_set_definition"] as $attribute_set_code => $definition) {
                $attributeSetDefinitions[$attribute_set_code] = json_decode($definition, true)[0];

                /**
                 * Check if template exists
                 */
                if (!$this->getContainer()->get('twig')->getLoader()->exists(
                    $attributeSetDefinitions[$attribute_set_code]["item_template"]
                )) {
                    unset($attributeSetDefinitions[$attribute_set_code]);
                }
            }
        }

        $html = null;

        foreach ($allItems as $key => $item) {
            if (!isset(
                    $attributeSetDefinitions[$item->getAttributeSet()->getAttributeSetCode()]
                ) || empty($attributeSetDefinitions[$item->getAttributeSet()->getAttributeSetCode()])) {
                continue;
            }
            $definition = $attributeSetDefinitions[$item->getAttributeSet()->getAttributeSetCode()];

            $data = array();
            $data["entity"] = $item;
            $data["definition"] = $definition;

            $html .= $this->renderView($definition["item_template"], array('data' => $data));
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/gridview/configurable", name="default_configurable_form")
     */
    public function defaultConfigurableForm(Request $request)
    {
        $p = $_POST;

        $this->initialize();

        if (!isset($p["blockId"]) || empty($p["blockId"])) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Block id is not defined"))
            );
        }
        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Product id is not defined"))
            );
        }

        /** @var PageBlock $block */
        $block = $this->pageBlockContext->getById($p["blockId"]);
        $blockContent = json_decode($block->getContent(), true);

        $parentEntity = $this->entityManager->getEntityByEntityTypeAndId($block->getEntityType(), $p["id"]);

        if (empty($parentEntity)) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Product does not exist"))
            );
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("productId", "eq", $parentEntity->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $allItems = $this->entityManager->getEntitiesByEntityTypeAndFilter($block->getEntityType(), $compositeFilters);

        $attributeSetDefinitions = array();

        foreach ($blockContent["attribute_set_definition"] as $attribute_set_code => $definition) {
            $attributeSetDefinitions[$attribute_set_code] = json_decode($definition, true)[0];

            /**
             * Check if template exists
             */
            if (!$this->getContainer()->get('twig')->getLoader()->exists(
                $attributeSetDefinitions[$attribute_set_code]["item_template"]
            )) {
                unset($attributeSetDefinitions[$attribute_set_code]);
            }
        }

        $html = null;

        foreach ($allItems as $key => $item) {
            if (!isset(
                    $attributeSetDefinitions[$item->getAttributeSet()->getAttributeSetCode()]
                ) || empty($attributeSetDefinitions[$item->getAttributeSet()->getAttributeSetCode()])) {
                continue;
            }
            $definition = $attributeSetDefinitions[$item->getAttributeSet()->getAttributeSetCode()];

            $data = array();
            $data["entity"] = $item;
            $data["definition"] = $definition;

            $html .= $this->renderView($definition["item_template"], array('data' => $data));
        }

        $html = $this->renderView(
            'AppBundle:Includes:modal.html.twig',
            array("html" => $html, "title" => $this->translator->trans("Select simple product"))
        );
        if (empty($html)) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('Error opening modal'))
            );
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }


    /**
     * @Route("/gridview/attribute_set_definition", name="get_attribute_set_definition")
     */
    public function getAttributeSetDefinition()
    {

        $p = $_POST;

        $this->initialize();

        if ((!isset($p["entityType"]) || empty($p["entityType"]))) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("entityType is not correct"))
            );
        }

        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->getContainer()->get("entity_type_context");
        $entityType = $entityTypeContext->getById($p["entityType"]);

        if (empty($entityType)) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("entityType does not exist"))
            );
        }

        /** @var AttributeSetContext $attributeSetContext */
        $attributeSetContext = $this->getContainer()->get('attribute_set_context');

        $attributeSets = $attributeSetContext->getBy(array("entityType" => $entityType));

        $html = $this->renderView(
            "AppBundle:Helper:grid_view_settings_attribute_set_list.html.twig",
            array('attributeSets' => $attributeSets)
        );

        return new JsonResponse(array('error' => false, 'html' => $html));
    }
}
