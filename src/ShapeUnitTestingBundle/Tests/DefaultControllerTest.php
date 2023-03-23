<?php

namespace ShapeUnitTestingBundle\Tests;

use ShapeUnitTestingBundle\Abstracts\AbstractWebTest;

class DefaultControllerTest extends AbstractWebTest
{
    public function testSiteIsWorking()
    {
        $_ENV["REQUIRED_LOGIN"] = 0;
        $this->client->request('GET', $this->getBaseUrl());
        if($this->client->getResponse()->getStatusCode() == "301"){
            $url = $this->client->getResponse()->getTargetUrl();
            $this->client->request('GET', $url);
        }
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testSimpleProductPageIsWorking()
    {
        $_ENV["REQUIRED_LOGIN"] = 0;
        $url = $this->getSimpleProductUrl();

        if (!empty($url)) {
            $this->client->request('GET', $url);
            if($this->client->getResponse()->getStatusCode() == "301"){
                $url = $this->client->getResponse()->getTargetUrl();
                $this->client->request('GET', $url);
            }
            $this->assertTrue($this->client->getResponse()->isSuccessful());
        }
    }

    public function testConfigurableProductPageIsWorking()
    {
        $_ENV["REQUIRED_LOGIN"] = 0;
        $url = $this->getConfigurableProductUrl();

        if (!empty($url)) {
            $this->client->request('GET', $url);
            if($this->client->getResponse()->getStatusCode() == "301"){
                $url = $this->client->getResponse()->getTargetUrl();
                $this->client->request('GET', $url);
            }
            $this->assertTrue($this->client->getResponse()->isSuccessful());
        }
    }
}
