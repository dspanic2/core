<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\ProductExportEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductExportListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onProductExportPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var ProductExportEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_export") {

            $password = StringHelper::generateRandomString(16);
            $secretKey = StringHelper::generateHash($entity->getAccount()->getId(), $password);

            $entity->setPassword($password);
            $entity->setSecretKey($secretKey);

            $basePath = $this->container->getParameter('web_path');

            $exportUrl = $basePath."Documents/export/".$secretKey.".xml";

            $entity->setAccessUrl($exportUrl);
        }
    }
}
