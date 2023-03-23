<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityContext;
use AppBundle\Context\EntityLevelPermissionContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\Entity;
use AppBundle\Entity\EntityLevelPermission;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\EntityValidation;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Events\EntityAfterCloneEvent;
use AppBundle\Events\EntityBeforeCloneEvent;
use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityPreDeletedEvent;
use AppBundle\Events\EntityPreSetCreatedEvent;
use AppBundle\Events\EntityPreSetUpdatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Factory\FactoryContext;
use AppBundle\Factory\FactoryEntity;
use AppBundle\Interfaces\Entity\IFormEntityInterface;
use AppBundle\Interfaces\Fields\FieldInterface;
use AppBundle\Models\AttributeListExportConfiguration;
use AppBundle\QueryBuilders\LookupQueryBuilder;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\Mapping\Cache;
use Exception;
use Symfony\Component\EventDispatcher\EventDispatcher;
use AppBundle\Helpers\EntityHelper;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;

class EntityManager extends AbstractBaseManager
{
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var FactoryContext $factoryContext */
    protected $factoryContext;
    /** @var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /** @var EntityContext $entityContext */
    protected $entityContext;
    /** @var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    /** @var \Doctrine\ORM\EntityManager $doctrineEntityManager */
    protected $doctrineEntityManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var EntityLevelPermissionContext $entityLevelPermissionContext */
    protected $entityLevelPermissionContext;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    protected $redisClient;

    public function initialize()
    {
        parent::initialize();
        $this->factoryContext = $this->container->get("factory_context");
        $this->entityTypeContext = $this->container->get("entity_type_context");
        $this->attributeContext = $this->container->get("attribute_context");
        $this->entityContext = $this->container->get("entity_context");
        $this->attributeSetContext = $this->container->get("attribute_set_context");
        $this->entityLevelPermissionContext = $this->container->get("entity_level_permissions_context");
        $this->databaseContext = $this->container->get("database_context");
        $this->doctrineEntityManager = $this->container->get("doctrine.orm.entity_manager");
    }

    public function getEntityTypeByCode($entitiyTypeCode)
    {
        return $this->entityTypeContext->getItemByCode($entitiyTypeCode);
    }

    public function getAttributeSetByCode($attributeSetCode)
    {
        return $this->attributeSetContext->getItemByCode($attributeSetCode);
    }

    public function getEntityTypeById($entitiyTypeId)
    {
        return $this->entityTypeContext->getById($entitiyTypeId);
    }

    public function getAttributeSetById($attributeSetId)
    {
        return $this->attributeSetContext->getById($attributeSetId);
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    public function getDoctrineEntityManager()
    {
        return $this->doctrineEntityManager;
    }

    /**
     * @param IFormEntityInterface $entity
     * @param false $loadPreviousValues
     * @return IFormEntityInterface|null
     */
    public function saveEntity(IFormEntityInterface $entity, $loadPreviousValues = false)
    {
        $isNew = true;
        $action = "create";

        if ($entity->getId() != null) {
            $isNew = false;
            $action = "update";
        }

        if (!$isNew) {
            $this->entityPreUpdated($entity);
        } else {
            $this->entityPreCreated($entity);
        }

        if ($entity->getEntityValidationCollection() != null) {
            return $entity;
        }

        $entityType = $entity->getEntityType();

        if (EntityHelper::checkIfPropertyExists($entity, "createdBy") and $isNew) {
            if (is_object($this->user)) {
                $entity->setCreatedBy($this->user->getUsername());
            } else {
                $entity->setCreatedBy("anon.");
            }
        }

        if (EntityHelper::checkIfPropertyExists($entity, "modifiedBy")) {
            if (is_object($this->user)) {
                $entity->setModifiedBy($this->user->getUsername());
            } else {
                $entity->setModifiedBy("anon.");
            }
        }

        /**
         * Fallback da ne provjerava privileges ako ne postoji ova kolona
         */
        if (EntityHelper::checkIfMethodExists($entityType, "getCheckPrivileges")) {
            if ($entityType->getCheckPrivileges()) {
                if (!is_object($this->user)) {
                    $this->logger->error("Anonymous user tried to {$action} entity: {$entityType->getEntityTypeCode()}");
                    return null;
                } else {
                    $hasPrivilege = true;
                    if ($isNew) {
                        if (!$this->user->hasPrivilege(1, $entity->getAttributeSet()->getUid())) {
                            $hasPrivilege = false;
                        }
                    } else {
                        if (!$this->user->hasPrivilege(3, $entity->getAttributeSet()->getUid())) {
                            $hasPrivilege = false;
                        }
                    }

                    if (!$hasPrivilege) {
                        $this->logger->error("{$this->user->getUsername()} user tried to {$action} entity: {$entityType->getEntityTypeCode()}");
                        throw new AccessDeniedException("{$this->user->getUsername()} user tried to {$action} entity: {$entityType->getEntityTypeCode()}");
                    }
                }
            }
        }

        $array = array();
        if (!$isNew && $loadPreviousValues) {

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }

            $this->databaseContext = $this->container->get("database_context");
            $q = "SELECT * FROM {$entity->getEntityType()->getEntityTable()} WHERE id = {$entity->getId()};";
            $array = $this->databaseContext->getSingleEntity($q);
        }

        $context = $this->factoryContext->getContext($entityType);

        $entity = $context->save($entity);

        if ($isNew) {
            $this->entityCreated($entity);
        } else {
            $this->entityUpdated($entity, $array);
        }

        return $entity;
    }

    /**
     * @param $entity
     * @return |null
     */
    public function saveEntityWithoutLog($entity)
    {
        $isNew = true;
        $action = "create";

        if ($entity->getId() != null) {
            $isNew = false;
            $action = "update";
        }

        /** @var EntityType $entityType */
        $entityType = $entity->getEntityType();

        if (EntityHelper::checkIfPropertyExists($entity, "createdBy") and $isNew) {
            if (is_object($this->user)) {
                $entity->setCreatedBy($this->user->getUsername());
            } else {
                $entity->setCreatedBy("anon.");
            }
        }

        if (EntityHelper::checkIfPropertyExists($entity, "modifiedBy")) {
            if (is_object($this->user)) {
                $entity->setModifiedBy($this->user->getUsername());
            } else {
                $entity->setModifiedBy("anon.");
            }
        }

        /**
         * Fallback da ne provjerava privileges ako ne postoji ova kolona
         */
        if (EntityHelper::checkIfMethodExists($entityType, "getCheckPrivileges")) {
            if ($entityType->getCheckPrivileges()) {
                if (!is_object($this->user)) {
                    $this->logger->error("Anonymous user tried to {$action} entity: {$entityType->getEntityTypeCode()}");
                    return null;
                } else {
                    $hasPrivilege = true;
                    if ($isNew) {
                        if (!$this->user->hasPrivilege(1, $entity->getAttributeSet()->getUid())) {
                            $hasPrivilege = false;
                        }
                    } else {
                        if (!$this->user->hasPrivilege(3, $entity->getAttributeSet()->getUid())) {
                            $hasPrivilege = false;
                        }
                    }

                    if (!$hasPrivilege) {
                        $this->logger->error("{$this->user->getUsername()} user tried to {$action} entity: {$entityType->getEntityTypeCode()}");
                        throw new AccessDeniedException("{$this->user->getUsername()} user tried to {$action} entity: {$entityType->getEntityTypeCode()}");
                    }
                }
            }
        }

        $context = $this->factoryContext->getContext($entityType);

        $entity = $context->save($entity);

        return $entity;
    }

    /**
     * @param AttributeSet $attributeSet
     * @param array $array
     * @return IFormEntityInterface
     * @throws Exception
     */
    public function saveFormEntity(AttributeSet $attributeSet, array $array): IFormEntityInterface
    {
        $isNew = empty($array["id"]);

        if (!$isNew) {
            /** @var Entity $entity */
            $entity = $this->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $array["id"]);
            if (empty($entity)) {
                throw new \Exception("Entity not found for id: " . $array["id"]);
            } else if (!empty($entity->getLocked())) {
                $entityValidation = new EntityValidation();
                $entityValidation->setTitle("Error");
                $entityValidation->setMessage("This entity is locked and cannot be updated");
                $entity->addEntityValidation($entityValidation);
                return $entity;
            }
        } else {
            /** @var Entity $entity */
            $entity = $this->getNewEntityByAttributSetName($attributeSet->getAttributeSetCode());
        }

