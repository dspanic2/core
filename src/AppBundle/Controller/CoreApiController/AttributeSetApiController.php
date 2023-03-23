<?php

namespace AppBundle\Controller\CoreApiController;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\DataTable\DataTablePager;
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
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class AttributeSetApiController extends FOSRestController
{

    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;

    public function initialize()
    {
        $this->attributeSetContext = $this->getContainer()->get("attribute_set_context");
        $this->entityTypeContext = $this->getContainer()->get("entity_type_context");
    }

    /**
     * @Route("/core/api/attribute_set", name="get_all_attribute_set")
     * @Method("GET")
     * @ApiDoc(
     *  resource=true,
     *  section="Attribute set",
     *  description="Get collection with definitions of all attributes set",
     *  parameters={{"name"="bundle", "dataType"="string", "required"=false, "description"="Filter types by bundle(ex:AppBundle)"}},
     * )
     */

    public function getAll(Request $request)
    {
        $this->initialize();
        $bundle = null;

        if ($request->query->has('bundle')) {
            $bundle = $request->query->get('bundle');
        }

        $itemsArray = array();


        $items = $this->attributeSetContext->getAll();


        /**@var \AppBundle\Entity\AttributeSet $item */
        foreach ($items as $item) {
            if ($bundle != null && $item->getEntityType()->getBundle() != $bundle) {
                continue;
            }

            $itemsArray[] = $item->convertToArray();
        }
        return new JsonResponse($itemsArray);
    }

    /**
     * @Route("/core/api/attribute_set/{entity_type_code}", name="get_all_attribute_sets_by_entity_type")
     * @Method("GET")
     * @ApiDoc(
     *  resource=true,
     *  section="Attribute set",
     *  description="Get collection with definitions of all attribute sets for an entity type",
     *  filters={},
     *  requirements={
     *       {
     *          "name"="entity_type_code",
     *          "dataType"="string",
     *          "requirement"="",
     *          "description"="Code of entity type for which you want to get collection of attribute sets"
     *      },
     *
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function getAttributeSetsByEntityTypeCode($entity_type_code)
    {
        $this->initialize();

        $entityType = $this->entityTypeContext->getItemByCode($entity_type_code);

        $itemsArray = array();
        $items = $this->attributeSetContext->getAttributeSetsByEntityType($entityType);


        /**@var \AppBundle\Entity\AttributeSet $item */
        foreach ($items as $item) {
            $itemsArray[] = $item->convertToArray();
        }
        return new JsonResponse($itemsArray);
    }
}
