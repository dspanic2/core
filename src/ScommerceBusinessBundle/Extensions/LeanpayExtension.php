<?php

namespace ScommerceBusinessBundle\Extensions;

use PaymentProvidersBusinessBundle\PaymentProviders\LeanpayProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LeanpayExtension extends \Twig_Extension
{
    /** @var LeanpayProvider $leanpayProvider */
    private $leanpayProvider;

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
            new \Twig_SimpleFunction('get_leanpay_installment_plans', array($this, 'getLeanpayInstallmentPlans')),
        ];
    }

    /**
     * @return array
     */
    public function getLeanpayInstallmentPlans($price)
    {
        if(empty($this->leanpayProvider)){
            $this->leanpayProvider = $this->container->get("leanpay_provider");
        }

        return $this->leanpayProvider->getInstallmentPlansForPrice($price);
    }
}
