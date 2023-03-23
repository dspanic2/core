<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\SearchFilter;
use AppBundle\Factory\FactoryContext;
use AppBundle\Managers\EntityManager;

class WorkingHoursBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return ('AppBundle:Block:'.$this->pageBlock->getType().'.html.twig');
    }

    public function GetPageBlockData()
    {
        return $this->pageBlockData;
    }
}
