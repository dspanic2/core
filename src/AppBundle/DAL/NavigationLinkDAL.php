<?php

namespace AppBundle\DAL;

use AppBundle\Entity\Page;
use AppBundle\Helpers\UUIDHelper;
use Doctrine\ORM\EntityRepository;

class NavigationLinkDAL extends CoreDataAccess
{
    const ALIAS = 'navigation_link';

    public function save($item)
    {
        $parentUrl = $item->getParent() != null ? $item->getParent()->getUrl() : "";
        $pageUrl = $item->getPage() != null ? $item->getPage()->getUrl() : "";
        $pageType = $item->getPage() != null ? $item->getPage()->getType() : "";
        $pageBundle = $item->getPage() != null ? $item->getPage()->getBundle() : "AppBundle";

        $item->setBundle($pageBundle);

        if ($item->getUid() == null) {
            $item->setUid(UUIDHelper::generateUUID());
        }
        return parent::save($item);
    }

    public function getNavigationParents()
    {
        return $this->findBy(self::ALIAS,array("isParent" => 1, "parent" => null), array('order' => 'ASC'));
    }

    public function getNavigationLinksByPage(Page $page)
    {
        return $this->findBy(self::ALIAS,array('page' => $page), array('id'=>'ASC'));
    }
}
