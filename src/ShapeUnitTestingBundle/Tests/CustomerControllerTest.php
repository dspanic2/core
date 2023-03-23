<?php

namespace ShapeUnitTestingBundle\Tests;

use ShapeUnitTestingBundle\Abstracts\AbstractWebTest;
use ShapeUnitTestingBundle\Models\ContactModel;

class CustomerControllerTest extends AbstractWebTest
{
    public function testRegisterCustomer()
    {
        $this->anonymizeTestUser();
        $newContact = new ContactModel();
        $_POST = $newContact->getPostData();
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/register_customer");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");
    }

    /**
     * @depends testRegisterCustomer
     */
    public function testLoginCustomer()
    {
        $newContact = new ContactModel();
        $_POST = $newContact->getPostData();
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/login_customer");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");
    }

    /**
     * @depends testRegisterCustomer
     */
    public function testResetPasswordRequestAction()
    {
        $newContact = new ContactModel();
        $_POST = $newContact->getPostData();
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/reset_password_request");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");
    }
}
