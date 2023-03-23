<?php

namespace CrmBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Controller\ListViewController;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\ListView;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\ListViewManager;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Managers\EmailTemplateManager;
use Doctrine\Common\Util\Inflector;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\Config\Definition\Exception\Exception;

class EmailTemplatePreviewBlock extends AbstractBaseBlock
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var  BlockManager $entityManager */
    protected $blockManager;
    /**@var ListViewContext $listViewContext */
    protected $listViewContext;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /**@var FactoryContext $factoryContext */
    protected $factoryContext;
    /**@var AttributeContext $factoryContext */
    protected $attributeContext;

    public function GetPageBlockTemplate()
    {
        return ('CrmBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
    }

    public function GetPageBlockData()
    {
        $this->pageBlockData["model"]["content"] = "";

        $this->entityManager = $this->container->get("entity_manager");

        $etEmailTemplate = $this->entityManager->getEntityTypeByCode("email_template");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var EmailTemplateEntity $emailTemplate */
        $emailTemplate = $this->entityManager->getEntityByEntityTypeAndId($etEmailTemplate, $this->pageBlockData["id"]);

        if (!empty($emailTemplate)) {
            $this->pageBlockData["model"]["email_template"] = $emailTemplate;

            // Fetch first entity
            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->container->get("database_context");

            $tableName = "{$emailTemplate->getProvidedEntityTypeId()}_entity";

            // Izuzetci kada entitet ne prati pattern
            if ($emailTemplate->getProvidedEntityTypeId() == "core_user") {
                $tableName = "user_entity";
            }

            $entityId = $emailTemplate->getEntityIdentifier();
            if(empty($entityId)){
                $q = "SELECT id FROM {$tableName} ORDER BY id ASC LIMIT 1";
                $data = $databaseContext->getSingleEntity($q);

                $entityId = $data["id"];
            }

            $entity = $this->entityManager->getEntityByEntityTypeAndId($this->entityManager->getEntityTypeByCode($emailTemplate->getProvidedEntityTypeId()), $entityId);

            if (!empty($entity)) {
                // Render email content
                /** @var RouteManager $routeManager */
                $routeManager = $this->container->get("route_manager");
                /** @var EmailTemplateManager $emailTemplateManager */
                $emailTemplateManager = $this->container->get("email_template_manager");

                $websiteData = $routeManager->getWebsiteDataById($_ENV["DEFAULT_WEBSITE_ID"]);

                $emailData = $emailTemplateManager->renderEmailTemplate($entity, $emailTemplate);

                $this->pageBlockData["model"]["content"] = $emailData["content"];
                $this->pageBlockData["model"]["data"] = [
                    "subject" => $emailData["subject"],
                    "site_base_data" => $routeManager->prepareSiteBaseData($_ENV["DEFAULT_WEBSITE_ID"]),
                ];
                $this->pageBlockData["model"]["data"]["current_language"] = $_ENV["DEFAULT_LANG"];
                $this->pageBlockData["model"]["data"]["site_base_data"]["site_base_url"] = $_ENV["SSL"] . "://" . $websiteData["base_url"] . "/";
                $this->pageBlockData["model"]["data"]["site_base_data"]["site_base_url_language"] = $_ENV["SSL"] . "://" . $websiteData["base_url"] . $routeManager->getLanguageUrl($routeManager->getStoreById($_ENV["DEFAULT_STORE_ID"])) . "/";
            }
        }

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'CrmBusinessBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
    }
}
