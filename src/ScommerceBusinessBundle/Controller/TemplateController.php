<?php

namespace ScommerceBusinessBundle\Controller;

use ScommerceBusinessBundle\Managers\TemplateManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class TemplateController extends Controller
{
    /** @var TemplateManager $templateManager */
    protected $templateManager;

    protected function initialize()
    {
        $this->templateManager = $this->container->get("template_manager");
    }

    /**
     * @param string $template
     * @param [] $data
     * @return Response
     */
    public function twigIncludeTwigAction($template, $data, $id = null)
    {
        $this->initialize();

        $html = "";

        $session = $this->container->get("session");

        $template = $this->templateManager->getTemplatePathByBundle($template, $session->get("current_website_id"), $id);

        if (!empty($template)) {
            $html = $this->renderView($template, $data);
        }

        return new Response($html);
    }
}
