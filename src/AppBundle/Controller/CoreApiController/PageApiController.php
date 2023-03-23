<?php

namespace AppBundle\Controller\CoreApiController;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewAttributeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Context\PageContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\Entity;
use AppBundle\Factory\FactoryManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\RestManager;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\View\View;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class PageApiController extends FOSRestController
{

    /**@var PageContext $pageContext */
    protected $pageContext;


    public function initialize()
    {
        $this->pageContext = $this->getContainer()->get("page_context");
    }

    /**
     * @Route("/core/api/page", name="get_all_page")
     * @Method("GET")
     * @ApiDoc(
     *  resource=true,
     *  section="Page",
     *  description="Get collection with definitions of all pages",
     *  filters={},
     *  parameters={{"name"="bundle", "dataType"="string", "required"=false, "description"="Filter types by bundle(ex:AppBundle)"}},
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function getAll(Request $request)
    {
        $this->initialize();
        $bundle = null;

        if ($request->query->has('bundle')) {
            $bundle = $request->query->get('bundle');
        }

        $itemsArray = array();

        if ($bundle != null) {
            $items = $this->pageContext->getBy(array("bundle" => $bundle), array("id" => "desc"));
        } else {
            $items = $this->pageContext->getAll();
        }

        /**@var \AppBundle\Entity\Page $item */
        foreach ($items as $item) {
            $itemsArray[] = $item->convertToArray();
        }
        return new JsonResponse($itemsArray);
    }
}
