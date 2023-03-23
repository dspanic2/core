<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractAutocompleteManager;
use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\StringHelper;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use Doctrine\Common\Inflector\Inflector;
use AppBundle\Managers\EntityManager;

class ContactAutocompleteManager extends AbstractAutocompleteManager
{
    public function initialize()
    {
        parent::initialize();
    }

    public function getAutoComplete($term, $attribute, $formData)
    {
        $account = null;

        if (isset($formData["account_id"]) && !empty($formData["account_id"])) {
            /** @var AccountManager $accountManager */
            $accountManager = $this->container->get("account_manager");

            $account = $accountManager->getAccountById($formData["account_id"]);
        } elseif (isset($formData["quote_id"]) && !empty($formData["quote_id"])) {

            /** @var QuoteManager $quoteManager */
            $quoteManager = $this->container->get("quote_manager");

            /** @var QuoteEntity $quote */
            $quote = $quoteManager->getQuoteById($formData["quote_id"]);

            $account = $quote->getAccount();
        } elseif (isset($formData["order_id"]) && !empty($formData["order_id"])) {

            /** @var OrderManager $orderManager */
            $orderManager = $this->container->get("order_manager");

            /** @var OrderEntity $order */
            $order = $orderManager->getOrderById($formData["order_id"]);

            $account = $order->getAccount();
        } elseif (isset($formData["project_id"]) && !empty($formData["project_id"])) {

            $et = $this->entityManager->getEntityTypeByCode("project");

            $project = $this->entityManager->getEntityByEntityTypeAndId($et,$formData["project_id"]);

            $account = $project->getAccount();

            if(empty($account)){
                return null;
            }
        }

        /**default limit to number of returned */
        $pagingFilter = new PagingFilter();
        $pagingFilter->setPageNumber(0);
        $pagingFilter->setPageSize(50);
        $filterAttribute = $attribute->getLookupAttribute();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        if (!empty($account)) {
            $compositeFilter->addFilter(new SearchFilter("account", "eq", $account->getId()));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($term)) {
            $terms = explode(" ", $term);
            foreach ($terms as $term) {
                if (strlen(trim($term)) < 2) {
                    continue;
                }
                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector("or");
                //$compositeFilter->addFilter(new SearchFilter(Inflector::camelize($attribute->getLookupAttribute()->getAttributeCode()), "bw", $term));
                $compositeFilter->addFilter(new SearchFilter("account.name", "bw", $term));
                $compositeFilter->addFilter(new SearchFilter("lastName", "bw", $term));
                $compositeFilter->addFilter(new SearchFilter("firstName", "bw", $term));
                $compositeFilters->addCompositeFilter($compositeFilter);
            }
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("lastName", "asc"));
        $sortFilters->addSortFilter(new SortFilter("firstName", "asc"));

        $entities = $this->entityManager->getEntitiesByEntityTypeAndFilter($filterAttribute->getEntityType(), $compositeFilters, $sortFilters, $pagingFilter);

        return $entities;
    }

    public function renderTemplate($data, $template, $attribute)
    {

        $ret = array();

        if ($template == null) {
            $template = "default";
        }

        $attributeCode = Inflector::camelize($attribute->getLookupAttribute()->getAttributeCode());

        $lookupAttribute = $this->attributeContext->getById($attribute->getLookupAttribute());
        $filterAttribute = $this->attributeContext->getById($lookupAttribute);
        $attributeCode = Inflector::camelize($filterAttribute->getAttributeCode());


        if (!empty($data)) {
            foreach ($data as $key => $d) {
                $ret[$key]["id"] = $d->getId();
                $ret[$key]["html"] = $this->twig->render("CrmBusinessBundle:AutocompleteTemplates:contact.html.twig", array('field_data' => $d, 'attribute' => $attributeCode));
            }
        }

        return $ret;
    }

    public function renderSingleItem($item, $attributeCode)
    {
        $ret["id"] = $item->getId();
        $ret["lookup_value"] = $this->twig->render("CrmBusinessBundle:AutocompleteTemplates:contact.html.twig", array('field_data' => $item, 'attribute' => $attributeCode));

        return $ret;
    }

    /**
     * Ovveride this method
     */
    public function getRenderedItemById($attribute, $id)
    {

        $entity = $this->entityManager->getEntityByEntityTypeAndId($attribute->getLookupEntityType(), $id);

        if (empty($entity)) {
            return false;
        }

        return $this->renderSingleItem($entity, EntityHelper::makeGetter($attribute->getLookupAttribute()->getAttributeCode()));
    }

    /**
     * Ovveride this method
     */
    public function getTemplate()
    {
        return "CrmBusinessBundle:AutocompleteTemplates:contact.html.twig";
    }

    /**
     * Ovveride this method
     */
    public function getTemplateForAddedEntities()
    {
        return "CrmBusinessBundle:AutocompleteTemplates:contact_added_entities.html.twig";
    }
}
