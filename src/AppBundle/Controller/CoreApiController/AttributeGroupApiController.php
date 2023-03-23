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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class AttributeGroupApiController extends FOSRestController
{

    /**@var AttributeGroupContext $attributeGroupContext */
    protected $attributeGroupContext;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;



    public function initialize()
    {
        $this->attributeGroupContext = $this->getContainer()->get("attribute_group_context");
        $this->attributeSetContext = $this->getContainer()->get("attribute_set_context");
    }


    /**
     * @Route("/core/api/attribute_group", name="get_all_attribute_group")
     * @Method("GET")
     * @ApiDoc(
     *  resource=true,
     *  section="Attribute group",
     *  description="Get collection with definitions of all attribute groups",
     *  parameters={{"name"="bundle", "dataType"="string", "required"=false, "description"="Filter types by bundle(ex:AppBundle)"}},
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function getAll(Request $request)
    {
        $this->initialize();
        $bundle = null;
        $itemsArray = array();

        if ($request->query->has('bundle')) {
            $bundle = $request->query->get('bundle');
        }

        $itemsArray = array();

        $items = $this->attributeGroupContext->getAll();

        /**@var \AppBundle\Entity\AttributeGroup $item */
        foreach ($items as $item) {
            if ($bundle != null && $item->getAttributeSet()->getEntityType()->getBundle() != $bundle) {
                continue;
            }

            $itemsArray[] = $item->convertToArray();
        }
        return new JsonResponse($itemsArray);
    }

    /**
     * @Route("/core/api/attribute_group/{attribute_set_code}", name="get_all_attribute_groups_by_attribute_set")
     * @Method("GET")
     * @ApiDoc(
     *  resource=true,
     *  section="Attribute group",
     *  description="Get collection with definitions of all attribute groups for attribute set",
     *  filters={},
     *  requirements={
     *       {
     *          "name"="attribute_set_code",
     *          "dataType"="string",
     *          "requirement"="",
     *          "description"="Code of attribute set for which you want to get collection of attribute groups"
     *      },
     *
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function getAttributeGroupsByAttributeSetCode($attribute_set_code)
    {
        $this->initialize();

        $attributeSet = $this->attributeSetContext->getItemByCode($attribute_set_code);

        if ($attributeSet == null) {
            $response = new Response();
            $response->setStatusCode(Response::HTTP_NO_CONTENT);
            return $response;
        }

        $itemsArray = array();
        $items = $this->attributeGroupContext->getAttributesGroupsBySet($attributeSet);

        /**@var \AppBundle\Entity\AttributeGroup $item */
        foreach ($items as $item) {
            $itemsArray[] = $item->convertToArray();
        }
        return new JsonResponse($itemsArray);
    }
}
