<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\EntityLogContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\EntityManager;
use Monolog\Logger;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Templating\TemplatingExtension;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;

class LogController extends AbstractController
{
    /**@var EntityLogContext $entityLogContext */
    protected $entityLogContext;

    protected $managedEntityType;

    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();

        $this->entityLogContext = $this->getContainer()->get('entity_log_context');
        $this->managedEntityType = "log";
    }

    /**
     * @Route("administrator/log", name="attribute_log")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();

        return new Response($this->renderView('AppBundle:Admin/Log:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/log/list", name="get_log_list")
     * @Method("POST")
     */
    public function GetList(Request $request)
    {
        $this->initialize();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);

        $entities = $this->entityLogContext->getItemsWithPaging($pager);

        $html = $this->renderView('AppBundle:Admin/Log:list.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType));
        $num_of_items = $this->entityLogContext->countAllItems();

        $ret = array();
        $ret["draw"] = $pager->getDraw();
        $ret["recordsTotal"] = $num_of_items;
        $ret["recordsFiltered"] = $num_of_items;
        $ret["data"] = array();
        $ret["html"] = $html;

        return new JsonResponse($ret);
    }

    /**
     * @Route("administrator/log/view/{id}/{attributeSetCode}", name="log_view_form")
     */
    public function viewAction($id, $attributeSetCode, Request $request)
    {

        $this->initialize();

        $entities = $this->entityLogContext->getBy(array('entityId' => $id, 'attributeSetCode' => $attributeSetCode), array('id' => 'DESC'));

        if (!isset($entities) || empty($entities)) {
            return new JsonResponse(array('error' => true, 'message' => 'Entity does not exist'));
        }

        return new Response($this->renderView('AppBundle:Admin/Log:view.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/log/restore", name="log_restore")
     */
    public function restoreAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Entity does not exist'));
        }

        $entity_restore_point = $this->entityLogContext->getById($p["id"]);
        $aEntityRestore = json_decode($entity_restore_point->getContent(), true);

        if (!isset($entity_restore_point) || empty($entity_restore_point)) {
            return new JsonResponse(array('error' => true, 'message' => 'Entity does not exist'));
        }

        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get('entity_manager');
        /** @var AttributeSet $attributeSet */
        $attributeSet = $entityManager->getAttributeSetByCode($entity_restore_point->getAttributeSetCode());
        $entity = $entityManager->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $entity_restore_point->getEntityId());

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Entity does not exist'));
        }

        $entity = $entityManager->arrayToEntity($entity, $aEntityRestore);
        $entity->setEntityStateId(1);
        $entityManager->saveEntity($entity);

        return new JsonResponse(array('error' => false, 'title' => 'Restoring', 'message' => 'Entity restored'));
    }
}
