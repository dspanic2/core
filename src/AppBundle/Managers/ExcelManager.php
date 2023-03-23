<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\Attribute;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\NumberHelper;
use Doctrine\ORM\PersistentCollection;

class ExcelManager extends AbstractBaseManager
{
    protected $objReader;
    /** @var array $excelStyles */
    protected $excelStyles;
    /** @var string $webPath */
    protected $webPath;
    /** @var string $backendUrl */
    protected $backendUrl;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    public function initialize()
    {
        parent::initialize();
        $this->objReader = $this->getContainer()->get("phpexcel");
        $this->backendUrl = $_ENV["BACKEND_URL"];
        $this->webPath = $_ENV["WEB_PATH"];

        $this->excelStyles = array(
            "bold" => array(
                "font" => array(
                    "bold" => true
                )
            ),
            "borders" => array(
                "borders" => array(
                    "allborders" => array(
                        "style" => \PHPExcel_Style_Border::BORDER_THIN
                    )
                )
            )
        );
    }

    /**
     * @return array
     */
    public function generateAlphasList()
    {
        $alphas_base = range('A', 'Z');
        $alphas_list = $alphas_base;
        foreach ($alphas_base as $prefix) {
            foreach ($alphas_base as $suffix) {
                $alphas_list[] = $prefix . $suffix;
            }
        }

        return $alphas_list;
    }

    /**
     * @param $fileLocation
     * @param $matched
     * @param int $firstDataRow
     * @param bool $parseSemicolon
     * @return array
     */
    public function getEntitiesFromTable($fileLocation, $matched, $firstDataRow = 2, $parseSemicolon = true)
    {
        $objReader = $this->objReader->createPHPExcelObject($fileLocation);
        $objWorksheet = $objReader->getActiveSheet();

        $headerRow = null;

        $entities = array();
        foreach ($objWorksheet->getRowIterator() as $key1 => $row) {
            if ($key1 == 1) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $key2 => $cell) {
                    $headerRow[$key2] = $cell->getValue();
                }
            }
            if ($key1 <= $firstDataRow) {
                continue;
            }

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $d = array();
            $i = 0;

            foreach ($cellIterator as $key2 => $cell) {
                if ($cell->isFormula()) {
                    $value = $cell->getCalculatedValue();
                } else {
                    $value = $cell->getValue();
                }

                if (\PHPExcel_Shared_Date::isDateTime($cell)) {
                    $value = \PHPExcel_Style_NumberFormat::toFormattedString($value, "yyyy-mm-dd hh:mm:ss");
                }

                if (strpos($value, '_x000D_') !== false) {
                    $value = str_replace('_x000D_', '', $value);
                }

                if ($value !== false && $value === "0") {
                    $value = trim($value);
                }

                if ($value === true) {
                    $value = 1;
                } elseif ($value === false) {
                    $value = 0;
                }

                if ($parseSemicolon) {
                    if (stripos($headerRow[$key2], ":") !== false) {
                        $tmp = explode(":", $headerRow[$key2]);
                        $d[$matched[$i]][$tmp[2]] = $value;
                    } else {
                        $d[$matched[$i]] = $value;
                    }
                } else {
                    $d[$matched[$i]] = $value;
                }

                $i++;

                /**
                 * Izbacuje grešku ako sva match-ana polja koja se koriste u import-u nisu jedan iza drugog
                 */
                if ($i > count($matched) - 1) {
                    break;
                }
            }