        if (!$isNew) {
            $array = $this->entityPreSetUpdated($entity, $array);
        } else {
            $array = $this->entityPreSetCreated($entity, $array);
        }

        $entity = $this->arrayToEntity($entity, $array);

        if ($entity->getEntityValidationCollection() != null) {
            return $entity;
        }

        if (!$isNew) {
            $action = "update";
            $this->entityPreUpdated($entity, $array);
        } else {
            $action = "create";
            $this->entityPreCreated($entity, $array);
        }

        if ($entity->getEntityValidationCollection() != null) {
            return $entity;
        }

        $entityType = $entity->getEntityType();

        if (EntityHelper::checkIfPropertyExists($entity, "createdBy") and $isNew) {
            if (is_object($this->user)) {
                $entity->setCreatedBy($this->user->getUsername());
            } else {
                $entity->setCreatedBy("anon.");
            }
        }

        if (EntityHelper::checkIfPropertyExists($entity, "modifiedBy")) {
            if (is_object($this->user)) {
                $entity->setModifiedBy($this->user->getUsername());
            } else {
                $entity->setModifiedBy("anon.");
            }
        }

        /**
         * Fallback da ne provjerava privileges ako ne postoji ova kolona
         */
        if (EntityHelper::checkIfMethodExists($entityType, "getCheckPrivileges")) {
            if ($entityType->getCheckPrivileges()) {
                if (!is_object($this->user)) {
                    $this->logger->error("Anonymous user tried to {$action} entity: {$entityType->getEntityTypeCode()}");
                    return $entity;
                } else {
                    $hasPrivilege = true;
                    if ($isNew) {
                        if (!$this->user->hasPrivilege(1, $entity->getAttributeSet()->getUid())) {
                            $hasPrivilege = false;
                        }
                    } else {
                        if (!$this->user->hasPrivilege(3, $entity->getAttributeSet()->getUid())) {
                            $hasPrivilege = false;
                        }
                    }

                    if (!$hasPrivilege) {
                        $this->logger->error("{$this->user->getUsername()} user tried to {$action} entity: {$entityType->getEntityTypeCode()}");
                        throw new AccessDeniedException("{$this->user->getUsername()} user tried to {$action} entity: {$entityType->getEntityTypeCode()}");
                    }
                }
            }
        }

        $context = $this->factoryContext->getContext($entityType);

        $entity = $context->save($entity);

        /**
         * Multiselect save
         */
        if (isset($array['multiselect'])) {
            $multiselectEntities = $array['multiselect'];

            foreach ($multiselectEntities as $multiselectEntity) {
                $linkedEntityType = $this->getEntityTypeByCode($multiselectEntity["link_entity"]);
                $parentEntityType = $this->getEntityTypeByCode($multiselectEntity["parent_entity"]);
                $childEntityType = $this->getEntityTypeByCode($multiselectEntity["child_entity"]);
                $attributeId = $multiselectEntity["attribute_id"];

                if (empty($linkedEntityType) || empty($parentEntityType) || empty($childEntityType)) {
                    continue;
                }

                /** @var Attribute $parentAttribute */
                $parentAttribute = $this->attributeContext->getOneBy(array("entityType" => $parentEntityType, "id" => $attributeId));
                /** @var Attribute $childAttribute */
                $childAttribute = $parentAttribute->getLookupAttribute();
                $relatedAttribut = $this->attributeContext->getOneBy(array("id" => $parentAttribute->getFrontendRelated()));

                $getterParent = EntityHelper::makeGetter($parentAttribute->getAttributeCode());
                $linked = $entity->{$getterParent}();
                $relatedIds = array();

                if (isset($multiselectEntity["related_ids"]) && !empty($multiselectEntity["related_ids"])) {
                    $relatedIds = $multiselectEntity["related_ids"];
                }

                if ($linked != null) {
                    $resave = false;
                    foreach ($linked as $k => $link) {
                        /**
                         * Delete link entities not in $relatedIds
                         */
                        if (!in_array($link->getId(), $relatedIds)) {
                            try {
                                unset($linked[$k]);
                                $resave = true;
                            } catch (\Exception $e) {
                                //throw new \Exception($e->getMessage());
                                //continue;
                            }
                        } else {
                            if (($key = array_search($link->getId(), $relatedIds)) !== false) {
                                unset($relatedIds[$key]);
                            }
                        }
                    }
                    if ($resave) {
                        $entity = $this->saveEntityWithoutLog($entity);
                    }
                }

                $relatedIds = array_filter($relatedIds);

                if (!empty($relatedIds)) {
                    /** @var AttributeSet $relatedAttributeSet */
                    $relatedAttributeSet = $this->getAttributeSetByCode($multiselectEntity["child_entity"]);

                    foreach ($relatedIds as $relatedId) {
                        $relatedEntity = $this->getEntityByEntityTypeAndId($relatedAttributeSet->getEntityType(), $relatedId);
                        $linkEntity = $this->getNewEntityByAttributSetName($multiselectEntity["link_entity"]);
                        if ($relatedAttribut != null) {
                            $setterParent = EntityHelper::makeSetter(str_replace("_id", "", $relatedAttribut->getAttributeCode()));
                        } else {
                            $setterParent = EntityHelper::makeSetter(str_replace("_id", "", $parentAttribute->getEntityType()->getEntityTypeCode()));
                        }
                        $setterChild = EntityHelper::makeSetter(str_replace("_id", "", $childAttribute->getAttributeCode()));

                        $linkEntity->{$setterParent}($entity);
                        $linkEntity->{$setterChild}($relatedEntity);

                        $this->saveEntity($linkEntity);
                    }
                }
            }
        }

