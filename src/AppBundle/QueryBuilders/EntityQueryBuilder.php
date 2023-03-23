<?php

namespace AppBundle\QueryBuilders;

use AppBundle\DataTable\DataTablePager;
use AppBundle\Helpers\StringHelper;
use AppBundle\Entity\Attribute;

class EntityQueryBuilder
{
    public static function getEntities($attributes, DataTablePager $pager)
    {
        $selectAttributes = "";
        $joinAttributes = "";

        /**@var Attribute $attribute */
        foreach ($attributes as $attribute) {
            $id = $attribute->getId();
            $model = $attribute->getAttributeModel();
            $table = $attribute->getBackendTable();
            $code = $attribute->getAttributeCode();
            $limit = StringHelper::format("LIMIT {0}, {1}", $pager->getStart(), $pager->getLenght());

            $selectAttributes = StringHelper::format(', {0}.`value` as `{1}` {2}', $code, $model, $selectAttributes);
            $joinAttributes = StringHelper::format(' LEFT JOIN {0} {1} ON {1}.entity_id=e.id and {1}.attribute_id={2} {3}', $table, $code, $id, $joinAttributes);
        }

        $filter = EntityQueryBuilder::filterQuery($attributes, $pager->getFilters());

        $statement = StringHelper::format(
            'SELECT * FROM( SELECT e.id {0} FROM `entity` e
                    LEFT JOIN entity_type t on e.entity_type_id=t.id
                    LEFT JOIN attribute a on a.entity_type_id=t.id {1} GROUP BY e.id)s {2} {3}',
            $selectAttributes,
            $joinAttributes,
            $filter,
            $limit
        );

        return $statement;
    }

    public static function filterQuery($attributes, $filters)
    {
        $filterQuery = "";
        if ($filters) {
            $filterQuery = "WHERE ";
            foreach ($filters as $key => $value) {
                /**@var Attribute $attribute */
                foreach ($attributes as $attribute) {
                    if ($attribute->getAttributeModel() == $key) {
                        if ($attribute->getBackendType() == 'text') {
                            $filterQuery = $filterQuery.StringHelper::format(" s.`{0}` like '%{1}%' AND", $key, $value);
                        } elseif ($attribute->getBackendType() == 'int') {
                            $filterQuery = $filterQuery.StringHelper::format(" s.`{0}` ={1} AND", $key, $value);
                        }
                    }
                }
            }
        }

        $filterQuery = str_replace("AND", "", $filterQuery);
        return $filterQuery;
    }
}
