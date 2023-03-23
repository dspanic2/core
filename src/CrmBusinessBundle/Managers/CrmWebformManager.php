<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ExcelManager;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;

class CrmWebformManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var ExcelManager $excelManager */
    protected $excelManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->databaseContext = $this->container->get("database_context");
        $this->excelManager = $this->container->get("excel_manager");
        $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
    }

    public function exportWebformSubmissions($webformId, $storeId = null)
    {
        $q = "
SELECT
	w.id AS webform_id,
	wsv.submission_id AS submission_id,
	wf.name AS field_name,
	wf.id AS field_id,
	wft.field_type_code AS field_code,
	wf.entity_type_code_id AS related_entity_code,
	wsv.submission_value AS submission_value
FROM
	webform_submission_value_entity AS wsv
	JOIN webform_submission_entity AS ws ON wsv.submission_id = ws.id
	JOIN webform_entity AS w ON w.id = ws.webform_id
	JOIN webform_field_entity AS wf ON wsv.field_id = wf.id
	JOIN webform_field_type_entity AS wft ON wft.id = wf.webform_field_type_id
WHERE w.id = {$webformId};
";
        $res = $this->databaseContext->getAll($q);

        if (!empty($res)) {
            if (empty($storeId)) {
                $storeId = $_ENV["DEFAULT_STORE_ID"];
            }

            foreach ($res as $key => $row) {
                $name = json_decode($row["field_name"], true);
                $res[$key]["field_name"] = $name[$storeId] ?? "";

                if ($row["field_code"] == "autocomplete" && !empty($row["related_entity_code"]) && !empty($row["submission_value"])) {
                    $entity = $this->entityManager->getEntityByEntityTypeCodeAndId($row["related_entity_code"], $row["submission_value"]);
                    if (EntityHelper::checkIfMethodExists($entity, "getName")) {
                        $res[$key]["submission_value"] = $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $entity, "name");
                    }
                } elseif ($row["field_code"] == "file" && !empty($row["submission_value"])) {
                    $res[$key]["submission_value"] = "{$_ENV["SSL"]}://{$_ENV["BACKEND_URL"]}/Documents/webform_submission_files/{$row["webform_id"]}/{$row["submission_id"]}/{$row["submission_value"]}";
                } elseif ($row["field_code"] == "html") {
                    unset($res[$key]);
                }
            }
        }

        $fieldColumns = [];
        foreach ($res as $row) {
            $fieldColumns[] = $row["field_name"];
        }
        $fieldColumns = array_unique($fieldColumns);

        $submissions = [];
        foreach ($res as $row) {
            if (!isset($submissions[$row["submission_id"]])) {
                $submissions[$row["submission_id"]] = [];
            }
            $submissions[$row["submission_id"]][$row["field_name"]] = $row["submission_value"];
        }

        $data = [];
        $data["Submission ID"] = [];
        foreach ($submissions as $id => $submissionValue) {
            $data["Submission ID"][] = $id;
            foreach($fieldColumns as $fieldColumn){
                $data[$fieldColumn][] = $submissionValue[$fieldColumn] ?? "";
            }
        }

        return $this->excelManager->exportArray($data, "webform_{$webformId}_submissions", null, false, false, true);
    }

}
