<?php

namespace AppBundle\Context;

use AppBundle\DAL\EntityDataAccess;

class EntityContext extends CoreContext
{
    /** @var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;

    public function __construct(
        EntityDataAccess $dataAccess,
        EntityTypeContext $entityTypeContext,
        AttributeContext $attributeContext
    ) {
        $this->entityTypeContext = $entityTypeContext;
        $this->attributeContext = $attributeContext;
        $this->dataAccess = $dataAccess;
    }

    public function getEntitiesWithPaging($entityTypeName, $pager)
    {
        $entityType = $this->entityTypeContext->getItemByCode($entityTypeName);
        $attributes = $this->attributeContext->getAttributesByEntityType($entityType);

        return $this->dataAccess->getEntitiesWithPaging($entityType, $attributes, $pager);
    }
}
