<?php

namespace ScommerceBusinessBundle\Extensions;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Entity\ProductEntity;
use Mobile_Detect;
use ScommerceBusinessBundle\Entity\SWebsiteEntity;
use ScommerceBusinessBundle\Managers\FrontProductsRulesManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductRulesExtension extends \Twig_Extension
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
            new \Twig_SimpleFunction('get_entity_active_rules', array($this, 'getEntityActiveRules')),
        ];
    }

    /**
     * Get rules from entity type that fit for provided product.
     *
     * @param $manager
     * @param $entityTypeCode
     * @param ProductEntity $product
     * @param bool $returnAll
     * @return string|array
     */
    public function getEntityActiveRules($manager, $entityTypeCode, ProductEntity $product, bool $returnAll = false)
    {
        if ($this->container->has($manager)) {
            /** @var FrontProductsRulesManager $manager */
            $manager = $this->container->get($manager);

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));

            $entityRules = $manager->getRulesByEntityTypeCode($entityTypeCode, $compositeFilter);
            if (!empty($entityRules)) {
                $ret = [];
                foreach ($entityRules as $ruleEntity) {
                    if (EntityHelper::checkIfMethodExists($ruleEntity, "getRules")) {
                        $rules = $ruleEntity->getRules();
                        if (empty(json_decode($rules, true))) {
                            continue;
                        }
                        if ($manager->productMatchesRules($product, $rules, $ruleEntity)) {
                            if ($returnAll) {
                                $ret[] = $ruleEntity;
                            } else {
                                return $ruleEntity;
                            }
                        }
                    }
                }
                if ($returnAll) {
                    return $ret;
                }
            }
        }
        return null;
    }
}
