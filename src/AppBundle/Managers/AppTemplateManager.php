<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Helpers\EntityHelper;
use ScommerceBusinessBundle\Entity\SWebsiteEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\Routing\Route;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use function GuzzleHttp\Psr7\parse_request;

class AppTemplateManager extends AbstractBaseManager
{
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;


    public function initialize()
    {
        parent::initialize();
        $this->cacheManager = $this->container->get("cache_manager");
    }

    /**
     * @param $template
     * @param int $websiteId
     * @return string
     */
    public function getTemplatePathByBundle($template, $websiteId = 1, $blockId = null)
    {
        if (empty($websiteId)) {
            $websiteId = 1;
        }

        $templateById = null;
        if (!empty($blockId)) {
            $templateById = str_ireplace(":", "/Id:", $template);
            $templateById = str_ireplace(".html.twig", "_{$blockId}.html.twig", $templateById);
        }

        $bundles = $this->getTemplateBundles($websiteId);
        foreach ($bundles as $bundle) {
            if (!empty($templateById) && $this->container->get('templating')->exists($bundle . ":" . $templateById)) {
                return $bundle . ":" . $templateById;
            }
            if ($this->container->get('templating')->exists($bundle . ":" . $template)) {
                return $bundle . ":" . $template;
            }
        }
        throw new NotFoundResourceException("Template {$template} was not found. Maybe missing a bundle in commerce_template_bundles setting?");
    }

    /**
     * @param int $websiteId
     * @return array
     */
    public function getTemplateBundles($websiteId = 1)
    {
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $websites = array();

        $cacheItem = $this->cacheManager->getCacheGetItem("websites_commerce_template_bundles");

        if (empty($cacheItem) || isset($_GET["rebuild_websites"])) {

            $commerceTemplateBundles = $this->cacheManager->getCacheItem("settings.commerce_template_bundles");
            if (!empty($commerceTemplateBundles)) {
                $websites = $this->cacheManager->setCacheItem("websites_commerce_template_bundles", array(1 => $commerceTemplateBundles));
            } else {
                if (empty($this->routeManager)) {
                    $this->routeManager = $this->container->get("route_manager");
                }

                $websiteEntities = $this->routeManager->getWebsites();

                if (!EntityHelper::isCountable($websiteEntities) || count($websiteEntities) == 0) {
                    throw new \InvalidArgumentException('"commerce_template_bundles" websites missing in database!');
                }

                /** @var SWebsiteEntity $websiteEntity */
                foreach ($websiteEntities as $websiteEntity) {

                    if (!EntityHelper::checkIfMethodExists($websiteEntity, "getCommerceTemplateBundles")) {
                        throw new \InvalidArgumentException('"commerce_template_bundles" website entity missing getCommerceTemplateBundles!');
                    }

                    $websites[$websiteEntity->getId()] = $websiteEntity->getCommerceTemplateBundles();
                }

                $this->cacheManager->setCacheItem("websites_commerce_template_bundles", $websites);
            }
        } else {
            $websites = $cacheItem->get();
        }

        if (!isset($websites[$websiteId]) || empty($websites[$websiteId])) {
            throw new \InvalidArgumentException('"commerce_template_bundles" setting is missing in database!');
        }

        return explode(",", $websites[$websiteId]);
    }
}
