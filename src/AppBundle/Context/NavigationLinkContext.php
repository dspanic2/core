<?php

namespace AppBundle\Context;

use AppBundle\Entity\Page;

class NavigationLinkContext extends CoreContext
{
    public function getNavigationParents()
    {
        return $this->dataAccess->getNavigationParents();
    }

    public function getNavigationLinksByPage(Page $page)
    {
        return $this->dataAccess->getNavigationLinksByPage($page);
    }
}
