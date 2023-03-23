<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SettingsEntity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use ScommerceBusinessBundle\Entity\SStoreEntity;


class ApplicationSettingsManager extends AbstractBaseManager
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $code
     * @return string|null
     */
    public function getRawApplicationSettingEntityByCode($code)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");

        $applicationSettingEntityType = $entityManager->getEntityTypeByCode("settings");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $code));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var SettingsEntity $setting */
        return $entityManager->getEntityByEntityTypeAndFilter($applicationSettingEntityType, $compositeFilters);
    }

    /**
     * @param $code
     * @return string|null
     */
    public function getRawApplicationSettingByCode($code)
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

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var SettingsEntity $setting */
        $setting = $entityManager->getEntityByEntityTypeAndFilter($applicationSettingEntityType, $compositeFilters);

        if (empty($setting)) {
            return null;
        }
        return $setting->getSettingsValue();
    }

    /**
     * @param $code
     * @return string|null
     */
    public function getApplicationSettingByCode($code)
    {
        /** @var CacheManager $cacheManager */
        $cacheManager = $this->container->get("cache_manager");
        $value = $cacheManager->getCacheItem("app_settings.{$code}");

        if (!$value || empty($value)) {
            $value = $this->getRawApplicationSettingByCode($code);
            $cacheManager->setCacheItem("app_settings.{$code}", $value);
        }

        return $value;
    }

    /**
     * @param SettingsEntity|null $entity
     * @param $data
     * @return SettingsEntity|null
     */
    public function createUpdateSettings(SettingsEntity $entity = null, $data){

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        if(empty($entity)){
            $entity = $this->entityManager->getNewEntityByAttributSetName("settings");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        $this->entityManager->saveEntity($entity);
        $this->entityManager->refreshEntity($entity);

        return $entity;
    }

    /**
     * @param $code
     * @param $storeId
     * @return string|null
     */
    public function getApplicationSettingByCodeAndStoreId($code, $storeId)
    {
        /** @var CacheManager $cacheManager */
        $cacheManager = $this->container->get("cache_manager");
        $value = $cacheManager->getCacheItem("app_settings.{$code}");

        if (!$value || empty($value)) {
            $value = $this->getRawApplicationSettingByCode($code);
            $cacheManager->setCacheItem("app_settings.{$code}", $value);
        }

        if (!isset($value[$storeId])) {
            return null;
        }

        return $value[$storeId];
    }

    /**
     * @param $code
     * @return string|null
     */
    public function getApplicationSettingByCodeAndStore($code, SStoreEntity $store)
    {
        /** @var CacheManager $cacheManager */
        $cacheManager = $this->container->get("cache_manager");
        $value = $cacheManager->getCacheItem("app_settings.{$code}");

        if (!$value || empty($value)) {
            $value = $this->getRawApplicationSettingByCode($code);
            $cacheManager->setCacheItem("app_settings.{$code}", $value);
        }

        if (!isset($value[$store->getId()])) {
            return null;
        }

        return $value[$store->getId()];
    }

    /**
     * @param int $storeId
     * @param string $code
     * @param string $newValue
     * @return bool
     */
    public function saveApplicationSettingValueByCode($storeId, $code, $newValue)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");

        $applicationSettingEntityType = $entityManager->getEntityTypeByCode("settings");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $code));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var SettingsEntity $setting */
        $setting = $entityManager->getEntityByEntityTypeAndFilter($applicationSettingEntityType, $compositeFilters);

        $values = $setting->getSettingsValue();
        $values[$storeId] = $newValue;
        $setting->setSettingsValue($values);
        $entityManager->saveEntityWithoutLog($setting);
    }

    /**
     * @param SettingsEntity $setting
     * @param $value
     * @return void
     */
    public function setApplicationSettingValue(SettingsEntity $setting, $value)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");

        $setting->setSettingsValue($value);
        $entityManager->saveEntity($setting);
        $entityManager->refreshEntity($setting);
    }

    /**
     * @param $name
     * @return SettingsEntity|string
     */
    public function addApplicationSetting($name, $code)
    {
        $existing = $this->getApplicationSettingByCode(StringHelper::convertStringToCode($name));
        if (empty($existing)) {
            /** @var EntityManager $entityManager */
            $entityManager = $this->container->get("entity_manager");

            /** @var SettingsEntity $applicationSetting */
            $applicationSetting = $entityManager->getNewEntityByAttributSetName("settings");
            $applicationSetting->setName(json_encode([$_ENV["DEFAULT_STORE_ID"] => $name]));
            $applicationSetting->setCode($code);
            $applicationSetting->setEntityStateId(1);

            $entityManager->saveEntityWithoutLog($applicationSetting);
            $entityManager->refreshEntity($applicationSetting);

            return $applicationSetting;
        }
        return $existing;
    }
}