        /**
         * Sprema related dokumente
         */
        if (isset($array['document_list'])) {
            $relatedDocuments = $array['document_list'];

            if (isset($array['save_files']) && $array['save_files'] == 1) {
                // Save documents on the fly

                /**
                 * Array koji treba doci da se ovo okine:
                 */
//                $array = [
//                    "delete_existing_files" => "1",
//                    "save_files" => "1",
//                    "document_list" => [
//                        "s_front_block_images" => [
//                            "name" => [
//                                0 => "1f4d8ea7-e9d9-48b7-b70c-819482fb10fb-cover.png"
//                            ],
//                            "type" => [
//                                0 => "image/png"
//                            ],
//                            "tmp_name" => [
//                                0 => "/tmp/phpixR8xF"
//                            ],
//                            "error" => [
//                                0 => 0
//                            ],
//                            "size" => [
//                                0 => 3802
//                            ]
//                        ]
//                    ]
//                ];
                foreach ($relatedDocuments as $attributeSetCode => $files) {
                    if (!is_array($files["name"])) {
                        // Is file field, not related entity
                        /** @var Attribute $attribute */
                        $attribute = $this->attributeContext->getOneBy(array("entityTypeId" => $entityType->getId(), "attributeCode" => $attributeSetCode));

                        if (empty($attribute)) {
                            continue;
                        }

                        $fileName = $files["name"] ?? "";

                        if (empty($fileName)) {
                            continue;
                        }
                        if (empty($this->helperManager)) {
                            $this->helperManager = $this->getContainer()->get('helper_manager');
                        }

                        $targetPath = $_ENV["WEB_PATH"] . $attribute->getFolder();
                        if (!file_exists($targetPath)) {
                            mkdir($targetPath, 0777, true);
                        }

                        /**
                         * Clean filename
                         */
                        $basename = $this->helperManager->getFilenameWithoutExtension($fileName);
                        $extension = strtolower($this->helperManager->getFileExtension($fileName));

                        $filename = $this->helperManager->nameToFilename($basename);

                        $filename = $filename . "." . $extension;

                        $filename = $this->helperManager->incrementFileName($targetPath, $filename);
                        $targetFile = $targetPath . $filename;

                        if (!move_uploaded_file($files["tmp_name"], $targetFile)) {
                            throw new \Exception("There has been an error saving file");
                        }

                        $setter = EntityHelper::makeSetter($attributeSetCode);
                        $entity->{$setter}($filename);
                        $this->saveEntity($entity);
                    } else {
                        /** @var AttributeSet $attributeSet */
                        $attributeSet = $this->attributeSetContext->getOneBy(array("attributeSetCode" => $attributeSetCode));

                        /** @var EntityType $fileEntityType */
                        $fileEntityType = $attributeSet->getEntityType();

                        if (!$fileEntityType->getIsDocument()) {
                            continue;
                        }

                        /** @var Attribute $attribute */
                        $attribute = $this->attributeContext->getOneBy(array("entityType" => $fileEntityType, "lookupAttributeSet" => $entity->getAttributeSet()));

                        if (empty($attribute)) {
                            continue;
                        }

                        /** @var Attribute $fileAttribute */
                        $fileAttribute = $this->attributeContext->getOneBy(array("entityType" => $fileEntityType, "attributeCode" => "file"));

                        if (empty($fileAttribute)) {
                            continue;
                        }

                        $attribute_code = str_replace("_id", "", $attribute->getAttributeCode());
                        $setter = EntityHelper::makeSetter($attribute_code);

                        if (isset($array['delete_existing_files']) && $array['delete_existing_files'] == 1) {
                            $compositeFilter = new CompositeFilter();
                            $compositeFilter->setConnector("and");
                            $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
                            $compositeFilter->addFilter(new SearchFilter(EntityHelper::makeAttributeName($attribute_code), "eq", $entity->getId()));

                            $compositeFilters = new CompositeFilterCollection();
                            $compositeFilters->addCompositeFilter($compositeFilter);

                            $items = $this->getEntitiesByEntityTypeAndFilter($attribute->getEntityType(), $compositeFilters);
                            if (!empty($items)) {
                                foreach ($items as $item) {
                                    $this->deleteEntityFromDatabase($item);
                                }
                            }
                        }

                        foreach ($files["name"] as $key => $fileName) {
                            if (empty($fileName)) {
                                continue;
                            }
                            if (empty($this->helperManager)) {
                                $this->helperManager = $this->getContainer()->get('helper_manager');
                            }

                            $targetPath = $_ENV["WEB_PATH"] . $fileAttribute->getFolder();
                            if (!file_exists($targetPath)) {
                                mkdir($targetPath, 0777, true);
                            }

                            $file = $this->getNewEntityByAttributSetName($attributeSetCode);

                            /**
                             * Clean filename
                             */
                            $basename = $this->helperManager->getFilenameWithoutExtension($fileName);
                            $extension = strtolower($this->helperManager->getFileExtension($fileName));

                            $filename = $this->helperManager->nameToFilename($basename);

                            $file->setFileType($extension);
                            $file->setFilename($filename);
                            $file->setFileType($extension);

                            $filename = $filename . "." . $extension;

                            $filename = $this->helperManager->incrementFileName($targetPath, $filename);
                            $targetFile = $targetPath . $filename;

                            if (!move_uploaded_file($files["tmp_name"][$key], $targetFile)) {
                                throw new \Exception("There has been an error saving file");
                            }

                            $file->setFile($filename);
                            $file->{$setter}($entity);
                            $file->setSize($files["size"][$key]);

                            $this->saveEntity($file);
                        }
                    }
                }
            } else {
                foreach ($relatedDocuments as $attributeSetCode => $relatedDocumentIds) {
                    $attributeSet = $this->attributeSetContext->getOneBy(array("attributeSetCode" => $attributeSetCode));
                    $attribute = $this->attributeContext->getOneBy(array("entityType" => $attributeSet->getEntityType(), "lookupAttributeSet" => $entity->getAttributeSet()));

                    if (empty($attribute)) {
                        continue;
                    }

                    $attribute_code = str_replace("_id", "", $attribute->getAttributeCode());
                    $getter = EntityHelper::makeGetter($attribute_code);
                    $setter = EntityHelper::makeSetter($attribute_code);

                    /** @var AttributeSet $relatedAttributeSet */
                    $relatedAttributeSet = $this->getAttributeSetByCode($attributeSetCode);

                    foreach ($relatedDocumentIds as $relatedDocumentId) {
                        $relatedDocument = $this->getEntityByEntityTypeAndId($relatedAttributeSet->getEntityType(), $relatedDocumentId);
                        if (!empty($relatedDocument)) {
                            $value = $relatedDocument->{$getter}();
                            if (!empty($value)) {
                                continue;
                            }

                            $relatedDocument->{$setter}($entity);
                            $this->saveEntity($relatedDocument);
                        }
                    }
                }
            }
        }

        $context->refresh($entity);

        if ($isNew) {
            $this->entityCreated($entity);
        } else {
            $this->entityUpdated($entity, $array);
        }

