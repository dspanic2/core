<?php

namespace ScommerceBusinessBundle\Abstracts;

use AppBundle\Abstracts\AbstractController;
use ScommerceBusinessBundle\Managers\TemplateManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

abstract class AbstractScommerceController extends AbstractController
{
    /** @var TemplateManager $templateManager */
    protected $templateManager;
    protected $twigBase;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->templateManager = $this->container->get("template_manager");
        if (empty($this->twigBase)) {
            $this->twigBase = $this->container->get('twig');
        }
    }
}
