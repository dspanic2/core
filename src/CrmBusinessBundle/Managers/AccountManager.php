<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\RoleEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Entity\UserEntity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\MailManager;
use AppBundle\Managers\TranslationManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountBankEntity;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AccountGroupEntity;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\CountryEntity;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Entity\FavoriteEntity;
use CrmBusinessBundle\Entity\GdprEntity;
use CrmBusinessBundle\Entity\GeneralQuestionEntity;
use CrmBusinessBundle\Entity\LeadSourceEntity;
use CrmBusinessBundle\Entity\LeadStatusEntity;
use CrmBusinessBundle\Entity\NewsletterEntity;
use CrmBusinessBundle\Entity\ProductContactRemindMeEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\TrackingEntity;
use CrmBusinessBundle\Entity\WarehouseEntity;
use Doctrine\Common\Util\Inflector;
use AppBundle\Helpers\FileHelper;
use FOS\UserBundle\Model\UserInterface;
use HrBusinessBundle\Entity\CityEntity;
use HrBusinessBundle\Entity\SexEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddConstraintValidatorsPass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AccountManager extends AbstractBaseManager
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /**@var AdministrationManager $administrationManager */
    protected $administrationManager;
    /**@var HelperManager $helperManager */
    protected $helperManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var NewsletterManager $newsletterManager */
    protected $newsletterManager;
    /** @var DefaultScommerceManager $sCommerceManager */
    protected $sCommerceManager;
    /** @var GetPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var EmailTemplateManager $emailTemplateManager */
    protected $emailTemplateManager;


    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param $id
     * @return |null
     */
    public function getAccountById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(AccountEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $filterBy
     * @param $value
     * @return |null
     */
    public function getAccountByFilter($filterBy, $value)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("account");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter(EntityHelper::makeAttributeName($filterBy), "eq", $value));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @return mixed
     */
    public function getAllAccounts()
    {

        $entityType = $this->entityManager->getEntityTypeByCode("account");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getLeadSourceById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(LeadSourceEntity::class);
        return $repository->find($id);
        /*$entityType = $this->entityManager->getEntityTypeByCode("lead_source");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $id
     * @return |null
     */
    public function getLeadStatusById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(LeadStatusEntity::class);
        return $repository->find($id);
        /*$entityType = $this->entityManager->getEntityTypeByCode("lead_status");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param AccountEntity $account
     * @param $data
     * @return AccountEntity
     */
    public function updateAccount(AccountEntity $account, $data)
    {

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($account, $setter)) {
                $account->$setter($value);
            }
        }

        $this->entityManager->saveEntity($account);
        $this->entityManager->refreshEntity($account);

        return $account;
    }

    /**
     * @param $attributeSetCode
     * @param $data
     * @return AccountEntity
     * @throws \Exception
     */
    public function insertAccount($attributeSetCode, $data, $skipLog = false)
    {

        /** @var AccountEntity $entity */
        $entity = $this->entityManager->getNewEntityByAttributSetName($attributeSetCode);

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($entity);
        } else {
            $this->entityManager->saveEntity($entity);
        }

        return $entity;
    }

    /**
     * @param AccountEntity $account
     * @return mixed
     */
    public function getContactsByAccount(AccountEntity $account)
    {

        $etContact = $this->entityManager->getEntityTypeByCode("contact");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("account", "eq", $account->getId()));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($etContact, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getContactById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(ContactEntity::class);
        return $repository->find($id);
        /*$etContact = $this->entityManager->getEntityTypeByCode("contact");

        return $this->entityManager->getEntityByEntityTypeAndId($etContact, $id);*/
    }

    /**
     * @param $email
     * @return ContactEntity
     */
    public function getContactByEmail($email)
    {

        $etContact = $this->entityManager->getEntityTypeByCode("contact");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("email", "eq", $email));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);
        /** @var ContactEntity $contact */
        $contact = $this->entityManager->getEntityByEntityTypeAndFilter($etContact, $compositeFilters);

        return $contact;
    }

    /**
     * @param $email
     * @return ContactEntity
     */
    public function getContactByFilter($additionalCompositeFilter = null)
    {

        $etContact = $this->entityManager->getEntityTypeByCode("contact");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($etContact, $compositeFilters);
    }

    /**
     * @param $email
     * @return CoreUserEntity
     */
    public function getCoreUserByEmail($email)
    {

        $et = $this->entityManager->getEntityTypeByCode("core_user");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("email", "eq", $email));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);
        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);

        return $coreUser;
    }

    /**
     * @param ContactEntity $contact
     * @param $data
     * @param $skipLog
     * @return ContactEntity
     */
    public function updateContact(ContactEntity $contact, $data, $skipLog = false)
    {

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($contact, $setter)) {
                $contact->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($contact);
        } else {
            $this->entityManager->saveEntity($contact);
        }
        $this->entityManager->refreshEntity($contact);

        return $contact;
    }

    /**
     * @param AddressEntity $address
     * @param $data
     * @return AddressEntity
     */
    public function updateAddress(AddressEntity $address, $data)
    {

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($address, $setter)) {
                $address->$setter($value);
            }
        }

        $this->entityManager->saveEntity($address);
        $this->entityManager->refreshEntity($address);

        return $address;
    }

    /**
     * @param $data
     * @return ContactEntity
     */
    public function insertContact($data)
    {
        $entity = $this->getContactByEmail($data["email"]);

        if (!empty($entity)) {
            $save = false;

            foreach ($data as $key => $value) {
                $setter = EntityHelper::makeSetter($key);
                $getter = EntityHelper::makeGetter($key);

                if (EntityHelper::checkIfMethodExists($entity, $getter)) {
                    if ($entity->$getter() != $value) {
                        $save = true;
                        $entity->$setter($value);
                    }
                }
            }

            if ($save) {
                $this->entityManager->saveEntity($entity);
            }

            return $entity;
        }

        /** @var ContactEntity $entity */
        $entity = $this->entityManager->getNewEntityByAttributSetName("contact");

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        $this->entityManager->saveEntity($entity);

        return $entity;
    }

    /**
     * @param $sessionId
     * @return bool|null
     */
    public function getTracking($sessionId)
    {

        if (empty($sessionId)) {
            return false;
        }

        $entityType = $this->entityManager->getEntityTypeByCode("tracking");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("sessionId", "eq", $sessionId));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param $data
     * @return bool|TrackingEntity|mixed|null
     */
    public function insertUpdateTracking($data)
    {

        /** @var TrackingEntity $tracking */
        $tracking = null;
        if (isset($data["session_id"]) && !empty($data["session_id"])) {
            $tracking = $this->getTracking($data["session_id"]);
        }

        if (empty($tracking)) {
            $tracking = $this->entityManager->getNewEntityByAttributSetName("tracking");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($tracking, $setter)) {
                $tracking->$setter($value);
            }
        }

        $this->entityManager->saveEntityWithoutLog($tracking);

        return $tracking;
    }

    /**
     * @return bool
     */
    public function automaticGdprDecline()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $expireAfterYear = 1;
        if (isset($_ENV["GDPR_EXPIRE_YEARS"]) && !empty($_ENV["GDPR_EXPIRE_YEARS"])) {
            $expireAfterYear = $_ENV["GDPR_EXPIRE_YEARS"];
        }

        $q = "UPDATE gdpr_entity SET is_active = 0, date_declined = NOW() WHERE last_update < NOW() - INTERVAL {$expireAfterYear} YEAR;";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param $data
     * @param ContactEntity|null $contact
     * @return GdprEntity|null
     * @throws \Exception
     */
    public function insertGdpr($data, ContactEntity $contact = null)
    {
        if (!isset($data["email"])) {
            return null;
        }

        $entityType = $this->entityManager->getEntityTypeByCode("gdpr");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("email", "eq", $data["email"]));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var GdprEntity $gdpr */
        $gdpr = $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
        if (empty($gdpr)) {
            /** @var GdprEntity $gdpr */
            $gdpr = $this->entityManager->getNewEntityByAttributSetName("gdpr");
            $gdpr->setEmail($data["email"]);
            if (isset($data["given_on_process"])) {
                $gdpr->setGivenOnProcess($data["given_on_process"]);
            }
        }

        if (isset($data["first_name"])) {
            $gdpr->setFirstName($data["first_name"]);
        }
        if (isset($data["last_name"])) {
            $gdpr->setLastName($data["last_name"]);
        }
        if (!empty($contact)) {
            $gdpr->setContact($contact);
        }

        $gdprExpireYears = 1;
        if (isset($_ENV["GDPR_EXPIRE_YEARS"]) && !empty($_ENV["GDPR_EXPIRE_YEARS"])) {
            $gdprExpireYears = $_ENV["GDPR_EXPIRE_YEARS"];
        }

        $gdpr->setLastUpdate(new \DateTime());
        $gdpr->setDateExpire((new \DateTime())->add(new \DateInterval("P" . $gdprExpireYears . "Y")));
        $gdpr->setIsActive(1);

        $this->entityManager->saveEntityWithoutLog($gdpr);

        /**
         * Ovo mozda nece biti ni potrebno
         */
        if (!empty($contact) && !$contact->getMarketingSignup()) {
            $this->insertMarketingSignup($contact);
        }

        return $gdpr;
    }

    /**
     * @param $id
     * @return |null
     */
    public function getSexById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(SexEntity::class);
        return $repository->find($id);
        /*$entityType = $this->entityManager->getEntityTypeByCode("sex");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $id
     * @return |null
     */
    public function getAddressById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(AddressEntity::class);
        return $repository->find($id);
        /*$entityType = $this->entityManager->getEntityTypeByCode("address");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $filterBy
     * @param $value
     * @return |null
     */
    public function getAddressByFilter($filterBy, $value)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("address");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter(EntityHelper::makeAttributeName($filterBy), "eq", $value));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $attributeSetCode
     * @param $data
     * @return AddressEntity
     */
    public function insertAddress($attributeSetCode, $data)
    {

        /** @var AddressEntity $entity */
        $entity = $this->entityManager->getNewEntityByAttributSetName($attributeSetCode);

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        $this->entityManager->saveEntity($entity);
        $this->entityManager->refreshEntity($entity);
        if (isset($data["account"]) && !empty($data["account"])) {
            $this->entityManager->refreshEntity($data["account"]);
        }
        if (isset($data["contact"]) && !empty($data["contact"])) {
            $this->entityManager->refreshEntity($data["contact"]);
        }

        return $entity;
    }

    /**
     * @param AddressEntity $address
     * @return bool
     */
    public function setHeadquartersAndBillingFlag(AddressEntity $address)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $headquartersSet = $address->getHeadquarters();
        $billingSet = $address->getBilling();

        if ($headquartersSet) {
            $q = "UPDATE address_entity SET headquarters = 0 WHERE account_id = {$address->getAccount()->getId()} AND id != {$address->getId()};";
            $this->databaseContext->executeNonQuery($q);
        } else {
            $q = "SELECT * FROM address_entity WHERE entity_state_id = 1 AND account_id = {$address->getAccount()->getId()} AND id != {$address->getId()} AND headquarters = 1;";
            $exists = $this->databaseContext->getAll($q);
            if (count($exists) > 1) {
                $q = "UPDATE address_entity SET headquarters = 0 WHERE account_id = {$address->getAccount()->getId()}";
                $this->databaseContext->executeNonQuery($q);
                $exists = null;
            }
            if (empty($exists)) {
                $q = "UPDATE address_entity SET headquarters = 1 WHERE entity_state_id = 1 AND account_id = {$address->getAccount()->getId()} LIMIT 1;";
                $this->databaseContext->executeNonQuery($q);
            }
        }

        if ($billingSet) {
            $q = "UPDATE address_entity SET billing = 0 WHERE account_id = {$address->getAccount()->getId()} AND id != {$address->getId()};";
            $this->databaseContext->executeNonQuery($q);
        } else {
            $q = "SELECT * FROM address_entity WHERE entity_state_id = 1 AND account_id = {$address->getAccount()->getId()} AND id != {$address->getId()} AND billing = 1;";
            $exists = $this->databaseContext->getAll($q);
            if (count($exists) > 1) {
                $q = "UPDATE address_entity SET billing = 0 WHERE account_id = {$address->getAccount()->getId()}";
                $this->databaseContext->executeNonQuery($q);
                $exists = null;
            }
            if (empty($exists)) {
                $q = "UPDATE address_entity SET billing = 1 WHERE entity_state_id = 1 AND account_id = {$address->getAccount()->getId()} LIMIT 1;";
                $this->databaseContext->executeNonQuery($q);
            }
        }

        return true;
    }

    /**
     * @param AccountEntity $account
     * @param $street
     * @return |null
     */
    public function getAddressByAccountAndStreet(AccountEntity $account, $street)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("address");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("account", "eq", $account->getId()));
        $compositeFilter->addFilter(new SearchFilter("street", "eq", $street));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getAccountBankById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(AccountBankEntity::class);
        return $repository->find($id);
        /*$entityType = $this->entityManager->getEntityTypeByCode("account_bank");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $data
     * @return AccountBankEntity
     */
    public function insertAccountBank($data)
    {

        /** @var AccountBankEntity $entity */
        $entity = $this->entityManager->getNewEntityByAttributSetName("account_bank");

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        $this->entityManager->saveEntity($entity);

        return $entity;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function insertAccountDocument($data)
    {

        /** @var AccountDocument $entity */
        $entity = $this->entityManager->getNewEntityByAttributSetName("account_document");

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        $this->entityManager->saveEntity($entity);

        return $entity;
    }

    /**
     * @param $postalCode
     * @return |null
     */
    public function getCityByPostalCode($postalCode)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("city");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("postalCode", "eq", $postalCode));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $postalCode
     * @param CountryEntity $country
     * @return |null
     */
    public function getCityByPostalCodeAndCountry($postalCode, CountryEntity $country)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("city");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("postalCode", "eq", $postalCode));
        $compositeFilter->addFilter(new SearchFilter("country", "eq", $country->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getCityById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(CityEntity::class);
        return $repository->find($id);

        /*$entityType = $this->entityManager->getEntityTypeByCode("city");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $data
     * @return CityEntity|null
     */
    public function insertCity($data)
    {

        /** @var CityEntity $entity */
        $entity = $this->entityManager->getNewEntityByAttributSetName("city");

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
     * @param $id
     * @return |null
     */
    public function getCountryById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(CountryEntity::class);
        return $repository->find($id);

        /*$entityType = $this->entityManager->getEntityTypeByCode("country");
        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $name
     * @return |null
     */
    public function getCountryByName($name)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("country");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $session = $this->container->get("session");
        if (empty($session->get("current_store_id"))) {
            $searchBy = $name;
        } else {
            $searchBy = json_encode(array($name, '$."' . $session->get("current_store_id") . '"'));
        }
        $compositeFilter->addFilter(new SearchFilter("name", "json_eq", $searchBy));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $data
     * @return CountryEntity
     */
    public function insertCountry($data)
    {

        /** @var CountryEntity $entity */
        $entity = $this->entityManager->getNewEntityByAttributSetName("country");

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        $this->entityManager->saveEntity($entity);

        return $entity;
    }

    /**
     * @param null $term
     * @param bool $filterCountries
     * @return mixed
     */
    public function getCountries($term = null, $data = null, $filterCountries = true)
    {
        $session = $this->container->get("session");
        $storeId = $session->get("current_store_id");

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $entityType = $this->entityManager->getEntityTypeByCode("country");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        /**
         * Filter available countries by delivery
         */
        if ($filterCountries) {

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }

            $additionalWhere = "";
            if (isset($data["delivery_type_id"]) && !empty($data["delivery_type_id"])) {
                $additionalWhere = " AND d.id = {$data["delivery_type_id"]} ";
            }

            $q = "SELECT DISTINCT(country_id) as country_id FROM delivery_prices_country_link_entity as dpcl LEFT JOIN delivery_prices_entity AS dp ON dpcl.delivery_prices_id = dp.id LEFT JOIN delivery_type_entity AS d ON dp.delivery_id = d.id
            WHERE d.entity_state_id = 1 and d.active = 1 and dp.entity_state_id = 1 and JSON_CONTAINS(d.show_on_store, '1', '$.\"{$storeId}\"') = '1' {$additionalWhere};";
            $countryIds = $this->databaseContext->getAll($q);
            $countryIds = array_column($countryIds, "country_id");

            if (!empty($countryIds)) {
                $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", array_filter($countryIds))));
            }
        }

        if (!empty($term)) {
            $compositeFilter->addFilter(new SearchFilter("name", "json_bw", json_encode(array($term, '$."' . $storeId . '"'))));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter(json_encode(array("name", '$."' . $storeId . '"')), "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param null $term
     * @param array $formData
     * @return array
     */
    public function getCities($term = null, $formData = array())
    {
        /**default limit to number of returned */
        $pagingFilter = new PagingFilter();
        $pagingFilter->setPageNumber(0);
        $pagingFilter->setPageSize(100);

        $entityType = $this->entityManager->getEntityTypeByCode("city");

        $compositeFilters = new CompositeFilterCollection();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        if ((isset($formData["is_shipping"]) && $formData["is_shipping"] == 1) && (isset($formData["shipping_country_id"]) && !empty($formData["shipping_country_id"]) && !isset($formData["shipping_address_same"]))) {
            $compositeFilter->addFilter(new SearchFilter("country.id", "eq", $formData["shipping_country_id"]));
        } elseif (isset($formData["country_id"]) && !empty($formData["country_id"])) {
            $compositeFilter->addFilter(new SearchFilter("country.id", "eq", $formData["country_id"]));
        }
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($term)) {

            $orderBy = null;
            $used = array();

            $compositeFilterSub = new CompositeFilter();
            $compositeFilterSub->setConnector("and");

            $term = trim($term);
            $term = explode(" ", $term);
            foreach ($term as $t) {
                if (strlen($t) > 2) {
                    if (preg_match('#[^0-9]#', $t)) {
                        if (empty($orderBy)) {
                            $orderBy = "name";
                        }
                        if (!isset($used["name"])) {
                            $used["name"] = 1;
                        } else {
                            continue;
                        }
                        $compositeFilterSub->addFilter(new SearchFilter("name", "bw", $t));
                    } else {
                        if (empty($orderBy)) {
                            $orderBy = "postalCode";
                        }
                        if (!isset($used["postalCode"])) {
                            $used["postalCode"] = 1;
                        } else {
                            continue;
                        }
                        $compositeFilterSub->addFilter(new SearchFilter("postalCode", "bs", $t));
                    }
                }
            }

            if (!empty($orderBy)) {
                $compositeFilters->addCompositeFilter($compositeFilterSub);
            }
        }

        $sortFilters = new SortFilterCollection();
        if (!empty($orderBy)) {
            $sortFilters->addSortFilter(new SortFilter($orderBy, "asc"));
        } else {
            $sortFilters->addSortFilter(new SortFilter("postalCode", "asc"));
            $sortFilters->addSortFilter(new SortFilter("name", "asc"));
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters, $pagingFilter);
    }

    /**
     * @param $email
     * @param ProductEntity $product
     * @return FavoriteEntity
     */
    public function getFavoriteByEmailAndProduct($email, ProductEntity $product)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("favorite");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("email", "eq", $email));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("product", "eq", $product->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var FavoriteEntity $favorite */
        $favorite = $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);

        return $favorite;
    }

    /**
     * @param $email
     * @return mixed
     */
    public function getFavoritesByEmail($email)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("favorite");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("email", "eq", $email));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param bool $recreate
     * @return bool
     */
    public function initializeFavorites($recreate = false)
    {

        $session = $this->container->get('session');
        if (!$session->get('favorites_set') || $recreate) {
            $productIds = array();

            /** @var TrackingEntity $tracking */
            $tracking = $this->getTracking($session->getId());

            if (!empty($tracking) && !empty($tracking->getEmail())) {
                $favorites = $this->getFavoritesByEmail($tracking->getEmail());
                if (!empty($favorites)) {

                    /** @var FavoriteEntity $favorite */
                    foreach ($favorites as $favorite) {
                        $productIds[] = $favorite->getProductId();
                    }
                }
            }

            $session->set('favorites_set', 1);
            $session->set('favorites_product_ids', $productIds);
        }

        return true;
    }

    /**
     * @param $data
     * @param ProductEntity $product
     * @param ContactEntity|null $contact
     * @return bool
     * @throws \Exception
     */
    public function insertUpdateFavorite($data, ProductEntity $product, ContactEntity $contact = null)
    {
        /**
         * Check if exists
         */
        $favorite = $this->getFavoriteByEmailAndProduct($data["email"], $product);

        if ($data["is_favorite"]) {
            if (!empty($favorite)) {
                $favorite->setActive(1);
                $favorite->setDateUnfavored(null);
                $this->entityManager->saveEntityWithoutLog($favorite);
            } else {
                /**
                 * Create new favorite
                 */
                /** @var FavoriteEntity $favorite */
                $favorite = $this->entityManager->getNewEntityByAttributSetName("favorite");
                $favorite->setEmail($data["email"]);
                $favorite->setFirstName($data["first_name"]);
                $favorite->setLastName($data["last_name"]);
                $favorite->setActive(1);
                $favorite->setProduct($product);
                $favorite->setContact($contact);
            }
        } else {
            if (empty($favorite)) {
                return false;
            }

            $favorite->setActive(0);
            $favorite->setDateUnfavored(new \DateTime());
        }

        $this->entityManager->saveEntityWithoutLog($favorite);
        $this->entityManager->clearManagerByEntityType($favorite->getEntityType());

        $this->initializeFavorites(true);

        return true;
    }

    /**
     * @param null $additionalFilter
     * @return null
     */
    public function getFilteredProductContactRemindMe($additionalFilter = null)
    {
        $et = $this->entityManager->getEntityTypeByCode("product_contact_remind_me");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalFilter)) {
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "asc"));

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param $data
     * @param ProductEntity $product
     * @param ContactEntity|null $contact
     * @return bool
     * @throws \Exception
     */
    public function insertUpdateRemindMe($data, ProductEntity $product, ContactEntity $contact = null, WarehouseEntity $warehouse = null)
    {
        /** @var ProductContactRemindMeEntity $remindMe */

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("email", "eq", $data["email"]));
        $compositeFilter->addFilter(new SearchFilter("product", "eq", $product->getId()));
        $compositeFilter->addFilter(new SearchFilter("sent", "eq", 0));
        if (!empty($warehouse)) {
            $compositeFilter->addFilter(new SearchFilter("warehouse", "eq", $warehouse->getId()));
        } else {
            $compositeFilter->addFilter(new SearchFilter("warehouse", "nu", null));
        }

        $remindMe = $this->getFilteredProductContactRemindMe($compositeFilter);

        if (empty($remindMe)) {
            $remindMe = $this->entityManager->getNewEntityByAttributSetName("product_contact_remind_me");
        }

        $data["hash"] = md5($data["email"] . time());
        $data["product"] = $product;
        $data["contact"] = $contact;
        $data["warehouse"] = $warehouse;
        $data["sent"] = 0;

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($remindMe, $setter)) {
                $remindMe->$setter($value);
            }
        }

        $this->entityManager->saveEntityWithoutLog($remindMe);
        $this->entityManager->refreshEntity($remindMe);

        return $remindMe;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function insertUpdateGeneralQuestion($data)
    {
        if (isset($data["general_question_id"])) {
            $entityType = $this->entityManager->getEntityTypeByCode("general_question");
            /** @var GeneralQuestionEntity $generalQuestion */
            $generalQuestion = $this->entityManager->getEntityByEntityTypeAndId($entityType, $data["general_question_id"]);
        }

        if (empty($generalQuestion)) {
            $generalQuestion = $this->entityManager->getNewEntityByAttributSetName("general_question");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($generalQuestion, $setter)) {
                $generalQuestion->$setter($value);
            }
        }

        $this->entityManager->saveEntityWithoutLog($generalQuestion);
        $this->entityManager->refreshEntity($generalQuestion);

        return $generalQuestion;
    }

    /**
     * @param $entity
     * @return mixed
     */
    public function save($entity)
    {

        $this->entityManager->saveEntity($entity);
        $this->entityManager->refreshEntity($entity);

        return $entity;
    }

    /**
     * @param $entity
     * @return mixed
     */
    public function delete($entity)
    {

        $this->entityManager->deleteEntity($entity);

        return $entity;
    }

    /**
     * @param ContactEntity $contact
     * @return bool
     */
    public function insertMarketingSignup(ContactEntity $contact)
    {

        if ($contact->getMarketingSignup()) {
            return true;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE contact_entity SET marketing_signup = 1 WHERE id = {$contact->getId()};";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param $addresses
     * @return array
     */
    public function gdprAnonymizeAddresses($addresses)
    {

        $addressArray = array();

        /** @var AddressEntity $address */
        foreach ($addresses as $address) {

            $data = array();
            $data["street"] = StringHelper::encrypt($address->getStreet());
            if (!empty($address->getName())) {
                $data["name"] = StringHelper::encrypt($address->getName());
            }
            if (!empty($address->getPhone())) {
                $data["phone"] = StringHelper::encrypt($address->getPhone());
            }
            if (!empty($address->getFirstName())) {
                $data["first_name"] = StringHelper::encrypt($address->getFirstName());
            }
            if (!empty($address->getLastName())) {
                $data["last_name"] = StringHelper::encrypt($address->getLastName());
            }
            $data["is_anonymized"] = 1;

            $this->updateAddress($address, $data);

            $addressArray[] = array("id" => $address->getId(), "street" => $data["street"]);
        }

        return $addressArray;
    }

    /**
     * @param ContactEntity $contact
     * @return bool
     */
    public function gdprAnonymize(ContactEntity $contact)
    {
        /**
         * Send email to admin
         */
        if (empty($this->emailTemplateManager)) {
            $this->emailTemplateManager = $this->container->get("email_template_manager");
        }

        /** @var EmailTemplateEntity $emailTemplate */
        $emailTemplate = $this->emailTemplateManager->getEmailTemplateByCode("account_removed");

        $to = array('email' => $_ENV["ORDER_EMAIL_RECIPIENT"], 'name' => $_ENV["ORDER_EMAIL_RECIPIENT"]);

        if (!isset($_ENV["UNIT_TEST_RUNNING"])) {
            if (!empty($emailTemplate)) {
                $this->emailTemplateManager->sendEmail("account_removed", $contact, $_ENV["DEFAULT_STORE_ID"], $to, null, null, null, null);
            } else {
                if (empty($this->mailManager)) {
                    $this->mailManager = $this->container->get("mail_manager");
                }

                $this->mailManager->sendEmail(
                    $to,
                    null,
                    null,
                    null,
                    $this->translator->trans('Account removed') . " {$contact->getFirstName()} - {$contact->getLastName()}",
                    "",
                    'account_removed',
                    array("contact" => $contact),
                    null,
                    array(),
                    $_ENV["DEFAULT_STORE_ID"]
                );
            }
        }

        if (empty($this->newsletterManager)) {
            $this->newsletterManager = $this->container->get("newsletter_manager");
        }

        $this->newsletterManager->removeContactFromNewsletter($contact);

        $email = $contact->getEmail();

        $data = array();
        $data["email"] = StringHelper::encrypt($contact->getEmail() . $contact->getId());
        $data["first_name"] = StringHelper::encrypt($contact->getFirstName());
        $data["last_name"] = StringHelper::encrypt($contact->getLastName());
        $data["full_name"] = StringHelper::encrypt($contact->getFullName());
        $data["date_of_birth"] = null;
        if (!empty($contact->getPhone())) {
            $data["phone"] = StringHelper::encrypt($contact->getPhone());
        }
        if (!empty($contact->getFax())) {
            $data["fax"] = StringHelper::encrypt($contact->getFax());
        }
        if (!empty($contact->getPhone2())) {
            $data["phone2"] = StringHelper::encrypt($contact->getPhone2());
        }
        if (!empty($contact->getHomePhone())) {
            $data["home_phone"] = StringHelper::encrypt($contact->getHomePhone());
        }
        if (EntityHelper::checkIfMethodExists($contact, "getPassword") && !empty($contact->getPassword())) {
            $data["password"] = StringHelper::encrypt($contact->getPassword());
        }
        $data["is_anonymized"] = 1;

        $this->updateContact($contact, $data);

        $addressArray = array();

        /** @var AccountEntity $account */
        $account = $contact->getAccount();

        if (!$account->getIsLegalEntity() || (EntityHelper::isCountable($account->getContacts()) && count($account->getContacts()) == 1)) {
            $addressArray = $this->anonymizeAccount($account);
        }

        $addresses = $contact->getAddresses();
        if (EntityHelper::isCountable($addresses) && count($addresses) > 0) {
            $addressArray = array_merge($addressArray, $this->gdprAnonymizeAddresses($addresses));
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT o.id, o.account_shipping_street, o.account_billing_street, o.name, o.account_phone, o.account_email, o.account_name FROM order_entity as o WHERE o.contact_id = {$contact->getId()};";
        $data = $this->databaseContext->getAll($q);

        if (!empty($data)) {
            foreach ($data as $d) {

                $updateArray = array();

                if (!empty($d["account_shipping_street"]) && !$account->getIsLegalEntity()) {
                    $updateArray["account_shipping_street"] = StringHelper::encrypt($d["account_shipping_street"]);
                }
                if (!empty($d["account_billing_street"]) && !$account->getIsLegalEntity()) {
                    $updateArray["account_billing_street"] = StringHelper::encrypt($d["account_billing_street"]);
                }
                if (!empty($d["name"])) {
                    $updateArray["name"] = StringHelper::encrypt($d["name"]);
                }
                if (!empty($d["account_name"]) && !$account->getIsLegalEntity()) {
                    $updateArray["account_name"] = StringHelper::encrypt($d["account_name"]);
                }
                if (!empty($d["account_phone"]) && !$account->getIsLegalEntity()) {
                    $updateArray["account_phone"] = StringHelper::encrypt($d["account_phone"]);
                }
                if (!empty($d["account_email"]) && !$account->getIsLegalEntity()) {
                    $updateArray["account_email"] = StringHelper::encrypt($d["account_email"]);
                }

                if (!empty($updateArray)) {
                    $updateString = "";
                    foreach ($updateArray as $key => $value) {
                        $updateString .= "{$key} = '{$value}',";
                    }
                    $updateString = substr($updateString, 0, -1);

                    $q = "UPDATE order_entity SET {$updateString} WHERE id = {$d["id"]};";
                    $this->databaseContext->executeNonQuery($q);
                }
            }
        }

        /**
         * Anonymize orders
         */
        /*$q = "SELECT o.id, o.account_shipping_street, o.account_billing_street, o.name, o.account_phone, o.account_email, o.account_name FROM order_customer_entity as oc LEFT JOIN order_entity as o ON oc.order_id = o.id WHERE oc.contact_id = {$contact->getId()} GROUP BY oc.order_id;";
        $data = $this->databaseContext->getAll($q);

        if(!empty($data)){
            foreach ($data as $d){

                $updateArray = Array();

                if(!empty($d["account_shipping_street"]) && !$account->getIsLegalEntity()){
                    $updateArray["account_shipping_street"] = StringHelper::encrypt($d["account_shipping_street"]);
                }
                if(!empty($d["account_billing_street"]) && !$account->getIsLegalEntity()){
                    $updateArray["account_billing_street"] = StringHelper::encrypt($d["account_billing_street"]);
                }
                if(!empty($d["name"])){
                    $updateArray["name"] = StringHelper::encrypt($d["name"]);
                }
                if(!empty($d["account_name"]) && !$account->getIsLegalEntity()){
                    $updateArray["account_name"] = StringHelper::encrypt($d["account_name"]);
                }
                if(!empty($d["account_phone"]) && !$account->getIsLegalEntity()){
                    $updateArray["account_phone"] = StringHelper::encrypt($d["account_phone"]);
                }
                if(!empty($d["account_email"]) && !$account->getIsLegalEntity()){
                    $updateArray["account_email"] = StringHelper::encrypt($d["account_email"]);
                }

                if(!empty($updateArray)){
                    $updateString = "";
                    foreach ($updateArray as $key => $value){
                        $updateString.= "{$key} = '{$value}',";
                    }
                    $updateString = substr($updateString, 0, -1);

                    $q = "UPDATE order_entity SET {$updateString} WHERE id = {$d["id"]};";
                    $this->databaseContext->executeNonQuery($q);
                }
            }
        }*/

        /**
         * Anonymize quotes
         */
        $q = "SELECT o.id, o.account_shipping_street, o.account_billing_street, o.name, o.account_phone, o.account_email, o.account_name, o.preview_hash FROM  quote_entity as o WHERE o.contact_id = {$contact->getId()};";
        $data = $this->databaseContext->getAll($q);

        if (!empty($data)) {
            foreach ($data as $d) {

                $updateArray = array();

                if (!empty($d["account_shipping_street"]) && !$account->getIsLegalEntity()) {
                    $updateArray["account_shipping_street"] = StringHelper::encrypt($d["account_shipping_street"]);
                }
                if (!empty($d["account_billing_street"]) && !$account->getIsLegalEntity()) {
                    $updateArray["account_billing_street"] = StringHelper::encrypt($d["account_billing_street"]);
                }
                if (!empty($d["name"])) {
                    $updateArray["name"] = StringHelper::encrypt($d["name"]);
                }
                if (!empty($d["account_name"]) && !$account->getIsLegalEntity()) {
                    $updateArray["account_name"] = StringHelper::encrypt($d["account_name"]);
                }
                if (!empty($d["account_phone"]) && !$account->getIsLegalEntity()) {
                    $updateArray["account_phone"] = StringHelper::encrypt($d["account_phone"]);
                }
                if (!empty($d["account_email"]) && !$account->getIsLegalEntity()) {
                    $updateArray["account_email"] = StringHelper::encrypt($d["account_email"]);
                }
                $updateArray["preview_hash"] = StringHelper::encrypt($d["preview_hash"]);

                if (!empty($updateArray)) {
                    $updateString = "";
                    foreach ($updateArray as $key => $value) {
                        $updateString .= "{$key} = '{$value}',";
                    }
                    $updateString = substr($updateString, 0, -1);

                    $q = "UPDATE quote_entity SET {$updateString} WHERE id = {$d["id"]};";
                    $this->databaseContext->executeNonQuery($q);
                }
            }
        }

        /**
         * Anonymize general_question_entity
         */
        $q = "SELECT * FROM general_question_entity WHERE email = '{$email}';";
        $data = $this->databaseContext->getAll($q);

        if (!empty($data)) {
            foreach ($data as $d) {

                $updateArray = array();

                if (!empty($d["email"])) {
                    $updateArray["email"] = StringHelper::encrypt($d["email"]);
                }
                if (!empty($d["fist_name"])) {
                    $updateArray["fist_name"] = StringHelper::encrypt($d["fist_name"]);
                }
                if (!empty($d["last_name"])) {
                    $updateArray["last_name"] = StringHelper::encrypt($d["last_name"]);
                }
                if (!empty($d["phone"])) {
                    $updateArray["phone"] = StringHelper::encrypt($d["phone"]);
                }
                $updateArray["is_anonymized"] = 1;

                if (!empty($updateArray)) {
                    $updateString = "";
                    foreach ($updateArray as $key => $value) {
                        $updateString .= "{$key} = '{$value}',";
                    }
                    $updateString = substr($updateString, 0, -1);

                    $q = "UPDATE general_question_entity SET {$updateString} WHERE id = {$d["id"]};";
                    $this->databaseContext->executeNonQuery($q);
                }
            }
        }

        /**
         * Anonymize favorites
         */
        $q = "SELECT * FROM favorite_entity WHERE email = '{$email}';";
        $data = $this->databaseContext->getAll($q);

        if (!empty($data)) {
            foreach ($data as $d) {

                $updateArray = array();

                if (!empty($d["email"])) {
                    $updateArray["email"] = StringHelper::encrypt($d["email"]);
                }
                if (!empty($d["fist_name"])) {
                    $updateArray["fist_name"] = StringHelper::encrypt($d["fist_name"]);
                }
                if (!empty($d["last_name"])) {
                    $updateArray["last_name"] = StringHelper::encrypt($d["last_name"]);
                }
                $updateArray["is_anonymized"] = 1;

                if (!empty($updateArray)) {
                    $updateString = "";
                    foreach ($updateArray as $key => $value) {
                        $updateString .= "{$key} = '{$value}',";
                    }
                    $updateString = substr($updateString, 0, -1);

                    $q = "UPDATE favorite_entity SET {$updateString} WHERE id = {$d["id"]};";
                    $this->databaseContext->executeNonQuery($q);
                }
            }
        }

        /**
         * Anonymize gdpr
         */
        $q = "SELECT * FROM gdpr_entity WHERE email = '{$email}';";
        $data = $this->databaseContext->getAll($q);

        if (!empty($data)) {
            foreach ($data as $d) {

                $updateArray = array();

                if (!empty($d["email"])) {
                    $updateArray["email"] = StringHelper::encrypt($d["email"]);
                }
                if (!empty($d["fist_name"])) {
                    $updateArray["fist_name"] = StringHelper::encrypt($d["fist_name"]);
                }
                if (!empty($d["last_name"])) {
                    $updateArray["last_name"] = StringHelper::encrypt($d["last_name"]);
                }

                if (!empty($updateArray)) {
                    $updateString = "";
                    foreach ($updateArray as $key => $value) {
                        $updateString .= "{$key} = '{$value}',";
                    }
                    $updateString = substr($updateString, 0, -1);

                    $q = "UPDATE gdpr_entity SET {$updateString} WHERE id = {$d["id"]};";
                    $this->databaseContext->executeNonQuery($q);
                }
            }
        }

        if (!empty($contact->getCoreUser())) {
            $coreUser = $contact->getCoreUser();

            $q = "UPDATE contact_entity SET core_user_id = null WHERE id = {$contact->getId()};";
            $this->databaseContext->executeNonQuery($q);

            $q = "DELETE FROM api_access_entity WHERE core_user_id = {$coreUser->getId()};";
            $this->databaseContext->executeNonQuery($q);

            $this->deleteCoreUser($coreUser);
        }

        /**
         * Custom
         */
        if (empty($this->sCommerceManager)) {
            $this->sCommerceManager = $this->container->get("scommerce_manager");
        }

        $this->sCommerceManager->gdprAnonymize($email);

        return true;
    }

    /**
     * @param AccountEntity $account
     */
    public function anonymizeAccount(AccountEntity $account)
    {

        $addressArray = array();

        $data = array();
        $data["email"] = StringHelper::encrypt($account->getEmail() . $account->getId());
        $data["first_name"] = StringHelper::encrypt($account->getFirstName());
        $data["last_name"] = StringHelper::encrypt($account->getLastName());
        if (!empty($account->getName())) {
            $data["name"] = StringHelper::encrypt($account->getName());
        }
        if (!empty($account->getPhone())) {
            $data["phone"] = StringHelper::encrypt($account->getPhone());
        }
        if (!empty($account->getPhone2())) {
            $data["phone2"] = StringHelper::encrypt($account->getPhone2());
        }
        if (!empty($account->getHomePhone())) {
            $data["home_phone"] = StringHelper::encrypt($account->getHomePhone());
        }
        if (!empty($account->getSecondaryEmail())) {
            $data["secondary_email"] = StringHelper::encrypt($account->getSecondaryEmail());
        }
        if (!empty($account->getOib())) {
            $data["oib"] = StringHelper::encrypt($account->getOib());
        }
        $data["is_anonymized"] = 1;

        $this->updateAccount($account, $data);

        $addresses = $account->getAddresses();
        if (EntityHelper::isCountable($addresses) && count($addresses) > 0) {
            $addressArray = $this->gdprAnonymizeAddresses($addresses);
        }

        return $addressArray;
    }

    /**
     * @param CoreUserEntity $coreUser
     * @return bool
     */
    public function deleteCoreUser(CoreUserEntity $coreUser)
    {

        if (empty($coreUser)) {
            return false;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "DELETE FROM user_entity WHERE id = {$coreUser->getId()};";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param ContactEntity $contact
     * @param null $password
     * @param bool $sendPassword
     * @param bool $sendEmail
     * @return bool
     */
    public function createUserForContact(ContactEntity $contact, $password = null, $sendPassword = true, $sendEmail = true)
    {
        if (empty($contact)) {
            return false;
        }
        if (!empty($contact->getCoreUser())) {
            return false;
        }

        $data = array();
        $data["email"] = $contact->getEmail();
        $data["first_name"] = $contact->getFirstName();
        $data["last_name"] = $contact->getLastName();
        $data["is_legal_entity"] = $contact->getAccount()->getIsLegalEntity();
        if (!empty($password)) {
            $data["password"] = $password;
        } elseif (EntityHelper::checkIfMethodExists($contact, "getPassword") && !empty($contact->getPassword())) {
            $data["password"] = $contact->getPassword();
        } else {
            $data["password"] = StringHelper::generateRandomString(6);
        }

        $ret = $this->createUser($data, $sendPassword, $sendEmail);
        if ($ret["error"]) {
            $this->logger->error("Error creating user on import: contact :" . $contact->getId());
            return false;
        }

        $contact->setCoreUser($ret["core_user"]);
        $this->entityManager->saveEntityWithoutLog($contact);
        $this->entityManager->refreshEntity($contact);

        return true;
    }

    /**
     * @param $data
     * @param bool $sendPassword
     * @param bool $sendEmail
     * @param array $secondaryEmailData
     *   Parameters:
     *     email
     *     template
     * @return array
     */
    public function createUser($data, $sendPassword = false, $sendEmail = true, $secondaryEmailData = [])
    {
        $ret = array();
        $ret["error"] = true;

        if (empty($this->administrationManager)) {
            $this->administrationManager = $this->container->get("administration_manager");
        }
        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        $userData = array();
        $userData["username"] = $data["email"];
        $userData["email"] = $data["email"];
        $userData["google_authenticator_secret"] = null;
        $userData["system_role"] = "ROLE_USER";
        $userData["password"] = $data["password"];

        /** @var UserEntity $user */
        $user = $this->administrationManager->createUpdateUser($userData);
        if (empty($user)) {
            $ret["message"] = $this->translator->trans('Error creating user, please try again');
            return $ret;
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCoreUserById($user->getId());
        if (empty($coreUser)) {
            $ret["message"] = $this->translator->trans('Error creating user, please try again');
            return $ret;
        }

        $coreUserData[] = array();
        $coreUserData["first_name"] = $data["first_name"];
        $coreUserData["last_name"] = $data["last_name"];

        /** @var TranslationManager $translationManager */
        $translationManager = $this->getContainer()->get("translation_manager");

        $coreUserData["core_language"] = $translationManager->getCoreLanguageById(CrmConstants::DEFAULT_CORE_LANGUAGE_ID);

        $this->administrationManager->updateCoreUser($coreUser, $coreUserData);

        /** @var RoleEntity $role */
        $role = $this->administrationManager->getRoleById(CrmConstants::DEFAULT_USER_ROLE_ID);

        $roleUser = $this->administrationManager->createRoleUser($role, $coreUser);
        if (empty($roleUser)) {
            $ret["message"] = $this->translator->trans('There has been an error please try again');
            return $ret;
        }

        $this->entityManager->refreshEntity($coreUser);
        $this->entityManager->refreshEntity($user);

        $ret["error"] = false;
        $ret["core_user"] = $coreUser;
        $ret["user"] = $user;

        $enableOutgoing = $_ENV["ENABLE_OUTGOING_EMAIL"] ?? 1;
        if ($enableOutgoing && $sendEmail) {
            if (empty($this->mailManager)) {
                $this->mailManager = $this->container->get("mail_manager");
            }

            $session = $this->container->get("session");

            /** @var EmailTemplateManager $emailTemplateManager */
            $emailTemplateManager = $this->container->get('email_template_manager');

            if (isset($data["is_legal_entity"]) && $data["is_legal_entity"] == 1) {
                /** @var EmailTemplateEntity $template */
                $template = $emailTemplateManager->getEmailTemplateByCode("new_account_legal");
                if (!empty($template)) {
                    $templateData = $emailTemplateManager->renderEmailTemplate(
                        $coreUser,
                        $template,
                        null,
                        [
                            "user" => $user,
                            "core_user" => $coreUser,
                            "password" => $data["password"],
                            "send_password" => $sendPassword,
                            "is_legal_entity" => $data["is_legal_entity"],
                            "data" => $data
                        ]
                    );

                    $templateAttachments = $template->getAttachments();
                    if (!empty($templateAttachments)) {
                        $attachments = $template->getPreparedAttachments();
                    }

                    $this->mailManager->sendEmail(array('email' => $user->getEmail(), 'name' => $user->getEmail()), null, null, null, $templateData["subject"], "", null, [], $templateData["content"], $attachments ?? [], $session->get("current_store_id"));
                } else {
                    $this->mailManager->sendEmail(array('email' => $user->getEmail(), 'name' => $user->getEmail()), null, null, null, $this->translator->trans('New account'), "", "new_account_legal", array("user" => $user, "core_user" => $coreUser, "password" => $data["password"], "send_password" => $sendPassword, "is_legal_entity" => $data["is_legal_entity"], "data" => $data), null, array(), $session->get("current_store_id"));
                }
            } else {
                /** @var EmailTemplateEntity $template */
                $template = $emailTemplateManager->getEmailTemplateByCode("new_account");
                if (!empty($template)) {
                    $templateData = $emailTemplateManager->renderEmailTemplate(
                        $coreUser,
                        $template,
                        null,
                        [
                            "user" => $user,
                            "core_user" => $coreUser,
                            "password" => $data["password"],
                            "send_password" => $sendPassword,
                            "is_legal_entity" => $data["is_legal_entity"] ?? 0,
                            "data" => $data
                        ]
                    );

                    $templateAttachments = $template->getAttachments();
                    if (!empty($templateAttachments)) {
                        $attachments = $template->getPreparedAttachments();
                    }

                    $this->mailManager->sendEmail(array('email' => $user->getEmail(), 'name' => $user->getEmail()), null, null, null, $templateData["subject"], "", null, [], $templateData["content"], $attachments ?? [], $session->get("current_store_id"));
                } else {
                    $this->mailManager->sendEmail(array('email' => $user->getEmail(), 'name' => $user->getEmail()), null, null, null, $this->translator->trans('New account'), "", "new_account_not_legal", array("user" => $user, "core_user" => $coreUser, "password" => $data["password"], "send_password" => $sendPassword, "is_legal_entity" => $data["is_legal_entity"], "data" => $data), null, array(), $session->get("current_store_id"));
                }
            }

            if (!empty($secondaryEmailData)) {
                if (isset($secondaryEmailData["email"]) && isset($secondaryEmailData["template"])) {
                    /** @var EmailTemplateEntity $secondaryTemplate */
                    $secondaryTemplate = $emailTemplateManager->getEmailTemplateByCode($secondaryEmailData["template"]);
                    if (!empty($secondaryTemplate)) {
                        $emailTemplateManager->sendEmail(
                            $secondaryEmailData["template"],
                            $coreUser,
                            $session->get("current_store_id"),
                            [
                                'email' => $secondaryEmailData["email"],
                                'name' => $secondaryEmailData["email"]
                            ],
                            null,
                            null,
                            null,
                            $template->getAttachments() ?? [],
                            [
                                "user" => $user,
                                "core_user" => $coreUser,
                                "password" => $data["password"],
                                "send_password" => $sendPassword,
                                "is_legal_entity" => $data["is_legal_entity"],
                                "data" => $data
                            ]
                        );
                    } else {
                        $this->mailManager->sendEmail(array('email' => $secondaryEmailData["email"], 'name' => $secondaryEmailData["email"]), null, null, null, $this->translator->trans('New account'), "", $secondaryEmailData["template"], array("user" => $user, "core_user" => $coreUser, "password" => $data["password"], "send_password" => $sendPassword, "is_legal_entity" => $data["is_legal_entity"], "data" => $data));
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * @param $data
     * @return array
     */
    public function updateUser($data)
    {

        $ret = array();
        $ret["error"] = true;

        if (!isset($data["password"])) {
            $data["password"] = null;
        }

        if (empty($this->administrationManager)) {
            $this->administrationManager = $this->container->get("administration_manager");
        }
        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        $userData = array();
        $userData["id"] = $data["id"];
        $userData["email"] = $data["email"];
        $userData["username"] = $data["email"];
        $userData["google_authenticator_secret"] = null;
        $userData["system_role"] = "ROLE_USER";
        $userData["password"] = $data["password"];

        /** @var UserEntity $user */
        $user = $this->administrationManager->createUpdateUser($userData);
        if (empty($user)) {
            $ret["message"] = $this->translator->trans('Error updating user, please try again');
            return $ret;
        }

        $this->entityManager->refreshEntity($user);

        $ret["error"] = false;
        $ret["user"] = $user;

        return $ret;
    }

    /**
     * @param $id
     * @return |null
     */
    public function getAccountGroupById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(AccountGroupEntity::class);
        return $repository->find($id);
        /*$entityType = $this->entityManager->getEntityTypeByCode("account_group");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $username
     * @param string $emailTemplate
     * @param string $urlPattern
     * @param bool $sendEmail
     * @return bool|mixed
     * @throws \Exception
     */
    public function requestPasswordReset($username, $emailTemplate = "reset_password", $urlPattern = null, $sendEmail = true)
    {

        /** @var $entity UserInterface */
        $entity = $this->container->get('fos_user.user_manager')->findUserByUsernameOrEmail($username);

        if (null === $entity) {
            return false;
        }

        $session = $this->getContainer()->get("session");

        if (empty($urlPattern)) {
            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }

            $urlPattern = "/{$this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 71,"s_page")}?token={token}";
        }

        /*if ($user->isPasswordRequestNonExpired($this->container->getParameter('fos_user.resetting.token_ttl'))) {
            return $this->render('FOSUserBundle:Resetting:passwordAlreadyRequested.html.twig');
        }*/

        if (null === $entity->getConfirmationToken()) {
            /** @var $tokenGenerator \FOS\UserBundle\Util\TokenGeneratorInterface */
            $tokenGenerator = $this->container->get('fos_user.util.token_generator');
            $entity->setConfirmationToken($tokenGenerator->generateToken());
        }

        if (empty($this->mailManager)) {
            $this->mailManager = $this->container->get("mail_manager");
        }

        $confirmationUrl = str_replace("{token}", $entity->getConfirmationToken(), $urlPattern);

        if ($sendEmail && $_ENV["ENABLE_OUTGOING_EMAIL"] == 1) {

            /** @var EmailTemplateManager $emailTemplateManager */
            $emailTemplateManager = $this->container->get('email_template_manager');
            /** @var EmailTemplateEntity $template */
            $template = $emailTemplateManager->getEmailTemplateByCode($emailTemplate);
            if (!empty($template)) {
                $templateData = $emailTemplateManager->renderEmailTemplate($entity, $template, null, array("confirmation_url" => $confirmationUrl));
                $templateAttachments = $template->getAttachments();
                if (!empty($templateAttachments)) {
                    $attachments = $template->getPreparedAttachments();
                }
                $this->mailManager->sendEmail(
                    array('email' => $entity->getEmail(), 'name' => $entity->getEmail()),
                    null,
                    null,
                    null,
                    $templateData["subject"],
                    "",
                    null,
                    [],
                    $templateData["content"],
                    $attachments ?? [],
                    $session->get("current_store_id")
                );
            } else {
                $session = $this->container->get("session");
                $this->mailManager->sendEmail(array('email' => $entity->getEmail(), 'name' => $entity->getEmail()), null, null, null, $this->translator->trans("Reset password"), "", $emailTemplate, array("user" => $entity, "confirmationUrl" => $confirmationUrl), null, array(), $session->get("current_store_id"));
            }
        }

        $entity->setPasswordRequestedAt(new \DateTime());
        $this->container->get('fos_user.user_manager')->updateUser($entity);

        return $confirmationUrl;
    }

    /**
     * @param $token
     * @param $password
     * @return bool
     */
    public function setNewPassword($token, $password)
    {

        /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
        $userManager = $this->container->get('fos_user.user_manager');

        $entity = $userManager->findUserByConfirmationToken($token);

        if (null === $entity) {
            return false;
        }

        if (empty($this->administrationManager)) {
            $this->administrationManager = $this->container->get("administration_manager");
        }

        $entity = $this->administrationManager->setUserPassword($entity, $password);

        $entity->setConfirmationToken(null);
        $entity->setPasswordRequestedAt(null);

        $this->administrationManager->saveUser($entity);

        return true;
    }

    /**
     * @param $token
     * @return UserInterface
     */
    public function getUserByPasswordResetToken($token)
    {

        /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
        $userManager = $this->container->get('fos_user.user_manager');

        return $userManager->findUserByConfirmationToken($token);
    }

    /**
     * @param AccountEntity $account
     * @param int $limit
     * @return array
     */
    public function getMostSoldProductsByAccount(AccountEntity $account, $limit = 10)
    {

        $ret = array();

        /** @var DatabaseContext $databaseContext */
        $databaseContext = $this->container->get("database_context");

        $q = "SELECT itm.product_id as prod_id, SUM(qty) as total FROM order_item_entity as itm LEFT JOIN order_entity as ord on itm.order_id = ord.id WHERE ord.account_id = {$account->getId()} GROUP BY itm.product_id ORDER BY total DESC LIMIT 10";
        $data = $databaseContext->getAll($q);

        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[] = $d["prod_id"];
            }
        }

        return $ret;
    }

    /**
     * @return |null
     */
    public function getDefaultContact()
    {

        if (empty($this->helperManager)) {
            $this->helperManager = $this->getContainer()->get("helper_manager");
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();

        if (!empty($user)) {
            return $user->getDefaultContact();
        }

        return null;
    }
}
