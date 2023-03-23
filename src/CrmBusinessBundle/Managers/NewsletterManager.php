<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\Email;
use AppBundle\Entity\ImapConnection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Interfaces\Providers\IEmailProvider;
use AppBundle\Managers\EntityManager;
use AppBundle\Models\MailAddress;
use AppBundle\Models\MailAddressCollection;
use AppBundle\Models\MailAttachment;
use AppBundle\Models\MailAttachmentCollection;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\NewsletterEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NewsletterManager extends AbstractBaseManager
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var IEmailProvider $newsletterMailProvider */
    protected $newsletterMailProvider;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var DefaultScommerceManager $sCommerceManager */
    protected $sCommerceManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @return ImapConnection
     */
    public function initializeConnection()
    {

        $connection = new ImapConnection();
        $connection->setUsername($_ENV["NEWSLETTER_IMAP_USERNAME"]);
        $connection->setPassword($_ENV["NEWSLETTER_IMAP_PASSWORD"]);
        $connection->setHost($_ENV["NEWSLETTER_IMAP_HOST"]);
        $connection->setPort($_ENV["NEWSLETTER_IMAP_PORT"]);
        $connection->setDebug($_ENV["NEWSLETTER_IMAP_DEBUG"]);
        $connection->setAuth($_ENV["NEWSLETTER_IMAP_AUTH"]);

        return $connection;
    }

    /**
     * @param $uid
     * @return |null
     */
    public function getNewsletterByUid($uid)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("newsletter");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("uid", "eq", $uid));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $email
     * @param $websiteId
     * @return |null
     */
    public function getNewsletterByEmail($email, $websiteId = null)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("newsletter");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("email", "eq", $email));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        if (!empty($websiteId)) {
            $compositeFilter->addFilter(new SearchFilter("store.website.id", "eq", $websiteId));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @return mixed
     */
    public function getActiveNewsletters()
    {
        $entityType = $this->entityManager->getEntityTypeByCode("newsletter");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("active", "eq", true));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $data
     * @param bool $doNotUnsubscribe
     * @return bool
     * @throws \Exception
     */
    public function insertUpdateNewsletterSubscriber($data,$doNotUnsubscribe = false)
    {
        $ret = Array();
        $ret["is_new"] = false;

        if (empty($data["store"])) {
            $data["store"] = $_ENV["DEFAULT_STORE_ID"];
        }

        /** @var SStoreEntity $store */
        $store = null;
        $websiteId = null;
        if (!empty($data["store"])) {
            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get('route_manager');
            }
            /** @var SStoreEntity $store */
            $store = $this->routeManager->getStoreById($data["store"]);
            $websiteId = $store->getWebsiteId();
        }

        /**
         * Check if exists
         */
        /** @var NewsletterEntity $subscription */
        $subscription = $this->getNewsletterByEmail($data["email"], $websiteId);

        if (empty($subscription) && empty($data["active"])) {
            return false;
        } elseif (empty($subscription)) {
            $subscription = $this->entityManager->getNewEntityByAttributSetName("newsletter");

            $subscription->setEmail($data["email"]);
            $subscription->setUid(md5(time() . $data["email"]));
            $ret["is_new"] = true;
        }
        elseif ($doNotUnsubscribe && empty($data["active"])){
            return false;
        }

        if (!empty($store)) {
            $subscription->setStore($store);
        }
        $subscription->setActive($data["active"]);
        if ($data["active"]) {
            $subscription->setDateUnsubscribed(null);
        } else {
            $subscription->setDateUnsubscribed(new \DateTime());
        }
        if (isset($data["contact"]) && !empty($data["contact"])) {
            $subscription->setContact($data["contact"]);
            $subscription->setFirstName($data["contact"]->getFirstName());
            $subscription->setLastName($data["contact"]->getLastName());
            $subscription->setAccount($data["contact"]->getAccount());
        }

        $this->entityManager->saveEntityWithoutLog($subscription);

        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $this->crmProcessManager->afterNewsletterSubscriptionChanged($subscription, $ret["is_new"]);

        return $ret;
    }

    /**
     * @param ContactEntity $contact
     * @return bool
     */
    public function removeContactFromNewsletter(ContactEntity $contact)
    {

        if (empty($this->sCommerceManager)) {
            $this->sCommerceManager = $this->container->get("scommerce_manager");
        }

        $this->sCommerceManager->removeContactFromNewsletter($contact);

        return true;
    }

    /**
     * @param int $limit
     * @return mixed[]
     */
    public function getNewsletterEmailsToSend($limit = 1000)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT * FROM newsletter_transaction_email_entity WHERE entity_state_id = 1 and (error is null or error = 0) AND (date_to_send is null or date_to_send <= NOW()) ORDER BY id desc LIMIT {$limit}";

        return $this->databaseContext->getAll($q);
    }

    /**
     * @param int $limit
     * @return bool
     */
    public function sendBulkNewsletterEmail($limit = 1000)
    {
        $transactionEmails = $this->getNewsletterEmailsToSend($limit);

        if (empty($transactionEmails)) {
            return true;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $connection = $this->initializeConnection();

        if (empty($this->newsletterMailProvider)) {
            $this->newsletterMailProvider = $this->container->get("newsletter_mail_provider");
        }

        $this->newsletterMailProvider->initialize($connection);

        $sentIds = array();

        foreach ($transactionEmails as $transactionEmail) {
            $data = json_decode($transactionEmail["content"], true);
            if (!isset($data['to'])) {
                throw new MissingMandatoryParametersException("to missing");
            }

            $email = new Email();

            reset($data['to']);
            $key = key($data['to']);

            $email->setSubject($transactionEmail["subject"]);
            $email->setText($data['text'] ?? "");
            $email->setHtml($data['html'] ?? null);

            $toAddress = new MailAddress($key, $data['to'][$key]);
            $toAddressCollection = new MailAddressCollection();
            $toAddressCollection->addMailAddress($toAddress);
            $email->setTo($toAddressCollection);

            $fromAddress = new MailAddress($data['from'][0], $data['from'][1]);
            $email->setFrom($fromAddress);

            $addressCollection = null;
            if (!empty($data['cc'])) {
                $addressCollection = new MailAddressCollection();
                foreach ($data['cc'] as $cc) {
                    reset($cc);
                    $key = key($cc);
                    $ccAddress = new MailAddress($key, $cc[$key]);
                    $addressCollection->addMailAddress($ccAddress);
                }
            }
            $email->setCc($addressCollection);

            $addressCollection = null;
            if (!empty($data['bcc'])) {
                $addressCollection = new MailAddressCollection();
                foreach ($data['bcc'] as $bcc) {
                    reset($bcc);
                    $key = key($bcc);
                    $bccAddress = new MailAddress($key, $bcc[$key]);
                    $addressCollection->addMailAddress($bccAddress);
                }
            }
            $email->setBcc($addressCollection);

            $replayTo = null;
            if (!empty($data['replyto'])) {
                if (!is_array($data['replyto'])) {
                    $data['replyto'] = array('email' => $data["replyto"], 'name' => $data["replyto"]);
                }
                reset($data['replyto']);
                $key = key($data['replyto']);

                $replayTo = new MailAddress($key, $data['replyto'][$key]);
            }
            $email->setReplyTo($replayTo);

            $mailAttachments = new MailAttachmentCollection();

            foreach ($data['attachment'] ?? array() as $item) {
                $attachment = new MailAttachment();
                $attachment->setUrl($item);
                $mailAttachments->addAttachment($attachment);
            }

            $email->setAttachments($mailAttachments);

            $result = $this->newsletterMailProvider->sendSingleEmail($email);

            unset($email);

            if (isset($result['error']) && $result['error']) {
                // Sending failed
                $q = "UPDATE newsletter_transaction_email_entity SET entity_state_id = 1, error = 1, error_reason = '{$result["result"]}' WHERE id = {$transactionEmail["id"]}";
                $this->databaseContext->executeNonQuery($q);
            } else {
                // Sending succeeded
                $q = "UPDATE newsletter_transaction_email_entity SET entity_state_id = 2, content = NULL, date_sent = NOW(), error = 0 WHERE id = {$transactionEmail["id"]}";
                $this->databaseContext->executeNonQuery($q);
                $sentIds[] = $transactionEmail["id"];
            }
        }

        /*if (!empty($sentIds)) {
          $q = "UPDATE newsletter_transaction_email_entity SET entity_state_id = 2, date_sent = NOw(), error = 0 WHERE id IN (" . implode(",", $sentIds) . ")";
          $this->databaseContext->executeNonQuery($q);
          $sentIds = Array();
        }*/

        return true;
    }

    /**
     * @return bool
     */
    public function deleteSentEmails()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "DELETE FROM newsletter_transaction_email_entity WHERE entity_state_id = 2;";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param $email
     * @param $storeID
     * @return bool
     */
    public function userIsSubscribed($email, $storeId = null)
    {
        if (empty($storeID)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }
        $q = "SELECT id FROM newsletter_entity WHERE entity_state_id = 1 AND email = '{$email}' AND store_id = {$storeId};";
        $result = $this->databaseContext->getSingleEntity($q);
        
        return !empty($result);
    }
}
