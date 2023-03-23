<?php

namespace ScommerceBusinessBundle\Extensions;

use CrmBusinessBundle\Entity\LoyaltyCardEntity;
use CrmBusinessBundle\Managers\LoyaltyManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoyaltyExtension extends \Twig_Extension
{
    /** @var LoyaltyManager $loyaltyManager */
    protected $loyaltyManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_loyalty_points', array($this, 'getLoyaltyPoints')),
            new \Twig_SimpleFunction('get_loyalty_points_by_card_id', array($this, 'getLoyaltyPointsByCardId'))
        ];
    }

    /**
     * @return array
     */
    public function getLoyaltyPoints()
    {
        if(empty($this->loyaltyManager)){
            $this->loyaltyManager = $this->container->get("loyalty_manager");
        }

        /** @var LoyaltyCardEntity $loyaltyCard */
        $loyaltyCard = $this->loyaltyManager->getCurrentLoyaltyCard();

        if(empty($loyaltyCard)){
            return null;
        }

        $availablePoints = $this->loyaltyManager->getAvailableLoyaltyPoints($loyaltyCard);
        $availableDiscounts = $this->loyaltyManager->getAvailableLoyaltyDiscountLevels($availablePoints);

        return [
            "points" => $availablePoints,
            "discounts" => $availableDiscounts,
            "selectedPercentage" => intval($loyaltyCard->getPercentDiscount())
        ];
    }

    /**
     * @return int
     */
    public function getLoyaltyPointsByCardId($loyaltyCardId)
    {
        if(empty($this->loyaltyManager)){
            $this->loyaltyManager = $this->container->get("loyalty_manager");
        }

        /** @var LoyaltyCardEntity $loyaltyCard */
        $loyaltyCard = $this->loyaltyManager->getLoyaltyCardById($loyaltyCardId);

        if(empty($loyaltyCard)){
            return null;
        }

        $availablePoints = $this->loyaltyManager->getAvailableLoyaltyPoints($loyaltyCard);

        return $availablePoints;
    }
}
