<?php

namespace ScommerceBusinessBundle\Controller;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use PevexBusinessBundle\Entity\SFrontBlockProductGroupsEntity;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Entity\BlogPostEntity;
use ScommerceBusinessBundle\Entity\SFrontBlockEntity;
use ScommerceBusinessBundle\Entity\SFrontBlockImagesEntity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Doctrine\Common\Inflector\Inflector;

class PageBuilderController extends AbstractScommerceController
{
    const TYPE = "s_front_block";

    /**@var FormManager $formManager */
    protected $formManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->initializeTwigVariables($request);

        $factoryManager = $this->getContainer()->get("factory_manager");
        $this->formManager = $factoryManager->loadFormManager(self::TYPE);
        $this->entityManager = $this->getContainer()->get("entity_manager");
    }

    /**
     * @Route("/api/start_page_builder", name="start_page_builder")
     * @Method("POST")
     */
    public function startPageBuilderAction(Request $request)
    {
        $this->initialize($request);

        $globals = $this->twigBase->getGlobals();

        if (!isset($globals["current_entity"]) || empty($globals["current_entity"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Entity with page builder not found')));
        }

        $response = new JsonResponse(array('error' => false));

        $cookieKey = 'page_builder_active-' . $globals["current_entity"]->getEntityType()->getEntityTypeCode() . "-" . $globals["current_entity"]->getId();

        $cookies = $request->cookies;
        if ($cookies->has($cookieKey) && $cookies->get($cookieKey) == 1) {
            $response->headers->setCookie(new Cookie($cookieKey, 0));
        } else {
            $response->headers->setCookie(new Cookie($cookieKey, 1));
        }
        return $response;
    }

    /**
     * @Route("/api/page_builder_get_settings", name="page_builder_get_settings")
     * @Method("POST")
     */
    public function pageBuilderGetSettingsAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();

        $p = $_POST;

        if (isset($p["block"]) && !empty($p["block"])) {
            /** @var SFrontBlockEntity $frontBlock */
            $frontBlock = $this->templateManager->getFrontBlockById($p["block"]);
            if (empty($frontBlock)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Block not found")));
            }

            $html = $this->renderView($this->templateManager->getTemplatePathByBundle("PageBuilder/FrontBlock:{$frontBlock->getType()}.html.twig", $session->get("current_website_id")), array(
                "block" => $frontBlock
            ));
        } else {
            $html = $this->renderView($this->templateManager->getTemplatePathByBundle("PageBuilder:page_builder_settings.html.twig", $session->get("current_website_id")), array());
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/api/page_builder_add_block", name="page_builder_add_block")
     * @Method("POST")
     */
    public function pageBuilderAddBlockAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (!isset($p["block_type"]) || empty($p["block_type"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing block type")));
        }

        $session = $request->getSession();

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("PageBuilder/FrontBlock:{$p["block_type"]}.html.twig", $session->get("current_website_id")), array());

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/api/page_builder_save_positions", name="page_builder_save_positions")
     * @Method("POST")
     */
    public function pageBuilderSavePositionsAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (!isset($p["data"]) || empty($p["data"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Layout data missing")));
        }

        $globals = $this->twigBase->getGlobals();

        if (!isset($globals["current_entity"]) || empty($globals["current_entity"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing parent entity")));
        }

        $parent = $globals["current_entity"];

        if (!EntityHelper::checkIfMethodExists($parent, "getLayout") && !EntityHelper::checkIfMethodExists($parent, "getContent")) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Undefined layout getter")));
        }

        try {
            $this->saveLayout($parent, $p["data"]);
        } catch (\Exception $e) {
            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }

        return new JsonResponse(array('error' => false));
    }

    /**
     * @Route("/api/page_builder_remove_block", name="page_builder_remove_block")
     * @Method("POST")
     */
    public function pageBuilderRemoveBlockAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (!isset($p["block"]) || empty($p["block"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing block")));
        }

        /** @var SFrontBlockEntity $block */
        $block = $this->entityManager->getEntityByEntityTypeCodeAndId("s_front_block", $p["block"]);
        if (empty($block)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Block not found")));
        }

        $globals = $this->twigBase->getGlobals();

        if (!isset($globals["current_entity"]) || empty($globals["current_entity"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing parent entity")));
        }

        $parent = $globals["current_entity"];

        if (!EntityHelper::checkIfMethodExists($parent, "getLayout")) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Parent entity does not support page builder")));
        }

        $content = $parent->getLayout();
        $content = json_decode($content, true);

        $key = array_search($p["block"], array_column($content, "id"));
        unset($content[$key]);

        $parent->setLayout(json_encode(array_values($content), JSON_UNESCAPED_UNICODE));
        $this->entityManager->saveEntity($parent);

        $this->entityManager->deleteEntityFromDatabase($block);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Block removed")));
    }

    /**
     * @Route("/api/page_builder_remove_image", name="page_builder_remove_image")
     * @Method("POST")
     */
    public function pageBuilderRemoveImageAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing image id")));
        }

        /** @var SFrontBlockImagesEntity $image */
        $image = $this->entityManager->getEntityByEntityTypeCodeAndId("s_front_block_images", $p["id"]);
        if (empty($image)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Image not found")));
        }

        $this->entityManager->deleteEntityFromDatabase($image);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Image removed")));
    }

    /**
     * @Route("/api/page_builder_save_block", name="page_builder_save_block")
     * @Method("POST")
     */
    public function pageBuilderSaveBlockAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $globals = $this->twigBase->getGlobals();

        if (!isset($globals["current_entity"]) || empty($globals["current_entity"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing parent entity")));
        }

        $page = $parent = $globals["current_entity"];

        if (isset($p["to_container"]) && !empty($p["to_container"])) {
            /** @var SFrontBlockEntity $containerBlock */
            $containerBlock = $this->entityManager->getEntityByEntityTypeCodeAndId("s_front_block", $p["to_container"]);

            if (empty($containerBlock)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Parent block not found")));
            }

            $parent = $containerBlock;
        }

        if (!EntityHelper::checkIfMethodExists($parent, "getLayout") && ($parent->getEntityType()->getEntityTypeCode() != "s_front_block" || $parent->getType() != "container")) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Parent entity does not support page builder")));
        }

        if (EntityHelper::checkIfMethodExists($parent, "getLayout")) {
            $getter = "getLayout";
            $setter = "setLayout";
        } elseif (EntityHelper::checkIfMethodExists($parent, "getContent")) {
            // Container block ima drugaciji layout atribut
            $getter = "getContent";
            $setter = "setContent";
        } else {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Undefined layout getter")));
        }


        if (!isset($p["page_builder_settings"])) {
            $p["page_builder_settings"] = json_encode($p["page_builder_settings"]);
        }

        if (isset($p["page_builder_settings"])) {
            $p["page_builder_settings"] = json_encode($p["page_builder_settings"]);
        } else {
            $p["page_builder_settings"] = "";
        }

        if (isset($p["active_from"]) && !empty($p["active_from"])) {
            $p["active_from"] = \DateTime::createFromFormat('d.m.Y H:i', $p["active_from"])->format("d/m/Y H:i:s");
        }
        if (isset($p["active_to"]) && !empty($p["active_to"])) {
            $p["active_to"] = \DateTime::createFromFormat('d.m.Y H:i', $p["active_to"])->format("d/m/Y H:i:s");
        }

        if (!empty($_FILES)) {
            $p["document_list"] = $_FILES;
        }

        /** @var SFrontBlockEntity $entity */
        $entity = $this->formManager->saveFormModel(self::TYPE, $p);

        // Force show on store
        if (empty($p["id"])) {
            $entity->setShowOnStore([3 => 1]);
            $this->entityManager->saveEntity($entity);
        }

        if (isset($p["related"]) && !empty($p["related"])) {
            foreach ($p["related"] as $relatedAttributeSetCode => $data) {
                foreach ($data as $relatedAttributeCode => $ids) {
                    $ids = array_filter($ids, 'is_numeric');

                    $attributeCode = Inflector::camelize($relatedAttributeCode);

                    /** @var EntityType $relatedEntityType */
                    $relatedEntityType = $this->entityManager->getEntityTypeByCode($relatedAttributeSetCode);

                    // Delete
                    $compositeFilter = new CompositeFilter();
                    $compositeFilter->setConnector("and");
                    $compositeFilter->addFilter(new SearchFilter("sFrontBlock.id", "eq", $entity->getId()));
                    $compositeFilter->addFilter(new SearchFilter("$attributeCode.id", "ni", implode(",", $ids)));

                    $compositeFilters = new CompositeFilterCollection();
                    $compositeFilters->addCompositeFilter($compositeFilter);

                    $toDelete = $this->entityManager->getEntitiesByEntityTypeAndFilter($relatedEntityType, $compositeFilters);

                    if (!empty($toDelete)) {
                        foreach ($toDelete as $delete) {
                            $this->entityManager->deleteEntityFromDatabase($delete);
                        }
                    }

                    // Check existing
                    $compositeFilter = new CompositeFilter();
                    $compositeFilter->setConnector("and");
                    $compositeFilter->addFilter(new SearchFilter("sFrontBlock.id", "eq", $entity->getId()));
                    $compositeFilter->addFilter(new SearchFilter("$attributeCode.id", "in", implode(",", $ids)));

                    $compositeFilters = new CompositeFilterCollection();
                    $compositeFilters->addCompositeFilter($compositeFilter);

                    $alreadyExisting = $this->entityManager->getEntitiesByEntityTypeAndFilter($relatedEntityType, $compositeFilters);

                    if (!empty($alreadyExisting)) {
                        $relatedGetter = EntityHelper::makeGetter($relatedAttributeCode);
                        foreach ($alreadyExisting as $existingLink) {
                            $existingRelated = $existingLink->{$relatedGetter}();
                            if (($key = array_search($existingRelated->getId(), $ids)) !== false) {
                                unset($ids[$key]);
                            }
                        }
                    }

                    // Insert remaining
                    if (!empty($ids)) {
                        $relatedSetter = EntityHelper::makeSetter($relatedAttributeCode);
                        foreach ($ids as $id) {
                            $loadedEntity = $this->entityManager->getEntityByEntityTypeCodeAndId($relatedAttributeCode, $id);
                            if (empty($loadedEntity)) {
                                continue;
                            }

                            /** @var SFrontBlockProductGroupsEntity $newRelatedEntity */
                            $newRelatedEntity = $this->entityManager->getNewEntityByAttributSetName($relatedAttributeSetCode);
                            $newRelatedEntity->setEntityStateId(1);
                            $newRelatedEntity->setSFrontBlock($entity);
                            $newRelatedEntity->{$relatedSetter}($loadedEntity);
                            $this->entityManager->saveEntity($newRelatedEntity);
                        }
                    }
                }
            }

            unset($p["related"]);
        }

        $result = [
            "error" => false,
            "message" => "",
            "is_new" => 0,
        ];

        if ($entity->getEntityValidationCollection() != null) {
            if (EntityHelper::isCountable($entity->getEntityValidationCollection())) {
                $result["error"] = true;
                $errors = [];
                foreach ($entity->getEntityValidationCollection() as $validationCollection) {
                    $errors[] = $validationCollection->getMessage();
                }

                $result["message"] = implode("<br>", $errors);
            }

            return new JsonResponse($result);
        }

        $content = $parent->{$getter}();
        $content = json_decode($content, true);

        if (empty($p["id"])) {
            $newBlock = array();
            $newBlock["id"] = "{$entity->getId()}";
            $newBlock["type"] = $entity->getType();
            $newBlock["name"] = $entity->getName();
            $newBlock["x"] = 0;
            $newBlock["y"] = 100;
            $newBlock["width"] = 12;
            $newBlock["height"] = 2;

            if (isset($p["before_block"]) && !empty($p["before_block"])) {
                // Add in place of block
                $key = array_search($p["before_block"], array_column($content, "id"));

                if (!$key) {
                    $getterBlock = "getContent";
                    $setterBlock = "setContent";

                    // key is empty, must be nested container
                    $nestedParent = $this->findNestedContainer($content, $p["before_block"]);
                    if (!empty($nestedParent)) {
                        $nestedContent = $nestedParent->{$getterBlock}();
                        $nestedContent = json_decode($nestedContent, true);
                        array_splice($nestedContent, $key, 0, [$newBlock]);

                        $nestedParent->{$setterBlock}(json_encode($nestedContent, JSON_UNESCAPED_UNICODE));

                        $this->templateManager->save($nestedParent);
                    } else {
                        $content[] = $newBlock;
                    }
                } else {
                    array_splice($content, $key, 0, [$newBlock]);
                }
            } else {
                $content[] = $newBlock;
            }

            $parent->{$setter}(json_encode($content, JSON_UNESCAPED_UNICODE));

            $this->templateManager->save($parent);

            $result["is_new"] = 1;
        }

        if (isset($p["to_container"]) && !empty($p["to_container"])) {
            $html = $this->templateManager->generateBlockHtml([
                "id" => $parent->getId(),
                "page" => $page
            ], $parent->getId());
            $result["to_container"] = $p["to_container"];
            $result["block_id"] = $parent->getId();
        } else {
            $html = $this->templateManager->generateBlockHtml([
                "id" => $entity->getId(),
                "page" => $page
            ], $entity->getId());
            $result["block_id"] = $entity->getId();
            $result["block_type"] = $entity->getType();
        }

        $result["html"] = $html;

        return new JsonResponse($result);
    }

    /**
     * @Route("/api/page_builder_move_block", name="page_builder_move_block")
     * @Method("POST")
     */
    public function pageBuilderMoveBlockAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (!isset($p["block"]) || empty($p["block"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing block")));
        }
        /**
         * 1 = UP
         * 2 = DOWN
         */
        if (!isset($p["direction"]) || empty($p["direction"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing direction")));
        }

        $globals = $this->twigBase->getGlobals();

        if (!isset($globals["current_entity"]) || empty($globals["current_entity"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing parent entity")));
        }

        $page = $parent = $globals["current_entity"];

        if (isset($p["parentBlockId"]) && !empty($p["parentBlockId"])) {
            /** @var SFrontBlockEntity $containerBlock */
            $containerBlock = $this->entityManager->getEntityByEntityTypeCodeAndId("s_front_block", $p["parentBlockId"]);

            if (empty($containerBlock)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Parent block not found")));
            }

            $parent = $containerBlock;
        }

        if (!EntityHelper::checkIfMethodExists($parent, "getLayout") && ($parent->getEntityType()->getEntityTypeCode() != "s_front_block" || $parent->getType() != "container")) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Parent entity does not support page builder")));
        }

        if (EntityHelper::checkIfMethodExists($parent, "getLayout")) {
            $getter = "getLayout";
            $setter = "setLayout";
        } elseif (EntityHelper::checkIfMethodExists($parent, "getContent")) {
            // Container block ima drugaciji layout atribut
            $getter = "getContent";
            $setter = "setContent";
        } else {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Undefined layout getter")));
        }

        $content = $parent->{$getter}();
        $content = json_decode($content, true);

        $key = array_search($p["block"], array_column($content, "id"));

        if ($p["direction"] == 1) {
            // Up
            if ($key == 0) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Block is already first")));
            }

            $previous = $content[$key - 1];
            $content[$key - 1] = $content[$key];
            $content[$key] = $previous;
        } elseif ($p["direction"] == 2) {
            // Down
            if ($key == count($content) - 1) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Block is already last")));
            }

            $previous = $content[$key + 1];
            $content[$key + 1] = $content[$key];
            $content[$key] = $previous;
        } else {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Unknown direction")));
        }

        $parent->{$setter}(json_encode($content, JSON_UNESCAPED_UNICODE));
        $this->entityManager->saveEntity($parent);

        return new JsonResponse(array('error' => false, 'reload' => true));
    }

    /**
     * @param $content
     * @param $targetId
     * @return SFrontBlockEntity|null
     */
    private function findNestedContainer($content, $targetId)
    {
        $block = null;
        foreach ($content as $blockData) {
            if ($blockData["type"] == "container") {
                /** @var SFrontBlockEntity $containerFrontBlock */
                $containerFrontBlock = $this->entityManager->getEntityByEntityTypeCodeAndId("s_front_block", $blockData["id"]);
                if (!empty($containerFrontBlock)) {
                    $content = $containerFrontBlock->getContent();
                    if (empty($content)) {
                        continue;
                    }
                    $content = json_decode($content, true);
                    $key = array_search($targetId, array_column($content, "id"));

                    if ($key) {
                        // found block
                        $block = $this->entityManager->getEntityByEntityTypeCodeAndId("s_front_block", $content[$key]["id"]);
                    } else {
                        $block = $this->findNestedContainer($content, $targetId);
                    }
                }
            }
        }

        return $block;
    }

    /**
     * @param $entity
     * @param $layout
     * @return void
     */
    private function saveLayout($entity, $layout)
    {
        $data = [];
        if (!empty($layout["items"])) {
            foreach ($layout["items"] as $itemData) {
                if (!empty($itemData["items"]) || $itemData["type"] == "container") {
                    $this->saveLayout($entity, $itemData);
                }
                unset($itemData["items"]);
                $data[] = $itemData;
            }
        }
        if ($layout["type"] == "builder_blocks") {
            if (EntityHelper::checkIfMethodExists($entity, "setLayout")) {
                $entity->setLayout(json_encode($data));
                $this->entityManager->saveEntity($entity);
            } elseif (EntityHelper::checkIfMethodExists($entity, "setContent")) {
                $entity->setLayout(json_encode($data));
                $this->entityManager->saveEntity($entity);
            }
        } else {
            /** @var SFrontBlockEntity $frontBlock */
            $frontBlock = $this->entityManager->getEntityByEntityTypeCodeAndId("s_front_block", $layout["id"]);
            $frontBlock->setContent(json_encode($data));
            $this->entityManager->saveEntity($frontBlock);
        }
    }
}
