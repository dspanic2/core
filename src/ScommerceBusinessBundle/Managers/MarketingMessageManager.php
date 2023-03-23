<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use CrmBusinessBundle\Entity\CampaignEntity;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\MarketingMessageEntity;

class MarketingMessageManager extends AbstractScommerceManager
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @return mixed|null
     */
    public function getActiveMessages()
    {
        $ids = $this->getActiveMessagesIds();

        if (empty($ids)) {
            return [];
        }

        $session = $this->container->get("session");
        $storeId = $session->get("current_store_id");

        $entityType = $this->entityManager->getEntityTypeByCode("marketing_message");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", $ids)));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("ord", "asc"));

        $messages = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
        if (empty($messages)) {
            return [];
        }

        $ret = [];
        $currentDateTime = new \DateTime("now");
        /** @var MarketingMessageEntity $message */
        foreach ($messages as $message) {
            /** @var CampaignEntity $campaignActive */
            $campaignActive = $message->getCampaignActive();
            if (!empty($message->getCampaignActive())) {
                if (!$campaignActive->getActive()) {
                    continue;
                }
                if ($campaignActive->getGoalReached()) {
                    continue;
                }
                if ((!empty($campaignActive->getStartDate()) && $campaignActive->getStartDate() > $currentDateTime) || (!empty($campaignActive->getEndDate()) && $campaignActive->getEndDate() < $currentDateTime)) {
                    continue;
                }
            }
            $ret[] = $message;
        }

        return $ret;

    }

    /**
     * @return array
     */
    public function getActiveMessagesIds()
    {
        $where = "";

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }
        $excludeIds = [];
        $popupsShown = $_COOKIE["popups_shown"] ?? "";
        if (!empty($popupsShown)) {
            $popupsShown = json_decode($popupsShown, true);
            $excludeIds = array_merge($excludeIds, $popupsShown);
        }
        $floatersShown = $_COOKIE["floaters_shown"] ?? "";
        if (!empty($floatersShown)) {
            $floatersShown = json_decode($floatersShown, true);
            $excludeIds = array_merge($excludeIds, $floatersShown);
        }
        $excludeIds = array_unique($excludeIds);

        if (!empty($excludeIds)) {
            $excludeIds = implode(",", $excludeIds);
            $where = " AND id NOT IN ({$excludeIds})";
        }

        $q = "
SELECT
	id 
FROM
	marketing_message_entity 
WHERE
	active = 1 
	AND (active_from IS NULL OR TIMESTAMP( active_from ) <= TIMESTAMP(NOW())) 
	AND (active_to IS NULL OR TIMESTAMP( active_to ) >= TIMESTAMP(NOW()));
        {$where}
        ";
        $res = $this->databaseContext->executeQuery($q);

        if (!empty($res)) {
            return array_column($res, "id");
        }

        return [];
    }

}
