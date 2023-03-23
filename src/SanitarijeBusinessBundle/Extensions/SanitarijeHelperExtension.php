<?php

namespace SanitarijeBusinessBundle\Extensions;

use SanitarijeBusinessBundle\Managers\SanitarijeHelperManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SanitarijeHelperExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('prepare_category_list_filters', array($this, 'prepareCategoryListFilters')),
            new \Twig_SimpleFunction('get_home_product_groups', array($this, 'getHomeProductGroups')),
            new \Twig_SimpleFunction('get_brand_product_count', array($this, 'getBrandProductCount')),
        ];
    }

    /**
     * @param $data
     * @return array
     */
    public function prepareCategoryListFilters($data)
    {
        $ret = [];
        $topCategory = 0;
        foreach ($data as $category1) {
            if (empty($topCategory)) {
                $topCategory = $category1["level"];
            }
            if ($category1["level"] < $topCategory) {
                $topCategory = $category1["level"];
            }
        }

        foreach ($data as $id1 => $category1) {
            if ($category1["level"] == $topCategory) {
                $ret[$id1] = $category1;

                foreach ($data as $id2 => $category2) {
                    if ($category2["level"] == $topCategory + 1 && $category2["parent_group"] == $id1) {
                        if (!isset($ret[$id1]["items"])) {
                            $ret[$id1]["items"] = [];
                        }
                        $ret[$id1]["items"][$id2] = $category2;

                        foreach ($data as $id3 => $category3) {
                            if ($category3["level"] == $topCategory + 2 && $category3["parent_group"] == $id2) {
                                if (!isset($ret[$id1]["items"][$id2]["items"])) {
                                    $ret[$id1]["items"][$id2]["items"] = [];
                                }
                                $ret[$id1]["items"][$id2]["items"][$id3] = $category3;

                                foreach ($data as $id4 => $category4) {
                                    if ($category4["level"] == 4 && $category4["parent_group"] == $id3) {
                                        if (!isset($ret[$id1]["items"][$id2]["items"][$id3]["items"])) {
                                            $ret[$id1]["items"][$id2]["items"][$id3]["items"] = [];
                                        }
                                        $ret[$id1]["items"][$id2]["items"][$id3]["items"][$id4] = $category4;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
//        dump();die;
        return $ret;
    }

    /**
     * @return array
     */
    public function getHomeProductGroups()
    {
        /** @var SanitarijeHelperManager $helperManager */
        $helperManager = $this->container->get("sanitarije_helper_manager");

        return $helperManager->getPreparedHomeCategories();
    }

    /**
     * @return int
     */
    public function getBrandProductCount($brandId)
    {
        /** @var SanitarijeHelperManager $helperManager */
        $helperManager = $this->container->get("sanitarije_helper_manager");

        return $helperManager->getBrandProductCount($brandId);
    }
}
