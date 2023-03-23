<?php

namespace AppBundle\Controller\CoreApiController;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewAttributeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Context\NavigationLinkContext;
use AppBundle\Context\PageContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\Entity;
use AppBundle\Factory\FactoryManager;
use AppBundle\Managers\EntityManager;
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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class NavigationLinkApiController extends FOSRestController
{

    /**@var NavigationLinkContext $navigationLinkContext */
    protected $navigationLinkContext;


    public function initialize()
    {
        $this->navigationLinkContext = $this->getContainer()->get("navigation_link_context");
    }

    /**
     * @Route("/core/api/navigation_link", name="get_all_navigation_link")
     * @Method("GET")
     * @ApiDoc(
     *  resource=true,
     *  section="Navigation Link",
     *  description="Get collection with definitions of all navigation links",
     *  filters={},
     *  parameters={{"name"="bundle", "dataType"="string", "required"=false, "description"="Filter types by bundle(ex:AppBundle)"}},
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function getAll()
    {
        $this->initialize();
        $bundle = null;

        $itemsArray = array();

        if ($bundle != null) {
            $items = $this->navigationLinkContext->getBy(array("bundle" => $bundle), array("id" => "desc"));
        } else {
            $items = $this->navigationLinkContext->getAll();
        }

        /**@var \AppBundle\Entity\NavigationLink $item */
        foreach ($items as $item) {
            $itemsArray[] = $item->convertToArray();
        }
        return new JsonResponse($itemsArray);
    }
}
