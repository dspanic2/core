<?php

namespace AppBundle\Abstracts;

use AppBundle\Entity\Page;
use AppBundle\Interfaces\Buttons\ButtonsInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractBaseButtons implements ButtonsInterface, ContainerAwareInterface
{
    protected $data;
    protected $type;
    protected $container;
    /** @var Page $page */
    protected $page;
    protected $twig;
    protected $translator;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Ovveride this method to initialize all services you will reqquire
     */
    public function initialize()
    {
        $this->translator = $this->container->get("translator");
    }

    public function setPage(Page $page){
        $this->page = $page;
    }

    public function getPage(){
        return $this->page;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getDefaultFormButtonsJson(){
        return '[{"type":"button","name":"Save","class":"btn-primary btn-blue","url":"","action":"return"},{"type":"button","name":"Save and continue","class":"btn-primary btn-blue","url":"","action":"continue"},{"type":"link","name":"Back","class":"btn-default btn-red","url":"","action":"back"}]';
    }

    public function getDefaultModalFormButtonsJson(){
        return '[{"type":"button","name":"Save","class":"btn-primary btn-blue","url":"","action":"close-modal"},{"type":"button","name":"Close","class":"btn-default btn-red","url":"","action":"dismiss-modal"}]';
    }

    public function getDefaultFormButtons(){
        return json_decode($this->getDefaultFormButtonsJson(),true);
    }

    public function GetFormPageButtons(){
        $buttonsJson = $this->getPage()->getButtons();
        if(empty($buttonsJson)){
            $buttonsJson = $this->getDefaultFormButtonsJson();
        }
        return json_decode($buttonsJson,true);
    }

    public function GetListPageButtons()
    {
        return json_decode($this->getPage()->getButtons(),true);
    }

    public function GetDashboardPageButtons()
    {
        return json_decode($this->getPage()->getButtons(),true);
    }

    public function GetModalFormPageButtons(){
        return json_decode($this->getDefaultModalFormButtonsJson(),true);
    }

    public function getButtons(){

        $data = $this->getData();

        $buttons = null;

        if($data["type"] == "form"){
            if(isset($data["is_modal"]) && $data["is_modal"]){
                $buttons = $this->GetModalFormPageButtons();
            }
            else{
                $buttons = $this->GetFormPageButtons();
            }
        }
        elseif($data["type"] == "list"){
            $buttons = $this->GetListPageButtons();
        }
        elseif($data["type"] == "dashboard"){
            $buttons = $this->GetDashboardPageButtons();
        }

        $html = null;

        if(!empty($buttons)){
            if(empty($this->twig)){
                $this->twig = $this->getContainer()->get('twig');
            }
            $html = $this->twig->render('AppBundle:Includes:buttons.html.twig', array("buttons" => $buttons));
        }

        return $html;
    }
}
