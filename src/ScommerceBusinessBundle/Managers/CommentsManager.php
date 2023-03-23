<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\SEntityCommentEntity;
use ScommerceBusinessBundle\Entity\SEntityRatingEntity;

class CommentsManager extends AbstractScommerceManager
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->helperManager = $this->container->get("helper_manager");
        $this->routeManager = $this->container->get("route_manager");
        $this->databaseContext = $this->container->get("database_context");
    }

    /**
     * @param object $entity
     * @return array
     */
    public function getEntityComments($entity, $filterByActive = true, $filterByStore = true)
    {
        $et = $this->entityManager->getEntityTypeByCode("s_entity_comment");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("sEntityType", "eq", $entity->getEntityType()->getId()));
        $compositeFilter->addFilter(new SearchFilter("entityId", "eq", $entity->getId()));

        if ($filterByActive) {
            $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));
        }

        if ($filterByStore) {
            $session = $this->container->get('session');
            $storeId = $session->get("current_store_id");
            if (empty($storeId)) {
                $storeId = $_ENV["DEFAULT_STORE_ID"];
            }
            $compositeFilter->addFilter(new SearchFilter("storeId", "eq", $storeId));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param object $entity
     * @return array
     */
    public function getEntityRatings($entity, $filterByActive = true, $filterByStore = true)
    {
        $et = $this->entityManager->getEntityTypeByCode("s_entity_rating");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("sEntityType", "eq", $entity->getEntityType()->getId()));
        $compositeFilter->addFilter(new SearchFilter("entityId", "eq", $entity->getId()));

        if ($filterByActive) {
            $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));
        }

        if ($filterByStore) {
            $session = $this->container->get('session');
            $storeId = $session->get("current_store_id");
            if (empty($storeId)) {
                $storeId = $_ENV["DEFAULT_STORE_ID"];
            }
            $compositeFilter->addFilter(new SearchFilter("storeId", "eq", $storeId));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param object $entity
     * @return int
     */
    public function getEntityAverageRating($entity)
    {
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $q = "SELECT AVG(rating) as average_rating FROM `s_entity_rating_entity` WHERE entity_state_id=1 AND active = 1 AND entity_id={$entity->getId()} AND s_entity_type={$entity->getEntityType()->getId()} AND store_id=$storeId;";
        $result = $this->databaseContext->getSingleEntity($q);

        if (empty($result)) {
            return 0;
        }

        return $result["average_rating"] ?? 0;
    }

    /**
     * @param $request
     * @param $p
     * @param SEntityRatingEntity $rating
     * @return SEntityCommentEntity|null
     */
    public function saveCommentFromPost($request, $p, $rating = null)
    {
        if (empty($p)) {
            return null;
        }

        $session = $request->getSession();
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $store = $this->routeManager->getStoreById($storeId);

        /** @var SEntityCommentEntity $newComment */
        $newComment = $this->entityManager->getNewEntityByAttributSetName("s_entity_comment");
        $isActive = 0;
        if (isset($_ENV["COMMENT_DEFAULT_ACTIVE"]) && $_ENV["COMMENT_DEFAULT_ACTIVE"] == 1) {
            $isActive = 1;
        }
        $newComment->setActive($isActive);
        $newComment->setStore($store);
        $newComment->setSEntityType($p["s_entity_type"]);
        $newComment->setEntityId($p["entity_id"]);
        $newComment->setComment($p["comment"]);
        $newComment->setSessionId($session->getId());

        if (isset($p["email"])) {
            $newComment->setEmail($p["email"]);
        }

        $clientIp = $_SERVER["HTTP_X_REAL_IP"] ?? null;
        if (empty($clientIp)) {
            $clientIp = $request->getClientIp();
        }
        $newComment->setIp($clientIp);

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();
        if (!empty($coreUser)) {
            $account = $coreUser->getDefaultAccount();
            if (!empty($account)) {
                $newComment->setAccount($account);
            }
        }

        if (isset($p["full_name"])) {
            $names = explode(" ", $p["full_name"]);
            $newComment->setFirstName($names[0]);
            unset($names[0]);
            $newComment->setFirstName(implode(" ", $names));
        } else {
            if (isset($p["first_name"])) {
                $newComment->setFirstName($p["first_name"]);
            }
            if (isset($p["last_name"])) {
                $newComment->setLastName($p["last_name"]);
            }
        }

        if (!empty($rating)) {
            $newComment->setEntityRating($rating);
        }

        $this->entityManager->saveEntity($newComment);

        return $newComment;
    }

    /**
     * @param $request
     * @param $p
     * @return SEntityRatingEntity|null
     */
    public function saveEntityRatePost($request, $p)
    {
        if (empty($p)) {
            return null;
        }

        $session = $request->getSession();
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $store = $this->routeManager->getStoreById($storeId);

        /** @var SEntityRatingEntity $newRating */
        $newRating = $this->entityManager->getNewEntityByAttributSetName("s_entity_rating");
        $isActive = 0;
        if (isset($_ENV["RATING_DEFAULT_ACTIVE"]) && $_ENV["RATING_DEFAULT_ACTIVE"] == 1) {
            $isActive = 1;
        }
        $newRating->setActive($isActive);
        $newRating->setStore($store);
        $newRating->setSEntityType($p["s_entity_type"]);
        $newRating->setEntityId($p["entity_id"]);
        $newRating->setRating($p["rate"]);
        $newRating->setSessionId($session->getId());

        $clientIp = $_SERVER["HTTP_X_REAL_IP"] ?? null;
        if (empty($clientIp)) {
            $clientIp = $request->getClientIp();
        }
        $newRating->setIp($clientIp);

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();
        if (!empty($coreUser)) {
            $account = $coreUser->getDefaultAccount();
            if (!empty($account)) {
                $newRating->setAccount($account);
            }
        }

        $this->entityManager->saveEntity($newRating);

        return $newRating;
    }

    /**
     * @param $ids
     * @return bool
     */
    public function approveComments($ids)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE s_entity_comment_entity SET active = 1 WHERE id in (" . implode(",", $ids) . ");";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param $ids
     * @return bool
     */
    public function approveRatings($ids)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE s_entity_rating_entity SET active = 1 WHERE id in (" . implode(",", $ids) . ");";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param $parameters
     * @return int|string
     */
    public function getCommentsForEntityByIdAndTypeId($parameters)
    {
        if (!empty($parameters)) {
            $parts = explode(",", $parameters);
            $entityId = $parts[0];
            $entityTypeCode = $parts[1];

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }
            $relatedEntityType = $this->entityManager->getEntityTypeByCode($entityTypeCode);
            $entity = $this->entityManager->getEntityByEntityTypeAndId($relatedEntityType, $entityId);

            $comments = $this->getEntityComments($entity, false, false);

            if (!empty($comments)) {
                $ret = [];

                /** @var SEntityCommentEntity $comment */
                foreach ($comments as $comment) {
                    $ret[] = $comment->getId();
                }

                return implode(",", $ret);
            }
        }

        return 0;
    }

    /**
     * @param $parameters
     * @return int|string
     */
    public function getRatingsForEntityByIdAndTypeId($parameters)
    {
        if (!empty($parameters)) {
            $parts = explode(",", $parameters);
            $entityId = $parts[0];
            $entityTypeCode = $parts[1];

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }
            $relatedEntityType = $this->entityManager->getEntityTypeByCode($entityTypeCode);
            $entity = $this->entityManager->getEntityByEntityTypeAndId($relatedEntityType, $entityId);

            $ratings = $this->getEntityRatings($entity, false, false);

            if (!empty($ratings)) {
                $ret = [];

                /** @var SEntityRatingEntity $rating */
                foreach ($ratings as $rating) {
                    $ret[] = $rating->getId();
                }

                return implode(",", $ret);
            }
        }

        return 0;
    }

    /**
     * @param $entityTypeId
     * @param $entityId
     * @return bool
     */
    public function userRatedEntity($entityTypeId, $entityId)
    {
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $q = "SELECT count(id) as rates FROM s_entity_rating_entity WHERE entity_state_id=1 AND entity_id={$entityId} AND s_entity_type={$entityTypeId} AND store_id={$storeId} AND session_id='{$session->getId()}';";
        $result = $this->databaseContext->getSingleEntity($q);

        return $result["rates"] > 0;
    }

    /**
     * @param $entityTypeId
     * @param $entityId
     * @return bool
     */
    public function userCommentedEntity($entityTypeId, $entityId)
    {
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $q = "SELECT count(id) as comments FROM s_entity_comment_entity WHERE entity_state_id=1 AND entity_id={$entityId} AND s_entity_type={$entityTypeId} AND store_id={$storeId} AND session_id='{$session->getId()}';";
        $result = $this->databaseContext->getSingleEntity($q);

        return $result["comments"] > 0;
    }

    /**
     * @param object $entity
     * @return array
     */
    public function getRatingsCount($entity)
    {
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $q = "SELECT
                    rating,
                    count(id) AS rating_count 
                FROM
                    s_entity_rating_entity 
                WHERE
                    entity_state_id = 1 
                    AND store_id = {$storeId}
                    AND active = 1 
                    AND entity_id = {$entity->getId()} 
                    AND s_entity_type = {$entity->getEntityType()->getId()} 
                GROUP BY
                    rating";
        $results = $this->databaseContext->getAll($q);

        if (empty($results)) {
            return [];
        }

        $ret = [];

        foreach ($results as $result) {
            $ret[$result["rating"]] = $result["rating_count"];
        }
        return $ret;
    }


    /**
     * @param $email
     * @param $sEntityType
     * @param $entityId
     * @return mixed
     */
    public function getCommentByEmailTypeAndId($email, $sEntityType, $entityId)
    {
        $et = $this->entityManager->getEntityTypeByCode("s_entity_comment");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("sEntityType", "eq", $sEntityType));
        $compositeFilter->addFilter(new SearchFilter("entityId", "eq", $entityId));
        $compositeFilter->addFilter(new SearchFilter("email", "eq", $email));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);
    }
}
