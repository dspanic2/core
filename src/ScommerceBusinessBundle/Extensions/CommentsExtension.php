<?php

namespace ScommerceBusinessBundle\Extensions;

use ScommerceBusinessBundle\Managers\CommentsManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CommentsExtension extends \Twig_Extension
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var CommentsManager $commentsManager */
    protected $commentsManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_entity_comments', array($this, 'getEntityComments')),
            new \Twig_SimpleFunction('get_entity_ratings', array($this, 'getEntityRatings')),
            new \Twig_SimpleFunction('get_number_of_comments', array($this, 'getNumberOfComments')),
            new \Twig_SimpleFunction('get_number_of_rates', array($this, 'getNumberOfRates')),
            new \Twig_SimpleFunction('get_average_rating', array($this, 'getAverageRating')),
            new \Twig_SimpleFunction('get_ratings_count', array($this, 'getRatingsCount')),
            new \Twig_SimpleFunction('user_rated', array($this, 'checkUserRated')),
        ];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getEntityComments($entity)
    {
        if (empty($this->commentsManager)) {
            $this->commentsManager = $this->container->get("comments_manager");
        }

        return $this->commentsManager->getEntityComments($entity);
    }

    /**
     * @param $entity
     * @return int
     * @throws \Exception
     */
    public function getNumberOfComments($entity)
    {
        return count($this->getEntityComments($entity));
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getEntityRatings($entity)
    {
        if (empty($this->commentsManager)) {
            $this->commentsManager = $this->container->get("comments_manager");
        }

        return $this->commentsManager->getEntityRatings($entity);
    }

    /**
     * @param $entity
     * @return int
     * @throws \Exception
     */
    public function getNumberOfRates($entity)
    {
        return count($this->getEntityRatings($entity));
    }

    /**
     * @param $entity
     * @return int
     * @throws \Exception
     */
    public function getAverageRating($entity)
    {
        if (empty($this->commentsManager)) {
            $this->commentsManager = $this->container->get("comments_manager");
        }
        return number_format($this->commentsManager->getEntityAverageRating($entity), 1, ".", "");
    }

    /**
     * @param $entity
     * @return int
     * @throws \Exception
     */
    public function getRatingsCount($entity)
    {
        if (empty($this->commentsManager)) {
            $this->commentsManager = $this->container->get("comments_manager");
        }

        return $this->commentsManager->getRatingsCount($entity);
    }

    /**
     * @param $entity
     * @return bool
     */
    public function checkUserRated($entity)
    {
        if (empty($this->commentsManager)) {
            $this->commentsManager = $this->container->get("comments_manager");
        }

        return $this->commentsManager->userCommentedEntity($entity->getEntityType()->getId(), $entity->getId());
    }

}
