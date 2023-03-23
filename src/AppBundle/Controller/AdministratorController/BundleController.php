<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\AttributeContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\CoreSyncManager;
use GuzzleHttp\Psr7\UploadedFile;
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

class BundleController extends AbstractController
{
    /**@var AttributeContext $attributeContext */
    protected $attributeContext;
    /**@var AdministrationManager $administrationManager */
    protected $administrationManager;

    protected $managedEntityType;


    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();

        $this->attributeContext = $this->getContainer()->get('attribute_context');
        $this->administrationManager = $this->getContainer()->get("administration_manager");

        $this->managedEntityType = "bundle";
    }

    /**
     * @Route("administrator/bundle", name="bundle_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        return new Response($this->renderView('AppBundle:Admin/Bundle:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }


    /**
     * @Route("administrator/bundle/import_bundle", name="import_bundle")
     */
    public function importBundleAction(Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        return new Response($this->renderView('AppBundle:Admin/Bundle:import_bundle.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/bundle/import_file", name="import_bundle_file")
     */
    public function importBundleFileAction(Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();
        set_time_limit(0);
        /** @var CoreSyncManager $syncManager */
        $syncManager = $this->getContainer()->get("core_sync_manager");

        /**@var \SplFileInfo $file */
        $file = $request->files->get('file');

        $importObjects = json_decode(file_get_contents($file->getPathname()), true);

        // The order matters.
        $syncManager->sync("entity_type", $importObjects["entityTypes"], false);
        $syncManager->sync("attribute", $importObjects["attributes"], false);
        $syncManager->sync("attribute_set", $importObjects["attributeSets"], false);
        $syncManager->sync("attribute_group", $importObjects["attributeGroups"], false);
        $syncManager->sync("list_view", $importObjects["listViews"], false);
        $syncManager->sync("page_block", $importObjects["pageBlocks"], false);
        $syncManager->sync("page", $importObjects["pages"], false);

        dump("test");
        die;

    }

    /**
     * @Route("administrator/bundle/list", name="get_bundle_list")
     * @Method("POST")
     */
    public function GetList(Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        /**
         * List all bundles
         */
        $bundles = $this->administrationManager->getBusinessBundles();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);


        $html = $this->renderView('AppBundle:Admin/Bundle:list.html.twig', array('entities' => $bundles, 'managed_entity_type' => $this->managedEntityType));


        $ret = array();
        $ret["draw"] = $pager->getDraw();
        $ret["recordsTotal"] = count($bundles);
        $ret["recordsFiltered"] = count($bundles);
        $ret["data"] = array();
        $ret["html"] = $html;

        return new JsonResponse($ret);
    }


    /**
     * @Route("administrator/bundle/export", name="bundle_export")
     * @Method("GET")
     */
    public function exportBundleAction(Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        $bundle = $request->query->get("bundle");

        $json = $this->administrationManager->exportBundleObjects($bundle);

        $name = $bundle . '.json';
        header('Content-disposition: attachment; filename=' . $name);
        header('Content-type: application/json');

        die($json);
    }


    /**
     * @Route("administrator/bundle/delete", name="bundle_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        $p = $_POST;
        $bundle = $p["id"];

        $success = $this->administrationManager->deleteBundleEntityTypes($bundle);

        if (!$success) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        } else {
            return new JsonResponse(array('error' => false, 'title' => 'Delete bundle', 'message' => 'Bundle entities deleted'));
        }
    }
}
