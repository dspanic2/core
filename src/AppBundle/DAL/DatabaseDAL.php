<?php

namespace AppBundle\DAL;

use Doctrine\ORM\EntityManager;
use Doctrine\DBAL\Connection;

class DatabaseDAL extends BaseDataAccess
{
    //protected $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->setEntityManager($entityManager);
        //$this->entityManager = $entityManager;
    }

    public function executeQuery($query)
    {
        $query = $this->getConnection()->prepare($query);
        $query->execute();
        $results = $query->fetchAll();
        return $results;
    }

    public function executeQueryWithParameters($query, $parameters)
    {
        $values = array();
        $types = array();

        foreach ($parameters as $parameter) {
            $values[$parameter["key"]] = $parameter["values"];
            $types[$parameter["key"]] = $parameter["type"];
        }

        $stmt = $this->getConnection()->executeQuery($query, $values, $types);
        $result = $stmt->fetchAll();

        return $result;
    }

    public function executeMultiResultSetQuery($query)
    {
        // Init
        $conn = $this->getConnection()->getWrappedConnection();

        // Processing
        if ($conn instanceof \Doctrine\DBAL\Driver\PDOConnection) {
            $stmt = $conn->prepare($query);
            //$stmt->execute($params);
            $stmt->execute();
            // Loop through the row sets
            $results = array();
            do {
                try {
                    $results[] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                }
            } while ($stmt->nextRowset());

            $stmt->closeCursor(); // Clean up
            return $results;
        } else {
            return false;
        }
    }

    public function getSingleEntity($query)
    {
        $query = $this->getConnection()->prepare($query);
        $query->execute();
        $results = $query->fetchAll();

        if (empty($results)) {
            return false;
        }

        return $results[0];
    }

    public function getAll($query)
    {
        $query = $this->getConnection()->prepare($query);
        $query->execute();
        $results = $query->fetchAll();
        return $results;
    }

    public function executeNonQuery($query)
    {
        $conn = $this->getConnection()->prepare($query);
        //dump($query);die;
        $conn->execute();
    }


    public function getSingleResult($query)
    {
        $query = $this->getConnection()->prepare($query);
         //dump($query);die;
        $query->execute();
        $results = $query->fetchAll();

        return $results[0]["count"];
    }

    public function getListTables()
    {
        $connection = $this->getConnection();
        $sm = $connection->getSchemaManager();

        return $sm->listTableNames();
    }

    public function getDatabase()
    {
        return $this->getConnection()->getDatabase();
    }

    public function quote($param)
    {
        return $this->getConnection()->quote($param);
    }
}
