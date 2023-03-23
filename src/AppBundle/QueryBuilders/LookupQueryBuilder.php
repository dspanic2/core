<?php

namespace AppBundle\QueryBuilders;

use AppBundle\Entity\Attribute;
use AppBundle\Helpers\StringHelper;

class LookupQueryBuilder
{
    public function getLookupValuesQuery(Attribute $attribute)
    {
        $lookupAttribute=$attribute->getLookupAttribute();

        $query=StringHelper::format("SELECT id, {1} as value from {0} ", $lookupAttribute->getBackendTable(), $lookupAttribute->getAttributeCode());

        return $query;
    }

    public function getRelatedLookupValuesQuery(Attribute $attribute, Attribute $related, $value)
    {
        //$lookupAttribute=$attribute->getLookupAttribute();
        $realtedLookupAttribute=$related->getLookupAttribute();

        $query=StringHelper::format(
            "SELECT ev.entity_id as id,ev.`value` FROM entity_varchar ev
                JOIN entity_int ei ON ei.entity_id=ev.entity_id
                WHERE ev.entity_type_id={0}  AND ei.`value`={1} AND ev.attribute_id={2}",
            $realtedLookupAttribute->getEntityType()->getId(),
            $value,
            $realtedLookupAttribute->getId()
        );

        return $query;
    }
}
