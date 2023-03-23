<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use ScommerceBusinessBundle\Entity\StaticContentEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EmailTemplateListener implements ContainerAwareInterface
{
    protected $container;

    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    /** @var EntityManager */
    protected $entityManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onEmailTemplateEntityPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var EmailTemplateEntity $entity */
        $entity = $event->getEntity();
        if ($entity->getEntityType()->getEntityTypeCode() == "email_template") {

            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }

            $code = StringHelper::convertStringToCode($entity->getName());

            $entity->setCode($code);
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onEmailTemplateEntityPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var EmailTemplateEntity $entity */
        $entity = $event->getEntity();
        if ($entity->getEntityType()->getEntityTypeCode() == "email_template") {

            if (empty($entity->getCode())) {
                $code = StringHelper::convertStringToCode($entity->getName());

                $entity->setCode($code);
            }
        }
    }
}
