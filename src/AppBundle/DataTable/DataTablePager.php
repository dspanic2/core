<?php

namespace AppBundle\DataTable;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\StringHelper;
use http\Env\Request;
use Sensio\Bundle\FrameworkExtraBundle;

class DataTablePager
{
    private $start;
    private $lenght;
    private $draw;
    private $columnOrder;
    private $sortOrder;
    private $search;
    private $filters;
    private $type;
    private $requestId;
    private $roles;
    private $compositeFilterCollection;
    private $sortFilterCollection;


    /**
     * DataTablePager constructor.
     * @param $start
     * @param $lenght
     * @param $draw
     * @param $columnOrder
     * @param $sortOrder
     * @param $search
     * @param $filters
     * @param $type
     */
    public function __construct($start = null, $lenght = null, $draw = null, $columnOrder = null, $sortOrder = null, $search = null, $filters = null, $type = null, $roles = null)
    {
        if (empty($start)) {
            $this->start = 0;
        }
        if (empty($lenght)) {
            $this->lenght = 1000;
        }
        if (empty($draw)) {
            $this->draw = 1;
        }
        if (empty($columnOrder)) {
            $this->columnOrder = "id";
        }
        if (empty($sortOrder)) {
            $this->sortOrder = "asc";
        }
        if (empty($search)) {
            $this->search = null;
        }
        if (empty($roles)) {
            $this->roles = null;
        }
        $this->compositeFilterCollection = new CompositeFilterCollection();
        $this->sortFilterCollection = new SortFilterCollection();
    }

    public function getStart()
    {
        return $this->start;
    }

    public function setStart($start)
    {
        $this->start = $start;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getDraw()
    {
        return $this->draw;
    }

    public function setDraw($draw)
    {
        $this->draw = $draw;
    }

    public function getLenght()
    {
        return $this->lenght;
    }

    public function setLenght($lenght)
    {
        $this->lenght = $lenght;
    }

    public function getColumnOrder()
    {
        return $this->columnOrder;
    }

    public function setColumnOrder($columnOrder)
    {
        if($columnOrder == "actions"){
            $columnOrder = "id";
        }
        $this->columnOrder = $columnOrder;
    }

    public function getSortOrder()
    {
        return $this->sortOrder;
    }

    public function setSortOrder($sortOrder)
    {
        $this->sortOrder = $sortOrder;
    }

    public function getSearch()
    {
        return $this->search;
    }

    public function setSearch($search)
    {
        $this->search = $search;
    }

    public function getFilters()
    {
        return $this->filters;
    }

    public function setFilters($filters)
    {
        $this->filters = $filters;
    }

    /**
     * @return mixed
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * @param mixed $requestId
     */
    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }

    public function addFilter(CompositeFilter $compositeFilter)
    {
        $this->compositeFilterCollection->addCompositeFilter($compositeFilter);
    }

    public function addSortFilter(SortFilter $sortFilter)
    {
        $this->sortFilterCollection->addSortFilter($sortFilter);
    }

    public function getRoles()
    {
        return $this->roles;
    }


    public function setRoles($roles)
    {
        $this->roles = $roles;
    }

    public function setFromPost($p)
    {
        $post = json_decode($p["data"]);


        $sort_column_id = 0;
        if (isset($post->order->value[0]->column)) {
            $sort_column_id = $post->order->value[0]->column;
        } elseif ($post->default_sort_id !== false) {
            $sort_column_id = $post->default_sort_id;
        }

        $this->setColumnOrder($post->columns->value["{$sort_column_id}"]->data);

        $this->setSortOrder("asc");
        if (isset($post->order->value[0]->dir)) {
            $this->setSortOrder($post->order->value[0]->dir);
        } elseif ($post->default_sort_id !== false && isset($post->default_sort_dir)) {
            $this->setSortOrder($post->default_sort_dir);
        }


        $sortFilter = new SortFilter();
        $sortFilter->setField($this->getColumnOrder());
        $sortFilter->setDirection($this->getSortOrder());

        $this->sortFilterCollection->addSortFilter($sortFilter);


        $this->setSearch($post->search->value->value);
        $this->setStart($post->start->value);
        $this->setLenght($post->length->value);
        $this->setDraw($post->draw->value);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");

        $filters = array();
        foreach ($post->columns->value as $field) {
            if (!empty($field->search->value)) {
                if ((strpos($field->search->value, 'yadcf_delim') !== false)) {
                    $dates = explode("-yadcf_delim-", $field->search->value);

                    if ($dates[0] != "") {
                        $filter = new SearchFilter();
                        $filter->setField($field->data);
                        $filter->setOperation('ge');
                        $filter->setValue(date("Y-m-d", strtotime($dates[0])));
                        $compositeFilter->addFilter($filter);
                    }
                    if ($dates[1] != "") {
                        $filter = new SearchFilter();
                        $filter->setField($field->data);
                        $filter->setOperation('le');
                        $filter->setValue(date("Y-m-d", strtotime($dates[1])));
                        $compositeFilter->addFilter($filter);
                    }
                } else {
                    $filter = new SearchFilter();
                    $filter->setField($field->data);
                    $filter->setOperation($field->search_type);
                    $filter->setValue($field->search->value);
                    $compositeFilter->addFilter($filter);
                }
            }
        }

        /**
         * Set advanced search filter
         */
        if (isset($p["custom_data"]) && !empty($p["custom_data"])) {
            $custom_data = json_decode($p["custom_data"]);
            foreach ($custom_data as $field) {
                if (!empty($field->search->value) || ($field->search->value === "0" && !empty($field->search_type))) {
                    $filter = new SearchFilter();
                    $filter->setField($field->data);
                    $filter->setOperation($field->search_type);
                    $filter->setValue($field->search->value);
                    $compositeFilter->addFilter($filter);
                }
            }
        }

        if ($compositeFilter->getFilters() != null) {
            $this->compositeFilterCollection->addCompositeFilter($compositeFilter);
        }

        /**
         * Set quick search filter
         */
        if (isset($p["quick_search_data"]) && !empty($p["quick_search_data"])) {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("or");

            $custom_data = json_decode($p["quick_search_data"]);
            foreach ($custom_data as $field) {
                if (!empty($field->search->value)) {
                    $filter = new SearchFilter();
                    $filter->setField($field->data);
                    $filter->setOperation($field->search_type);
                    $filter->setValue($field->search->value);
                    $compositeFilter->addFilter($filter);
                }
            }

            $this->compositeFilterCollection->addCompositeFilter($compositeFilter);
        }

        //$filters[]=$compositeFilter;
    }

