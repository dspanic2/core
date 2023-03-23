<?php

namespace CrmBusinessBundle\Extensions;

use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Managers\BarcodeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GenerateMoneyTransferPaymentSlipExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var BarcodeManager $barcodeManager */
    protected $barcodeManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('generate_money_transfer_payment_slip', array($this, 'generateMoneyTransferPaymentSlip')),
        ];
    }

    public function generateMoneyTransferPaymentSlip(OrderEntity $order)
    {
        if(empty($this->barcodeManager)){
            $this->barcodeManager = $this->container->get("barcode_manager");
        }

        return $this->barcodeManager->generatePDF417Barcode($order);
    }
}
