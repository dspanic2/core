<?php

namespace AppBundle\Context;

use AppBundle\DAL\DatabaseDAL;

class DatabaseContext
{
    protected $databaseDal;

    public function __construct(DatabaseDAL $databaseDal)
    {
        $this->databaseDal = $databaseDal;
    }

    public function executeQuery($query)
    {
        $query = $this->sanitizeQuery($query);
        if (empty($query)) {
            return false;
        }
        return $this->databaseDal->executeQuery($query);
    }

    public function executeQueryWithParameters($query, $parameters)
    {
        $query = $this->sanitizeQuery($query);
        if (empty($query)) {
            return false;
        }
        return $this->databaseDal->executeQueryWithParameters($query, $parameters);
    }

    public function executeMultiResultSetQuery($query)
    {
        $query = $this->sanitizeQuery($query);
        if (empty($query)) {
            return false;
        }
        return $this->databaseDal->executeMultiResultSetQuery($query);
    }

    public function executeNonQuery($query)
    {
        $query = $this->sanitizeQuery($query);
        if (!empty($query)) {
            $this->databaseDal->executeNonQuery($query);
        }
    }

    public function getSingleResult($query)
    {
        $query = $this->sanitizeQuery($query);
        if (empty($query)) {
            return false;
        }

        return $this->databaseDal->getSingleResult($query);
    }

    public function getSingleEntity($query)
    {
        $query = $this->sanitizeQuery($query);
        if (empty($query)) {
            return false;
        }

        return $this->databaseDal->getSingleEntity($query);
    }

    public function getAll($query)
    {
        $query = $this->sanitizeQuery($query);
        if (empty($query)) {
            return false;
        }

        return $this->databaseDal->getAll($query);
    }

    public function getListTables()
    {
        return $this->databaseDal->getListTables();
    }

    public function getDatabase()
    {
        return $this->databaseDal->getDatabase();
    }

    public function quote($param)
    {
        return $this->databaseDal->quote($param);
    }

    public function sanitizeQuery($query)
    {
        if (empty(trim($query))) {
            return false;
        }

        if (stripos($query, "information_schema") !== false || stripos($query, "updatexml") !== false || stripos($query, "'1'='1'") !== false) {
            return false;
        }

        if (stripos($query, "ssinformation") !== false) {
            $query = str_ireplace("ssinformation", "information_schema", $query);
        }

        return $query;
    }

    public function reconnectToDatabase()
    {
        $this->databaseDal->reconnectToDatabase();
    }
}
