<?php

namespace AppBundle\EventListener;

use AppBundle\Managers\ErrorLogManager;
use http\Client\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ExceptionListener
{
    /**
     * @var ContainerInterface
     */
    protected $container;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        if ($exception instanceof NotFoundHttpException || stripos($exception->getMessage(),"No route found for") !== false) {
            return;
        }

        $folder = $_ENV["WEB_PATH"]."../var/logs/";
        if(!file_exists($folder)){
            mkdir($folder,0777,true);
        }

        $filePath = "exception.log";

        $now = new \DateTime();

        $fp = fopen($folder.$filePath, "a");
        fwrite($fp, $now->format("Y-m-d H:i:s")." ## Line: ".$exception->getLine()." ## ".$exception->getMessage()."\r\n");
        fwrite($fp, $exception->getTraceAsString()."\r\n");
        fclose($fp);

        if($_ENV["IS_PRODUCTION"]){
            if(empty($this->errorLogManager)){
                $this->errorLogManager = $this->container->get("error_log_manager");
            }

            $errorLogData = Array();
            $errorLogData["is_handeled"] = 0;
            $errorLogData["open_ticket"] = 0;
            if(isset($_ENV["SUPPORT_OPEN_TICKET"]) && $_ENV["SUPPORT_OPEN_TICKET"] == 1){
                $errorLogData["open_ticket"] = 1;
            }

            $this->errorLogManager->logExceptionEvent("Exception listener",$exception,false, array(), null, null, $errorLogData);
        }
    }
}