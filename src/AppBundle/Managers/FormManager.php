<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Abstracts\AbstractEntity;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityAttributeContext;
use AppBundle\Context\PageContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\Entity;
use AppBundle\Entity\EntityLink;
use AppBundle\Entity\EntityValidate;
use AppBundle\Entity\LookupValue;
use AppBundle\Entity\Page;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\FileHelper;
use AppBundle\Interfaces\Entity\IFormEntityInterface;
use AppBundle\Interfaces\Managers\FormManagerInterface;
use AppBundle\QueryBuilders\LookupQueryBuilder;
use Doctrine\Common\Util\Inflector;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\VarDumper\VarDumper;

class FormManager extends AbstractBaseManager implements FormManagerInterface
{
    /**@var AttributeContext $attributeContext */
    protected $attributeContext;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    /**@var DatabaseContext $databaseContext */
    protected $databaseContext;
    /**@var EntityAttributeContext $entityAttributeContext */
    protected $entityAttributeContext;
    /**@var AttributeGroupContext $attributeGroupContext */
    protected $attributeGroupContext;
    /** @var PageContext $pageContext */
    protected $pageContext;
    /**@var EntityManager $entityManager */
    protected $entityManager;

    public function initialize()
    {
        parent::initialize();
        $this->attributeContext = $this->container->get("attribute_context");
        $this->databaseContext = $this->container->get("database_context");
        $this->attributeSetContext = $this->container->get("attribute_set_context");
        $this->attributeGroupContext = $this->container->get("attribute_group_context");
        $this->entityAttributeContext = $this->container->get("entity_attribute_context");
        $this->entityManager = $this->container->get("entity_manager");
        $this->pageContext = $this->container->get("page_context");
    }

    public function getTemplate($typeName, $viewName)
    {
        $attributeSet = $this->entityManager->getAttributeSetByCode($typeName);
        if (!empty($attributeSet->getLayouts())) {
            $layouts = json_decode($attributeSet->getLayouts());
            if ($layouts->$viewName) {
                return $layouts->$viewName->type;
            }
        }
        return "1column";
    }

    public function getFormAttributes($typeName)
    {
        $attributeSet = $this->entityManager->getAttributeSetByCode($typeName);
        $attributesGroups = $this->attributeGroupContext->getAttributesGroupsBySet($attributeSet);

        $attributes = array();

        foreach ($attributesGroups as $attributesGroup) {
            $entityAttributes = $this->entityAttributeContext->getByAttributeGroup($attributesGroup);

            foreach ($entityAttributes as $entityAttribute) {
                $attributes[] = $entityAttribute->getAttribute();
            }
        }

        $attributes = $this->processFormAtributes($attributes);

        return $attributes;
    }

    public function getFormModel(AttributeSet $attributeSet, $id, $formType, $attributeGroupId = null)
    {

        $entityType = $attributeSet->getEntityType();

        /**
         * Check privileges
         */
        if (!$this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
            $authorized = false;

            /**
             * Check create
             */
            if (!$id && $formType == "form" && $this->user->hasPrivilege(1, $attributeSet->getUid())) {
                $authorized = true;
            } /**
             * Check edit
             */
            elseif ($id && $formType == "form" && $this->user->hasPrivilege(3, $attributeSet->getUid())) {
                $authorized = true;
            } /**
             * Check view
             */
            elseif ($id && $formType == "view" && $this->user->hasPrivilege(2, $attributeSet->getUid())) {
                $authorized = true;
            }

            if (!$authorized) {
                $this->logger->info("Unauthorized access: username " . $this->user->getUsername() . " - getForm " . $attributeSet->getAttributeSetCode());
                return false;
            }
        }

        $attributesGroups = $this->attributeGroupContext->getAttributesGroupsBySet($attributeSet);

        $groups_array = array();

        foreach ($attributesGroups as $attributesGroup) {
            if (!empty($attributeGroupId)) {
                if ($attributeGroupId != $attributesGroup->getId()) {
                    continue;
                }
            }

            $entityAttributes = $this->entityAttributeContext->getByAttributeGroup($attributesGroup);

            $attributesGroup->setEntityAttributes($entityAttributes);
            $groups_array[$attributesGroup->getId()] = $attributesGroup;
        }

        if (isset($id)) {
            $entity = $this->entityManager->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $id);
        } else {
            $entity = $this->entityManager->getNewEntityByAttributSetName($attributeSet->getAttributeSetCode());
        }

        if ($entity instanceof AbstractEntity) {
            $entity->setEntityStateId(1);
            $entity->setEntityType($entityType);
            $entity->setAttributeSet($attributeSet);
        }

        //dump($entity->getFluentLanguages());die;

        //$form_entity = null;

        //if ($entity instanceof FormEntityInterface)
        //   $form_entity = $this->entityManager->entityToArray($entity);


        /*dump($form_entity);
        die;*/

        $links = array();

