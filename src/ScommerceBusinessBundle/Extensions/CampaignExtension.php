<?php

namespace ScommerceBusinessBundle\Extensions;

use CrmBusinessBundle\Entity\CampaignEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CampaignExtension extends \Twig_Extension
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('campaign_is_active', array($this, 'campaignIsActive')),
        ];
    }

    /**
     * @param CampaignEntity $campaign
     * @return false
     */
    function campaignIsActive(CampaignEntity $campaign = null)
    {
        if (empty($campaign)) {
            return false;
        }
        if (!$campaign->getActive()) {
            return false;
        }
        if ($campaign->getGoalReached()) {
            return false;
        }

        $currentDateTime = new \DateTime("now");
        if ((!empty($campaign->getStartDate()) && $campaign->getStartDate() > $currentDateTime) || (!empty($campaign->getEndDate()) && $campaign->getEndDate() < $currentDateTime)) {
            return false;
        }

        return true;
    }
}
