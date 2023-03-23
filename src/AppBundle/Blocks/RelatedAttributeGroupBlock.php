<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;

class RelatedAttributeGroupBlock extends AbstractBaseBlock
{
    /**@var FormManager $formManager */
    protected $formManager;
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function GetPageBlockTemplate()
    {
        if (isset($this->pageBlockData["model"]) && !empty($this->pageBlockData["model"])) {
            return ('AppBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
        } else {
            return ('AppBundle:Block:block_error.html.twig');
        }
    }

    public function GetPageBlockData()
    {

        $childEntityType = $this->pageBlock->getEntityType();
        $parentEntityType = $this->pageBlockData["page"]->getEntityType();
        $parentId = $this->pageBlockData["id"];
        $childId = null;

        if (!empty($this->pageBlock->getEntityType()) && !empty($parentEntityType) && !empty($parentId)) {

            $data = $this->pageBlock->getContent();

            if(empty($this->attributeContext)){
                $this->attributeContext = $this->container->get("attribute_context");
            }

            if(empty($this->entityManager)){
                $this->entityManager = $this->container->get("entity_manager");
            }

            $data = json_decode($data,true);

            /**
             * Ako nema filtera trazi se direktan atribut na trenutnom entitetu koji gleda na parenta
             */
            if(!isset($data["filter"]) || empty($data["filter"])){



                /** @var Attribute $attribute */
                $attribute = $this->attributeContext->getBy(Array("entityType" => $parentEntityType->getId(), "lookupEntityType" => $childEntityType->getId()));

                if(!empty($attribute)){

                    if(is_array($attribute)){
                        $attribute = $attribute[0];
                    }

                    $entity = $this->entityManager->getEntityByEntityTypeAndId($parentEntityType,$parentId);

                    $getter = EntityHelper::makeGetter($attribute->getAttributeCode());

                    if(StringHelper::endsWith( $getter, "Id" )){
                        $getter = substr($getter, 0, -2);
                    }

                    if(!empty($entity)){
                        $childEntity = $entity->$getter();

                        if(!empty($childEntity) && $childEntity->getEntityStateId() == 1){
                            $childId = $childEntity->getId();
                        }
                    }
                }
            }
            /**
             * Kada ima filtera trazi se atribut na child entitetu koji gleda na trenutni
             */
            else{

                if(isset($data["related_attribute_uid"]) && !empty($data["related_attribute_uid"])){
                    /** @var Attribute $attribute */
                    $attribute = $this->attributeContext->getItemByUid($data["related_attribute_uid"]);
                }
                else{
                    /** @var Attribute $attribute */
                    $attribute = $this->attributeContext->getBy(Array("entityType" => $childEntityType->getId(), "lookupEntityType" => $parentEntityType->getId()));
                }

                if(!empty($attribute)) {

                    $compositeFilters = new CompositeFilterCollection();

                    $compositeFilter = new CompositeFilter();
                    $compositeFilter->setConnector("and");
                    $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

                    $compositeFilters->addCompositeFilter($compositeFilter);

                    $decodedFilters = (array)json_decode($data["filter"]);

                    $currentDate = new \DateTime();

                    foreach ($decodedFilters as $decodedFilter) {
                        if (isset($decodedFilter->filters)) {
                            $compositeFilter = new CompositeFilter();
                            $compositeFilter->setConnector($decodedFilter->connector);

                            foreach ($decodedFilter->filters as $filter) {
                                $searchFilter = new SearchFilter();
                                $filter->value = str_replace("{id}", $parentId, $filter->value);
                                $filter->value = str_replace("{now}", $currentDate->format("Y-m-d H:i:s"), $filter->value);
                                //$filter->value = str_replace("{user_id}", $this->user->getId(), $filter->value);

                                if (stripos($filter->value, "{parentEntity}") !== false) {
                                    $parentEntity = $this->entityManager->getEntityByEntityTypeAndId($parentEntityType, $parentId);

                                    $filter_parts = explode(".", $filter->value);
                                    unset($filter_parts[0]);

                                    $fValue = $parentEntity;
                                    foreach ($filter_parts as $filter_part) {
                                        $getter = EntityHelper::makeGetter($filter_part);
                                        $fValue = $fValue->{$getter}();
                                    }

                                    $filter->value = $fValue;
                                }

                                $searchFilter->setFromArray($filter);
                                $compositeFilter->addFilter($searchFilter);
                            }
                            $compositeFilters->addCompositeFilter($compositeFilter);
                        }
                    }

                    $childEntity = $this->entityManager->getEntityByEntityTypeAndFilter($childEntityType,$compositeFilters);

                    if(!empty($childEntity)){
                        $childId = $childEntity->getId();
                    }
                }
            }

            if(!empty($childId)){
                $this->formManager = $this->factoryManager->loadFormManager($this->pageBlock->getEntityType()->getEntityTypeCode());
                $this->pageBlockData["model"] = $this->formManager->getFormModel($this->pageBlock->getAttributeSet(), $childId, $this->pageBlockData["subtype"], $this->pageBlock->getRelatedId());
            }
            else{
                $this->pageBlockData["model"] = true;
            }
        }

        $this->pageBlockData["disable_edit"] = true;

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
    }


    public function GetPageBlockSetingsData()
    {
        $attributeGroupsContext = $this->container->get('attribute_group_context');
        $attributeGroups = $attributeGroupsContext->getBy(Array(), Array("attributeSet" => "asc"));
        $data = json_decode($this->pageBlock->getContent());

        /*$disableEdit = false;
        if (isset($data->disableEdit)) {
            $disableEdit = $data->disableEdit;
        }*/

        $filter = false;
        if (isset($data->filter)) {
            $filter = $data->filter;
        }
        $relatedAttributeUid = null;
        if (isset($data->related_attribute_uid)) {
            $relatedAttributeUid = $data->related_attribute_uid;
        }


        return array(
            'entity' => $this->pageBlock,
            /*'disable_edit' => $disableEdit,*/
            'filter' => $filter,
            'related_attribute_uid' => $relatedAttributeUid,
            'attribute_groups' => $attributeGroups,
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $settings = [];
        $p = $_POST;

        $attributeGroupsContext = $this->container->get('attribute_group_context');
        $attributeGroup = $attributeGroupsContext->getById($data["relatedId"]);

        $attributeSet = $attributeGroup->getAttributeSet();
        $this->pageBlock->setAttributeSet($attributeSet);
        $this->pageBlock->setEntityType($attributeSet->getEntityType());

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setRelatedId($attributeGroup->getId());
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        /*$settings["disableEdit"] = false;
        if (isset($p["disableEdit"]) && !empty($p["disableEdit"])) {
            $settings["disableEdit"] = $p["disableEdit"];
        }*/

        $settings["filter"] = trim($p["filter"]);
        $settings["related_attribute_uid"] = trim($p["related_attribute_uid"]);

        $this->pageBlock->setContent(json_encode($settings));

        return $blockManager->save($this->pageBlock);
    }

    public function isVisible()
    {
        //Check permission
        return true;
    }

}