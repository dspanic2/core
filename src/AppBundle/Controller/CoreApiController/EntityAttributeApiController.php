<?php

namespace AppBundle\Controller\CoreApiController;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityAttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\Entity;
use AppBundle\Entity\EntityAttribute;
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

class EntityAttributeApiController extends FOSRestController
{

    /**@var EntityAttributeContext  $entityAttributeContext */
    protected $entityAttributeContext;

    public function initialize()
    {
        $this->entityAttributeContext = $this->getContainer()->get("entity_attribute_context");
    }

    /**
     * @Route("/core/api/entity_attribute/list", name="get_all_entity_attribute")
     */
    public function getAll()
    {
        $this->initialize();

        $itemsArray = array();
        $items = $this->entityAttributeContext->getAll();

        /**@var \AppBundle\Entity\EntityAttribute $item */
        foreach ($items as $item) {
            $itemsArray[] = $item->convertToArray();
        }
        return new JsonResponse($itemsArray);
    }
}
