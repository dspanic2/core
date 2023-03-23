<?php

namespace ScommerceBusinessBundle\Managers;

use CrmBusinessBundle\Entity\ContactEntity;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;

class ApiMobileManager extends AbstractScommerceManager
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param ContactEntity $contact
     * @return array|bool
     */
    public function getContactDataArray(ContactEntity $contact)
    {

        if (empty($contact)) {
            return false;
        }

        $ret["email"] = $contact->getEmail();
        $ret["first_name"] = $contact->getFirstName();
        $ret["last_name"] = $contact->getLastName();
        $ret["full_name"] = $contact->getFullName();
        $dateOfBirth = $contact->getDateOfBirth();
        if (!empty($dateOfBirth)) {
            $ret["date_of_birth"] = $dateOfBirth->format("Y-m-d");
        }

        return $ret;
    }
}