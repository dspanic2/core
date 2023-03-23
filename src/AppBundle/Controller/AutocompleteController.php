<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\Entity;
use AppBundle\Factory\FactoryEntityType;
use AppBundle\Managers\AutocompleteManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AutocompleteController extends AbstractController
{
    /** @var AutocompleteManager $autocompleteManager */
    protected $autocompleteManager;
    /** @var  AttributeContext $attributeCtx */
    protected $attributeCtx;
    /** @var  PageBlockContext $blockContext */
    protected $blockContext;

    protected function initialize()
    {
        parent::initialize();
        $this->attributeCtx = $this->getContainer()->get("attribute_context");
        $this->blockContext = $this->getContainer()->get("page_block_context");
    }

    public function indexAction($name)
    {
        return $this->render('', array('name' => $name));
    }

    /**
     * @Route("/autocomplete/get", name="get_autocomplete")
     * @Method("GET")
     */
    public function getAutoComplete(Request $request)
    {
        $this->initialize();
        $template = $request->get("template");
        $manager = "autocomplete_manager";

        if ($template != "default") {
            $manager = $template."_".$manager;
        }

        $this->autocompleteManager = $this->getContainer()->get($manager);

        $term = "";
        if (isset($_GET["q"]["term"])) {
            $term = $_GET["q"]["term"];
        }
        $attribute_id = $_GET["id"];

        $formData = null;
        if (isset($_GET["form"])) {
            $formData = array();
            parse_str($_GET["form"], $formData);
        }

        if(strlen($attribute_id) > 15){
            /** @var Entity\Attribute $attribute */
            $attribute = $this->attributeCtx->getItemByUid($attribute_id);
        }
        else{
            /** @var Entity\Attribute $attribute */
            $attribute = $this->attributeCtx->getById($attribute_id);
        }

        $lookupAttribute = null;


        if ($attribute->getLookupAttribute()->getLookupEntityType() == null) {
            /** @var Entity\EntityType $entityType */
            $lookupEntityType = $attribute->getLookupEntityType();
            $lookupAttributeSet = $attribute->getLookupAttributeSet();
            $lookupAttribute = $attribute->getLookupAttribute();
        } else {
            /** @var Entity\EntityType $entityType */
            $lookupEntityType = $attribute->getLookupAttribute()->getLookupEntityType();
            $lookupAttribute = $attribute->getLookupAttribute()->getLookupAttribute();
            $lookupAttributeSet = $attribute->getLookupAttribute()->getLookupAttributeSet();
        }


        $create_option = false;
        $create_new_type = "simple";
        $create_new_url = null;
        if ($attribute->getEnableModalCreate() && ($this->getContainer()->get('security.authorization_checker')->isGranted('ROLE_ADMIN') || ($this->user->hasPrivilege(1, $lookupAttributeSet->getUid())))) {
            $create_option = true;
            $lookupAttributes = $this->attributeCtx->getAttributesByEntityType($lookupEntityType);
            if (count($lookupAttributes) > 2) {
                $block = null;

                if (!empty($attribute->getModalPageBlockId())) {
                    /** @var Entity\PageBlock $block */
                    $block = $this->blockContext->getById($attribute->getModalPageBlockId());
                }

                if (empty($block)) {
                    /** @var Entity\PageBlock $block */
                    $block = $this->blockContext->getOneBy(array("type" => "edit_form", "attributeSet" => $lookupAttributeSet));
                }

                if (empty($block)) {
                    $create_option = false;
                } else {
                    $create_new_type = "complex";
                    $create_new_url = $this->generateUrl('block_modal_view', array('block_id' => $block->getId()));
                }
            }
        }

        $data = $this->autocompleteManager->getAutocomplete($term, $attribute, $formData);
        $ret = $this->autocompleteManager->renderTemplate($data, $template, $attribute);

        return new JsonResponse(array('error' => false, 'ret' => $ret, 'create_new' => $create_option, 'create_new_type', 'create_new_type' => $create_new_type, 'create_new_url' => $create_new_url));
    }

    /**
     * @Route("/autocomplete/get", name="get_autocomplete")
     * @Method("POST")
     */
    public function getAutoCompletePost(Request $request)
    {
        $this->initialize();
        $template = $request->get("template");
        $manager = "autocomplete_manager";

        if ($template != "default") {
            $manager = $template."_".$manager;
        }

        $this->autocompleteManager = $this->getContainer()->get($manager);

        $term = "";
        if (isset($_POST["q"]["term"])) {
            $term = $_POST["q"]["term"];
        }
        $attribute_id = $_POST["id"];

        $formData = null;
        if (isset($_POST["form"])) {
            $formData = array();
            parse_str($_POST["form"], $formData);
        }

        if(strlen($attribute_id) > 15){
            /** @var Entity\Attribute $attribute */
            $attribute = $this->attributeCtx->getItemByUid($attribute_id);
        }
        else{
            /** @var Entity\Attribute $attribute */
            $attribute = $this->attributeCtx->getById($attribute_id);
        }

        $lookupAttribute = null;


        if ($attribute->getLookupAttribute()->getLookupEntityType() == null) {
            /** @var Entity\EntityType $entityType */
            $lookupEntityType = $attribute->getLookupEntityType();
            $lookupAttributeSet = $attribute->getLookupAttributeSet();
            $lookupAttribute = $attribute->getLookupAttribute();
        } else {
            /** @var Entity\EntityType $entityType */
            $lookupEntityType = $attribute->getLookupAttribute()->getLookupEntityType();
            $lookupAttribute = $attribute->getLookupAttribute()->getLookupAttribute();
            $lookupAttributeSet = $attribute->getLookupAttribute()->getLookupAttributeSet();
        }


        $create_option = false;
        $create_new_type = "simple";
        $create_new_url = null;
        if ($attribute->getEnableModalCreate() && ($this->getContainer()->get('security.authorization_checker')->isGranted('ROLE_ADMIN') || ($this->user->hasPrivilege(1, $lookupAttributeSet->getUid())))) {
            $create_option = true;
            $lookupAttributes = $this->attributeCtx->getAttributesByEntityType($lookupEntityType);
            if (count($lookupAttributes) > 2) {
                $block = null;

                if (!empty($attribute->getModalPageBlockId())) {
                    /** @var Entity\PageBlock $block */
                    $block = $this->blockContext->getById($attribute->getModalPageBlockId());
                }

                if (empty($block)) {
                    /** @var Entity\PageBlock $block */
                    $block = $this->blockContext->getOneBy(array("type" => "edit_form", "attributeSet" => $lookupAttributeSet));
                }

                if (empty($block)) {
                    $create_option = false;
                } else {
                    $create_new_type = "complex";
                    $create_new_url = $this->generateUrl('block_modal_view', array('block_id' => $block->getId()));
                }
            }
        }

        $data = $this->autocompleteManager->getAutocomplete($term, $attribute, $formData);
        $ret = $this->autocompleteManager->renderTemplate($data, $template, $attribute);

        return new JsonResponse(array('error' => false, 'ret' => $ret, 'create_new' => $create_option, 'create_new_type', 'create_new_type' => $create_new_type, 'create_new_url' => $create_new_url));
    }

    /**
     * @Route("/autocomplete/create", name="create_autocomplete")
     * @Method("POST")
     */
    public function createAutoComplete(Request $request)
    {
        $this->initialize();
        /** @var AutocompleteManager $autocompleteManager */
        $autocompleteManager = $this->getContainer()->get("autocomplete_manager");

        $p = $_POST;


        /** @var Entity\Attribute $attribute */
        $attribute = $this->attributeCtx->getById($p["attribute_id"]);
        unset($p["attribute_id"]);

        if ($attribute->getLookupAttribute()->getLookupEntityType() == null) {
            /** @var Entity\EntityType $entityType */
            $lookupEntityType = $attribute->getLookupEntityType();
        } else {
            /** @var Entity\EntityType $entityType */
            $lookupEntityType = $attribute->getLookupAttribute()->getLookupEntityType();
        }

        /** @var Entity\EntityType $createType */
        $createType = $lookupEntityType->getEntityTypeCode();

        $newEntityData = $autocompleteManager->createItem($createType, $p);

        return new JsonResponse(array('error' => false, 'ret' => $newEntityData));
    }

    /**
     * @Route("/autocomplete/get_value_by_id", name="get_autocomplete_value_by_id")
     * @Method("POST")
     */
    public function getAutocompleteValueById(Request $request)
    {

        $this->initialize();

        $p = $_POST;

        $template = $p["template"];
        $manager = "autocomplete_manager";

        if ($template != "default") {
            $manager = $template."_".$manager;
        }

        $this->autocompleteManager = $this->getContainer()->get($manager);

        $attribute = $this->attributeCtx->getById($p["attribute_id"]);

        $renderdItem = $this->autocompleteManager->getRenderedItemById($attribute, $p["id"]);
        if (empty($renderdItem)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Value cannot be found")));
        }

        return new JsonResponse(array('error' => false, 'id' => $p["id"], 'value' => $renderdItem["lookup_value"]));
    }
}
