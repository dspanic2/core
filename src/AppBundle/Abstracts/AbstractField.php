<?php

namespace AppBundle\Abstracts;

use AppBundle\Entity\Attribute;
use AppBundle\Entity\ListViewAttribute;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use AppBundle\Managers\CacheManager;
use Doctrine\Common\Cache\PhpFileCache;
use Doctrine\Common\Inflector\Inflector;
use Monolog\Logger;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractField implements FieldInterface, ContainerAwareInterface
{
    /**@var \AppBundle\Entity\Attribute $attribute */
    protected $attribute;
    /**@var \AppBundle\Entity\ListViewAttribute $listViewAttribute */
    protected $listViewAttribute;
    protected $entity;
    protected $parentEntity;
    protected $parent;
    protected $formType;
    protected $advancedSearchValue;
    protected $quickSearchValue;
    protected $databaseContext;
    protected $advancedSearchType;
    protected $listMode;

    /**@var Logger $logger */
    protected $logger;

    /**@var TwigEngine $twig */
    protected $twig;

    protected $container;

    protected $frontendDisplayFormat;

    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Ovveride this method to initialize all services you will require
     */
    public function initialize()
    {
        $this->logger = $this->container->get('logger');
        $this->twig = $this->container->get("templating");
        $this->cacheManager = $this->container->get("cache_manager");
    }

    public function SetFrontendDisplayFormat($frontendDisplayFormat)
    {
        $this->frontendDisplayFormat = $frontendDisplayFormat;
    }

    public function GetFrontendDisplayFormat()
    {
        return $this->frontendDisplayFormat;
    }

    public function GetListViewTemplate()
    {
        $attribute = $this->GetAttribute();

        return 'AppBundle:Fields/ListView:' . $attribute->getFrontendType() . '.html.twig';
    }

    public function GetFormTemplate()
    {
        $attribute = $this->GetAttribute();

        return 'AppBundle:Fields/Form:' . $attribute->getFrontendType() . '.html.twig';
    }

    public function GetAdvancedSearchTemplate()
    {
        $attribute = $this->GetAttribute();

        return 'AppBundle:Fields/AdvancedSearch:' . $attribute->getFrontendType() . '.html.twig';
    }

    public function GetQuickSearchTemplate()
    {
        $attribute = $this->GetAttribute();

        return 'AppBundle:Fields/QuickSearch:' . $attribute->getFrontendType() . '.html.twig';
    }

    public function GetListViewHeaderTemplate()
    {
        $attribute = $this->GetAttribute();

        return 'AppBundle:Fields/ListView/Header:' . $attribute->getFrontendType() . '.html.twig';
    }

    public function SetParentEntity($parentEntity)
    {

        $this->parentEntity = $parentEntity;
    }

    public function GetParentEntity()
    {

        return $this->parentEntity;
    }



    public function GetListViewValue()
    {

        $field = $this->GetListViewAttribute()->getField();

        $entity = $this->GetEntity();

        if (strpos($field, ".")) {
            $getters = EntityHelper::getPropertyAccessor($field);

            $value = $entity->{$getters[0]}();

            $count = count($getters);
            $count = $count - 2;

            if ($count == 0) {
                $this->SetParentEntity($value);
            }

            foreach ($getters as $key => $getter) {
                if ($key == 0) {
                    continue;
                }
                if (!empty($value) && is_object($value)) {
                    $value = $value->{$getter}();

                    if ($key == $count) {
                        $this->SetParentEntity($value);
                    }
                } else {
                    $value = "";
                }
            }

            return $value;
        } else {
            $getter = EntityHelper::makeGetter($field);

            return $entity->{$getter}();
        }

        return false;
    }

    public function GetListViewFormattedValue()
    {
        return $this->GetListViewValue();
    }

    public function GetFormFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $entity = $this->GetEntity();

        $value = $entity->{$getter}();

        /**
         * If new check for default
         */
        if (empty($this->GetEntity()->getId()) && !empty($this->GetAttribute()->getDefaultValue())) {
            $value = $this->GetAttribute()->getDefaultValue();
        }

        return $value;
    }

    public function GetListViewEditableValue()
    {
        $field = $this->GetListViewAttribute()->getField();

        $entity = $this->GetEntity();

        if (strpos($field, ".")) {
            $getters = EntityHelper::getPropertyAccessor($field);

            $value = $entity->{$getters[0]}();

            $count = count($getters);
            $count = $count - 2;

            if ($count == 0) {
                $this->SetParentEntity($value);
            }

            foreach ($getters as $key => $getter) {
                if ($key == 0) {
                    continue;
                }
                if (!empty($value) && is_object($value)) {
                    $value = $value->{$getter}();

                    if ($key == $count) {
                        $this->SetParentEntity($value);
                    }
                } else {
                    $value = "";
                }
            }

            return $value;
        } else {
            $getter = EntityHelper::makeGetter($field);

            return $entity->{$getter}();
        }

        return false;
    }

    public function GetFieldClass()
    {
        return $this->attribute->getFrontendClass();
    }

    public function SetAttribute(Attribute $attribute)
    {
        $this->attribute = $attribute;
    }

    public function GetAttribute()
    {
        return $this->attribute;
    }

    public function SetListViewAttribute(ListViewAttribute $listViewAttribute)
    {
        $this->listViewAttribute = $listViewAttribute;
    }

    public function GetListViewAttribute()
    {
        return $this->listViewAttribute;
    }

    public function SetEntity($entity)
    {
        $this->entity = $entity;
    }

    public function GetEntity()
    {
        return $this->entity;
    }

    public function SetParent($parent)
    {
        $this->parent = $parent;
    }

    public function GetParent()
    {
        return $this->parent;
    }

    public function SetFormType($formType)
    {
        $this->formType = $formType;
    }

    public function GetFormType()
    {
        return $this->formType;
    }

    public function GetListViewHtml()
    {

        if (!$this->GetListViewAttribute()->getDisplay()) {
            return false;
        }

        if (strpos($this->GetListViewAttribute()->getField(), ".") > 0) {
            $this->GetListViewAttribute()->getAttribute()->setReadOnly(1);
        }

        if ($this->entity->getLocked()) {
            $this->GetListViewAttribute()->getAttribute()->setReadOnly(1);
        }

        return $this->twig->render($this->GetListViewTemplate(), array(
            'width' => $this->GetListViewAttribute()->getColumnWidth(),
            'value' => $this->GetListViewFormattedValue(),
            'editableValue' => $this->GetListViewEditableValue(),
            'fieldClass' => $this->GetFieldClass(),
            'entity_id' => $this->entity->getId(),
            'entity' => $this->GetEntity(),
            "attribute" => $this->GetListViewAttribute()->getAttribute(),
            'field' => $this,
            'listMode' => $this->getListMode(),
        ));
    }

    public function GetFormFieldHtml()
    {
        if ((empty($this->GetEntity()) || empty($this->GetEntity()->getId())) && !$this->GetAttribute()->getFrontendDisplayOnNew()) {
            return "";
        } elseif ($this->GetFormType() == "form" && !empty($this->GetEntity()) && !empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnUpdate()) {
            return "";
        } elseif ($this->GetFormType() == "view" && !empty($this->GetEntity()) && !empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnView()) {
            return "";
        }

        $stores = $this->getStores();

        return $this->twig->render($this->GetFormTemplate(), array(
            'attribute' => $this->GetAttribute(),
            'value' => $this->GetFormFormattedValue(),
            'formType' => $this->GetFormType(),
            'entity_id' => $this->entity->getId(),
            'stores' => $stores));
    }

    public function GetListViewHeaderHtml()
    {

        if (!$this->GetListViewAttribute()->getDisplay()) {
            return false;
        }

        return $this->twig->render($this->GetListViewHeaderTemplate(), array(
            'width' => $this->GetListViewAttribute()->getColumnWidth(),
            'field' => $this->GetListViewAttribute()->getField(),
            'show_filter' => $this->GetListViewAttribute()->getListView()->getShowFilter(),
            'label' => $this->GetListViewAttribute()->getLabel(),
        ));
    }

    public function SetAdvancedSearchValue($advancedSearchValue)
    {
        $this->advancedSearchValue = $advancedSearchValue;
    }

    public function GetAdvancedSearchValue()
    {
        return $this->advancedSearchValue;
    }

    public function SetAdvancedSearchType($advancedSearchType)
    {
        $this->advancedSearchType = $advancedSearchType;
    }

    public function GetAdvancedSearchType()
    {
        return $this->advancedSearchType;
    }

    public function SetQuickSearchValue($quickSearchValue)
    {
        $this->quickSearchValue = $quickSearchValue;
    }

    public function GetQuickSearchValue()
    {
        return $this->quickSearchValue;
    }

    public function GetAdvancedSearchFieldHtml()
    {

        return $this->twig->render($this->GetAdvancedSearchTemplate(), array('attribute' => $this->GetAttribute(), 'value' => $this->GetAdvancedSearchValue(), 'search_type' => $this->GetAdvancedSearchType()));
    }

    public function GetQuickSearchFieldHtml()
    {

        return $this->twig->render($this->GetQuickSearchTemplate(), array('attribute' => $this->GetAttribute(), 'value' => $this->GetQuickSearchValue()));
    }


    public function setEntityValueFromArray(array $array)
    {
        $value = $array[$this->attribute->getAttributeCode()];

        $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $value);
    }

    public function getInput()
    {
        return "";
    }

    public function getType()
    {
        return "";
    }

    public function getBackendType()
    {
        return "varchar";
    }

    public function getCustomAdmin(Attribute $attribute = null)
    {
        return "";
    }

    public function getStores()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT s.id as store_id, s.name as store_name, l.code as language_code, l.id as language_id, w.id as website_id, w.name as website_name FROM s_store_entity as s 
        LEFT JOIN core_language_entity as l ON s.core_language_id = l.id
        LEFT JOIN s_website_entity as w ON s.website_id = w.id
        WHERE s.entity_state_id = 1 and w.entity_state_id = 1
        ORDER BY w.id ASC, s.id ASC";

        return $this->databaseContext->getAll($q);
    }

    public function getCoreLanguages(){

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT id, code as name FROM core_language_entity WHERE entity_state_id = 1;";

        return $this->databaseContext->getAll($q);
    }

    public function getWebsites()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT w.id as website_id, w.name as website_name FROM s_website_entity as w WHERE w.entity_state_id = 1 ORDER BY w.id ASC";

        return $this->databaseContext->getAll($q);
    }

    /**
     * @return mixed
     */
    public function getListMode()
    {
        return $this->listMode;
    }

    /**
     * @param mixed $listMode
     */
    public function setListMode($listMode)
    {
        $this->listMode = $listMode;
    }
}
