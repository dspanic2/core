<?php

namespace ShapeUnitTestingBundle\Models;

class ContactModel
{
    /**
     * @var string
     */
    protected $firstName = "Shape";

    /**
     * @return string
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @var string
     */
    protected $lastName = "UnitTest";

    /**
     * @return string
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @var date
     */
    protected $dateOfBirth = "1991-04-05";

    /**
     * @return date
     */
    public function getDateOfBirth()
    {
        return $this->dateOfBirth;
    }

    /**
     * @var string
     */
    protected $email = "shape-unit@mail.com";

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @var string
     */
    protected $phone = "0999999999";

    /**
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @var string
     */
    protected $fullName = "Shape UnitTest";

    /**
     * @return string
     */
    public function getFullName()
    {
        return $this->fullName;
    }

    /**
     * @var bool
     */
    protected $isActive = 1;

    /**
     * @return bool
     */
    public function getIsActive()
    {
        return $this->isActive;
    }

    /**
     * @var string
     */
    protected $password = "Test123!";

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @var string
     */
    protected $street = "Test ulica 1";

    /**
     * @return string
     */
    public function getStreet()
    {
        return $this->street;
    }

    /**
     * @return string
     */
    public function getCountryId()
    {
        return $_ENV["DEFAULT_COUNTRY"];
    }

    /**
     * @var string
     */
    protected $cityName = "Zagreb";

    /**
     * @return string
     */
    public function getCityName()
    {
        return $this->cityName;
    }

    /**
     * @var string
     */
    protected $postalCode = 10000;

    /**
     * @return string
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }

    /**
     * @return array
     */
    public function getPostData()
    {
        return [
            "email" => $this->getEmail(),
            "password" => $this->getPassword(),
            "repeat_password" => $this->getPassword(),
            "first_name" => $this->getFirstName(),
            "last_name" => $this->getLastName(),
            "country_id" => $this->getCountryId(),
            "city_name" => $this->getCityName(),
            "postal_code" => $this->getPostalCode(),
            "street" => $this->getStreet(),
            "phone" => $this->getPhone(),

            "shipping_city_name" => $this->getCityName(),
            "shipping_postal_code" => $this->getPostalCode(),
            "shipping_street" => $this->getStreet(),
            "shipping_country_id" => $this->getCountryId(),
        ];
    }
}