        return $entity;
    }

    /**
     * @param $entities
     * @param EntityType $entityType
     * @return bool|null
     */
    public function saveArrayEntities($entities, EntityType $entityType)
    {
        /**
         * Fallback da ne provjerava privileges ako ne postoji ova kolona
         */
        if (EntityHelper::checkIfMethodExists($entityType, "getCheckPrivileges")) {
            if ($entityType->getCheckPrivileges()) {
                if (!is_object($this->user)) {
                    $this->logger->error("Anonymous user tried to saveArrayEntities entity: {$entityType->getEntityTypeCode()}");
                    return null;
                } else {
                    $hasPrivilege = true;
                    if (!$this->user->hasPrivilege(3, $entities[0]->getAttributeSet()->getUid())) {
                        $hasPrivilege = false;
                    }

                    if (!$hasPrivilege) {
                        $this->logger->error("{$this->user->getUsername()} user tried to saveArrayEntities entity: {$entityType->getEntityTypeCode()}");
                        throw new AccessDeniedException("{$this->user->getUsername()} user tried to saveArrayEntities entity: {$entityType->getEntityTypeCode()}");
                    }
                }
            }
        }

        $context = $this->factoryContext->getContext($entityType);
        $context->saveArray($entities);
        return true;
    }

    /**
     * @param IFormEntityInterface $entity
     * @return IFormEntityInterface|null
     */
    public function deleteEntity(IFormEntityInterface $entity)
    {
        $entityType = $entity->getEntityType();

        /**
         * Fallback da ne provjerava privileges ako ne postoji ova kolona
         */
        if (EntityHelper::checkIfMethodExists($entityType, "getCheckPrivileges")) {
            if ($entityType->getCheckPrivileges()) {
                if (!is_object($this->user)) {
                    $this->logger->error("Anonymous user tried to delete entity: {$entityType->getEntityTypeCode()}");
                    return null;
                } else {
                    $hasPrivilege = true;
                    if (!$this->user->hasPrivilege(4, $entity->getAttributeSet()->getUid())) {
                        $hasPrivilege = false;
                    }

                    if (!$hasPrivilege) {
                        $this->logger->error("{$this->user->getUsername()} user tried to delete entity: {$entityType->getEntityTypeCode()}");
                        throw new AccessDeniedException("{$this->user->getUsername()} user tried to delete entity: {$entityType->getEntityTypeCode()}");
                    }
                }
            }
        }

        $this->entityPreDeleted($entity);

        if ($entity->getEntityValidationCollection() != null) {
            return $entity;
        }

        $context = $this->factoryContext->getContext($entityType);
        $entity->setEntityStateId(2);
        $entity = $context->save($entity);

        $this->entityDeleted($entity);

        return $entity;
    }

    /**
     * @param $entity
     * @return mixed
     */
    public function deleteEntityFromDatabase($entity)
    {
        $entityType = $entity->getEntityType();

        /**
         * Fallback da ne provjerava privileges ako ne postoji ova kolona
         */
        if (EntityHelper::checkIfMethodExists($entityType, "getCheckPrivileges")) {
            if ($entityType->getCheckPrivileges()) {
                if (!is_object($this->user)) {
                    $this->logger->error("Anonymous user tried to deleteEntityFromDatabase entity: {$entityType->getEntityTypeCode()}");
                    return null;
                } else {
                    $hasPrivilege = true;
                    if (!$this->user->hasPrivilege(4, $entity->getAttributeSet()->getUid())) {
                        $hasPrivilege = false;
                    }

                    if (!$hasPrivilege) {
                        $this->logger->error("{$this->user->getUsername()} user tried to deleteEntityFromDatabase entity: {$entityType->getEntityTypeCode()}");
                        throw new AccessDeniedException("{$this->user->getUsername()} user tried to deleteEntityFromDatabase entity: {$entityType->getEntityTypeCode()}");
                    }
                }
            }
        }

        $context = $this->factoryContext->getContext($entityType);
        $context->delete($entity);

        return $entity;
    }

    /**
     * @param $attributeSetCode
     * @return mixed
     */
    public function getNewEntityByAttributSetName($attributeSetCode)
    {
        /** @var EntityType $entityType */
        $attributeSet = $this->getAttributeSetByCode($attributeSetCode);

        $entity = FactoryEntity::getObject($attributeSet->getEntityType()->getBundle(), $attributeSet->getEntityType()->getEntityModel());

        $entity->setEntityStateId(1);
        $entity->setEntityType($attributeSet->getEntityType());
        $entity->setAttributeSet($attributeSet);

        $attributes = $this->attributeContext->getAttributesByEntityType($entity->getEntityType());
        $entity->setAttributes($attributes);

        return $entity;
    }

    /**
     * @param $entity
     * @param $roles
     */
    public function setEntityUniquePermissions($entity, $roles)
    {
        $permissions = array();

        /**clean previous permissions*/
        $this->entityLevelPermissionContext->removeEntityPermissions($entity);

        foreach ($roles as $role) {
            $permission = new EntityLevelPermission();
            $permission->setEntityId($entity->getId());
            $permission->setEntityTypeId(($entity->getEntityType()->getId()));
            $permission->setRoleId($role->getId());
            $permissions[] = $permission;
        }

        $this->entityLevelPermissionContext->saveArray($permissions);
    }

    /**
     * @param $entity
     * @param $array
     * @return mixed
     */
    public function createEntityFromArray($entity, $array)
    {
        /** @var Attribute $attribute */
        foreach ($entity->getAttributes() as $attribute) {
            /**
             * Skipping core attributes
             */
            if ($attribute->getAttributeCode() == "created") {
                continue;
            }
            if ($attribute->getAttributeCode() == "modified") {
                continue;
            }

            /** @var FieldInterface $field */
            $field = $this->container->get($attribute->getFrontendType() . '_field');
            $field->SetAttribute($attribute);
            $field->SetEntity($entity);

            if (isset($array[$attribute->getAttributeCode()])) {
                $field->setEntityValueFromArray($array);
            }
        }

        return $entity;
    }

    /**
     * @param $entity
     * @param bool $get_related
     * @return array|false
     */
    public function entityToArray($entity, $get_related = true)
    {
        if (empty($entity)) {
            return false;
        }

        $array = array();
        $attributes = $entity->getAttributes(); // dogada se da vraca null
        //todo OVDJE TREBA SLOZITI CACHE
        if ($attributes == null || empty($attributes)) {
            $entityType = $entity->getEntityType();
            $attributes = $this->attributeContext->getAttributesByEntityType($entityType);
        }

        if ($entity->getId() != null) {
            foreach ($attributes as $attribute) {
                $getter = EntityHelper::makeGetter($attribute->getAttributeCode());

                /**
                 * Prvo gleda backend type
                 * Nakon toga frontend type
                 */
                if ($attribute->getBackendType() == "reverse_lookup") {
                    $related_entity_ids = $this->getChildrenIds($attribute->getLookupEntityType()->getId(), $entity->getId());
                    if (!empty($related_entity_ids)) {
                        foreach ($related_entity_ids as $id) {
                            $related_entity = $this->getEntityByEntityTypeAndId($attribute->getlookupEntityType(), $id);
                            $array[$attribute->getAttributeCode()][] = $this->entityToArray($related_entity, false);
                        }
                    }
                } elseif ($attribute->getBackendType() == "lookup") {
                    if ($get_related) {
                        $related_entity = null;
                        $relatedId = null;
                        $relatedJson = null;

                        if ($attribute->getFrontendType() == "autocomplete" || $attribute->getFrontendType() == "lookup_image" || $attribute->getFrontendType() == "select") {
                            $relatedId = $entity->$getter();

                            if (isset($relatedId) && !empty($relatedId)) {
                                $related_entity = $this->getEntityByEntityTypeAndId($attribute->getLookupEntityType(), $relatedId);
                                $related_entity = $this->entityToArray($related_entity, false);
                                $related_entity["lookup_value"] = $related_entity[$attribute->getLookupAttribute()->getAttributeCode()];
                                $array[$attribute->getAttributeCode()][] = $related_entity;
                            } else {
                                $array[$attribute->getAttributeCode()][] = array();
                            }
                        } elseif (strpos($attribute->getFrontendType(), "_autocomplete") !== false) {
                            $relatedId = $entity->$getter();

                            if (isset($relatedId) && !empty($relatedId)) {
                                $related_entity = $this->getEntityByEntityTypeAndId($attribute->getLookupEntityType(), $relatedId);
                                $related_entity = $this->entityToArray($related_entity, false);
                                $related_entity["lookup_value"] = $related_entity[$attribute->getLookupAttribute()->getAttributeCode()];
                                $array[$attribute->getAttributeCode()][] = $related_entity;
                            } else {
                                $array[$attribute->getAttributeCode()][] = array();
                            }
                        } elseif ($attribute->getFrontendType() == "multiselect") {
                            $relatedJsonArray = array();
                            $relatedEntities = $entity->$getter();


                            $lookupAttribute = $this->attributeContext->getById($attribute->getLookupAttribute());
                            $filterAttribute = $this->attributeContext->getById($lookupAttribute->getLookupAttribute());


                            $manager = "autocomplete_manager";

                            if ($attribute->getFrontendModel() != "default") {
                                $manager = $attribute->getFrontendModel() . "_" . $manager;
                            }

                            /** @var AutocompleteManager $autocompleteManager */
                            $autocompleteManager = $this->container->get($manager);


                            if (!empty($relatedEntities) && !empty($filterAttribute)) {
                                foreach ($relatedEntities as $key => $related_entity) {
                                    $relatedJsonArray[] = $autocompleteManager->renderSingleItem($related_entity, Inflector::camelize($filterAttribute->getAttributeCode()));
                                }
                            }


                            $array[$attribute->getAttributeCode()] = $relatedJsonArray;
                            // dump($array[$attribute->getAttributeCode()]);die;
                        } else {
                            dump($attribute->getBackendType());
                            dump($attribute->getFrontendType());
                        }
                    }
                } elseif ($attribute->getBackendType() == "option") {
                    $value = array();

                    if ($attribute->getFrontendInput() == "select") {
                        $optionValues = $attribute->getOptionValues();
                        if (isset($optionValues) && !empty($optionValues)) {
                            foreach ($optionValues as $optionValue) {
                                if ($optionValue->getOption() == $entity->$getter()) {
                                    $value["option"] = $optionValue->getOption();
                                    $value["value"] = $optionValue->getValue();
                                    break;
                                }
                            }
                        }
                    } elseif ($attribute->getFrontendInput() == "multiselect") {
                        $relatedJsonArray = array();
                        $relatedEntities = $entity->$getter();

                        $lookupAttribute = $this->attributeContext->getById($attribute->getLookupAttribute());
                        $filterAttribute = $this->attributeContext->getById($lookupAttribute->getLookupAttribute());

                        if (!empty($relatedEntities)) {
                            foreach ($relatedEntities as $key => $related_entity) {
                                //dump($related_entity);die;
                                $related_entity = $this->getEntityByEntityTypeAndId($related_entity->getEntityType(), $related_entity->getId());

                                $related_entity = $this->entityToArray($related_entity, false);
                                $related_entity["lookup_value"] = $related_entity[Inflector::camelize($attribute->getLookupAttribute()->getAttributeCode())];
                                $relatedJsonArray[] = $related_entity;
                            }
                        }

                        $array[$attribute->getAttributeCode()] = $relatedJsonArray;
                        // dump($array);die;
                    } else {
                        dump($attribute->getBackendType());
                        dump($attribute->getFrontendType());
                    }

                    $array[$attribute->getAttributeCode()] = $value;
                } elseif ($attribute->getBackendType() == "file") {
                } else {
                    if ($attribute->getBackendType() == "date") {
                        if ($entity->$getter() != null) {
                            $array[$attribute->getAttributeCode()] = date_format($entity->$getter(), "d/m/Y");
                        } else {
                            $array[$attribute->getAttributeCode()] = false;
                        }
                    } elseif ($attribute->getFrontendType() == "datetime") {
                        if ($entity->$getter() != null) {
                            $array[$attribute->getAttributeCode()] = date_format($entity->$getter(), "d/m/Y H:i");
                        } else {
                            $array[$attribute->getAttributeCode()] = false;
                        }
                    } else {
                        $array[$attribute->getAttributeCode()] = $entity->$getter();
                    }
                }
            }
        } else {
            foreach ($entity->getAttributes() as $attribute) {
                if ($attribute->getBackendType() == "lookup") {
                    /*if ($attribute->getDefaultValue() != "") {
                        $val = array();
                        $val[] = $attribute->getDefaultValue();
                        $entityLookupAttribute = new EntityLookupAttribute($this->factoryContext);
                        $value = $entityLookupAttribute->getAttributeFormValue($attribute, $val);
                        $array[$attribute->getAttributeCode()] = $value;
                    }*/

                    if ($attribute->getFrontendType() == "select") {
                        $qb = new LookupQueryBuilder();
                        $values = $this->databaseContext->executeQuery($qb->getLookupValuesQuery($attribute));
                        if (isset($values)) {
                            foreach ($values as $value) {
                                $lookup = new Entity\LookupValue();
                                $lookup->setId($value['id']);
                                $lookup->setValue($value['value']);
                                $lookups[] = $lookup;
                            }
                        }
                        $attribute->setLookupValues($lookups);
                    }
                }
            }
        }

        if ($entity->getCreated() != null) {
            $array["created"] = date_format($entity->getCreated(), "Y-m-d H:i");
        }
        if ($entity->getModified() != null) {
            $array["modified"] = date_format($entity->getModified(), "Y-m-d H:i");
        }

        if ($entity->getEntityType()->getIsDocument()) {
            $array["size"] = $entity->getSize();
            $array["file_type"] = $entity->getFileType();
            $array["filename"] = $entity->getFilename();
        }
        $array["entity_type_code"] = $entity->getEntityType()->getEntityTypeCode();
        $array["attribute_set_code"] = $entity->getAttributeSet()->getAttributeSetCode();

        return $array;
    }

    /**
     * @param $entity
     * @param $attribute
     * @return false
     */
    public function getValue($entity, $attribute)
    {
        $collection = $this->getCollectionName($attribute->getBackendModel());
        if (isset($entity->$collection)) {
            foreach ($entity->$collection as $item) {
                if ($item->getAttributeId() == $attribute->getId()) {
                    return $item->getValue();
                };
            }
        }
        return false;
    }

    /**
     * @param $entity
     * @param $entityTypesToAvoid
     * @return array
     */
    public function getChildEntities($entity, $entityTypesToAvoid)
    {
        $childEntities = array();

        $attributes = $this->attributeContext->getBy(array("lookupEntityType" => $entity->getEntityType()->getId()));

        if (!empty($attributes)) {

            /** @var Attribute $attribute */
            foreach ($attributes as $attribute) {
                if (!empty($entityTypesToAvoid) && in_array($attribute->getEntityType()->getEntityTypeCode(), $entityTypesToAvoid)) {
                    continue;
                }

                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector("and");
                $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
                $compositeFilter->addFilter(new SearchFilter(EntityHelper::makeAttributeName($attribute->getAttributeCode()), "eq", $entity->getId()));

                $compositeFilters = new CompositeFilterCollection();
                $compositeFilters->addCompositeFilter($compositeFilter);

                $items = $this->getEntitiesByEntityTypeAndFilter($attribute->getEntityType(), $compositeFilters);

                if (!empty($items)) {
                    foreach ($items as $item) {
                        $childEntities[$attribute->getEntityType()->getEntityTypeCode() . "_" . $item->getId()] = $item;
                    }
                }
            }
        }

        return $childEntities;
    }

    /**
     * @param $fromEntity
     * @param $convertToCode
     * @param array $overrides
     * @param bool $tryAssignLookupAttributes
     * @param array $childEntities
     * @return mixed
     */
    public function cloneEntity($fromEntity, $convertToCode, $overrides = [], $tryAssignLookupAttributes = false, $childEntities = [])
    {
        $fromEntity = $this->entityBeforeClone($fromEntity);

        $attributes = $this->getAttributesOfEntityType($fromEntity->getEntityType()->getEntityTypeCode(), false);

        $newEntity = $this->getNewEntityByAttributSetName($convertToCode);

        /** @var Attribute $attribute */
        foreach ($attributes as $attribute) {
            if ($attribute->getAttributeCode() == "id") {
                continue;
            }

            if (!empty($overrides) && array_key_exists($attribute->getAttributeCode(), $overrides)) {
                $setter = EntityHelper::makeSetter($attribute->getAttributeCode());
                $value = $overrides[$attribute->getAttributeCode()];
            } else {
                if ($attribute->getBackendType() == "lookup") {
                    if (strpos($attribute->getAttributeCode(), '_id') == false) {
                        continue; //skip many to many
                    }

                    $setter = EntityHelper::makeSetter(str_replace("_id", "", $attribute->getAttributeCode()));
                    $value = $fromEntity->{EntityHelper::makeGetter(str_replace("_id", "", $attribute->getAttributeCode()))}();

                    if ($tryAssignLookupAttributes) {
                        $lookupAssignes = [];

                        $lookupEntity = $fromEntity->{EntityHelper::makeGetter(str_replace("_id", "", $attribute->getAttributeCode()))}();

                        if (EntityHelper::isCountable($lookupEntity) && !empty($lookupEntity)) {
                        } elseif (!empty($lookupEntity) && $lookupEntity != null) {
                            //get lookup entity attributes
                            $lookupEntityType = $lookupEntity->getAttributeSet()->getEntityType();
                            $lookupAttributes = $this->attributeContext->getAttributesByEntityType($lookupEntityType);

                            foreach ($lookupAttributes as $lookupAttribute) {
                                if ($lookupAttribute->getAttributeCode() == "id") {
                                    continue;
                                }
                                if ($lookupAttribute->getBackendType() == "lookup") {
                                    continue;
                                }

                                $lookupSetter = EntityHelper::makeSetter(str_replace("_id", "", $attribute->getAttributeCode()) . "_" . $lookupAttribute->getAttributeCode());
                                $lookupValue = $lookupEntity->{EntityHelper::makeGetter($lookupAttribute->getAttributeCode())}();

                                $lookupAssignes[] = [
                                    "setter" => $lookupSetter,
                                    "value" => $lookupValue
                                ];
                            }
                        }
                    }
                } else {
                    $setter = EntityHelper::makeSetter($attribute->getAttributeCode());
                    $value = $fromEntity->{EntityHelper::makeGetter($attribute->getAttributeCode())}();
                }
            }

            if (EntityHelper::checkIfMethodExists($newEntity, $setter)) {
//                if ($value instanceof \DateTime) {
//                    $value = $value->format('Y-m-d');
//                    $value = \DateTime::createFromFormat("Y-m-d", $value);
//                }

                $newEntity->{$setter}($value);

            }

            if ($tryAssignLookupAttributes && isset($lookupAssignes) && !empty($lookupAssignes)) {
                foreach ($lookupAssignes as $lookupAssign) {
                    if (EntityHelper::checkIfMethodExists($newEntity, $lookupAssign["setter"])) {
                        $newEntity->{$lookupAssign["setter"]}($lookupAssign["value"]);
                    }
                }
            }
        }
        //$this->detach($fromEntity);
        $this->saveEntity($newEntity);

        $this->entityAfterClone($newEntity);

        /** @deprecated Left ony for backwords compatibility */
        if ($newEntity && !empty($childEntities)) {
            foreach ($childEntities as $atributeSetCode => $newAttributeSetCode) {
                $mAttributeSet = $this->getAttributeSetByCode($atributeSetCode);

                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector("and");
                $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
                $compositeFilter->addFilter(new SearchFilter(EntityHelper::makeAttributeName($newEntity->getEntityType()->getEntityTypeCode()), "eq", $fromEntity->getId()));

                $compositeFilters = new CompositeFilterCollection();
                $compositeFilters->addCompositeFilter($compositeFilter);

                $children = $this->getEntitiesByAttributeSetAndFilter($mAttributeSet, $compositeFilters);

                if (!empty($children)) {
                    foreach ($children as $child) {
                        $newChild = $this->cloneEntity($child, $newAttributeSetCode);
                        $setter = EntityHelper::makeSetter($newEntity->getEntityType()->getEntityTypeCode());
                        $newChild->{$setter}($newEntity);
                        $this->saveEntity($newEntity);
                    }
                }
            }
        }

        return $newEntity;
    }

    /**
     * @param EntityType $entityType
     * @return array
     */
    public function getAttributesListExportConfiguration(EntityType $entityType)
    {
        $attributeExportConfigurations = array();

        $attributes = $this->attributeContext->getBy(array("entityType" => $entityType), array("id" => "asc"));

        /** @var Attribute $attribute */
        foreach ($attributes as $attribute) {

            if ($attribute->getBackendType() != "lookup") {
                $attributeExportConfiguration = new AttributeListExportConfiguration();
                $attributeExportConfiguration->setDisplayName($attribute->getFrontendLabel());
                $attributeExportConfiguration->setPath($attribute->getAttributeCode());
                $attributeExportConfigurations[] = $attributeExportConfiguration;
            } else {
                $attribute = $this->getLookupAttributes($attribute);

                /** @var Attribute $lookupAttribute */
                foreach ($attribute->getLookupAttributes() as $lookupAttribute) {

                    if ($attribute->getFrontendType() == "multiselect" && !empty($attribute->getLookupAttribute())) {
                        if ($lookupAttribute->getId() != $attribute->getLookupAttribute()->getId()) {
                            continue;
                        }
                    }

                    $path = str_replace("_id", "", $attribute->getAttributeCode());

                    while ($lookupAttribute->getLookupAttribute()) {
                        if ($attribute->getFrontendType() != "multiselect") {
                            $path = $path . "." . str_replace("_id", "", $lookupAttribute->getAttributeCode());
                        }

                        $lookupAttribute = $lookupAttribute->getLookupAttribute();
                    }

                    $path = $path . "." . $lookupAttribute->getAttributeCode();
                    $label = $attribute->getFrontendLabel() . ":" . $lookupAttribute->getFrontendLabel();

                    $attributeExportConfiguration = new AttributeListExportConfiguration();
                    $attributeExportConfiguration->setDisplayName($label);
                    $attributeExportConfiguration->setPath($path);
                    $attributeExportConfigurations[] = $attributeExportConfiguration;
                }
            }
        }

        return $attributeExportConfigurations;
    }

    protected function buildLookupAttributePath(Attribute $attribute, &$path)
    {
        if ($attribute->getBackendType() == "lookup") {
            $path = $path . "." . $attribute->getAttributeCode();
            if ($attribute->getFrontendType() == "multiselect") {
                $path = $path . "." . $attribute->getLookupAttribute()->getLookupAttribute()->getAttributeCode();
            } else {
                $path = $path . "." . $attribute->getLookupAttribute()->getAttributeCode();
                if ($attribute->getLookupAttribute()->getBackendType() == "lookup") {
                    $this->buildLookupAttributePath($attribute->getLookupAttribute(), $path);
                }
            }
            $path = str_replace("_id", "", $path);
        } else {
            $path = $path . "." . $attribute->getAttributeCode();
        }
    }

    /**
     * @param $attribute
     * @param array $usedEntityTypes
     * @return mixed
     */
    protected function getLookupAttributes($attribute, &$usedEntityTypes = array())
    {
        $lookupAttributes = null;
        if (array_key_exists($attribute->getLookupEntityType()->getEntityTypeCode(), $usedEntityTypes)) {
            $lookupAttributes = $usedEntityTypes[$attribute->getLookupEntityType()->getEntityTypeCode()];
        } else {
            $lookupAttributes = $this->attributeContext->getBy(array('entityType' => $attribute->getLookupEntityType()), array("id" => "asc"));
            $usedEntityTypes[$attribute->getLookupEntityType()->getEntityTypeCode()] = $lookupAttributes;
        }

        foreach ($lookupAttributes as $lookupAttribute) {
            $lookupAttribute->setParentLookup($attribute);
        }

        $attribute->setLookupAttributes($lookupAttributes);

        return $attribute;
    }

    /**
     * @param EntityType $entityType
     * @return array
     */
    public function getAttributesOfEntityTypeByKey(EntityType $entityType)
    {
        $attributesList = array();

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $attributesList = $this->cacheManager->getCacheItem("attribute_list_{$entityType->getEntityTypeCode()}");

        if (empty($attributesList)) {

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }

            $q = "SELECT * FROM attribute WHERE entity_type_id = {$entityType->getId()} ORDER BY id ASC;";
            $attributes = $this->databaseContext->getAll($q);
            //$attributes = $this->attributeContext->getBy(array('entityType' => $entityType), array("id" => "asc"));

            /** @var Attribute $attribute */
            foreach ($attributes as $attribute) {

                $attributeCode = $attribute["attribute_code"];
                $attributesList[$attributeCode] = $attribute;

                $pattern = 'Id\\b';
                $attributeCode = trim(preg_replace('/' . $pattern . '/i', '', $attributeCode));

                $attributesList[EntityHelper::makeAttributeName($attributeCode)] = $attribute;
            }

            $this->cacheManager->setCacheItem("attribute_list_{$entityType->getEntityTypeCode()}", $attributesList);
        }

        return $attributesList;
    }

    /**
     * @param $entityTypeCode
     * @param bool $recursive
     * @param array $usedEntityTypes
     * @return array
     */
    public function getAttributesOfEntityType($entityTypeCode, $recursive = true, &$usedEntityTypes = array())
    {
        $attributesList = array();

        $entityType = $this->entityTypeContext->getOneBy(array("entityTypeCode" => $entityTypeCode));

        $usedEntityTypes[] = $entityTypeCode;

        $attributes = $this->attributeContext->getBy(array('entityType' => $entityType), array("id" => "asc"));

        /** @var Attribute $attribute */
        foreach ($attributes as $attribute) {
            if ($attribute->getBackendType() != "lookup") {
                $attributesList[] = $attribute;//Array("entity_type_code" => $attribute->getEntityType()->getEntityTypeCode(), "attribute_id" => $attribute->getId(), "frontend_type" => $attribute->getFrontendType(), "attribute_code" => $attribute->getAttributeCode(), "label" => $attribute->getFrontendLabel());
            } elseif ($attribute->getBackendType() == "lookup" && $attribute->getFrontendType() == "multiselect") {
                //$tmp = $attribute->getAttributeCode().".".$attribute->getLookupAttribute()->getLookupAttribute()->getAttributeCode();
                //$attribute->setAttributeCode($tmp);
                $attributesList[] = $attribute;
            } elseif ($attribute->getBackendType() == "lookup" && !$recursive) {
                $attributesList[] = $attribute;//Array("entity_type_code" => $attribute->getEntityType()->getEntityTypeCode(), "attribute_id" => $attribute->getId(), "frontend_type" => $attribute->getFrontendType(), "attribute_code" => $attribute->getAttributeCode(), "label" => $attribute->getFrontendLabel());
            }
        }

        if ($recursive) {
            foreach ($attributes as $attribute) {
                if ($attribute->getBackendType() == "lookup") {
                    if (in_array($attribute->getLookupEntityType()->getEntityTypeCode(), $usedEntityTypes)) {
                        continue;
                    }
                    $attributesListTmp = $this->getAttributesOfEntityType($attribute->getLookupEntityType()->getEntityTypeCode(), true, $usedEntityTypes);
                    foreach ($attributesListTmp as $key => $attributeListTmp) {
                        //$attributesListTmp[$key]["attribute_code"] = rtrim($attribute->getAttributeCode(),"_id").".".$attributeListTmp["attribute_code"];
                        $attributesListTmp[$key]->setAttributeCode(str_replace("_id", "", $attribute->getAttributeCode()) . "." . $attributeListTmp->getAttributeCode());
                    }
                    $attributesList = array_merge($attributesList, $attributesListTmp);
                }
            }
        }

        return $attributesList;
    }

    /**
     * @param $entityArray
     * @param $attributes
     * @param $relatedEntites
     * @return array
     */
    public function validateImportEntity($entityArray, $attributes, $relatedEntites)
    {
        foreach ($entityArray as $key => $value) {
            if (strpos($key, ".")) {
                $keyTmp = explode(".", $key);

                /** @var Attribute $selectedAttribute */
                $selectedAttribute = null;

                foreach ($attributes as $attribute) {
                    if ($keyTmp[0] == $attribute->getAttributeCode()) {
                        $selectedAttribute = $attribute;
                        break;
                    }
                }

                unset($entityArray[$key]);

                if (empty($selectedAttribute)) {
                    continue;
                }

                if ($selectedAttribute->getFrontendType() == "multiselect") {
                    $multiselectArray = array();
                    $multiselectArray["link_entity"] = $selectedAttribute->getLookupEntityType()->getEntityTypeCode();
                    $multiselectArray["parent_entity"] = $selectedAttribute->getEntityType()->getEntityTypeCode();
                    $multiselectArray["child_entity"] = $selectedAttribute->getLookupAttribute()->getLookupEntityType()->getEntityTypeCode();
                    $multiselectArray["attribute_id"] = $selectedAttribute->getId();
                    $multiselectArray["related_ids"] = array();

                    $value = explode(",", $value);
                    foreach ($value as $v) {
                        $v = trim(strtolower($v));
                        if (isset($relatedEntites[$keyTmp[0]][$v])) {
                            $multiselectArray["related_ids"][] = $relatedEntites[$keyTmp[0]][$v];
                        }
                    }

                    if (!empty($multiselectArray["related_ids"])) {
                        $entityArray["multiselect"][] = $multiselectArray;
                    }
                } else {
                    $value = trim(strtolower($value));
                    if (isset($relatedEntites[$keyTmp[0]][$value])) {
                        $entityArray[$keyTmp[0]] = $relatedEntites[$keyTmp[0]][$value];
                    }
                }
            }
        }

        foreach ($entityArray as $key => $value) {
            /** @var Attribute $attribute */
            foreach ($attributes as $attribute) {
                if ($key == $attribute->getAttributeCode()) {
                    /**
                     * Clean integer
                     */
                    if ($attribute->getAttributeCode() == "show_on_store") {
                        $entityArray["show_on_store_checkbox"] = $value;
                    } elseif ($attribute->getFrontendType() == "integer") {
                        $value = intval($value);
                        if (empty($value) || $value < 0) {
                            $value = 0;
                        }
                        $entityArray[$key] = $value;
                    } /**
                     * Clean bool
                     */
                    elseif ($attribute->getFrontendType() == "checkbox") {
                        if (empty(trim($value))) {
                            unset($entityArray[$key]);
                            continue;
                        }
                        if (trim(strtolower($value)) === "true" || $value == 1) {
                            $value = 1;
                        } elseif (trim(strtolower($value)) === "false") {
                            $value = 0;
                        } else {
                            $value = 0;
                        }
                        $entityArray[$key] = $value;
                    } /**
                     * Clean decimal
                     */
                    elseif ($attribute->getFrontendType() == "integer") {
                        $value = floatval($value);
                        if (empty($value) || $value < 0) {
                            $value = 0;
                        }
                        $entityArray[$key] = $value;
                    } /**
                     * Clean date
                     */
                    elseif ($attribute->getFrontendInput() == "date") {
                        /*$value = $this->helperManager->createDateFromString($value);
                        if (empty($value)) {
                            unset($entityArray[$key]);
                            continue;
                        }*/
                        $entityArray[$key] = null;
                    }
                    break;
                } else {
                    continue;
                }
            }
        }

        return array("entityArray" => $entityArray, "errors" => "");
    }

    /**
     * @param $entityArray
     * @param $primaryKeyAttributeCode
     * @param $attributeSet
     * @param $attributes
     * @param $relatedEntites
     * @return array
     */
    public function importEntity($entityArray, $primaryKeyAttributeCode, $attributeSet, $attributes, $relatedEntites)
    {
        $result = array();
        $result["error"] = false;
        $result["messages"] = array();

        $ret = $this->validateImportEntity($entityArray, $attributes, $relatedEntites);

        $entityArray = $ret["entityArray"];
        if (!empty($ret["errors"])) {
            $result["messages"] = $ret["errors"];
        }

        $entity = null;
        $primaryKeyAttributeName = EntityHelper::makeAttributeName($primaryKeyAttributeCode);
        if (!empty($entityArray[$primaryKeyAttributeCode])) {
            if ($primaryKeyAttributeCode == "id") {
                $entity = $this->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $entityArray[$primaryKeyAttributeCode]);
            } else {
                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector("and");
                $compositeFilter->addFilter(new SearchFilter($primaryKeyAttributeName, "eq", $entityArray[$primaryKeyAttributeCode]));

                $compositeFilters = new CompositeFilterCollection();
                $compositeFilters->addCompositeFilter($compositeFilter);

                $entity = $this->getEntityByAttributeSetAndFilter($attributeSet, $compositeFilters);
            }


            if (!empty($entity)) {
                $entityArray["id"] = $entity->getId();
            }
        }

        $result["error"] = false;

        try {
            $entity = $this->saveFormEntity($attributeSet, $entityArray);
            if ($entity->getEntityValidationCollection() != null) {
                $result["error"] = true;
                if (EntityHelper::isCountable($entity->getEntityValidationCollection())) {
                    foreach ($entity->getEntityValidationCollection() as $validationCollection) {
                        $result["messages"][] = $validationCollection->getMessage();
                    }
                }
            }
        } catch (Exception $e) {

            $result["error"] = true;
            $result["messages"][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * @param $attributeSetCode
     * @param $entities
     * @param $primaryKeyAttributeCode
     * @param $matchedAttributes
     * @return array
     */
    public function importEntites($attributeSetCode, $entities, $primaryKeyAttributeCode, $matchedAttributes)
    {
        $results = array();

        $attributeSet = $this->attributeSetContext->getOneBy(array("attributeSetCode" => $attributeSetCode));

        $attributesTmp = $this->getAttributesOfEntityType($attributeSet->getEntityType()->getEntityTypeCode(), false);
        $attributes = array();
        $relatedEntites = array();

        foreach ($matchedAttributes as $matchedAttribute) {
            $relatedAttributeCode = null;

            if (strpos($matchedAttribute, ".")) {
                $matchedAttribute = explode(".", $matchedAttribute);
                $relatedAttributeCode = $matchedAttribute[1];
                $matchedAttribute = $matchedAttribute[0];
            }
            foreach ($attributesTmp as $attributeTmp) {
                if ($attributeTmp->getAttributeCode() == $matchedAttribute) {
                    $attributes[$matchedAttribute] = $attributeTmp;
                    break;
                }
            }

            /**
             * Get related entity values
             */
            if (!empty($relatedAttributeCode)) {

                /** @var Attribute $relatedAttribute */
                $relatedAttribute = $attributes[$matchedAttribute];
                if ($relatedAttribute->getFrontendType() == "multiselect") {
                    $context = $this->factoryContext->getContext($relatedAttribute->getLookupAttribute()->getLookupEntityType());
                } else {
                    $context = $this->factoryContext->getContext($relatedAttribute->getLookupEntityType());
                }

                $relatedEntitesTmp = $context->getAllItems();

                $getter = EntityHelper::makeGetter($relatedAttributeCode);

                foreach ($relatedEntitesTmp as $relatedEntityTmp) {
                    $value = $relatedEntityTmp->{$getter}();
                    if (is_array($value)) {
                        $value = reset($value);
                    }
                    $valueKey = trim(strtolower($value));
                    $relatedEntites[$matchedAttribute][$valueKey] = $relatedEntityTmp->getId();

                    if ($relatedAttribute->getBackendType() == "varchar") {
                        $valueTrans = $this->translator->trans($value);
                        $valueTransKey = trim(strtolower($valueTrans));
                        $relatedEntites[$matchedAttribute][$valueTransKey] = $relatedEntityTmp->getId();
                    }
                }

                unset($relatedEntitesTmp);
                if ($relatedAttribute->getFrontendType() == "multiselect") {
                    $this->clearManagerByEntityType($relatedAttribute->getLookupAttribute()->getLookupEntityType());
                } else {
                    $this->clearManagerByEntityType($relatedAttribute->getLookupEntityType());
                }
            }
        }

        $results["updated"] = 0;

        foreach ($entities as $entity) {

            $result = $this->importEntity($entity, $primaryKeyAttributeCode, $attributeSet, $attributes, $relatedEntites);
            if (!empty($result)) {
                $results["entities"][] = $result;
                if ($result["error"] == false) {
                    $results["updated"]++;
                }
            }

            $this->clearManagerByEntityType($attributeSet->getEntityType());
        }

        return $results;
    }

    /**
     * @param $entity
     * @param $array
     * @return mixed
     */
    public function arrayToEntity($entity, $array)
    {
        if (empty($entity->getAttributes())) {
            $attributes = $this->attributeContext->getAttributesByEntityType($entity->getEntityType());
            $entity->setAttributes($attributes);
        }

        $entity = $this->createEntityFromArray($entity, $array);
        return $entity;
    }

    public function clearManager()
    {
        return $this->entityContext->clearManager();
    }

    public function detach($entity)
    {
        $this->entityContext->detach($entity);
    }

    public function remove($entity)
    {
        $this->entityContext->remove($entity);
    }

    public function refreshEntity($entity)
    {
        return $this->entityContext->refresh($entity);
    }

    /**
     * Clears the EntityManager for specified entity type. All entities of that type
     * that are currently managed by this EntityManager become detached.
     *
     * @param EntityType $entityType
     * @return void
     */
    public function clearManagerByEntityType(EntityType $entityType)
    {
        $context = $this->factoryContext->getContext($entityType);
        return $context->clearManager();
    }

    /**
     * @param EntityType $entityType
     * @param $id
     * @return null
     */
    public function getEntityByEntityTypeAndId(EntityType $entityType, $id)
    {
        /**
         * 21.4. Testiramo novih dohvat
         */
        $context = $this->factoryContext->getContext($entityType);
        return $context->getById($id);
    }

    /**
     * @param $entityTypeCode
     * @param $id
     * @return |null
     */
    public function getEntityByEntityTypeCodeAndId($entityTypeCode, $id)
    {

        $entityType = $this->getEntityTypeByCode($entityTypeCode);

        if (empty($entityType)) {
            return null;
        }

        return $this->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param EntityType $entityType
     * @param CompositeFilterCollection $filters
     * @param SortFilterCollection|null $sortFilters
     * @param PagingFilter|null $pagingFilter
     * @return mixed
     */
    public function getEntitiesByEntityTypeAndFilter(EntityType $entityType, CompositeFilterCollection $filters, SortFilterCollection $sortFilters = null, PagingFilter $pagingFilter = null)
    {
        $context = $this->factoryContext->getContext($entityType);
        $entities = $context->getItemsWithFilter($filters, $sortFilters, $pagingFilter);
        return $entities;
    }

    /**
     * @param AttributeSet $attributeSet
     * @param CompositeFilterCollection $filters
     * @param SortFilterCollection|null $sortFilters
     * @return mixed
     */
    public function getEntitiesByAttributeSetAndFilter(AttributeSet $attributeSet, CompositeFilterCollection $filters, SortFilterCollection $sortFilters = null)
    {
        $entityType = $attributeSet->getEntityType();

        $compositeFilter = new CompositeFilter();

        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("attributeSet.id", "eq", $attributeSet->getId()));

        //dump($attributeSet,$attributeSet->getId());die;

        $filters->addCompositeFilter($compositeFilter);

        $context = $this->factoryContext->getContext($entityType);
        $entities = $context->getItemsWithFilter($filters, $sortFilters);
        return $entities;
    }

    /**
     * @param EntityType $entityType
     * @param CompositeFilterCollection $filters
     * @param SortFilterCollection|null $sortFilters
     * @return |null
     */
    public function getEntityByEntityTypeAndFilter(EntityType $entityType, CompositeFilterCollection $filters, SortFilterCollection $sortFilters = null)
    {
        $context = $this->factoryContext->getContext($entityType);
        $entities = $context->getItemsWithFilter($filters, $sortFilters);

        if (empty($entities)) {
            return null;
        } else {
            return $entities[0];
        }
    }

    /**
     * @param AttributeSet $attributeSet
     * @param CompositeFilterCollection $filters
     * @param SortFilterCollection|null $sortFilters
     * @return |null
     */
    public function getEntityByAttributeSetAndFilter(AttributeSet $attributeSet, CompositeFilterCollection $filters, SortFilterCollection $sortFilters = null)
    {
        $entityType = $attributeSet->getEntityType();
        $compositeFilter = new CompositeFilter();

        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("attributeSet.id", "eq", $attributeSet->getId()));

        $filters->addCompositeFilter($compositeFilter);

        $context = $this->factoryContext->getContext($entityType);
        $entities = $context->getItemsWithFilter($filters, $sortFilters);

        if (empty($entities)) {
            return null;
        } else {
            return $entities[0];
        }
    }

    /**Event definitions*/
    public function entityCreated($entity)
    {
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(EntityCreatedEvent::NAME, new EntityCreatedEvent($entity));
    }

    public function entityPreSetCreated($entity, $data = array())
    {
        $eventDispatcher = $this->container->get("event_dispatcher");
        $event = new EntityPreSetCreatedEvent($entity, $data);
        $eventDispatcher->dispatch(EntityPreSetCreatedEvent::NAME, $event);
        return $event->getData();
    }

    public function entityPreSetUpdated($entity, $data = array())
    {
        $eventDispatcher = $this->container->get("event_dispatcher");
        $event = new EntityPreSetUpdatedEvent($entity, $data);
        $eventDispatcher->dispatch(EntityPreSetUpdatedEvent::NAME, $event);
        return $event->getData();
    }

    public function entityPreCreated($entity, $data = array())
    {
        $eventDispatcher = $this->container->get("event_dispatcher");
        $event = new EntityPreCreatedEvent($entity, $data);
        $eventDispatcher->dispatch(EntityPreCreatedEvent::NAME, $event);
    }

    public function entityPreUpdated($entity, $data = array())
    {
        $eventDispatcher = $this->container->get("event_dispatcher");
        $event = new EntityPreUpdatedEvent($entity, $data);
        $eventDispatcher->dispatch(EntityPreUpdatedEvent::NAME, $event);
    }

    public function entityUpdated($entity, $previousValuesArray = null)
    {
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(EntityUpdatedEvent::NAME, new EntityUpdatedEvent($entity, $previousValuesArray));
    }

    public function entityPreDeleted($entity)
    {
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(EntityPreDeletedEvent::NAME, new EntityPreDeletedEvent($entity));
    }

    public function entityDeleted($entity)
    {
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(EntityDeletedEvent::NAME, new EntityDeletedEvent($entity));
    }

    /**
     * @param $entity
     * @return mixed
     */
    public function entityBeforeClone($entity)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");

        $event = new EntityBeforeCloneEvent($entity);
        return $eventDispatcher->dispatch(EntityBeforeCloneEvent::NAME, $event)->getEntity();
    }

    /**
     * @param $entity
     */
    public function entityAfterClone($entity)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");

        $event = new EntityAfterCloneEvent($entity);
        $eventDispatcher->dispatch(EntityAfterCloneEvent::NAME, $event);
    }

    /**
     * @param Attribute $attribute
     * @return string
     */
    public function createAttributeCodeChain(Attribute $attribute)
    {
        $attributeChain = Inflector::camelize($attribute->getAttributeCode());

        if (!empty($attribute->getLookupAttribute())) {
            $attributeChain = rtrim($attributeChain, "Id");
            $attributeChain = $attributeChain . "." . $this->createAttributeCodeChain($attribute->getLookupAttribute());
        }

        return $attributeChain;
    }

    /**
     * @param $entity
     * @param $attributeChain
     * @return false
     */
    public function getValueFromAttributeChainAndEntity($entity, $attributeChain)
    {
        $value = false;

        if (empty($entity)) {
            return $value;
        }

        if (strpos($attributeChain, '.') !== false) {
            $attrCode = explode('.', $attributeChain);

            $value = $entity;
            foreach ($attrCode as $code) {
                if (!empty($value) && method_exists($value, EntityHelper::makeGetter($code))) {
                    $value = $value->{EntityHelper::makeGetter($code)}();
                } else {
                    return false;
                }
            }
        } else {
            if (method_exists($entity, EntityHelper::makeGetter($attributeChain))) {
                $value = $entity->{EntityHelper::makeGetter($attributeChain)}();
            }
        }

        return $value;
    }

    /**
     * @param EntityType $entityType
     * @return array
     */
    public function getAttributeCodesByEntityType(EntityType $entityType)
    {
        $ret = array();

        $attributes = $this->attributeContext->getAttributesByEntityType($entityType);

        if (!empty($attributes)) {
            /** @var Attribute $attribute */
            foreach ($attributes as $attribute) {
                $ret[] = $attribute->getAttributeCode();
                $ret[] = EntityHelper::makeAttributeName($attribute->getAttributeCode());
            }
        }

        return $ret;
    }

    /**
     * @param EntityType $entityType
     * @return array
     */
    public function getAttributeCodesAndFrontendTypeByEntityType(EntityType $entityType)
    {
        $ret = array();

        $attributes = $this->attributeContext->getAttributesByEntityType($entityType);

        if (!empty($attributes)) {
            /** @var Attribute $attribute */
            foreach ($attributes as $attribute) {
                $ret[$attribute->getAttributeCode()] = $attribute->getFrontendType();
                $ret[EntityHelper::makeAttributeName($attribute->getAttributeCode())] = $attribute->getFrontendType();
            }
        }

        return $ret;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function deleteRedisCacheKeys($key)
    {

        try {
            if (empty($this->redisClient)) {
                $this->redisClient = $this->container->get("snc_redis.default");
            }

            $keys = $this->redisClient->keys($key);
            if (!empty($keys)) {
                foreach ($keys as $key) {
                    $this->redisClient->del($key);
                }
            }
        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}
