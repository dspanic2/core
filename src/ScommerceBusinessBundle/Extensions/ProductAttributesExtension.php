<?php

namespace ScommerceBusinessBundle\Extensions;

use CrmBusinessBundle\Entity\ProductEntity;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use ScommerceBusinessBundle\Managers\SproductManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductAttributesExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var SproductManager $sProductManager */
    protected $sProductManager;
    /** @var DefaultScommerceManager $defaultScommerceManager */
    protected $defaultScommerceManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_product_attribute_value_by_key', array($this, 'getProductAttributeValueByKey')),
            new \Twig_SimpleFunction('get_product_attribute_by_key', array($this, 'getProductAttributeByKey')),
            new \Twig_SimpleFunction('get_product_attribute_option_by_id', array($this, 'getProductAttributeOptionById')),
            new \Twig_SimpleFunction('get_product_attribute_values_by_key', array($this, 'getProductAttributeValuesByKey')),
            new \Twig_SimpleFunction('get_product_attribute_additional_parameters_by_key', array($this, 'getProductAttributeAdditionalParametersByKey')),
            new \Twig_SimpleFunction('get_product_delivery_date', array($this, 'getProductDeliveryDate')),
        ];
    }

    public function getProductAttributeValueByKey(ProductEntity $product, $attribute_key)
    {
        if (empty($this->sProductManager)) {
            $this->sProductManager = $this->container->get("s_product_manager");
        }

        return $this->sProductManager->getProductAttributeValueByKey($product, $attribute_key);
    }

    public function getProductAttributeByKey($attribute_key)
    {
        if (empty($this->sProductManager)) {
            $this->sProductManager = $this->container->get("s_product_manager");
        }

        return $this->sProductManager->getProductAttributeByKey($attribute_key);
    }

    public function getProductAttributeOptionById($optionId)
    {
        if (empty($this->sProductManager)) {
            $this->sProductManager = $this->container->get("s_product_manager");
        }

        return $this->sProductManager->getProductAttributeOptionById($optionId);
    }

    public function getProductAttributeValuesByKey($attribute_key, $productGroupId = null)
    {
        if (empty($this->sProductManager)) {
            $this->sProductManager = $this->container->get("s_product_manager");
        }

        return $this->sProductManager->getProductAttributeValuesByKey($attribute_key, $productGroupId);
    }

    public function getProductAttributeAdditionalParametersByKey($attribute_key)
    {
        if (empty($this->sProductManager)) {
            $this->sProductManager = $this->container->get("s_product_manager");
        }

        return $this->sProductManager->getProductAttributeAdditionalParametersByKey($attribute_key);
    }

    public function getProductDeliveryDate(ProductEntity $product)
    {

        if (empty($this->defaultScommerceManager)) {
            $this->defaultScommerceManager = $this->container->get("scommerce_manager");
        }

        return $this->defaultScommerceManager->getProductDeliveryDate($product);
    }
}
