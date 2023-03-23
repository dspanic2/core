<?php

namespace AppBundle\QueryBuilders;

use AppBundle\DataTable\DataTablePager;
use AppBundle\DataTable\SearchFilterHelper;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\ListView;
use AppBundle\Entity\ListViewAttribute;
use AppBundle\Helpers\StringHelper;

/**OBSOLETE*/
class ListViewQueryBuilder
{
/**
    public function getListViewQuery(ListView $listView, DataTablePager $pager, $entityAttributes)
    {

        if ($pager->getSortOrder()) {
            $order = StringHelper::format("ORDER BY {0} {1}", $pager->getColumnOrder(), $pager->getSortOrder());
        } else {
            $order = StringHelper::format("ORDER BY t0.id ASC", $pager->getColumnOrder(), $pager->getSortOrder());
        }

        if ($pager->getLenght()) {
            $limit = StringHelper::format("LIMIT {0},{1}", $pager->getStart(), $pager->getLenght());
        } else {
            $limit = StringHelper::format("LIMIT 0,100", $pager->getStart(), $pager->getLenght());
        }

        $select = "";
        $where = "";

        $join = " LEFT JOIN attribute_set ta on ta.id=t0.attribute_set_id ";

        $attr_array = array();

        foreach ($listView->getListViewAttributes() as $viewAttribute) {

            $attribute = $viewAttribute->getAttribute();

            if ($attribute->getBackendType() == "lookup") {
                $select = StringHelper::format("{0}, t{1}.{2} as {3}",
                    $select, $attribute->getId(), $viewAttribute->getField(),
                    $attribute->getLookupEntityType()->getEntityTypeCode() . "_" . $viewAttribute->getField());

                if (strpos($join, $attribute->getLookupAttribute()->getBackendTable()) == false)
                    $join = StringHelper::format("{0} LEFT JOIN {1} t{2} on t{2}.id=t0.{3} ",
                        $join, $attribute->getLookupAttribute()->getBackendTable(), $attribute->getId(), $attribute->getAttributeCode());

            }
            elseif ($attribute->getBackendType() == "option") {
                $select = StringHelper::format("{0}, t{1}.value as {2}",
                    $select, $attribute->getId(), $attribute->getAttributeCode());
                $join = StringHelper::format(" {0} LEFT JOIN (SELECT i.id,a.value FROM {3} i LEFT JOIN attribute_option_value a ON a.attribute_id={1} and a.`option`=i.{2}
                                               )t{1} ON t{1}.id=t0.id  ",
                    $join, $attribute->getId(), $attribute->getAttributeCode(), $attribute->getBackendTable());
            }else
                $select = StringHelper::format("{0}, t0.{1}", $select, $attribute->getAttributeCode());
        }

        $where = $listView->getFilter();
        $join = StringHelper::format("{0} {1}", $join, "LEFT JOIN entity_link l on l.entity_id=t0.id and l.entity_type_id=t0.entity_type_id ");

        if ($pager->getFilters()) {


            //$where .= " AND ( ";

            foreach ($pager->getFilters() as $key => $filter) {
                if ($key == 0) {
                    $where = StringHelper::format("{0} {1}  ", $where, $filter->getSqlOperation());
                } else {
                    $where = StringHelper::format("{0} {1} {2}  ", $where, $filter->getConnector(), $filter->getSqlOperation());
                }
            }

        }

        if ($where != "")
            $where = StringHelper::format("WHERE {1} ", $listView->getEntityType()->getId(), $where);

        $standard_where = StringHelper::format("WHERE t0.entity_type_id={0} AND t0.entity_state_id=1 ", $listView->getEntityType()->getId());

        $select_add = "t0.id,";
        if(strpos($select,"t0.id")){
            $select_add = "";
        }
        $select = ltrim($select, ",");

        $query = StringHelper::format("SELECT * FROM ( SELECT {0}ta.attribute_set_code, {1} FROM {2} t0 {3} {7})t {4}  {5} {6}", $select_add,
            $select, $listView->getEntityType()->getEntityTable(), $join, $where, $order, $limit, $standard_where);

        // dump(preg_replace( "/\r|\n/", "", $query ));die;
        return $query;
    }

    public function getListViewFilteredCount(ListView $listView, DataTablePager $pager, $entityAttributes)
    {
        if ($pager->getFilters()) {
            $limit = StringHelper::format("LIMIT {0},{1}", $pager->getStart(), $pager->getLenght());
            $order = StringHelper::format("ORDER BY {0} {1}", $pager->getColumnOrder(), $pager->getSortOrder());
        } else {
            $limit = StringHelper::format("LIMIT 0,100", $pager->getStart(), $pager->getLenght());
            $order = StringHelper::format("ORDER BY t0.id desc", $pager->getColumnOrder(), $pager->getSortOrder());
        }

        $select = "";

        $where = "";

        $join = "";

        $attr_array = array();

        foreach ($entityAttributes as $entityAttribute) {

            $attribute = $entityAttribute->getAttribute();
            if ($pager->getFilters()) {
                foreach ($pager->getFilters() as $filter) {
                    if ($filter->getField() == $attribute->getAttributeCode()) {
                        if ($attribute->getBackendType() == "option")
                            $filter->setField(StringHelper::format("t{0}.value", $attribute->getId()));
                    }
                }
            }

            if ($attribute->getBackendType() == "lookup") {
                $select = StringHelper::format("{0}, t{1}.value as {2}",
                    $select, $attribute->getId(), $attribute->getAttributeCode());

                $join = StringHelper::format("{0} LEFT JOIN (SELECT l.id,l.{2} as value FROM {3} l)t{1} on t{1}.id=t0.{4} ",
                    $join, $attribute->getId(), $attribute->getLookupAttribute()->getAttributeCode(), $attribute->getLookupAttribute()->getBackendTable(), $attribute->getAttributeCode());
            } else
                $select = StringHelper::format("{0}, t0.{1}", $select, $attribute->getAttributeCode());

        }

        $where = $listView->getFilter();
        $join = StringHelper::format("{0} {1}", $join, "LEFT JOIN entity_link l on l.entity_id=t0.id and l.entity_type_id=t0.entity_type_id ");

        if ($pager->getFilters()) {
            foreach ($pager->getFilters() as $filter) {
                $where = StringHelper::format("{0} AND {1}  ", $where, $filter->getSqlOperation());
            }
        }

        $where = $listView->getFilter();

        if ($where != "")
            //    $where = StringHelper::format("WHERE {0} ", $where);
            $where = StringHelper::format("WHERE  {0} ", $where);



        $select = ltrim($select, ",");
        $query = StringHelper::format("SELECT  COUNT(*) as count FROM {1} t0 {2}  {3}  ",
            $select, $listView->getEntityType()->getEntityTable(), $join, $where);


        return $query;

    }

    public function getListViewTotalCount(ListView $listView)
    {
        $where = StringHelper::format("WHERE t0.entity_type_id={0} ", $listView->getEntityType()->getId());

        $query = StringHelper::format("SELECT COUNT(*) as count FROM {0} t0 {1}", $listView->getEntityType()->getEntityTable(), $where);
        return $query;
    }*/
}
