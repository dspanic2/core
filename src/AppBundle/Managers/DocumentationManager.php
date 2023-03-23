<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class DocumentationManager extends AbstractBaseManager
{

    /**@var DatabaseContext $databaseContext */
    protected $databaseContext;

    public function initialize()
    {
        parent::initialize();
        $this->databaseContext = $this->container->get("database_context");
    }

    /**
     * @param null $bundle
     * @param array $excludeBundles
     * @return array
     */
    public function generateDocumentation($bundle = null, $excludeBundles = array())
    {

        $data = array();

        if (!empty($bundle)) {
            $data[$bundle] = $this->getBundleAttributes($bundle);
        } else {
            $bundles = $this->getAllBundles();

            foreach ($bundles as $bundle) {
                if (in_array($bundle, $excludeBundles)) {
                    continue;
                }

                $data[$bundle] = $this->getBundleAttributes($bundle);
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    public function getAllBundles()
    {

        $sql = "SELECT DISTINCT(bundle) FROM entity_type ORDER BY bundle ASC;";

        $bundles = $this->databaseContext->executeQuery($sql);

        $data = array();

        if (!empty($bundles)) {
            foreach ($bundles as $bundle) {
                $data[] = $bundle["bundle"];
            }
        }

        return $data;
    }

    /**
     * @param $bundle
     * @return array
     */
    public function getBundleAttributes($bundle)
    {

        $data = array();

        $sql = "SELECT * FROM entity_type WHERE bundle = '{$bundle}' ORDER BY entity_type_code ASC;";

        $entityTypes = $this->databaseContext->executeQuery($sql);

        if (empty($entityTypes)) {
            return array();
        }

        foreach ($entityTypes as $entityType) {
            $data[$entityType["entity_type_code"]]["entity_type"] = $entityType;

            $sql = "SELECT * FROM attribute WHERE entity_type_id = '{$entityType["id"]}' ORDER BY attribute_code ASC;";

            $data[$entityType["entity_type_code"]]["attributes"] = $this->databaseContext->executeQuery($sql);

            $sql = "SELECT * FROM list_view WHERE entity_type = '{$entityType["id"]}' ORDER BY display_name ASC;";

            $data[$entityType["entity_type_code"]]["list_views"] = $this->databaseContext->executeQuery($sql);
        }

        return $data;
    }
}
