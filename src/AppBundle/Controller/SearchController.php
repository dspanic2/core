<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Factory\FactoryManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class SearchController extends AbstractController
{
    protected $factoryManager;
    protected $listViewManager;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;

    protected function initialize()
    {
        parent::initialize();
        $this->attributeSetContext = $this->getContainer()->get('attribute_set_context');
        $this->factoryManager = new FactoryManager($this);
        $this->listViewManager = $this->factoryManager->loadListViewManager();
    }

    /**
     * @Route("advanced_search/index", name="advanced_search_index")
     */
    public function searchAdvancedIndexAction(Request $request)
    {
        $this->initialize();

        $data["return_url"] = "";

        $removeArray = array(
            "currency",
            "department",
            "approval",
            "worktime",
            "expense",
            "travel_log",
            "loko_drive",
            "manufacturer",
            "children",
            "file",
            "adress_contact",

            "potential",
            "group_tender",
            "absence",
            "health_check",
            "complaints",
            "action",
            "certification",
            "work_contract",
            "absence_request",

        );

        $attribute_sets = $this->attributeSetContext->getAll();
        foreach ($attribute_sets as $key => $attribute_set) {
            if (in_array($attribute_set->getAttributeSetCode(), $removeArray)) {
                unset($attribute_sets[$key]);
            }
        }

        return $this->render('AppBundle:Search:advanced.html.twig', array('data' => $data, 'attribute_sets' => $attribute_sets));
    }

    /**
     * @Route("advanced_search/get_attributes", name="advanced_search_get_attributes")
     */
    public function getAttributesAction(Request $request)
    {
        $p = $_POST;
        $html = "";
        $selected_attribute = null;

        if (!isset($p["code"]) || empty($p["code"])) {
            return new JsonResponse(array('error' => false, 'html' => $html));
        }

        $this->initialize();

        $this->formManager = $this->factoryManager->loadFormManager($p["code"]);

        $attributes = $this->formManager->getFormAttributes($p["code"]);

        if (isset($p["selected_attribute_code"]) && !empty($p["selected_attribute_code"])) {
            $selected_attribute_code = $p["selected_attribute_code"];
        }

        /**
         * Ovo treba rijesiti u selectu
         */
        foreach ($attributes as $key => $attribute) {
            if ($attribute->getFrontendInput() == 'reverse_lookup' || $attribute->getFrontendInput() == 'file') {
                unset($attributes[$key]);
            }
            if (isset($selected_attribute_code) && !empty($selected_attribute_code) && $attribute->getAttributeCode() == $selected_attribute_code) {
                $attributes[$key]->selected = true;
                $selected_attribute = $attribute;
            }
        }

        $html = $this->renderView("AppBundle:Search:advanced_row.html.twig", array('attributes' => $attributes, 'attribute' => $selected_attribute));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("advanced_search/search", name="advanced_search")
     */
    public function searchAdvancedAction(Request $request)
    {
        $p = $_POST;

        $this->initialize();

        $model = $this->listViewManager->getListViewModel($p["entity"], "default");

        $model["parent_id"] = null;
        //$model["lookup_attribute"] = $request->get('lookup_attribute');
        $model["display_type"] = "modal";
        $model["parent_entity_type_id"] = null;
        $model["disable_load"] = true;
        $model["list_view"]->setShowLimit(150);
        $model["list_view"]->setShowFilter(0);
        $model["list_view"]->dataBefore = "prepareProductPost";


        /**
         * Dohvat row i actions templatea
         */
        $layouts = $model["type"]->getLayouts();
        $layouts = json_decode($layouts);

        $row_template_key = "row_".$model["display_type"];
        $actions_template_key = "actions_".$model["display_type"];

        if (!empty($layouts->list_view->{$row_template_key})) {
            $model["row_template"] = $layouts->list_view->{$row_template_key};
        } else {
            $model["row_template"] = $request->get('row_template');
        }

        if (!empty($layouts->list_view->{$actions_template_key})) {
            $model["actions_template"] = $layouts->list_view->{$actions_template_key};
        } else {
            $model["actions_template"] = $request->get('actions_template');
        }

        foreach ($model["list_view"]->getListViewAttributes() as $key => $listViewAttribute) {
            if ($listViewAttribute->getAttribute()->getFrontendInput() == "select" && $listViewAttribute->getAttribute()->getBackendType() == "option") {
                $optionValuesJson = array();
                foreach ($listViewAttribute->getAttribute()->getOptionValues() as $key2 => $value) {
                    $optionValuesJson[$key2]["value"] = "{$value->getValue()}";
                    $optionValuesJson[$key2]["label"] = $value->getValue();
                }
                $optionValuesJson = json_encode($optionValuesJson);
                $model["list_view"]->getListViewAttributes()[$key]->getAttribute()->setOptionValuesJson($optionValuesJson);
            } elseif ($listViewAttribute->getAttribute()->getFrontendInput() == "select" && $listViewAttribute->getAttribute()->getBackendType() == "lookup") {
                $optionValuesJson = array();

                $values = $this->listViewManager->getDistinctLookupValues($listViewAttribute->getAttribute());

                foreach ($values as $key2 => $value) {
                    $optionValuesJson[$key2]["value"] = $value["value"];
                    $optionValuesJson[$key2]["label"] = $value["value"];
                }

                $optionValuesJson = json_encode($optionValuesJson);
                $model["list_view"]->getListViewAttributes()[$key]->getAttribute()->setOptionValuesJson($optionValuesJson);
            }
        }

        $html = $this->renderView("AppBundle:ListView:table.html.twig", $model);

        return new JsonResponse(array('error' => false, 'html' => $html, 'title' => 'Some title', 'message' => 'Some message'));
    }
}
