<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\Page;
use AppBundle\Entity\RoleEntity;
use AppBundle\Factory\FactoryManager;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\FormManager;
use AppBundle\Managers\PageManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;


class DefaultController extends AbstractController
{
    /**@var PageManager $pageManager */
    protected $pageManager;
    /**@var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var TokenStorage */
    protected $tokenStorage;
    /**@var FactoryManager $factoryManger */
    protected $factoryManager;

    protected function initialize()
    {
        parent::initialize();
        $this->pageManager = $this->getContainer()->get('page_manager');
        $this->tokenStorage = $this->getContainer()->get("security.token_storage");
        $this->factoryManager = $this->getContainer()->get("factory_manager");
        $this->cacheManager = $this->getContainer()->get("cache_manager");
    }

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();

        $session = $request->getSession();
        $session->set("_locale_type", "backend");

        if (!is_object($this->user) || empty($this->user)) {
            return $this->redirect("/login", 301);
        }

        if (is_object($this->user) && $this->user->getUsername() == "anonymus") {
            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            return $this->redirect("/login", 301);
        }

        $default_path = "/page/home/dashboard";

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        $userRoles = $this->helperManager->getRolesForCoreUser($coreUser);
        $roleDefaultPath = "";
        if (!empty($userRoles)) {
            /** @var RoleEntity $role */
            foreach ($userRoles as $role) {
                if (EntityHelper::checkIfPropertyExists($role, "defaultPath") && !empty($role->getDefaultPath())) {
                    $roleDefaultPath = $role->getDefaultPath();
                    break;
                }
            }
        }

        if (empty($coreUser)) {
            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            return $this->redirect("/login", 301);
        }

        if (EntityHelper::checkIfPropertyExists($coreUser, "defaultPath") && !empty($coreUser->getDefaultPath())) {
            return $this->redirect($coreUser->getDefaultPath(), 301);
        } elseif (!empty($roleDefaultPath)) {
            return $this->redirect($roleDefaultPath, 301);
        } else {
            return $this->redirect($default_path, 301);
        }

        throw new NotFoundHttpException('Homepage is not set in database "default_path"');
    }

    /**
     * @Route("/back", name="navigateBack")
     */
    public function navigateBackAction(Request $request)
    {
        $this->initialize();
        $session = $request->getSession();
    }

    /**
     * @Route("/page/{url}/{type}/{id}", defaults={"type"=null,"id"=null}, name="page_view")
     */
    public function pageAction($url, $type, $id, Request $request)
    {

        $this->initialize();

        $session = $request->getSession();
        $session->set("_locale_type", "backend");

        $breadcrumbs = $session->get("breadcrumbs");
        if ($request->query->get('noref') == null) {
            if ($breadcrumbs == null) {
                $breadcrumbs = array();
                $breadcrumbs[] = $request->headers->get('referer');
            } else {
                if (end($breadcrumbs) != $request->headers->get('referer') && end($breadcrumbs) != $request->getUri()) {
                    $breadcrumbs[] = $request->headers->get('referer');
                }

                if (end($breadcrumbs) != $request->headers->get('referer') && end($breadcrumbs) == $request->getUri()) {
                    array_pop($breadcrumbs);
                }

            }
            $session->set("breadcrumbs", $breadcrumbs);
        }
        $subtype = $type;

        /**@var Page $page */
        $page = $this->pageManager->loadPage($type, $url);

        /**
         * So that every form can have a default view
         */
        if (empty($page) && $type == "view") {
            $type = "form";
            $page = $this->pageManager->loadPage($type, $url);
        }

        $data = array();

        $content = json_decode($page->getContent(), true);
        $content = $this->helperManager->prepareBlockGrid($content);

        $page->setPreparedContent($content);

        $breadcrumbs = $breadcrumbs ?? [];

        $data["dropbox_key"] = null;
        $data["type"] = $type;
        $data["return_url"] = end($breadcrumbs);
        $data["breadcrumbs"] = $breadcrumbs;
        $data["subtype"] = $subtype;
        $data["page"] = $page;
        $data["id"] = $id;
        if ($this->user != "anon." && $this->user != null) {
            $data["locale"] = $this->user->getCoreLanguage()->getCode();
        }
        $data["parent"]["id"] = $request->get("pid");
        $data["parent"]["attributeSetCode"] = $request->get("ptype");
        $data["quickSearchQuery"] = null;
        if (!empty($request->get("query"))) {
            $data["quickSearchQuery"] = $request->get("query");
        }

        if (is_object($this->user) && $this->user->hasPrivilege(5, $page->getUid())) {

            /**@var FormManager $formManager */
            $formManager = $this->factoryManager->loadFormManager($type);
            $data["model"] = $formManager->getFormModel($page->getAttributeSet(), $id, $subtype);
            return new Response($this->renderView("AppBundle:Dashboard:index.html.twig", array('data' => $data)));
        } else {

            $default_unauthorised_path = "/login";

            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            return $this->redirect($default_unauthorised_path, 301);
            //return new Response($this->renderView("AppBundle:AccessDenied:index.html.twig", Array('data' => $data)));
        }
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function errorAction(Request $request)
    {
        $this->initialize();

        return new Response($this->renderView("AppBundle:Error:404.html.twig", array()));
    }
}
