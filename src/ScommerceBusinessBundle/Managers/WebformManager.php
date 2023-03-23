<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SearchFilter;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\WebformEntity;
use ScommerceBusinessBundle\Entity\WebformFieldEntity;
use ScommerceBusinessBundle\Entity\WebformGroupEntity;
use ScommerceBusinessBundle\Entity\WebformSubmissionEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;

class WebformManager extends AbstractScommerceManager
{
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var DefaultCrmProcessManager */
    protected $crmProcessManager;

    public function initialize()
    {
        parent::initialize();
        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }
    }

    /**
     * @param $id
     * @return |null
     */
    public function getWebformById($id)
    {
        $menuEntityType = $this->entityManager->getEntityTypeByCode("webform");
        return $this->entityManager->getEntityByEntityTypeAndId($menuEntityType, $id);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getWebformSubmissionById($id)
    {
        $menuEntityType = $this->entityManager->getEntityTypeByCode("webform_submission");
        return $this->entityManager->getEntityByEntityTypeAndId($menuEntityType, $id);
    }

    /**
     * @return |null
     */
    public function getWebformFieldTypes()
    {
        $et = $this->entityManager->getEntityTypeByCode("webform_field_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->setConnector("and");

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters);
    }

    /**
     * @param WebformEntity $webform
     * @return |null
     */
    public function prepareBuilderData($webform)
    {
        $data = [];

        $groups = $webform->getGroups() ?? [];

        /** @var WebformGroupEntity $group */
        foreach ($groups as $group) {
            $data[] = [
                "group" => $group,
                "fields" => $group->getFields() ?? [],
            ];
        }

        return $data;
    }

    /**
     * @param $p
     * @return array|null
     * @throws \Exception
     */
    public function saveWebformSubmissionFromPost($p)
    {
        if (empty($p)) {
            return null;
        }

        if (!isset($p["webform"])) {
            return null;
        }

        $session = $this->container->get("session");
        $storeId = $session->get("current_store_id");

        $webformId = $p["webform"];
        unset($p["webform"]);

        /** @var EntityType $fieldEntityType */
        $webformEntityType = $this->entityManager->getEntityTypeByCode("webform");

        /** @var WebformEntity $webform */
        $webform = $this->entityManager->getEntityByEntityTypeAndId($webformEntityType, $webformId);
        $webformName = $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $webform, "name");

        /** @var EntityType $fieldEntityType */
        $fieldEntityType = $this->entityManager->getEntityTypeByCode("webform_field");

        /** @var WebformFieldEntity $errorField */
        $errorField = $this->validateWebform($webform, $p);
        if (!empty($errorField)) {
            throw new \Exception($this->translator->trans('Please enter') . " {$this->getPageUrlExtension->getEntityStoreAttribute($storeId, $errorField, "name")}");
        }

        $data = [];
        foreach ($p as $fieldKey => $fieldValue) {
            if ($fieldKey == "files") {
                // Is saved only to webform submission value
                continue;
            } else {
                $fieldData = explode("-", $fieldKey);
                if (!empty($fieldData) && isset($fieldData[1])) {
                    $fieldId = $fieldData[1];

                    /** @var WebformFieldEntity $field */
                    $field = $this->entityManager->getEntityByEntityTypeAndId($fieldEntityType, $fieldId);

                    $label = $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $field, "name");

                    $data[] = [
                        "field" => $fieldId,
                        "label" => $label,
                        "value" => $fieldValue,
                    ];
                }
            }
        }

        /** @var WebformSubmissionEntity $newSubmission */
        $newSubmission = $this->entityManager->getNewEntityByAttributSetName("webform_submission");

        $newSubmission->setName("Webform submission for: {$webformName}");
        $newSubmission->setContent(json_encode($data));
        $newSubmission->setWebform($webform);

        $this->entityManager->saveEntity($newSubmission);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $ret = [];
        $ret["webform"] = $webform;

        $data = $this->crmProcessManager->afterWebformSubmitted($newSubmission, $p);

        if (empty($data)) {
            return $ret;
        }
        $ret["data"] = $data;

        return $ret;
    }

    /**
     * @param $webform
     * @param $postData
     * @return WebformFieldEntity|null
     */
    public function validateWebform($webform, $postData)
    {
        // Validate fields
        $groups = $webform->getGroups();
        /** @var WebformGroupEntity $group */
        foreach ($groups as $group) {
            $fields = $group->getFields();
            /** @var WebformFieldEntity $field */
            foreach ($fields as $field) {
                $filedId = $field->getId();
                if ($field->getRequired() && empty($postData["field-{$filedId}"])) {
                    return $field;
                }
            }
        }

        return null;
    }
}
