<?php

namespace AppBundle\Controller\CoreApiController;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\Entity;
use AppBundle\Factory\FactoryManager;
use AppBundle\Managers\EntityManager;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\View\View;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class AttributeApiController extends FOSRestController
{

    /**@var AttributeContext $attributeContext */
    protected $attributeContext;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /**@var PageBlockContext $pageBlockContext */
    protected $pageBlockContext;

    public function initialize()
    {
        $this->attributeContext = $this->getContainer()->get("attribute_context");
        $this->entityTypeContext = $this->getContainer()->get("entity_type_context");
        $this->pageBlockContext = $this->getContainer()->get("page_block_context");
    }


    /**
     * @Route("/core/api/attribute", name="get_all_attribute")
     * @Method("GET")
     * @ApiDoc(
     *  resource=true,
     *  section="Attribute",
     *  description="Get collection with definitions of all attributes",
     *  filters={},
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function getAll()
    {
        $this->initialize();

        $itemsArray = array();
        $items = $this->attributeContext->getAll();

        /**@var \AppBundle\Entity\Attribute $item */
        foreach ($items as $item) {
            $itemArray = array();
            $itemArray = $item->convertToArray();

            if ($item->getModalPageBlockId() != null) {
                $modalBlock = $this->pageBlockContext->getById($item->getModalPageBlockId());
                if ($modalBlock != null) {
                    $itemArray["modalPageBlockUid"] = $modalBlock->getUid();
                } else {
                    $itemArray["modalPageBlockId"] = "";
                }
            }

            $itemsArray[] = $itemArray;
        }
        return new JsonResponse($itemsArray);
    }

    /**
     * @Route("/core/api/attribute/{entity_type_code}", name="get_all_attributes_by_entity_type")
     * @Method("GET")
     * @ApiDoc(
     *  resource=true,
     *  section="Attribute",
     *  description="Get collection with definitions of all attributes for an entity type",
     *  filters={},
     *  requirements={
     *       {
     *          "name"="entity_type_code",
     *          "dataType"="string",
     *          "requirement"="",
     *          "description"="Code of entity type for which you want to get collection of attributes"
     *      },
     *
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function getAttributesByEntityTypeCode($entity_type_code)
    {
        $this->initialize();

        $entityType = $this->entityTypeContext->getItemByCode($entity_type_code);

        if ($entityType == null) {
            $response = new Response();
            $response->setStatusCode(Response::HTTP_NO_CONTENT);
            return $response;
        }

        $itemsArray = array();
        $items = $this->attributeContext->getAttributesByEntityType($entityType);

        /**@var \AppBundle\Entity\Attribute $item */
        foreach ($items as $item) {
            $itemArray = array();
            $itemArray = $item->convertToArray();

            if ($item->getModalPageBlockId() != null) {
                $modalBlock = $this->pageBlockContext->getById($item->getModalPageBlockId());
                $itemArray["modalPageBlockUid"] = $modalBlock->getUid();
            }

            $itemsArray[] = $itemArray;
        }
        return new JsonResponse($itemsArray);
    }

    /**
     * @Route("/core/api/attribute/{entity_type_code}/{attribute_code}", name="get_single_attribute_by_code")
     * @Method("GET")
     * @ApiDoc(
     *  resource=true,
     *  section="Attribute",
     *  description="Get definition of attribute by attribute code",
     *  filters={},
     *  requirements={
     *       {
     *          "name"="entity_type_code",
     *          "dataType"="string",
     *          "requirement"="",
     *          "description"="Code of entity type that is parent of attribute for which you want to get definition"
     *      },
     *      {
     *          "name"="attribute_code",
     *          "dataType"="string",
     *          "requirement"="",
     *          "description"="Code of attribute for which you want to get definition"
     *      },
     *  },
     *  output={"collection"=false, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function getAttributeByCode($entity_type_code, $attribute_code)
    {
        $this->initialize();

        $notFoundResponse = new Response();
        $notFoundResponse->setStatusCode(Response::HTTP_NO_CONTENT);

        $entityType = $this->entityTypeContext->getItemByCode($entity_type_code);

        if ($entityType == null) {
            return $notFoundResponse;
        }

        /**@var Attribute $item */
        $item = $this->attributeContext->getAttributeByCode($attribute_code, $entityType);

        if ($item == null) {
            return $notFoundResponse;
        }

        $itemArray[] = $item->convertToArray();

        if ($item->getModalPageBlockId() != null) {
            $modalBlock = $this->pageBlockContext->getById($item->getModalPageBlockId());
            $itemArray["modalPageBlockUid"] = $modalBlock->getUid();
        }


        return new JsonResponse($itemArray);
    }
}
