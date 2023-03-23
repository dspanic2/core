<?php

namespace AppBundle\Controller\CoreApiController;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewAttributeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Context\PageBlockContext;
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

class PageBlockApiController extends FOSRestController
{

    /**@var PageBlockContext $pageBlockContext */
    protected $pageBlockContext;


    public function initialize()
    {
        $this->pageBlockContext = $this->getContainer()->get("page_block_context");
    }

    /**
     * @Route("/core/api/page_block", name="get_all_page_block")
     * @Method("GET")
     * @ApiDoc(
     *  resource=true,
     *  section="Page block",
     *  description="Get collection with definitions of all page blocks",
     *  parameters={{"name"="bundle", "dataType"="string", "required"=false, "description"="Filter by bundle(ex:AppBundle)"}},
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function getAll(Request $request)
    {
        $this->initialize();

        /**@var \AppBundle\Context\AttributeGroupContext $attributeGroupContext */
        $attributeGroupContext = $this->getContainer()->get("attribute_group_context");
        /**@var \AppBundle\Context\ListViewContext $listViewContext */
        $listViewContext = $this->getContainer()->get("list_view_context");



        $bundle = null;
        $itemsArray = array();

        if ($request->query->has('bundle')) {
            $bundle = $request->query->get('bundle');
        }

        $items = $this->pageBlockContext->getAll();

        /**@var \AppBundle\Entity\PageBlock $item */
        foreach ($items as $item) {
            $relatedUid = "";
            $relatedItem = null;
            if ($bundle != null && $item->getEntityType()->getBundle() != $bundle) {
                continue;
            }

            $itemArray = $item->convertToArray();

            if ($itemArray["type"] == "list_view") {
                $relatedItem = $listViewContext->getById($item->getRelatedId());
            }

            if ($itemArray["type"] == "attribute_group" || $itemArray["type"] == "custom_html") {
                $relatedItem = $attributeGroupContext->getById($item->getRelatedId());
            }

            if ($relatedItem != null) {
                $relatedUid = $relatedItem->getUid();
            }

            $itemArray["relatedUid"] = $relatedUid;
            $itemsArray[] = $itemArray;
        }
        return new JsonResponse($itemsArray);
    }
}
