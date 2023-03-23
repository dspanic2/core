<?php

namespace ScommerceBusinessBundle\Controller;

use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Entity\BlogCategoryEntity;
use ScommerceBusinessBundle\Managers\BlogManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class BlogController extends AbstractScommerceController
{
    /** @var FormManager $formManager */
    protected $formManager;
    /** @var BlogManager $blogManager */
    protected $blogManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->initializeTwigVariables($request);
    }

    /**
     * @Route("/get_blog_posts", name="get_blog_posts")
     * @Method("POST")
     */
    public function getBlogPostsAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $session = $request->getSession();

        /** @var BlogManager $blogManager */
        $blogManager = $this->container->get("blog_manager");

        $loadAll = false;

        if (!isset($p["blog_category"]) || empty($p["blog_category"])) {
            $loadAll = true;
        }

        if (!isset($p["get_all_blog_posts"])) {
            $p["get_all_blog_posts"] = 1;
        }

        /** Defaults */
        if (!isset($p["page_number"]) || empty($p["page_number"])) {
            $p["page_number"] = 1;
        }

        $p["filter"] = null;

        $p["sort"] = json_encode([[
            "sort_by" => "created",
            "sort_dir" => "desc",
        ]]);

        if (isset($_ENV["BLOG_GRID_SORT"]) && !empty($_ENV["BLOG_GRID_SORT"])) {
            $p["sort"] = $_ENV["BLOG_GRID_SORT"];
        }

        if (isset($_ENV["NEWS_GRID_DEFAULT_PAGE_SIZE"]) && !empty($_ENV["NEWS_GRID_DEFAULT_PAGE_SIZE"])) {
            $p["page_size"] = $_ENV["NEWS_GRID_DEFAULT_PAGE_SIZE"];
        } else {
            $p["page_size"] = 9;
        }

        if (!$loadAll) {
            /** @var BlogCategoryEntity $blogCategory */
            $blogCategory = $blogManager->getBlogCategoryById($p["blog_category"]);
            if (empty($blogCategory)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Blog category does not exist")));
            }
        }

        $ret = $blogManager->getFilteredBlogPosts($p, $loadAll);

        $hasNextPage = false;

        if (!empty($ret['entities'])) {
            $hasNextPage = $blogManager->calculateIfNextPageExists($p, $ret["total"]);
            $pagerHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/News:news_list_pager.html.twig", $session->get("current_website_id")), []);
            $html = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/News:news_list.html.twig", $session->get("current_website_id")), [
                'blogs' => $ret['entities'],
            ]);
        } else {
            $html = null;
//            $html = $this->twig->render("ScommerceBusinessBundle:Components/News:product_list_no_results.html.twig", []);
        }

        //ako je $ret["error"] = true
        //todo $html staviti error html
        //ovo se moze dogoditi samo ako netko proba otici na page 100 koji ne postoji

        // @TODO vratiti jos ukupan broj rezultata, vezane pretrage (ako ima toga) i keyword koji je pretrazivan
        return new JsonResponse(array(
            'error' => $ret["error"],
            'total' => $ret["total"],
            'grid_html' => $html,
            'pager_html' => $pagerHtml ?? null,
            'has_next_page' => $hasNextPage,
        ));
    }
}
