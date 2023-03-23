<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\NavigationLinkContext;
use AppBundle\Context\PageContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\NavigationLinkManager;
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

class NavigationLinkController extends AbstractController
{
    /**@var NavigationLinkContext $navigationLinkContext */
    protected $navigationLinkContext;
    /**@var PageContext $pageContext */
    protected $pageContext;
    /**@var AdministrationManager $administrationManager */
    protected $administrationManager;
    /**@var NavigationLinkManager $navigationLinkManager */
    protected $navigationLinkManager;
    
    protected $managedEntityType;

    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();

        $this->navigationLinkContext = $this->getContainer()->get("navigation_link_context");
        $this->pageContext = $this->getContainer()->get("page_context");
        $this->administrationManager = $this->getContainer()->get("administration_manager");
        $this->navigationLinkManager = $this->getContainer()->get("navigation_link_manager");

        $this->managedEntityType = "navigation_link";
    }

    /**
     * @Route("administrator/navigation_link", name="navigation_link_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();

        return new Response($this->renderView('AppBundle:Admin/NavigationLink:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/navigation_link/list", name="get_navigation_link_list")
     * @Method("POST")
     */
    public function GetList(Request $request)
    {
        $this->initialize();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);
        $entities = $this->navigationLinkContext->getItemsWithPaging($pager);

        $html = $this->renderView('AppBundle:Admin/NavigationLink:list.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType));
        $num_of_items = $this->navigationLinkContext->countAllItems();

        $ret = array();
        $ret["draw"] = $pager->getDraw();
        $ret["recordsTotal"] = $num_of_items;
        $ret["recordsFiltered"] = $num_of_items;
        $ret["data"] = array();
        $ret["html"] = $html;

        return new JsonResponse($ret);
    }

    /**
     * @Route("administrator/navigation_link/update/{id}", defaults={"id"=null}, name="navigation_link_update_form")
     */
    public function updateAction($id, Request $request)
    {
        $this->initialize();

        $navigationLinks = $this->navigationLinkManager->getNavigationJson();
        $pages = $this->pageContext->getAll();

        /**
         * Create
         */
        if (empty($id)) {
            return new Response($this->renderView(
                'AppBundle:Admin/NavigationLink:form.html.twig',
                array(
                    'entity' => null,
                    'navigation_json' => $navigationLinks,
                    'pages' => $pages,
                    'managed_entity_type' => $this->managedEntityType,
                )
            ));
        }


        /**
         * Update
         */
        else {
            $entity = $this->navigationLinkContext->getById($id);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
            }

            $navigationLinks = $this->navigationLinkManager->getNavigationJson();


            return new Response($this->renderView(
                'AppBundle:Admin/NavigationLink:form.html.twig',
                array(
                    'entity' => $entity,
                    'navigation_json' => $navigationLinks,
                    'pages' => $pages,
                    'managed_entity_type' => $this->managedEntityType,
                )
            ));
        }

        return false;
    }

    /**
     * @Route("administrator/navigation_link/delete", name="navigation_link_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->navigationLinkContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Attribute group does not exist'));
        }

        if (!$this->navigationLinkManager->delete($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete attribute group', 'message' => 'Attribute group has been deleted'));
    }

    /**
     * @param $navigationLinks
     * @return array
     */
    public function getIds($navigationLinks)
    {

        $usedIds = array();

        foreach ($navigationLinks as $navigationLink) {
            if (!empty($navigationLink["id"])) {
                $usedIds[] = $navigationLink["id"];
            }
            if (!empty($navigationLink["children"])) {
                $usedIdsChildren = $this->getIds($navigationLink["children"]);
                $usedIds = array_merge($usedIds, $usedIdsChildren);
            }
        }

        return $usedIds;
    }

    /**
     * @Route("administrator/navigation_link/save", name="navigation_link_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["navigation_json"]) || empty($p["navigation_json"])) {
            return new JsonResponse(array('error' => true, 'message' => 'navigation_json is not correct'));
        }

        $navigationArray = json_decode($p["navigation_json"], true);

        $navigationLinks = $this->navigationLinkManager->getNavigationJson();
        $navigationLinks = json_decode($navigationLinks, true);
        $usedIds = $this->getIds($navigationLinks);

        $currentIds = $this->getIds($navigationArray);

        foreach ($navigationArray as $key => $navigationLink) {
            if (!$this->navigationLinkManager->addNavigationLink($navigationLink, $key, null)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }
        }

        foreach ($usedIds as $usedId) {
            if (!in_array($usedId, $currentIds)) {
                $navigationLink = $this->navigationLinkContext->getById($usedId);
                if (!empty($navigationLink)) {
                    $this->navigationLinkManager->delete($navigationLink);
                }
            }
        }

        return new JsonResponse(array('error' => false, 'title' => 'Save navigation', 'message' => 'Navigation has been saved', 'entity' =>  array('id' => 1)));
    }
}
