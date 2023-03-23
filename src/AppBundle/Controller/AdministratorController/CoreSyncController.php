<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\EntityType;
use AppBundle\Factory\FactoryEntityType;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\CoreSyncManager;
use AppBundle\Managers\DatabaseManager;
use AppBundle\Managers\RestManager;
use AppBundle\Managers\SyncManager;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class CoreSyncController extends AbstractController
{
    /**@var CoreSyncManager $coreSyncManager */
    protected $coreSyncManager;
    /** @var EntityTypeContext $entityTypeCtx */
    protected $entityTypeCtx;
    /** @var DatabaseManager $databaseManager */
    protected $databaseManager;
    /** @var AdministrationManager $administrationManager */
    protected $administrationManager;
    /** @var SyncManager $syncManager */
    protected $syncManager;

    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();
        $this->coreSyncManager = $this->getContainer()->get('core_sync_manager');
    }

    /**
     * @Route("/administrator/sync_tool", name="administrator_sync_tool")
     */
    public function syncronizationTool(Request $request)
    {
        $this->initialize();

        return new Response($this->renderView('AppBundle:Admin/Sync:index.html.twig', array()));
    }

    /**
     * @Route("/administrator/compare", name="administrator_compare")
     */
    public function coreCompareSync(Request $request)
    {
        $this->initialize();

        $restManager = new RestManager();
        $core_type = $request->get("compare_type");
        $remoteUrl = $request->get("remote_url");
        $showChangesOnly = $request->get("showChangesOnly") == "true" ? true : false;

        $url = rtrim($remoteUrl, "/")."/core/api/".$core_type;
        $remoteArray = $restManager->get($url);

        if (empty($remoteArray)) {
            return new JsonResponse(array('error' => true, 'message' => "Remote array is empty"));
        }

        $results = ($this->coreSyncManager->compareLocalToRemoteArray($core_type, $remoteArray, $showChangesOnly));

        $html = $this->renderView(
            'AppBundle:Admin/Sync:'.$core_type.'.html.twig',
            array('results' => $results, "compare_type" => $core_type, "remote_url" => $remoteUrl)
        );

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/administrator/import", name="administrator_import")
     */
    public function importView(Request $request)
    {
        $this->initialize();

        return new Response($this->renderView('AppBundle:Admin/Sync:import.html.twig', array()));
    }

    /**
     * @Route("/administrator/import/json", name="administrator_import_json")
     * @param Request $request
     * @return JsonResponse
     */
    public function importAction(Request $request)
    {
        try {
            $content = $request->request->get("export_content");
            $content = json_decode($content, true);

            /** @var CoreSyncManager $syncManager */
            $syncManager = $this->getContainer()->get("core_sync_manager");

            $getKey = function ($key) use ($content) {
                if (isset($content[$key]) && is_array($content[$key])) {
                    return $content[$key];
                }
                throw new Exception(
                    "Missing key ".$key." in provided export file."
                );
            };

            $content = [
                "entity_type" => $getKey("entityType"),
                "attribute" => $getKey("attributes"),
                "attribute_set" => $getKey("attributeSets"),
                "attribute_group" => $getKey("attributeGroups"),
            ];

            // The order matters.
            $syncManager->sync("entity_type", [$content["entity_type"]]);
            $syncManager->sync("attribute", $content["attribute"]);
            $syncManager->sync("attribute_set", $content["attribute_set"]);
            $syncManager->sync("attribute_group", $content["attribute_group"]);
        } catch (Exception $e) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'message' => $e->getMessage(),
                )
            );
        }

        return new JsonResponse(
            array(
                'error' => false,
                'message' => "Import successful!",
            )
        );
    }

    /**
     * @Route("/administrator/difference", name="administrator_difference")
     */
    public function viewDifference(Request $request)
    {
        $this->initialize();

        $restManager = new RestManager();
        $core_type = $request->get("core_type");
        $remoteUrl = $request->get("remote_url");
        $uid = $request->get("uid");

        $url = $url = rtrim($remoteUrl, "/")."/core/api/".$core_type;
        $remoteArray = $restManager->get($url);

        if (empty($remoteArray)) {
            return new JsonResponse(array('error' => true, 'message' => "Remote array is empty"));
        }

        $results = ($this->coreSyncManager->viewDifference($core_type, $remoteArray, $uid));

        return new Response(
            $this->renderView(
                'AppBundle:Admin/Sync:difference.html.twig',
                array('results' => $results, "compare_type" => $core_type, "remote_url" => $remoteUrl)
            )
        );
    }

    /**
     * @Route("/administrator/sync", name="admin_sync")
     */
    public function syncEntityType(Request $request)
    {
        $this->initialize();

        $uids = $request->get("checked_items");

        if ($uids == null) {
            return new JsonResponse(
                array('error' => true, 'error_message' => "Error occured", 'message' => "No items selected")
            );
        }

        $restManager = new RestManager();
        $core_type = $request->get("compare_type");
        $remoteUrl = $request->get("remote_url");
        $showChangesOnly = $request->get("showChangesOnly") == "true" ? true : false;

        $url = $url = rtrim($remoteUrl, "/")."/core/api/".$core_type;
        $remoteArray = $restManager->get($url);

        $entityTypeRebuildArray = Array();

        if (empty($remoteArray)) {
            return new JsonResponse(array('error' => true, 'message' => "Remote array is empty"));
        }

        foreach ($remoteArray as $k => $r) {
            if (!in_array($r["uid"], $uids)) {
                unset($remoteArray[$k]);
            }
        }

        if(!empty($remoteArray) && $core_type == "attribute"){
            foreach ($remoteArray as $rm){
                $entityTypeRebuildArray[] = $rm["entityTypeCode"];
            }
        }

        try {
            $this->coreSyncManager->sync($core_type, $remoteArray);

            $remoteArray = $restManager->get($remoteUrl."/core/api/".$core_type);
            $results = ($this->coreSyncManager->compareLocalToRemoteArray($core_type, $remoteArray, $showChangesOnly));
            $html = $this->renderView(
                'AppBundle:Admin/Sync:'.$core_type.'.html.twig',
                array('results' => $results, "compare_type" => $core_type, "remote_url" => $remoteUrl)
            );
        } catch (\Exception $e) {
            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }

        if(!empty($entityTypeRebuildArray)){
            $entityTypeRebuildArray = array_unique($entityTypeRebuildArray);

            if(empty($this->entityTypeCtx)){
                $this->entityTypeCtx = $this->getContainer()->get("entity_type_context");
            }
            if(empty($this->databaseManager)){
                $this->databaseManager = $this->getContainer()->get("database_manager");
            }
            if(empty($this->administrationManager)){
                $this->administrationManager = $this->getContainer()->get("administration_manager");
            }

            foreach ($entityTypeRebuildArray as $entityTypeCode){

                /** @var EntityType $entityType */
                $entityType = $this->entityTypeCtx->getItemByCode($entityTypeCode);

                $this->databaseManager->createTableIfDoesntExist($entityType, null);
                $this->administrationManager->generateDoctrineXML($entityType, true);
                $this->administrationManager->generateEntityClasses($entityType, true);
            }
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/administrator/reset_to_default", name="reset_to_default")
     */
    public function resetToDefaultAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if(!isset($p["id"]) || empty($p["id"])){
            return new JsonResponse(array('error' => true, 'message' => "Id is empty"));
        }
        if(!isset($p["table"]) || empty($p["table"])){
            return new JsonResponse(array('error' => true, 'message' => "Table is empty"));
        }

        if(empty($this->administrationManager)){
            $this->administrationManager = $this->getContainer()->get("administration_manager");
        }

        $entity = $this->administrationManager->getEntityByTableAndId($p["table"],$p["id"]);

        if(empty($entity)){
            return new JsonResponse(array('error' => true, 'message' => "Missing entity"));
        }

        if(empty($this->syncManager)){
            $this->syncManager = $this->getContainer()->get("sync_manager");
        }

        $this->syncManager->resetToDefault($p["table"],$entity->getUid());

        return new JsonResponse(array('error' => false, 'title' => 'Success', 'message' => "Entity has been reset"));
    }

    /**
     * @Route("/administrator/unset_custom", name="unset_custom")
     */
    public function unsetCustomAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if(!isset($p["id"]) || empty($p["id"])){
            return new JsonResponse(array('error' => true, 'message' => "Id is empty"));
        }
        if(!isset($p["table"]) || empty($p["table"])){
            return new JsonResponse(array('error' => true, 'message' => "Table is empty"));
        }

        if(empty($this->administrationManager)){
            $this->administrationManager = $this->container->get("administration_manager");
        }

        $entity = $this->administrationManager->getEntityByTableAndId($p["table"],$p["id"]);

        if(empty($entity)){
            return new JsonResponse(array('error' => true, 'message' => "Missing entity"));
        }

        if (!$this->administrationManager->changeIsCustomByTable($p["table"],$entity,0)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Success', 'message' => 'Entity has been unset custom'));
    }
}
