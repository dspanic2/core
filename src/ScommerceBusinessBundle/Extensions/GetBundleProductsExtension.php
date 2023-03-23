<?php

namespace ScommerceBusinessBundle\Extensions;

use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Managers\ProductManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GetBundleProductsExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;
    /** @var ProductManager $productManager */
    protected $productManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('get_bundle_products', array($this, 'getBundleProducts')),
        ];
    }

    public function getBundleProducts($product)
    {
        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_BUNDLE) {
            if (empty($this->productManager)) {
                $this->productManager = $this->container->get('product_manager');
            }
            return $this->productManager->getBundleProductDetails($product);
        }
        return [];
    }
}
