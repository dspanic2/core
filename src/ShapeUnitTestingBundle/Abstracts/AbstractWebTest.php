<?php

namespace ShapeUnitTestingBundle\Abstracts;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Managers\AccountManager;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ShapeUnitTestingBundle\Models\ContactModel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractWebTest extends WebTestCase
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface|null
     */
    protected $container;
    /**
     * @var Symfony\Bundle\FrameworkBundle\Client
     */
    protected $client;

    public function setUp()
    {
        $_SERVER['REMOTE_ADDR'] = "127.0.0.1";
        $_ENV["UNIT_TEST_RUNNING"] = 1;
        $_ENV["ENABLE_OUTGOING_EMAIL"] = 0;

        $this->client = static::createClient();
        $this->container = $this->client->getContainer();
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        if ($_ENV["IS_PRODUCTION"] == 0) {
            return "{$_ENV["SSL"]}://admin:Admin123!@{$_ENV["FRONTEND_URL"]}";
        }
        return "{$_ENV["SSL"]}://{$_ENV["FRONTEND_URL"]}";
    }

    /**
     * @return null
     */
    public function getSimpleProduct()
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isVisible", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isSaleable", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("productType.id", "eq", CrmConstants::PRODUCT_TYPE_SIMPLE));
        $compositeFilter->addFilter(new SearchFilter("readyForWebshop", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var ProductEntity $product */
        $product = $entityManager->getEntityByEntityTypeAndFilter($entityManager->getEntityTypeByCode("product"), $compositeFilters);

        return $product;
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function getSimpleProductUrl($product = null)
    {
        if (empty($product)) {
            /** @var ProductEntity $product */
            $product = $this->getSimpleProduct();
        }

        if (empty($product)) {
            return "";
        }

        /** @var GetPageUrlExtension $getPageUrlExtension */
        $getPageUrlExtension = $this->container->get("get_page_url_extension");

        return $this->getBaseUrl() . "/" . $getPageUrlExtension->getEntityStoreAttribute($_ENV["DEFAULT_STORE_ID"], $product, "url");
    }

    /**
     * @return null
     */
    public function getConfigurableProduct()
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isVisible", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isSaleable", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isSaleable", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("productType.id", "eq", CrmConstants::PRODUCT_TYPE_CONFIGURABLE));
        $compositeFilter->addFilter(new SearchFilter("readyForWebshop", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $entityManager->getEntityByEntityTypeAndFilter($entityManager->getEntityTypeByCode("product"), $compositeFilters);
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function getConfigurableProductUrl()
    {
        /** @var ProductEntity $product */
        $product = $this->getConfigurableProduct();

        if (empty($product)) {
            return "";
        }

        /** @var GetPageUrlExtension $getPageUrlExtension */
        $getPageUrlExtension = $this->container->get("get_page_url_extension");

        return $this->getBaseUrl() . "/" . $getPageUrlExtension->getEntityStoreAttribute($_ENV["DEFAULT_STORE_ID"], $product, "url");
    }

    /**
     * @return void
     */
    public function anonymizeTestUser()
    {
        /** @var AccountManager $accountManager */
        $accountManager = $this->container->get("account_manager");

        $contactData = new ContactModel();
        $email = $contactData->getEmail();

        /** @var ContactEntity $testContact */
        $testContact = $accountManager->getContactByEmail($email);

        if (!empty($testContact)) {
            $accountManager->gdprAnonymize($testContact);
        }

        /** @var DatabaseContext $databaseContext */
        $databaseContext = $this->container->get("database_context");

        $q = "DELETE FROM newsletter_entity WHERE email = '{$email}';";
        $databaseContext->executeNonQuery($q);

        $q = "DELETE FROM newsletter_transaction_email_entity WHERE email = '{$email}';";
        $databaseContext->executeNonQuery($q);

        $q = "DELETE FROM gdpr_entity WHERE email = '{$email}';";
        $databaseContext->executeNonQuery($q);

        $q = "DELETE FROM user_entity WHERE email = '{$email}';";
        $databaseContext->executeNonQuery($q);

        $q = "DELETE FROM general_question_entity WHERE email = '{$email}';";
        $databaseContext->executeNonQuery($q);

        $q = "DELETE FROM favorite_entity WHERE email = '{$email}';";
        $databaseContext->executeNonQuery($q);

        $q = "DELETE FROM account_entity WHERE email = '{$email}';";
        $databaseContext->executeNonQuery($q);
    }

    /**
     * @param $method
     * @param $path
     * @param string $referer
     * @return mixed
     */
    public function shapePostRequest($method, $path, string $referer = "")
    {
        if (empty($referer)) {
            $referer = $this->getBaseUrl() . "/";
        }
        $this->client->request($method, $path, [], [], ['HTTP_REFERER' => $referer]);
        return json_decode($this->client->getResponse()->getContent(), true);
    }

    /**
     * @param $method
     * @param $path
     * @param string $referer
     * @return mixed
     */
    public function shapePostRequestBool($method, $path, string $referer = "")
    {
        if (empty($referer)) {
            $referer = $this->getBaseUrl() . "/";
        }
        $this->client->request($method, $path, [], [], ['HTTP_REFERER' => $referer]);
        return $this->client->getResponse()->isSuccessful();
    }
}
