<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Entity\TransactionEmailEntity;
use AppBundle\Entity\TransactionEmailSentEntity;
use AppBundle\Events\TransactionEmailSentEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

class TransactionEmailManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param $content
     * @param null $dateToSend
     */
    public function createUpdateTransactionEmail($content, $dateToSend = null, $relatedEntityTypeId = null, $relatedEntityId = null)
    {
        /** @var TransactionEmailEntity $transactionEmail */
        $transactionEmail = $this->entityManager->getNewEntityByAttributSetName("transaction_email");
        $transactionEmail->setContent(json_encode($content));
        $transactionEmail->setEmail($content["email"]);
        $transactionEmail->setSubject($content["subject"]);
        if (!empty($dateToSend)) {
            $transactionEmail->setDateToSend($dateToSend);
        }
        $transactionEmail->setSEntityType($relatedEntityTypeId);
        $transactionEmail->setEntityId($relatedEntityId);
        return $this->entityManager->saveEntityWithoutLog($transactionEmail);
    }

    /**
     * @return mixed
     */
    public function getTransactionEmailsToSend()
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->getContainer()->get("entity_manager");
        }

        $entityType = $this->entityManager->getEntityTypeByCode("transaction_email");

        $compositeFilters = new CompositeFilterCollection();

        /**
         * 1 = not sent
         * 2 = sent
         */
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        if (isset($_ENV["EMAIL_ERROR_NUMBER_OF_TRIES"]) && $_ENV["EMAIL_ERROR_NUMBER_OF_TRIES"] >= 0) {
            $compositeFilter2 = new CompositeFilter();
            $compositeFilter2->setConnector("or");
            $compositeFilter2->addFilter(new SearchFilter("numberOfTries", "lt", $_ENV["EMAIL_ERROR_NUMBER_OF_TRIES"]));
            $compositeFilter2->addFilter(new SearchFilter("numberOfTries", "nu"));
            $compositeFilters->addCompositeFilter($compositeFilter2);
        }

        $compositeFilters->addCompositeFilter($compositeFilter);

        $limit = 10;
        if(isset($_ENV["EMAIL_NUMBER_OF_EMAILS_TO_SEND"]) && !empty(intval($_ENV["EMAIL_NUMBER_OF_EMAILS_TO_SEND"]))){
            $limit = intval($_ENV["EMAIL_NUMBER_OF_EMAILS_TO_SEND"]);
        }

        $pagingFilter = new PagingFilter();
        $pagingFilter->setPageNumber(0);
        $pagingFilter->setPageSize($limit);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters, $pagingFilter);
    }

    /**
     * @param TransactionEmailEntity $transactionEmail
     */
    public function setTransactionEmailSent(TransactionEmailEntity $transactionEmail)
    {
        $transactionEmail->setError(0);
        $transactionEmail->setDateSent(new \DateTime("now"));
        $transactionEmail->setEntityStateId(2);
        $this->entityManager->saveEntityWithoutLog($transactionEmail);
    }

    /**
     * @param TransactionEmailEntity $transactionEmail
     * @param array $result
     */
    public function setTransactionEmailFailed(TransactionEmailEntity $transactionEmail, $result)
    {
        $transactionEmail->setError(1);
        $error = $result;
        if (is_array($result)) {
            $error = $result["message"] ?? "error when sending mail";
        }
        $transactionEmail->setErrorReason($error);

        $tried = $transactionEmail->getNumberOfTries();
        if (empty($tried)) {
            $tried = 1;
        } else {
            $tried++;
        }
        $transactionEmail->setNumberOfTries($tried);

        $this->entityManager->saveEntityWithoutLog($transactionEmail);
    }

    /**
     * @param TransactionEmailEntity $transactionEmail
     * @param $sentMimeMessage
     */
    public function dispatchTransactionEmailSent(TransactionEmailEntity $transactionEmail, $sentMimeMessage)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(TransactionEmailSentEvent::NAME, new TransactionEmailSentEvent($transactionEmail, $sentMimeMessage));
    }

    /**
     * @param int $limit
     * @return bool
     */
    public function transferMailToSentEmails($limit = 500){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        /**
         * Delete support emails
         */
        $q = "DELETE FROM transaction_email_entity WHERE (email = 'support@shipshape-solutions.com' OR email = 'service@shipshape-solutions.com' OR email = 'service@shipshape.hr' OR email = 'support@shipshape.hr') and entity_state_id = 2";
        $this->databaseContext->executeNonQuery($q);

        $q = "SELECT t.id, c.id as contact_id, t.date_sent, t.subject, t.content, t.entity_id, t.s_entity_type FROM transaction_email_entity AS t LEFT JOIN contact_entity as c ON t.email = c.email WHERE t.entity_state_id = 2 AND c.id is not null and date_sent is not null LIMIT {$limit};";
        $data = $this->databaseContext->getAll($q);

        $transactionEmailSentInsert = "INSERT INTO transaction_email_sent_entity (entity_type_id,attribute_set_id,created,modified,modified_by,created_by,entity_state_id,subject,contact_id,date_sent,content,entity_id,s_entity_type) VALUES ";
        $transactionEmailSentValues = Array();
        if(!empty($data)){

            if(empty($this->entityManager)){
                $this->entityManager = $this->container->get("entity_manager");
            }

            /** @var AttributeSet $asTransactionEmailSent */
            $asTransactionEmailSent = $this->entityManager->getAttributeSetByCode("transaction_email_sent");

            foreach ($data as $d){
                $transactionEmailSentValues[] = str_ireplace("''", "NULL", "({$asTransactionEmailSent->getEntityTypeId()},{$asTransactionEmailSent->getId()},NOW(),NOW(),'system','system',1,'".addslashes($d["subject"])."',{$d["contact_id"]},'{$d["date_sent"]}','".addslashes($d["content"])."','{$d["entity_id"]}','{$d["s_entity_type"]}')");
            }

            if(!empty($transactionEmailSentValues)){

                try{
                   $this->databaseContext->executeNonQuery($transactionEmailSentInsert.implode(",",$transactionEmailSentValues));
                }
                catch (\Exception $e){

                    if (empty($this->errorLogManager)) {
                        $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                    }

                    $this->errorLogManager->logExceptionEvent("Error transfering emails to archive", $e, true);

                    return false;
                }

                $ids = array_column($data,"id");
                $q = "DELETE FROM transaction_email_entity WHERE id IN (".implode(",",$ids).");";
                $this->databaseContext->executeNonQuery($q);

                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getTransactionEmailSentById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(TransactionEmailSentEntity::class);
        return $repository->find($id);
    }
}
