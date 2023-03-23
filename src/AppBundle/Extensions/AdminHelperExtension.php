<?php

namespace AppBundle\Extensions;

use AppBundle\Abstracts\AbstractBaseButtons;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\PageManager;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AdminHelperExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;
    protected $translator;
    /** @var GetPageUrlExtension */
    protected $getPageUrlExtension;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->translator = $this->container->get("translator");
        $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_entity_from_url', array($this, 'getEntityFromUrl')),
            new \Twig_SimpleFunction('generate_admin_title', array($this, 'generateAdminTitle')),
            new \Twig_SimpleFunction('reorder_admin_menu', array($this, 'reorderAdminMenu')),
            new \Twig_SimpleFunction('get_page_buttons', array($this, 'getPageButtons')),
        ];
    }

    /**
     * @param array $items
     * @return string
     */
    public function reorderAdminMenu($items)
    {
        return $this->reorder($items);
    }

    /**
     * Dohvaca buttone za bilo koji page u adminu
     */
    public function getPageButtons($data)
    {

        $attributeSetCode = null;
        if (isset($data["page"]) && !empty($data["page"])) {
            if (is_object($data["page"])) {
                $attributeSetCode = $data["page"]->getAttributeSet()->getAttributeSetCode();
            } elseif (isset($data["block"]) && !empty($data["block"])) {
                $attributeSetCode = $data["block"]->getAttributeSet()->getAttributeSetCode();
            }
        }

        if (!empty($attributeSetCode) && $this->container->has($attributeSetCode . "_buttons")) {
            /**@var AbstractBaseButtons $block */
            $buttonService = $this->container->get($attributeSetCode . "_buttons");
        } else {
            $buttonService = $this->container->get("default_buttons");
        }

        $buttonService->setData($data);
        if (isset($data["page"]) && !empty($data["page"]) && is_object($data["page"])) {
            $buttonService->setPage($data["page"]);
        }

        return $buttonService->getButtons();
    }

    private function reorder($items, $orderAlphabetically = false)
    {
        if ($orderAlphabetically) {
            usort($items, function ($a, $b) {
                return strcmp($this->translator->trans($a->displayName), $this->translator->trans($b->displayName));
            });
        }
        foreach ($items as $key => $item) {
            if (isset($item->children)) {
                $items[$key]->children = $this->reorder($item->children, true);
            }
        }

        return $items;
    }

    /**
     * @param $url
     * @return null
     */
    public function getEntityFromUrl($url)
    {
        $pieces = explode("/", $url);

        $type = $pieces[count($pieces) - 3];
        $id = $pieces[count($pieces) - 1];

        if (empty($type) || empty($id) || !intval($id)) {
            return null;
        }

        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");

        $entity = $entityManager->getEntityByEntityTypeCodeAndId($type, $id);
        if (empty($entity)) {
            return null;
        }

        return $entity;
    }

    /**
     * @param $entity
     * @param string $prefix
     * @return string
     */
    public function generateAdminTitle($entity = null, string $prefix = "")
    {
        $title = $_ENV["SITE_NAME"];

        if (!empty($entity)) {
            if (method_exists($entity, "getName")) {
                $title = $entity->getName();
            } elseif (method_exists($entity, "getLabel")) {
                $title = $entity->getLabel();
            } elseif (method_exists($entity, "getFrontendLabel")) {
                $title = $entity->getFrontendLabel();
            } elseif (method_exists($entity, "getDisplayName")) {
                $title = $entity->getDisplayName();
            } elseif (method_exists($entity, "getTitle")) {
                $title = $entity->getTitle();
            } elseif (method_exists($entity, "getUsername")) {
                $title = $entity->getUsername();
            } elseif (method_exists($entity, "getSubject")) {
                $title = $entity->getSubject();
            }

            if (is_array($title)) {
                if (isset($title[$_ENV["DEFAULT_STORE_ID"]])) {
                    $title = $this->translator->trans($title[$_ENV["DEFAULT_STORE_ID"]]);
                } else {
                    $title = $_ENV["SITE_NAME"];
                }
            } elseif (is_string($title)) {
                $title = $this->translator->trans($title);
            } else {
                $title = $_ENV["SITE_NAME"];
            }
        }

        if (!empty($prefix)) {
            $title = $this->translator->trans($prefix) . " - " . $title;
        }

        return $title;
    }
}
