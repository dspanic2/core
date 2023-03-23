<?php

namespace ShapeUnitTestingBundle\Tests;

use CrmBusinessBundle\Entity\ProductEntity;
use ShapeUnitTestingBundle\Abstracts\AbstractWebTest;
use ShapeUnitTestingBundle\Models\ContactModel;

class CartControllerTest extends AbstractWebTest
{
    public function testAddRemoveToCartAction()
    {
        /** @var ProductEntity $product */
        $product = $this->getSimpleProduct();

        $qtyStep = floatval($product->getQtyStep());
        if (empty($qtyStep)) {
            $qtyStep = 1;
        }

        $_POST = [
            "product_id" => $product->getId(),
            "qty" => $qtyStep,
        ];
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/add_to_cart", $this->getSimpleProductUrl());
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");

        $_POST = [
            "data" => [
                [
                    [
                        "name" => "quote_item_id",
                        "value" => $response["quote_item_id"],
                    ],
                    [
                        "name" => "product_id",
                        "value" => $product->getId(),
                    ],
                    [
                        "name" => "qty",
                        "value" => $qtyStep + $qtyStep,
                    ]
                ]
            ]
        ];
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/update_cart", $this->getSimpleProductUrl());
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");

        $_POST = [
            "product_id" => $product->getId(),
            "qty" => $qtyStep,
        ];
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/remove_from_cart", $this->getSimpleProductUrl());
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");
    }

    public function testCheckout()
    {
        $newContact = new ContactModel();

        /** @var ProductEntity $product */
        $product = $this->getSimpleProduct();

        $qtyStep = floatval($product->getQtyStep());
        if (empty($qtyStep)) {
            $qtyStep = 1;
        }

        $_POST = [
            "product_id" => $product->getId(),
            "qty" => $qtyStep,
        ];
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/add_to_cart", $this->getSimpleProductUrl());
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");

        $_POST = [
            "email" => $newContact->getEmail(),
            "phone" => $newContact->getPhone(),
        ];
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/update_cart_customer_marketing_data");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");

        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/get_cart_totals");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");

        $this->assertTrue($this->shapePostRequestBool("POST", "{$this->getBaseUrl()}/cart/get_mini_cart_total"));

        $this->assertTrue($this->shapePostRequestBool("POST", "{$this->getBaseUrl()}/cart/get_mini_cart_count"));

        $this->assertTrue($this->shapePostRequestBool("POST", "{$this->getBaseUrl()}/cart/get_mini_cart"));
    }

    public function testCheckoutAction()
    {
        $this->anonymizeTestUser();

        $newContact = new ContactModel();

        /** @var ProductEntity $product */
        $product = $this->getSimpleProduct();

        $qtyStep = floatval($product->getQtyStep());
        if (empty($qtyStep)) {
            $qtyStep = 1;
        }

        $_POST = [
            "product_id" => $product->getId(),
            "qty" => $qtyStep,
        ];
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/add_to_cart", $this->getSimpleProductUrl());
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");

        $_POST = $newContact->getPostData();

        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/update_cart_customer_data");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");

        $_POST["shipping_address_same"] = 0;
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/update_cart_delivery_address");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");

        $_POST["delivery_type_id"] = 1;
        $_POST["payment_type_id"] = 1;
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/update_cart_payment_and_delivery");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");

        $_POST["message"] = "Shape unit message";
        $_POST["delivery_message"] = "Shape unit delivery message";
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/update_cart_message");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");

        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/generate_confirm_modal");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");
    }

    public function testValidateCustomerEmailAction()
    {
        $this->anonymizeTestUser();
        
        $newContact = new ContactModel();

        $_POST = [
            "email" => $newContact->getEmail(),
        ];
        $response = $this->shapePostRequest("POST", "{$this->getBaseUrl()}/cart/validate_customer_email");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");
    }

    public function testGetDeliveryTypeAutocompleteAction()
    {
        $_POST["q"]["term"] = "Adres";
        $response = $this->shapePostRequest("GET", "{$this->getBaseUrl()}/cart/get_delivery_type_autocomplete");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");
    }

    public function testGetPaymentTypeAutocompleteAction()
    {
        $_GET["q"]["term"] = "kartic";
        $_GET["form"] = "delivery_type_id=1";
        $response = $this->shapePostRequest("GET", "{$this->getBaseUrl()}/cart/get_payment_type_autocomplete");
        $this->assertTrue($response["error"] == false, $response["message"] ?? "");
    }
}
