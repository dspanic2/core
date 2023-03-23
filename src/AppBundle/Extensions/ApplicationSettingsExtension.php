<?php

namespace AppBundle\Extensions;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SettingsEntity;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ApplicationSettingsExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var ApplicationSettingsManager $applicationSettingsManager */
    protected $applicationSettingsManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_application_setting', array($this, 'getSettingByKey')),
            new \Twig_SimpleFunction('get_application_setting_file', array($this, 'getSettingFile')),
        ];
    }

    /**
     * @param $key
     * @return bool|string|null
     */
    public function getSettingByKey($key)
    {
        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->container->get("application_settings_manager");
        }

        /** @var Session $session */
        $session = $this->container->get("session");
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $value = $this->applicationSettingsManager->getApplicationSettingByCode($key);

        if (is_array($value)) {
            $value = $value[$storeId] ?? null;
        }

        return $value;
    }

    /**
     * @param $key
     * @return bool|string|null
     */
    public function getSettingFile($code)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");

        /** @var Session $session */
        $session = $this->container->get("session");
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $applicationSettingEntityType = $entityManager->getEntityTypeByCode("settings");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $code));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));
        $compositeFilter->addFilter(new SearchFilter("file", "nn"));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var SettingsEntity $setting */
        $setting = $entityManager->getEntityByEntityTypeAndFilter($applicationSettingEntityType, $compositeFilters);

        if (empty($setting)) {
            return null;
        }
        return $setting->getFile();
    }
}
