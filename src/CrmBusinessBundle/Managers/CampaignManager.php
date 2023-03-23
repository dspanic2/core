<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\CampaignEntity;
use CrmBusinessBundle\Entity\DiscountCartRuleEntity;
use CrmBusinessBundle\Entity\DiscountCatalogEntity;
use CrmBusinessBundle\Entity\ProductLabelEntity;
use Doctrine\Common\Util\Inflector;

class CampaignManager extends AbstractBaseManager
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var EmailTemplateManager $emailTemplateManager */
    protected $emailTemplateManager;


    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param null $additionalFilter
     * @return null
     */
    public function getFilteredCampaigns($additionalFilter = null)
    {

        $et = $this->entityManager->getEntityTypeByCode("campaign");

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

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param $data
     * @param CampaignEntity|null $campaign
     * @param false $skipLog
     * @return CampaignEntity|null
     */
    public function createUpdateCampaign($data, CampaignEntity $campaign = null, $skipLog = false)
    {
        if (empty($campaign)) {
            /** @var CampaignEntity $campaign */
            $campaign = $this->entityManager->getNewEntityByAttributSetName("campaign");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($campaign, $setter)) {
                $campaign->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($campaign);
        } else {
            $this->entityManager->saveEntity($campaign);
        }
        $this->entityManager->refreshEntity($campaign);

        return $campaign;
    }

    /**
     * @param CampaignEntity $campaign
     * @return bool
     */
    public function setGoalReached(CampaignEntity $campaign){

        $data = Array();
        $data["goal_reached"] = 1;

        $this->createUpdateCampaign($data,$campaign);

        /*if(!empty($campaign->getDiscountCoupon()) && $campaign->getDiscountCoupon()->getIsActive()){
            $discountCoupon = $campaign->getDiscountCoupon();

            $discountCoupon->setIsActive(0);
            $this->entityManager->saveEntity($discountCoupon);
        }*/

        $discountCatalogs = $campaign->getDiscountCatalogs();
        if(EntityHelper::isCountable($discountCatalogs) && count($discountCatalogs) > 0){

            /** @var DiscountCatalogEntity $discountCatalog */
            foreach ($discountCatalogs as $discountCatalog){
                if($discountCatalog->getIsActive()){
                    $discountCatalog->setIsActive(0);
                    $this->entityManager->saveEntity($discountCatalog);
                }
            }
        }

        $discountCartRules = $campaign->getDiscountCartRules();
        if(EntityHelper::isCountable($discountCartRules) && count($discountCartRules) > 0){

            /** @var DiscountCartRuleEntity $discountCartRule */
            foreach ($discountCartRules as $discountCartRule){
                if($discountCartRule->getIsActive()){
                    $discountCartRule->setIsActive(0);
                    $this->entityManager->saveEntity($discountCartRule);
                }
            }
        }

        /**
         * Labele
         */
        $et = $this->entityManager->getEntityTypeByCode("product_label");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("campaignActive", "eq", $campaign->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "asc"));

        $labels = $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
        if(EntityHelper::isCountable($labels) && count($labels) > 0){

            /** @var ProductLabelEntity $label */
            foreach ($labels as $label){
                if($label->getIsActive()){
                    $label->setIsActive(0);
                    $this->entityManager->saveEntity($label);
                }
            }
        }

        /**
         * Send campaign goal reached email
         */
        if(!empty($campaign->getEmailNotify()) && !empty($campaign->getEmailContent())){
            $emails = explode(",",$campaign->getEmailNotify());

            if(empty($this->emailTemplateManager)){
                $this->emailTemplateManager = $this->container->get("email_template_manager");
            }

            $email = $emails[0];
            unset($emails[0]);
            $to = Array('email' => $email, 'name' => $email);

            $cc = Array();
            if(!empty($emails)){
                foreach ($emails as $email2){
                    $cc[] = Array('email' => $email2, 'name' => $email2);
                }
            }

            $this->emailTemplateManager->sendEmail("campaign_goal_reached",$campaign,$_ENV["DEFAULT_STORE_ID"],$to,null,$cc);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function campaignEnded(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $now = new \DateTime();

        $q = "SELECT c.id FROM campaign_entity as c LEFT JOIN discount_coupon_entity as dc ON c.discount_coupon_id = dc.id AND dc.entity_state_id = 1 WHERE goal_reached = 0 AND (
            end_date < '".$now->format("Y-m-d H:i:s")."' OR dc.is_active = 0 OR dc.date_valid_to < '".$now->format("Y-m-d H:i:s")."' OR c.id IN (SELECT campaign_id FROM campaign_discount_catalog_link_entity as cd LEFT JOIN discount_catalog_entity AS d ON cd.discount_catalog_id = d.id WHERE d.entity_state_id = 1 AND (d.is_active = 0 OR d.date_to < '".$now->format("Y-m-d H:i:s")."'))
            OR c.id IN (SELECT campaign_id FROM campaign_discount_cart_rule_link_entity as cd LEFT JOIN discount_cart_rule_entity AS d ON cd.discount_cart_rule_id = d.id WHERE d.entity_state_id = 1 AND (d.is_active OR d.date_valid_to < '".$now->format("Y-m-d H:i:s")."'))
        )";
        $data = $this->databaseContext->getAll($q);

        if(empty($data)){
            return true;
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("id", "id", implode(",",array_column($data,"id"))));

        $campaigns = $this->getFilteredCampaigns($compositeFilter);

        if(EntityHelper::isCountable($campaigns) && count($campaigns)){

            /** @var CampaignEntity $campaign */
            foreach ($campaigns as $campaign){
                $this->setGoalReached($campaign);
            }
        }

        return true;
    }

}
