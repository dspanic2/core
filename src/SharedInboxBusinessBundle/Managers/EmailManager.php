<?php

namespace SharedInboxBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\MailManager;
use SharedInboxBusinessBundle\Constants\SharedInboxConstants;
use SharedInboxBusinessBundle\Entity\EmailEntity;
use SharedInboxBusinessBundle\Entity\SharedInboxConnectionEntity;
use SharedInboxBusinessBundle\Interfaces\IEmailProvider;

class EmailManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var AttributeSet $connectionSet */
    protected $connectionSet;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->mailManager = $this->container->get("mail_manager");
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getEmailById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(EmailEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $parentEmailId
     * @return mixed |null
     */
    public function getEmailsByParentId($parentEmailId)
    {
        $etEmail = $this->entityManager->getEntityTypeByCode("email");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("parentEmailId", "eq", $parentEmailId));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilter = new SortFilter();
        $sortFilter->setDirection("asc");
        $sortFilter->setField("mailDatetime");

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter($sortFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($etEmail, $compositeFilters, $sortFilters);
    }

    /**
     * @param $messageId
     * @return |null
     */
    public function getEmailByMessageId($messageId)
    {
        $etEmail = $this->entityManager->getEntityTypeByCode("email");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("messageId", "eq", $messageId));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($etEmail, $compositeFilters);
    }

    /**
     * @param $sharedInboxConnectionId
     * @return array
     */
    public function getEmailsByMessagesId($sharedInboxConnectionId){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT id, message_id FROM email_entity WHERE shared_inbox_connection_id = {$sharedInboxConnectionId};";
        $data = $this->databaseContext->getAll($q);

        $ret = array_column($data,"message_id");

        $ret = array_flip($ret);

        return $ret;
    }

    /**
     * @param $additionalFilter
     * @return mixed
     */
    public function getFilteredConnections($additionalFilter = null){

        if(empty($this->connectionSet)){
            $this->connectionSet = $this->entityManager->getAttributeSetByCode("shared_inbox_connection");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if(!empty($additionalFilter)){
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        return $this->entityManager->getEntitiesByAttributeSetAndFilter($this->connectionSet, $compositeFilters);
    }

    /**
     * @param $additionalFilter
     * @return void
     */
    public function importEmails($additionalFilter = null)
    {
        $connections = $this->getFilteredConnections($additionalFilter);

        /** @var SharedInboxConnectionEntity $connection */
        foreach ($connections as $connection) {
            /** @var IEmailProvider $provider */
            $provider = $this->getContainer()->get(strtolower($connection->getType()->getName()) . "_provider");
            $provider->setConnection($connection);
            $provider->getEmails("INBOX");
            $provider->getEmails("INBOX.Sent");
        }
    }

    /**
     * @param EmailEntity $email
     * @param $message
     * @return bool
     */
    public function sendReplyEmail(EmailEntity $email, $message, $cc = Array())
    {
        $data = array();
        $data["headers"] = array(
            "In-Reply-To" => $email->getMessageId(),
            "References" =>
                !empty($email->getReferencesIds()) ?
                    $email->getReferencesIds() . " " . $email->getMessageId() :
                    $email->getMessageId()
        );

        $html = $this->twig->render("SharedInboxBusinessBundle:Email:email_reply.html.twig",
            array("message" => $message, "email" => $email));

        $subject = $email->getSubject();
        if (stripos($subject, "RE: ") === false) {
            $subject = "RE: " . $subject;
        }

        $to = array();
        $to["email"] = $email->getMailFrom();
        $to["name"] = $email->getMailFrom();

        $mailTo = explode("; ", $email->getMailTo());
        if (count($mailTo) > 1) {
            foreach ($mailTo as $key => $value) {
                if ($key >= 1) {
                    $value = trim($value);
                    $cc[$key]["email"] = $value;
                    $cc[$key]["name"] = $value;
                }
            }
        }

        if (empty($cc) && !empty($email->getCc())) {
            foreach (explode("; ", $email->getCc()) as $key => $value) {
                $value = trim($value);
                $cc[$key]["email"] = $value;
                $cc[$key]["name"] = $value;
            }
        }

        $bcc = array();
        if (!empty($email->getBcc())) {
            foreach (explode("; ", $email->getBcc()) as $key => $value) {
                $value = trim($value);
                $bcc[$key]["email"] = $value;
                $bcc[$key]["name"] = $value;
            }
        }

        return $this->mailManager->sendEmail(
            $to,
            $cc,
            $bcc,
            null,
            $subject,
            null,
            null,
            $data,
            $html);
    }

    /**
     * @param EmailEntity $email
     * @return array
     */
    public function parseCorrespondence(EmailEntity $email)
    {
        $ret = array(
            "email" => $email,
            "replies" => array()
        );

        $emails = $this->getEmailsByParentId($email->getId());
        if (!empty($emails)) {
            /** @var EmailEntity $e */
            foreach ($emails as $e) {
                $temp = $this->parseCorrespondence($e);
                if (!empty($temp)) {
                    $ret["replies"][$e->getId()] = $temp;
                }
            }
        }

        return $ret;
    }

    /**
     * @param int $limit
     * @return mixed
     */
    public function getUnprocessedEmails($limit = 100)
    {
        $etEmail = $this->entityManager->getEntityTypeByCode("email");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("state", "eq", SharedInboxConstants::EMAIL_STATE_UNPROCESSED));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "asc"));

        /**default limit to number of returned */
        $pagingFilter = new PagingFilter();
        $pagingFilter->setPageNumber(0);
        $pagingFilter->setPageSize($limit);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($etEmail, $compositeFilters, $sortFilters, $pagingFilter);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getSharedInboxConnectionById($id){

        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(SharedInboxConnectionEntity::class);
        return $repository->find($id);
    }


    /**
     * @param $data
     * @param SharedInboxConnectionEntity|null $sharedInboxConnection
     * @param $skipLog
     * @return SharedInboxConnectionEntity|null
     */
    public function insertUpdateSharedInboxConnection($data, SharedInboxConnectionEntity $sharedInboxConnection = null, $skipLog = true){

        if (empty($sharedInboxConnection)) {
            /** @var SharedInboxConnectionEntity $sharedInboxConnection */
            $sharedInboxConnection = $this->entityManager->getNewEntityByAttributSetName("shared_inbox_connection");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($sharedInboxConnection, $setter)) {
                $sharedInboxConnection->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($sharedInboxConnection);
        } else {
            $this->entityManager->saveEntity($sharedInboxConnection);
        }
        $this->entityManager->refreshEntity($sharedInboxConnection);

        return $sharedInboxConnection;
    }

    /**
     * @param $data
     * @param EmailEntity|null $email
     * @param $skipLog
     * @return EmailEntity|SharedInboxConnectionEntity|null
     */
    public function insertUpdateEmail($data, EmailEntity $email = null, $skipLog = true){

        if (empty($email)) {
            /** @var EmailEntity $email */
            $email = $this->entityManager->getNewEntityByAttributSetName("email");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($email, $setter)) {
                $email->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($email);
        } else {
            $this->entityManager->saveEntity($email);
        }
        $this->entityManager->refreshEntity($email);

        return $email;
    }
}