            if (array_filter($d)) {
                $entities[] = $d;
            }
        }

        return $entities;
    }

    /**
     * @param $fileLocation
     * @return array
     */
    public function getHeadersFromTable($fileLocation)
    {
        $objWorksheet = $this->objReader->createPHPExcelObject($fileLocation)->getActiveSheet();

        $headers = array();

        foreach ($objWorksheet->getRowIterator() as $key1 => $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $d = array();
            foreach ($cellIterator as $key2 => $cell) {
                if ($cell->isFormula()) {
                    $value = $cell->getCalculatedValue();
                } else {
                    $value = $cell->getValue();
                }
                if (strpos($value, '_x000D_') !== false) {
                    $value = str_replace('_x000D_', '', $value);
                }
                $d[] = $value;
            }
            $headers[$key1 - 1] = $d;

            if (count($headers) >= 2) {
                break;
            }
        }

        return $headers;
    }

    /**
     * Export an array of column arrays to Excel file. Example:
     *
     * $data = Array(
     *      "column1" => Array(
     *          "row1",
     *          "row2"
     *      ),
     *      "column2" => Array(
     *          "row1",
     *          "row2"
     *      )
     * );
     *
     * @param array $data
     * @param $title
     * @param null $folder
     * @param false $drawBorders
     * @param false $boldTitle
     * @param false $autoSize
     * @param null $colNames
     * @param bool $addHash
     * @return string
     */
    public function exportArray(array $data,
                                      $title,
                                      $folder = null,
                                      $drawBorders = false,
                                      $boldTitle = false,
                                      $autoSize = false,
                                      $colNames = null,
                                      $addHash = true)
    {
        if (empty($folder)) {
            $folder = "/Documents/export/";
        }

        if (!file_exists($this->webPath . $folder)) {
            mkdir($this->webPath . $folder, 0777, true);
        }

        $title = substr($title, 0, 30);
        $alphas = $this->generateAlphasList();

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->getContainer()->get("cache_manager");
        }
        $decimalFormat = $_ENV["DECIMAL_FORMAT"] ?? null;
        $decimalFormat = json_decode($decimalFormat, true);

        $phpExcelObject = $this->objReader->createPHPExcelObject();
        $phpExcelObject->getProperties()
            ->setCreator("shape")
            ->setLastModifiedBy("shape")
            ->setTitle($title);

        $phpExcelObject->setActiveSheetIndex(0);
        $phpExcelObject->getActiveSheet()
            ->setTitle($title);

        $colId = $rowId = 0;
        foreach ($data as $colName => $col) {

            $phpExcelObject->getActiveSheet()->setCellValue($alphas[$colId] . "1", $colName);
            if (!empty($autoSize)) {
                $phpExcelObject->getActiveSheet()->getColumnDimension($alphas[$colId])->setAutoSize(true);
            }
            $rowId = 2;
            foreach ($col as $value) {
                if (is_numeric($value)) {
                    if (is_float($value + 0)) {
                        $decimalFormatCustom = $_ENV["DECIMAL_FORMAT{$colName}"] ?? null;
                        if(!empty($decimalFormatCustom)){
                            $decimalFormat = json_decode($decimalFormatCustom, true);
                        }
                        if (!empty($decimalFormat)) {
                            $value = number_format($value, $decimalFormat["decimals"], $decimalFormat["dec_point"], $decimalFormat["thousands_sep"]);
                        }
                    } else {
                        $phpExcelObject->getActiveSheet()
                            ->getStyle($alphas[$colId] . $rowId)
                            ->getNumberFormat()
                            ->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_NUMBER);
                    }
                } else if ($value instanceof \DateTime) {
                    $value = $value->format("Y-m-d H:i:s");
                }
                if(is_array($value)){
                    $value = json_encode($value);
                }
                $value = (string)$value;
                $phpExcelObject->getActiveSheet()->setCellValue($alphas[$colId] . $rowId, $value);
                $rowId++;
            }
            $colId++;
        }

        if (!empty($boldTitle)) {
            $phpExcelObject->getActiveSheet()->getStyle("A1:" . $alphas[$colId] . "1")->applyFromArray($this->excelStyles["bold"]);
        }
        if (!empty($drawBorders)) {
            $phpExcelObject->getActiveSheet()->getStyle("A1:" . $alphas[$colId - 1] . ($rowId - 1))->applyFromArray($this->excelStyles["borders"]);
        }

        if ($addHash) {
            $title .= "_" . sha1(time());
        }
        $filename = $folder . strtolower($title) . ".xlsx";

        $writer = $this->objReader->createWriter($phpExcelObject, "Excel2007");
        $writer->save($this->webPath . $filename);

        return $filename;
    }

    /**
     * Export an entity array to Excel file. Example:
     *
     * $data = Array(
     *      "entity1" => Array(
     *          "attribute1" => "value1",
     *          "attribute2" => "value2"
     *      ),
     *      "entity2" => Array(
     *          "attribute1" => "value1",
     *          "attribute2" => "value2"
     *      )
     * );
     *
     * @param array $data
     * @param $title
     * @param null $folder
     * @param false $autoSize
     * @param false $boldTitle
     * @param false $drawBorders
     * @param bool $addHash
     * @return string
     */
    public function exportEntityArray(array $data,
                                            $title,
                                            $folder = null,
                                            $autoSize = false,
                                            $boldTitle = false,
                                            $drawBorders = false,
                                            $addHash = true)
    {
        if (empty($folder)) {
            $folder = "/Documents/export/";
        }

        if (!file_exists($this->webPath . $folder)) {
            mkdir($this->webPath . $folder, 0777, true);
        }

        $title = substr($title, 0, 30);

        $phpExcelObject = $this->objReader->createPHPExcelObject();
        $phpExcelObject->getProperties()
            ->setCreator("shape")
            ->setLastModifiedBy("shape")
            ->setTitle($title);

        $phpExcelObject->setActiveSheetIndex(0);
        $phpExcelObject->getActiveSheet()
            ->setTitle($title);

        $alphas = $this->generateAlphasList();
        $keysAlphas = [];

        $rowId = 1;
        foreach ($data as $d) {
            foreach ($d as $key => $value) {
                if (!isset($keysAlphas[$key])) {
                    $keysAlphas[$key] = $alphas[count($keysAlphas)];
                }
                if ($autoSize) {
                    $phpExcelObject->getActiveSheet()
                        ->getColumnDimension($keysAlphas[$key])
                        ->setAutoSize(true);
                }
                if (is_numeric($value)) {
                    if (is_float($value + 0)) {
                        $value = NumberHelper::formatDecimal($value);
                    } else {
                        $phpExcelObject->getActiveSheet()
                            ->getStyle($keysAlphas[$key] . ($rowId + 1))
                            ->getNumberFormat()
                            ->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_NUMBER);
                    }
                }
                $phpExcelObject->getActiveSheet()
                    ->setCellValue($keysAlphas[$key] . "1", $key);
                $phpExcelObject->getActiveSheet()
                    ->setCellValue($keysAlphas[$key] . ($rowId + 1), $value);
            }
            $rowId++;
        }

        if ($boldTitle) {
            $phpExcelObject->getActiveSheet()
                ->getStyle("A1:" . $alphas[count($keysAlphas)] . "1")
                ->applyFromArray($this->excelStyles["bold"]);
        }
        if ($drawBorders) {
            $phpExcelObject->getActiveSheet()
                ->getStyle("A1:" . $alphas[count($keysAlphas) - 1] . $rowId)
                ->applyFromArray($this->excelStyles["borders"]);
        }

        if ($addHash) {
            $title .= "_" . sha1(time());
        }

        $filename = $folder . strtolower($title) . ".xlsx";

        $writer = $this->objReader->createWriter($phpExcelObject, "Excel2007");
        $writer->save($this->webPath . $filename);

        return $filename;
    }

    /**
     * @param $fileLocation
     * @param int $firstDataRow
     * @return array
     */
    public function importEntityArray($fileLocation, $firstDataRow = 2)
    {
        $data = [];

        $phpExcelObject = $this->objReader->createPHPExcelObject($fileLocation);

        foreach ($phpExcelObject->getAllSheets() as $sheet) {

            $headers = [];
            foreach ($sheet->getRowIterator() as $rowKey => $row) {

                $rowValues = [];
                $everyValueIsNull = true;

                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                foreach ($cellIterator as $columnKey => $cell) {
                    if ($cell->isFormula()) {
                        $value = $cell->getCalculatedValue();
                    } else {
                        $value = $cell->getValue();
                    }
                    if ($value instanceof \PHPExcel_RichText) {
                        $value = $value->getPlainText();
                    }
                    if ($value) {
                        $everyValueIsNull = false;
                    }
                    if (!isset($headers[$columnKey])) {
                        $headers[$columnKey] = $value;
                    } else {
                        $rowValues[$headers[$columnKey]] = $value;
                    }
                }

                if ($rowKey < $firstDataRow) {
                    continue;
                }
                if ($everyValueIsNull) {
                    break;
                }

                $data[$sheet->getTitle()][$rowKey] = $rowValues;
            }
        }
        
        return $data;
    }

    /**
     * @param $value
     * @param $getters
     * @param int $i
     * @return mixed|string
     */
    public function getNextEntityAttributeValue($value, $getters, $i = 0)
    {
        if (!empty($value)) {
            if (array_key_exists($i, $getters)) {
                if(!EntityHelper::checkIfMethodExists($value,$getters[$i])){
                    return null;
                }
                $value = $value->{$getters[$i]}();
                if ($value instanceof PersistentCollection || is_array($value)) {
                    $tmp = null;
                    foreach ($value as $v) {
                        $str = $this->getNextEntityAttributeValue($v, $getters, is_array($value) ? ++$i : $i + 1);
                        if (!empty($str)) {
                            if (is_string($str) || is_numeric($str)) {
                                $tmp .= ", " . $str;
                            }
                        }
                    }
                    $value = ltrim($tmp, ", ");
                } else {
                    $value = $this->getNextEntityAttributeValue($value, $getters, ++$i);
                }
            }
        }

        return $value;
    }

    /**
     * @param $attributes
     * @param $entities
     * @param $entityTypeCode
     * @return string
     */
    public function exportTemplate($attributes, $entities, $entityTypeCode)
    {
        $values = array();
        $getters = array();

        foreach ($attributes as $key => $attribute) {
            $attribute = explode("|SS|", $attribute);
            $values[$attribute[0]] = array($attribute[1]);
            $getters[$attribute[0]] = EntityHelper::getPropertyAccessor($attribute[0]);
        }

        if (!empty($entities)) {
            foreach ($entities as $entity) {
                foreach ($getters as $key => $getter) {
                    $values[$key][] = $this->getNextEntityAttributeValue($entity, $getter);
                }
            }
        }

        $filepath = $this->exportArray($values, $entityTypeCode);

        return "{$_ENV["SSL"]}://" . $this->backendUrl . $_ENV["FRONTEND_URL_PORT"] . $filepath;
    }

    /**
     * @param $attributes
     * @param $entityTypeCode
     * @return string
     */
    public function importTemplate($attributes, $entityTypeCode)
    {
        $values = array();

        /** @var Attribute $attribute */
        foreach ($attributes as $attribute) {
            $key = $attribute->getAttributeCode();
            if ($attribute->getFrontendType() == "multiselect") {

                if(empty($attribute->getLookupAttribute()->getLookupAttribute())){
                    continue;
                }
                $key = $key . "." . $attribute->getLookupAttribute()->getLookupAttribute()->getAttributeCode();
            }

            $values[$key][] = $this->translator->trans($attribute->getEntityType()->getEntityTypeCode()) .
                " - " . $this->translator->trans($attribute->getFrontendLabel());
        }

        $filepath = $this->exportArray($values, $entityTypeCode);

        return "{$_ENV["SSL"]}://" . $this->backendUrl . $_ENV["FRONTEND_URL_PORT"] . $filepath;
    }
}