    public function setFromRequest(\Symfony\Component\HttpFoundation\Request $request)
    {
        $post = json_decode($request->get("data"));

        foreach ($post->columns->value as $key => $field){
            if($field->data == "actions"){
                unset($post->columns->value[$key]);

            }
        }

        $sort_column_id = 0;
        if (isset($post->order->value[0]->column)) {
            $sort_column_id = $post->order->value[0]->column;
        } elseif ($post->default_sort_id !== false) {
            $sort_column_id = $post->default_sort_id;
        }

        $this->setColumnOrder($post->columns->value["{$sort_column_id}"]->data);

        $sortType = null;
        if(isset($post->order->value[0]->sort_type)){
            $sortType = $post->order->value[0]->sort_type;
        }

        $this->setSortOrder("asc");
        if (isset($post->order->value[0]->dir)) {
            $this->setSortOrder($post->order->value[0]->dir);
        } elseif ($post->default_sort_id !== false && isset($post->default_sort_dir)) {
            $this->setSortOrder($post->default_sort_dir);
        }

        $sortFilter = new SortFilter();
        $columnOrder = $this->getColumnOrder();

        /** Override for json_store fileds */
        if($sortType == "store"){
            $columnOrder = json_encode(Array($columnOrder,"$.\"{$_ENV["DEFAULT_STORE_ID"]}\""));
        }
        $sortFilter->setField($columnOrder);
        $sortFilter->setDirection($this->getSortOrder());

        $this->sortFilterCollection->addSortFilter($sortFilter);

        $this->setSearch($post->search->value->value);
        $this->setStart($post->start->value);
        $this->setLenght($post->length->value);
        $this->setDraw($post->draw->value);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");

        $filters = array();
        foreach ($post->columns->value as $field) {
            if (!empty($field->search->value)  || ($field->search->value === "0" && !empty($field->search_type))) {
                if ((strpos($field->search->value, 'yadcf_delim') !== false)) {
                    $dates = explode("-yadcf_delim-", $field->search->value);

                    if ($dates[0] != "") {
                        $filter = new SearchFilter();
                        $filter->setField($field->data);
                        $filter->setOperation('ge');
                        $filter->setValue(date("Y-m-d", strtotime($dates[0])));
                        $compositeFilter->addFilter($filter);
                    }
                    if ($dates[1] != "") {
                        $filter = new SearchFilter();
                        $filter->setField($field->data);
                        $filter->setOperation('le');
                        $filter->setValue(date("Y-m-d", strtotime($dates[1])));
                        $compositeFilter->addFilter($filter);
                    }
                } else {
                    $filter = new SearchFilter();
                    $filter->setField($field->data);
                    $filter->setOperation($field->search_type);
                    $filter->setValue($field->search->value);
                    $compositeFilter->addFilter($filter);
                }
            }
        }

        /**
         * Set advanced search filter
         */
        $customData = $request->get("custom_data");
        if (!empty($customData)) {
            $custom_data = json_decode($customData);
            foreach ($custom_data as $field) {
                if (!empty($field->search->value) || ($field->search->value === "0" && !empty($field->search_type))) {
                    $filter = new SearchFilter();
                    $filter->setField($field->data);
                    $filter->setOperation($field->search_type);
                    $filter->setValue($field->search->value);
                    $compositeFilter->addFilter($filter);
                }
            }
        }

        if ($compositeFilter->getFilters() != null) {
            $this->compositeFilterCollection->addCompositeFilter($compositeFilter);
        }

        /**
         * Set quick search filter
         */
        $quickSearchData = $request->get("quick_search_data");
        if (!empty($quickSearchData)) {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("or");

            $custom_data = json_decode($quickSearchData);
            foreach ($custom_data as $field) {
                if (!empty($field->search->value) || ($field->search->value === "0" && !empty($field->search_type))) {
                    $filter = new SearchFilter();
                    $filter->setField($field->data);
                    $filter->setOperation($field->search_type);
                    $filter->setValue($field->search->value);
                    $compositeFilter->addFilter($filter);
                }
            }

            $this->compositeFilterCollection->addCompositeFilter($compositeFilter);
        }

        //$filters[]=$compositeFilter;
    }

    /**
     * @return mixed
     */
    public function getCompositeFilterCollection()
    {
        return $this->compositeFilterCollection;
    }

    /**
     * @return SortFilterCollection
     */
    public function getSortFilterCollection()
    {
        return $this->sortFilterCollection;
    }
}
