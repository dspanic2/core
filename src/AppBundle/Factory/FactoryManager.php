<?php

namespace AppBundle\Factory;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Helpers\StringHelper;
use AppBundle\Interfaces\Managers\FormManagerInterface;
use AppBundle\Interfaces\Managers\ListViewManagerInterface;
use Symfony\Component\DependencyInjection\Container;
use AppBundle\ManagerExtensions;
use AppBundle\Managers;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FactoryManager extends AbstractBaseManager
{
    public function loadFormManager($name)
    {

        if ($this->container->has($name."_manager")) {
            $extension = $this->container->get($name."_manager");

            if ($extension instanceof FormManagerInterface) {
                return $extension;
            } else {
                return $this->container->get("form_manager");
            }
                //throw(new \Exception(StringHelper::format("FactoryManager: Class {0} does not implement FormExtensionInterface ", get_class($extension))));
        } else {
            return $this->container->get("form_manager");
        }
    }

    public function loadListViewManager($name = null)
    {
        if (isset($name) && $this->container->has($name)) {
            $extension = $this->container->get($name);

            if ($extension instanceof ListViewManagerInterface) {
                return $extension;
            } else {
                throw(new \Exception(StringHelper::format("FactoryManager: Class {0} does not implement ListViewManagerInterface ", get_class($extension))));
            }
        } else {
            return $this->container->get("list_view_manager");
        }
    }
}
