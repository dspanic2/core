<?php

namespace AppBundle\QueryBuilders;

use AppBundle\Entity\Attribute;
use AppBundle\Entity\EntityType;
use AppBundle\Helpers\StringHelper;

class AttributeQueryBuilder
{

    public function getDistinctLookupValues($attribute)
    {
        //dump($attribute);die;
        $query = StringHelper::format(
            "SELECT distinct(value),entity_id FROM {0} where attribute_id={1}",
            $attribute->getLookupAttribute()->getBackendTable(),
            $attribute->getLookupAttribute()->getId()
        );

        return $query;
    }

    public function getGroupsForPrincipalLookupValues($prinicpal)
    {

        if ($prinicpal == "-1") {
            $query = StringHelper::format("SELECT ev.entity_id,ev.value FROM entity_varchar ev
                                        JOIN entity_int ei ON ei.entity_id=ev.entity_id AND ev.entity_type_id=27
                                        WHERE ei.entity_type_id=27;");
        } else {
            $query = StringHelper::format("SELECT ev.entity_id,ev.value FROM entity_varchar ev
                                        JOIN entity_int ei ON ei.entity_id=ev.entity_id AND ev.entity_type_id=27
                                        WHERE ei.entity_type_id=27 AND ei.value=(SELECT entity_id FROM entity_varchar where entity_type_id=26 and `value`='{0}');", $prinicpal);
        }

        return $query;
    }
}
