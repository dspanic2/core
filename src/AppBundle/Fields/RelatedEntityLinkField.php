<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Entity\EntityType;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use AppBundle\Managers\EntityManager;
use Doctrine\Common\Inflector\Inflector;

class RelatedEntityLinkField extends AbstractField implements FieldInterface
{

    public function getBackendType()
    {
        return "varchar";
    }

    public function GetFormFieldHtml()
    {
        $sourceModel = $this->GetAttribute()->getSourceModel();
        if (!empty($sourceModel)) {
            $sourceModel = json_decode($sourceModel, true);
            if (isset($sourceModel["entity_type_id_attribute"]) && isset($sourceModel["entity_id_attribute"])) {
                /** @var EntityManager $entityManager */
                $entityManager = $this->container->get("entity_manager");

                $entityTypeGetter = EntityHelper::makeGetter($sourceModel["entity_type_id_attribute"]);
                /** @var EntityType $entityType */
                $entityType = $entityManager->getEntityTypeById($this->entity->{$entityTypeGetter}());
                if (empty($entityType)) {
                    return $this->twig->render(
                        $this->GetFormTemplate(),
                        array(
                            'attribute' => $this->GetAttribute(),
                            'entity' => $this->GetEntity(),
                            'formType' => $this->GetFormType(),
                            'missing_entity' => ""
                        )
                    );
                }

                $entityIdGetter = EntityHelper::makeGetter($sourceModel["entity_id_attribute"]);
                $entityId = $this->entity->{$entityIdGetter}();
                if (empty($entityId)) {
                    return $this->twig->render(
                        $this->GetFormTemplate(),
                        array(
                            'attribute' => $this->GetAttribute(),
                            'entity' => $this->GetEntity(),
                            'formType' => $this->GetFormType(),
                            'missing_entity' => $entityType->getEntityTypeCode()
                        )
                    );
                }

                $entity = $entityManager->getEntityByEntityTypeAndId($entityType, $entityId);
                if (empty($entity)) {
                    return $this->twig->render(
                        $this->GetFormTemplate(),
                        array(
                            'attribute' => $this->GetAttribute(),
                            'entity' => $this->GetEntity(),
                            'formType' => $this->GetFormType(),
                            'missing_entity' => $entityType->getEntityTypeCode() . " - " . $entityId
                        )
                    );
                }

                $name = $entity->getName();
                if (is_array($name)) {
                    $name = implode(", ", $name);
                }

                return $this->twig->render(
                    $this->GetFormTemplate(),
                    array(
                        'attribute' => $this->GetAttribute(),
                        'entity' => $this->GetEntity(),
                        'formType' => $this->GetFormType(),
                        'url' => "/page/{$entityType->getEntityTypeCode()}/form/{$entityId}",
                        'name' => $name
                    )
                );
            }
        }

        return $this->twig->render(
            $this->GetFormTemplate(),
            array(
                'attribute' => $this->GetAttribute(),
                'entity' => $this->GetEntity(),
                'formType' => $this->GetFormType(),
                'missing_source_model' => true
            )
        );
    }

    public function GetListViewHtml()
    {
        $sourceModel = $this->GetAttribute()->getSourceModel();
        if (!empty($sourceModel)) {
            $sourceModel = json_decode($sourceModel, true);
            if (isset($sourceModel["entity_type_id_attribute"]) && isset($sourceModel["entity_id_attribute"])) {
                /** @var EntityManager $entityManager */
                $entityManager = $this->container->get("entity_manager");

                $entityIdGetter = EntityHelper::makeGetter($sourceModel["entity_id_attribute"]);
                $entityId = $this->entity->{$entityIdGetter}();

                $entityTypeGetter = EntityHelper::makeGetter($sourceModel["entity_type_id_attribute"]);
                /** @var EntityType $entityType */
                $entityType = $entityManager->getEntityTypeById($this->entity->{$entityTypeGetter}());

                $relatedEntity = $entityManager->getEntityByEntityTypeAndId($entityType, $entityId);

                if (empty($relatedEntity)) {
                    return $this->twig->render(
                        $this->GetListViewTemplate(),
                        array(
                            'width' => $this->GetListViewAttribute()->getColumnWidth(),
                            'fieldClass' => $this->GetFieldClass(),
                            "attribute" => $this->GetListViewAttribute()->getAttribute(),
                            'missing_entity' => $entityType->getEntityTypeCode() . " - " . $entityId
                        )
                    );
                }

                $name = $relatedEntity->getName();
                if (is_array($name)) {
                    $name = implode(", ", $name);
                }

                return $this->twig->render(
                    $this->GetListViewTemplate(),
                    array(
                        'width' => $this->GetListViewAttribute()->getColumnWidth(),
                        'fieldClass' => $this->GetFieldClass(),
                        "attribute" => $this->GetListViewAttribute()->getAttribute(),
                        'type' => $entityType->getEntityTypeCode(),
                        'url' => "/page/{$entityType->getEntityTypeCode()}/form/{$entityId}",
                        'name' => $name
                    )
                );
            }
        }

        return $this->twig->render(
            $this->GetListViewTemplate(),
            array(
                'width' => $this->GetListViewAttribute()->getColumnWidth(),
                'fieldClass' => $this->GetFieldClass(),
                "attribute" => $this->GetListViewAttribute()->getAttribute(),
                'missing_source_model' => true
            )
        );
    }
}
