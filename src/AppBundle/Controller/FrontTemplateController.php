<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use ScommerceBusinessBundle\Entity\SFrontBlockEntity;
use ScommerceBusinessBundle\Entity\SPageEntity;
use ScommerceBusinessBundle\Entity\STemplateTypeEntity;
use ScommerceBusinessBundle\Managers\TemplateManager;
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

class FrontTemplateController extends AbstractController
{
    /**@var TemplateManager $templateManager */
    protected $templateManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    protected $blockTypes;

    protected function initialize()
    {
        parent::initialize();
        $this->templateManager = $this->getContainer()->get('template_manager');
        $this->entityManager = $this->getContainer()->get('entity_manager');
    }

    private function initializeBackend()
    {

        //TODO add check if registered service with _block extension implements Block interface
        $this->blockTypes = array();
        $services = $this->container->getServiceIds();

        foreach ($services as $service) {
            if (strpos($service, '_front_block') !== false) {
                $this->blockTypes[str_replace("_front_block", "", $service)] = array(
                    "attribute-set" => true,
                    "content" => true,
                    "is_available_in_block" => 1,
                    "is_available_in_page" => 1
                );
            }
        }
        ksort($this->blockTypes);
    }

    /**
     * @Route("/template/block/form", name="front_block_update_form")
     * @Method("POST")
     */
    public function updateAction(Request $request)
    {
        $this->initialize();
        $this->initializeBackend();

        // TODO dodati provjeru

        $p = $_POST;

        if (!isset($p["is_front"]) || empty($p["is_front"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Is front is empty')));
        }

        /**
         * Create
         */
        if (empty($p["id"])) {
            if (!isset($p["parent_type"]) || empty($p["parent_type"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent entity type is empty')));
            }
            if (!isset($p["parent_id"]) || empty($p["parent_id"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent id is empty')));
            }

            $data = array(
                'entity' => null,
                'block_types' => $this->blockTypes,
                'parent_id' => $p["parent_id"],
                'parent_type' => $p["parent_type"]
            );

            $html_part = $this->renderView("ScommerceBusinessBundle:FrontBlockSettings:abstract_base_block.html.twig", $data);

            $html = $this->renderView(
                "AppBundle:Includes:modal.html.twig",
                array(
                    'entity' => null,
                    'html' => $html_part,
                    'is_front' => $p["is_front"],
                    'title' => $this->translator->trans("Create new block")
                )
            );

            return new JsonResponse(array('error' => false, 'html' => $html));
        } /**
         * Update
         */
        else {
            if (!isset($p["id"]) || empty($p["id"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is empty')));
            }

            $entity = $this->templateManager->getFrontBlockById($p["id"]);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Does not exist')));
            }

            $frontBlock = $this->templateManager->getBlock($entity, null);
            $blockSettings = $frontBlock->GetBlockSetingsData();

            $html_part = $this->renderView($frontBlock->GetBlockSetingsTemplate(), $blockSettings);

            $html = $this->renderView(
                "AppBundle:Includes:modal.html.twig",
                array(
                    'entity' => $entity,
                    'html' => $html_part,
                    'is_front' => $p["is_front"],
                    'title' => $this->translator->trans("Create new block")
                )
            );

            return new JsonResponse(array('error' => false, 'html' => $html));
        }

        return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('There has been an error please try again')));
    }

    /**
     * @Route("/template/block/form/add_existing", name="front_block_add_existing_form")
     * @Method("POST")
     */
    public function addExistingFrontBlockAction(Request $request)
    {
        $this->initialize();
        $this->initializeBackend();

        // TODO dodati provjeru

        $p = $_POST;

        if (!isset($p["parent_type"]) || empty($p["parent_type"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent entity type is empty')));
        }
        if (!isset($p["parent_id"]) || empty($p["parent_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent id is empty')));
        }

        $blocks = $this->templateManager->getFrontBlocks();

        $data = array(
            'entity' => null,
            'blocks' => $blocks,
            'parent_id' => $p["parent_id"],
            'parent_type' => $p["parent_type"]
        );

        $html_part = $this->renderView("ScommerceBusinessBundle:FrontBlockSettings:add_existing_block.html.twig", $data);

        $html = $this->renderView(
            "AppBundle:Includes:modal.html.twig",
            array(
                'entity' => null,
                'html' => $html_part,
                'is_front' => false,
                'title' => $this->translator->trans("Create new block")
            )
        );

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/template/block/remove", name="front_block_remove")
     * @Method("POST")
     */
    public function removeAction(Request $request)
    {
        $this->initialize();
        $this->initializeBackend();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is empty')));
        }
        if (!isset($p["parent_type"]) || empty($p["parent_type"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent entity type is empty')));
        }
        if (!isset($p["parent_id"]) || empty($p["parent_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent id is empty')));
        }

        /** @var SFrontBlockEntity $entity */
        $entity = $this->templateManager->getFrontBlockById($p["id"]);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('There has been an error please try again')));
        }

        $parentEntity = null;
        if ($p["parent_type"] == "s_template_type") {
            $parentEntity = $this->templateManager->getTemplateTypeById($p["parent_id"]);
        } elseif ($p["parent_type"] == "s_page") {
            $parentEntityType = $this->entityManager->getEntityTypeByCode($p["parent_type"]);
            /** @var SPageEntity $parent */
            $parentEntity = $this->entityManager->getEntityByEntityTypeAndId($parentEntityType, $p["parent_id"]);
        } else {
            $parentEntity = $this->templateManager->getFrontBlockById($p["parent_id"]);
        }

        $block = $this->templateManager->getBlock($entity, null);
        $blockSettings = $block->GetBlockSetingsData($block);

        if (isset($blockSettings["show_content"]) && $blockSettings["show_content"] == 1) {
            if (strpos($entity->getContent(), "width") !== false) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Block is not empty.')));
            }
        }

        $this->templateManager->deleteFrontBlock($entity, $parentEntity, false);

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Remove front block'), 'message' => $this->translator->trans('Front block has been removed')));
    }

    /**
     * @Route("/template/block/get", name="front_block_get")
     * @Method("POST")
     */
    public function getAction(Request $request)
    {
        $this->initialize();
        $this->initializeBackend();

        // $this->authenticateAdministrator($request);

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is not correct')));
        }

        /** @var SFrontBlockEntity $entity */
        $entity = $this->templateManager->getFrontBlockById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Front block does not exist')));
        }

        /*if($p["is_front"] == "true"){
            $html = $this->renderView("ScommerceBusinessBundle:FrontBlockSettings:abstract_base_block.html.twig",
                array(
                    'data' => Array('block' => $entity),
                )
            );
        }
        else{*/

        $block_id = $p["id"];
        $data = array();
        $data["x"] = 0;
        $data["y"] = 50;
        $data["width"] = 6;
        $data["height"] = 6;

        $html = $this->templateManager->generateAdminBlockHtml($data, $block_id);

        //}

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/template/block/add_existing_block", name="front_block_add_existing_block")
     * @Method("POST")
     */
    public function addExistingBlockAction(Request $request)
    {
        $this->initialize();
        $this->initializeBackend();

        // $this->authenticateAdministrator($request);

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is empty')));
        }
        if (!isset($p["parent_id"]) || empty($p["parent_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Block parent is not defined')));
        }
        if (!isset($p["parent_type"]) || empty($p["parent_type"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Block parent type is not defined')));
        }

        /**
         * Set parent
         */
        $layoutColumnKey = "content";
        if ($p["parent_type"] == "s_template_type") {
            /** @var STemplateTypeEntity $parent */
            $parent = $this->templateManager->getTemplateTypeById($p["parent_id"]);
        } else {
            $parent = $this->templateManager->getFrontBlockById($p["parent_id"]);
        }

        $parentEntityType = $this->entityManager->getEntityTypeByCode($p["parent_type"]);
        $parentEntity = $this->entityManager->getEntityByEntityTypeAndId($parentEntityType, $p["parent_id"]);

        if (method_exists($parentEntity, "getLayout")) {
            $parent = $parentEntity;
            $layoutColumnKey = "layout";
        }

        if (empty($parent)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent cannot be found')));
        }

        /** @var SFrontBlockEntity $entity */
        $entity = $this->templateManager->getFrontBlockById($p["id"]);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('There has been an error please try again')));
        }

        if (empty($layoutColumnKey)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Layout column key not set')));
        }

        $getter = EntityHelper::makeGetter($layoutColumnKey);
        $setter = EntityHelper::makeSetter($layoutColumnKey);
        $content = $parent->$getter();

        $content = json_decode($content, true);

        $newBlock = array();
        $newBlock["id"] = "{$entity->getId()}";
        $newBlock["type"] = $entity->getType();
        $newBlock["name"] = $entity->getName();
        $newBlock["x"] = 0;
        $newBlock["y"] = 100;
        $newBlock["width"] = 12;
        $newBlock["height"] = 2;

        $content[] = $newBlock;
        $content = json_encode($content);

        $parent->$setter($content);

        $this->templateManager->save($parent);

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Add existing block'), 'message' => $this->translator->trans('Block has been added'), 'entity' => array('id' => $entity->getId())));
    }

    /**
     * @Route("/template/block/save", name="front_block_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();
        $this->initializeBackend();

        // $this->authenticateAdministrator($request);

        $p = $_POST;

        if (!isset($p["name"]) || empty($p["name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Name is empty')));
        }
        if (!isset($p["class"]) || empty($p["class"])) {
            $p["class"] = null;
        }
        if (!isset($p["content"]) || empty($p["content"])) {
            $p["content"] = null;
        }
        if (!isset($p["dataAttributes"]) || empty($p["dataAttributes"])) {
            $p["dataAttributes"] = null;
        }

        /**
         * INSERT
         */
        if (!isset($p["id"]) || empty($p["id"])) {
            if (!isset($p["type"]) || empty($p["type"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Type is empty')));
            }
            if (!isset($p["parent_id"]) || empty($p["parent_id"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Block parent is not defined')));
            }
            if (!isset($p["parent_type"]) || empty($p["parent_type"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Block parent type is not defined')));
            }

            /**
             * Set parent
             */
            $layoutColumnKey = "content";
            if ($p["parent_type"] == "s_template_type") {
                $parent = $this->templateManager->getTemplateTypeById($p["parent_id"]);
            } elseif ($p["parent_type"] == "s_page") {
                $layoutColumnKey = "layout";
                $parentEntityType = $this->entityManager->getEntityTypeByCode($p["parent_type"]);
                /** @var SPageEntity $parent */
                $parent = $this->entityManager->getEntityByEntityTypeAndId($parentEntityType, $p["parent_id"]);
            } else {
                $parent = $this->templateManager->getFrontBlockById($p["parent_id"]);
            }

            if (empty($parent)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent cannot be found')));
            }

            $entity = $this->templateManager->createNewFrontBlock($p);

            if (empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('There has been an error please try again')));
            }

            if (empty($layoutColumnKey)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Layout column key not set')));
            }

            $getter = EntityHelper::makeGetter($layoutColumnKey);
            $setter = EntityHelper::makeSetter($layoutColumnKey);
            $content = $parent->$getter();
            $content = json_decode($content, true);

            $newBlock = array();
            $newBlock["id"] = "{$entity->getId()}";
            $newBlock["type"] = $p["type"];
            $newBlock["name"] = $p["name"];
            $newBlock["x"] = 0;
            $newBlock["y"] = 100;
            $newBlock["width"] = 12;
            $newBlock["height"] = 2;

            $content[] = $newBlock;
            $content = json_encode($content);

            $parent->$setter($content);

            $this->templateManager->save($parent);

            return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Insert new block group'), 'message' => $this->translator->trans('Block has been added'), 'entity' => array('id' => $entity->getId())));
        } /**
         * UPDATE
         */
        else {
            if (!isset($p["id"]) || empty($p["id"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is not correct')));
            }

            $entity = $this->templateManager->getFrontBlockById($p["id"]);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Page block does not exist')));
            }

            $block = $this->templateManager->getBlock($entity, null);
            $entity = $block->SaveBlockSettings($p);

            if (empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('There has been an error please try again')));
            }

            return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Update block'), 'message' => $this->translator->trans('Block has been updated'), 'entity' => array('id' => $entity->getId())));
        }

        return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('There has been an error please try again')));
    }

    /**
     * @Route("/template/block/grid", name="front_block_grid")
     */
    public function adminBlockAction(Request $request)
    {
        $block_id = $request->get('block_id');
        $data = array();
        $data["x"] = $request->get('x') ?? 0;
        $data["y"] = $request->get('y') ?? 100;
        $data["width"] = $request->get('width') ?? 12;
        $data["height"] = $request->get('height') ?? 2;

        $this->initialize();
        $this->initializeBackend();

        $html = $this->templateManager->generateAdminBlockHtml($data, $block_id);

        if (empty($html)) {
            return new Response();
        }

        return new Response($html);
    }

    /**
     * @Route("/template/block/view", name="front_block_view")
     */
    public function blockAction(Request $request)
    {

        $data = $request->get('data');
        $block_id = $request->get('block_id');

        $this->initialize();

        $html = $this->templateManager->generateBlockHtml($data, $block_id);

        if (empty($html)) {
            return new Response();
        }

        return new Response($html);
    }

    /**
     * @Route("/template/block/inline-save", name="front_block_inline_save")
     */
    public function blockInlineSaveAction(Request $request)
    {

        $values = $request->get('values');
        $blockId = $request->get('block_id');
        $blockType = $request->get('block_type');

        $this->initialize();

        $block = $this->templateManager->getFrontBlockById($blockId);

        if (empty($block)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Failed'), 'message' => $this->translator->trans('Block not found!')));
        }

        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get("entity_manager");

        try {
            $preparedValues = [];
            foreach ($values as $key => $value) {
                if (strpos($key, "_") === false) {
                    $preparedValues[$key] = $value;
                } else {
                    $parts = explode("_", $key);
                    $preparedValues[$parts[0]][$parts[1]] = $value;
                }
            }
            foreach ($preparedValues as $key => $value) {
                $setter = EntityHelper::makeSetter($key);
                $block->$setter($value);
            }
            $entityManager->saveEntityWithoutLog($block);
        } catch (\Exception $e) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Failed'), 'message' => $e->getMessage()));
        }
        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Block saved')));
    }
}