        /**
         * @deprecated 15.01.2019 Davor
         */
        /*if (!empty($entity) && $entity->getId() != null) {
            $links = $this->entityLinkContext->getAllByEntityTypeAndId($entity->getEntityType()->getId(), $entity->getId());
        }*/
        if (!empty($attributeGroupId)) {
            return (array('attributesGroup' => $groups_array[$attributeGroupId], 'type' => $attributeSet, 'entity' => $entity, 'links' => $links));
        }

        return (array('attributesGroup' => $groups_array, 'type' => $attributeSet, 'entity' => $entity, 'links' => $links));
        /*}
        else{
            throw new \Exception("Not authorized: ".$entityType->getId());
        }*/
    }

    /**
     * @param $typeName
     * @param $array
     * @return IFormEntityInterface|bool
     * @throws \Exception
     */
    public function saveFormModel($typeName, $array)
    {
        if (!is_object($this->user) || empty($this->user)) {
            return false;
        }

        /** @var Page $page */
        $page = $this->pageContext->getOneBy(array("type" => "form", "url" => $typeName), array());
        if ($page != null) {
            /** @var AttributeSet $attributeSet */
            $attributeSet = $page->getAttributeSet();
        } else {
            $attributeSet = $this->attributeSetContext->getOneBy(array("attributeSetCode" => $typeName));
        }

        if (empty($attributeSet)) {
            throw new \InvalidArgumentException("Missing attribute_set");
        }

        /** @var \AppBundle\Entity\EntityType $entityType */
        $entityType = $attributeSet->getEntityType();

        /**
         * Check privileges
         */
        if (!$this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN') && $entityType->getCheckPrivileges()) {
            $authorized = false;

            /**
             * Check create
             */
            if ((!isset($array['id']) || empty($array['id'])) && $this->user->hasPrivilege(1, $attributeSet->getUid())) {
                $authorized = true;
            } /**
             * Check edit
             */
            elseif (isset($array['id']) && $this->user->hasPrivilege(3, $attributeSet->getUid())) {
                $authorized = true;
            }

            if (!$authorized) {
                $this->logger->info("Unauthorized access: username " . $this->user->getUsername() . " - saveForm " . $attributeSet->getAttributeSetCode());
                return false;
            }
        }

        return $this->entityManager->saveFormEntity($attributeSet, $array);
    }

    public function validateFormModel($typeName, $array)
    {
        $entityValidate = new EntityValidate();
        $entityValidate->setIsValid(true);

        return $entityValidate;
    }

    public function deleteFormModel($typeName, $entity_id): IFormEntityInterface
    {

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->entityManager->getAttributeSetByCode($typeName);

        $entity = $this->entityManager->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $entity_id);

        $web_dir = rtrim($_ENV["WEB_PATH"],"/");

        if (!empty($entity->getAttributes())) {
            foreach ($entity->getAttributes() as $attribute) {
                if ($attribute->getFrontendType() == "file") {
                    $folder = $attribute->getFolder();

                    $getter = EntityHelper::makeGetter($attribute->getAttributeCode());
                    $setter = EntityHelper::makeSetter($attribute->getAttributeCode());

                    $filename = $entity->{$getter}();
                    if ($filename != "") {
                        $filePath = $web_dir . $folder . $filename;

                        if (file_exists($filePath)) {
                            $newFileName = FileHelper::addHashToFilename($filename);
                            rename($filePath, $web_dir . $folder . $newFileName);
                            $entity->{$setter}($newFileName);
                        }
                    }
                }
            }
        }

        $this->entityManager->deleteEntity($entity);

        return $entity;
    }

    public function getRelatedOptions($code, $val, $related_code)
    {
        $mainAttribute = $this->attributeContext->getAttributeByName($code);
        $relatedAttribute = $this->attributeContext->getAttributeByName($related_code);
        $lookups = array();

        $qb = new LookupQueryBuilder();
        $values = $this->databaseContext->executeQuery($qb->getRelatedLookupValuesQuery($mainAttribute, $relatedAttribute, $val));

        foreach ($values as $value) {
            $lookup = new LookupValue();
            $lookup->setId($value['id']);
            $lookup->setValue($value['value']);
            $lookups[] = $lookup;
        }
        return $lookups;
    }

    protected function processFormAtributes($attributes)
    {
        foreach ($attributes as $attribute) {
            $lookups = array();

            if ($attribute->getBackendType() == "lookup") {
                $qb = new LookupQueryBuilder();
                $values = $this->databaseContext->executeQuery($qb->getLookupValuesQuery($attribute));
                if (isset($values)) {
                    foreach ($values as $value) {
                        $lookup = new LookupValue();
                        $lookup->setId($value['id']);
                        $lookup->setValue($value['value']);
                        $lookups[] = $lookup;
                    }
                }
                $attribute->setLookupValues($lookups);
                if ($attribute->getDefaultValue() == "current_user") {
                    $attribute->setDefaultValue($this->user->getId());
                }
            }
        }
        return $attributes;
    }
}
