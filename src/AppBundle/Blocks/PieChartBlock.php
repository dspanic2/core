<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Abstracts\AbstractChartBlock;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\SearchFilter;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\EntityManager;
use Doctrine\DBAL\Connection;

class PieChartBlock extends AbstractChartBlock
{

}
