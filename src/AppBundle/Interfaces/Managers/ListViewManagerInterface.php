<?php

namespace AppBundle\Interfaces\Managers;

use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\ListView;
use Symfony\Component\HttpFoundation\Request;

interface ListViewManagerInterface
{

    public function getListViewModel($view_id);

    public function getListViewDataModel(ListView $view, DataTablePager $pager);

    public function getEntityType($typeName);

    public function getTemplate($typeName, $viewName);
}
