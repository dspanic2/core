<?php

namespace AppBundle\DAL;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Validator\Constraints\IsFalse;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Doctrine\DBAL\Query\Expression\CompositeExpression;

class BaseDataAccess
{
    protected $entityManager;

    /**
     * @param $entityManager
     */
    public function setEntityManager($entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return mixed
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @return mixed
     */
    public function getConnection()
    {
        return $this->getEntityManager()->getConnection();
    }

    public function reconnectToDatabase()
    {
        if (!($connection = $this->getEntityManager()->getConnection())->ping()) {
            $connection->close();
            $connection->connect();
        }
    }
}
