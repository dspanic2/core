<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Entity\ErrorLogEntity;
use AppBundle\Managers\ErrorLogManager;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Config\Definition\Exception\Exception;

class ErrorLogController extends AbstractController
{
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * @Route("/error_logs/mark_resolved", name="error_log_mark_resolved")
     * @Method("POST")
     */
    public function errorLogMarkResolved(Request $request)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        $p = $_POST;

        if(empty($p["id"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing id")));
        }

        if(empty($this->errorLogManager)){
            $this->errorLogManager = $this->getContainer()->get("error_log_manager");
        }

        /** @var ErrorLogEntity $errorLog */
        $errorLog = $this->errorLogManager->getErrorLogById($p["id"]);

        if(empty($errorLog)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error log not found")));
        }

        if($errorLog->getResolved()){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error log already marked as resolved")));
        }

        $data = Array();
        $data["resolved"] = 1;

        $this->errorLogManager->insertUpdateErrorLog($data,$errorLog);

        return new JsonResponse(array("error" => false, "message" => $this->translator->trans("Error log marked as resolved")));
    }
}
