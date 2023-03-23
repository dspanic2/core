<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\DocumentationManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Templating\TemplatingExtension;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;

class DocumentationController extends AbstractController
{
    protected $managedEntityType;

    /** @var DocumentationManager $documentationManager */
    protected $documentationManager;

    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();
        $this->managedEntityType = "documentation";
        $this->documentationManager = $this->getContainer()->get("documentation_manager");
    }

    /**
     * @Route("administrator/documentation", name="documentation_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();

        return new Response($this->renderView('AppBundle:Admin/Documentation:index.html.twig',
            array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("/administrator/documentation/generate", name="documentation_generate")
     * @Method("GET")
     */
    public function generatePDFWithoutImages(Request $request)
    {
        $this->initialize();

        $filename = "documentation_" . date("d_m_Y", time()) . ".pdf";

        $bundles = $this->documentationManager->generateDocumentation(null, array());

        $header = $this->renderView('AppBundle:Admin/Documentation:pdf_header.html.twig');
        $html = $this->renderView('AppBundle:Admin/Documentation:pdf_body.html.twig', ['data' => $bundles]);
        $footer = $this->renderView('AppBundle:Admin/Documentation:pdf_footer.html.twig');

        $snappy = $this->getContainer()->get('knp_snappy.pdf');

        $options = [
            'footer-html' => $footer,
            'header-html' => $header,
            'page-size' => 'A4'
        ];

        $pdf = $snappy->getOutputFromHtml($html, $options);

        return new Response(
            $pdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }
}
