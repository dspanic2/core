<?php

namespace CrmBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Controller\ListViewController;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\ListView;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\ListViewManager;
use Doctrine\Common\Util\Inflector;
use Symfony\Component\Config\Definition\Exception\Exception;

class QuoteDescriptionsBlock extends AbstractBaseBlock
{
    /**@var EntityManager $entityManager*/
    protected $entityManager;
    /** @var  BlockManager $entityManager*/
    protected $blockManager;
    /**@var ListViewContext $listViewContext */
    protected $listViewContext;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /**@var FactoryContext $factoryContext */
    protected $factoryContext;
    /**@var AttributeContext $factoryContext */
    protected $attributeContext;

    public function GetPageBlockTemplate()
    {
        return ('CrmBusinessBundle:Block:'.$this->pageBlock->getType().'.html.twig');
    }

    public function GetPageBlockData()
    {
        $this->entityManager = $this->container->get("entity_manager");
        $this->listViewContext = $this->container->get("list_view_context");
        $this->entityTypeContext = $this->container->get("entity_type_context");
        $this->factoryContext = $this->container->get("factory_context");
        $this->attributeContext = $this->container->get("attribute_context");

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'CrmBusinessBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        /** @var Attribute $attrObj */

        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->container->get('attribute_context');

        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get('entity_type_context');
        $entityTypes = $entityTypeContext->getAll();

        $data = json_decode($this->pageBlock->getContent());

        $attr = [];
        if (isset($data->title1)) {
            $attrObj = $attributeContext->getBy(array('attributeCode' => $data->attribute,'entityType' => $this->pageBlock->getAttributeSet()->getEntityType()));
            if (!empty($attrObj)) {
                $attrObj = $attrObj[0];
                $attr = [
                    "id" => $attrObj->getAttributeCode(),
                    "name" => $attrObj->getFrontendLabel()
                ];
            }
        }

        return array(
            'entity' => $this->pageBlock,
            'entity_types' => $entityTypes,
            'attribute' => $attr,
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
