<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\ListView;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Events\CalendarDragAndDropEvent;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Interfaces\Blocks\BlockInterface;
use Doctrine\Common\Inflector\Inflector;
use AppBundle\Blocks\AttributeGroupBlock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Welp\IcalBundle\Factory\Factory;
use Welp\IcalBundle\Response\CalendarResponse;

class CalendarManager extends AbstractBaseManager
{
    /**@var PageBlockContext $pageBlockContext */
    protected $pageBlockContext;
    /**@var ListViewContext $listViewContext */
    protected $listViewContext;
    /**@var FactoryContext $factoryContext */
    protected $factoryContext;
    /**@var ListViewManager $listViewManager */
    protected $listViewManager;
    /** @var  EntityManager $entityManager */
    protected $entityManager;
    /** @var  BlockManager $entityManager */
    protected $blockManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;

    public function initialize()
    {
        parent::initialize();
        $this->factoryContext = $this->container->get("factory_context");
        $this->listViewContext = $this->container->get("list_view_context");
        $this->pageBlockContext = $this->container->get("page_block_context");
        $this->blockManager = $this->container->get('block_manager');
        $this->entityManager = $this->container->get('entity_manager');
        $this->listViewManager = $this->container->get('list_view_manager');
    }

    public function dispatchCalendarDragAndDrop($entity)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");

        /** @var CalendarDragAndDropEvent $event */
        $event = new CalendarDragAndDropEvent($entity);
        return $eventDispatcher->dispatch(CalendarDragAndDropEvent::NAME, $event)->getIsValid();
    }

    /**
     * @param $data
     * @return array
     * @throws \Exception
     */
    public function getCalendarData($data)
    {

        $content = $this->blockManager->getBlockById($data["block_id"])->getContent();
        $content = json_decode($content);

        $raw_data = [];
        if (!empty($content)) {
            foreach ($content->list_view as $list_view_id) {

                /** @var ListView $list_view */
                $list_view = $this->listViewManager->getListViewModel($list_view_id);

                if (empty($list_view)) {
                    continue;
                }

                $entityType = $list_view->getEntityType();
                $attributeSet = $list_view->getAttributeSet();
                $entityTypeCode = $entityType->getEntityTypeCode();

                $entityType = $this->entityManager->getEntityTypeByCode($entityTypeCode);
                $context = $this->factoryContext->getContext($entityType);

                $pager = new DataTablePager();
                $pager->setStart(0);
                $pager->setLenght(10000);

                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector("and");
                $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
                $pager->addFilter($compositeFilter);

                /**
                 * Prepare getters
                 */
                $start_attr = "startListView".$list_view_id."Attribute";
                $end_attr = "endListView".$list_view_id."Attribute";
                $title_attr = "titleListView".$list_view_id."Attribute";
                $title_attr2 = "title2ListView".$list_view_id."Attribute";
                $title_attr3 = "title3ListView".$list_view_id."Attribute";
                $description_attr = "descriptionListView".$list_view_id."Attribute";
                $color_attr = "colorListView".$list_view_id."Attribute";

                $colorAttrCode = null;
                if (isset($content->list_view_attributes->$color_attr)) {
                    $colorAttrCode = $content->list_view_attributes->$color_attr;
                }

                $titleAttrCode = null;
                if (isset($content->list_view_attributes->$title_attr)) {
                    $titleAttrCode = $content->list_view_attributes->$title_attr;
                }
                $titleAttrCode2 = null;
                if (isset($content->list_view_attributes->$title_attr2)) {
                    $titleAttrCode2 = $content->list_view_attributes->$title_attr2;
                }
                $titleAttrCode3 = null;
                if (isset($content->list_view_attributes->$title_attr3)) {
                    $titleAttrCode3 = $content->list_view_attributes->$title_attr3;
                }
                $descriptionAttrCode = null;
                if (isset($content->list_view_attributes->$description_attr)) {
                    $descriptionAttrCode = $content->list_view_attributes->$description_attr;
                }
                $startAttrCode = null;
                $startAttr = null;
                if (isset($content->list_view_attributes->$start_attr)) {
                    $startAttrCode = $content->list_view_attributes->$start_attr;
                }
                $endAttrCode = null;
                $endAttr = null;
                if (isset($content->list_view_attributes->$end_attr)) {
                    $endAttrCode = $content->list_view_attributes->$end_attr;
                }

                /** @var AttributeContext $attributeContext */
                $attributeContext = $this->container->get("attribute_context");

                /**
                 * New (attribute_id)
                 */

                if (!empty($startAttrCode)) {

                    if (preg_match('/^\d+$/', $startAttrCode)) {
                        /** @var Attribute $startAttr */
                        $startAttr = $attributeContext->getById($startAttrCode);
                    }
                    else {
                        /** @var Attribute $startAttr */
                        $startAttr = $attributeContext->getItemByUid($startAttrCode);
                    }
                    $startAttrCode = $this->listViewManager->getListViewAttributeField($startAttrCode, $list_view);
                    //$pager->setColumnOrder($startAttrCode);
                    //$pager->setSortOrder("asc");

                    $sortFilter = new SortFilter();
                    $sortFilter->setField($startAttrCode);
                    $sortFilter->setDirection("desc");

                    $pager->addSortFilter($sortFilter);
                }
                if (!empty($endAttrCode)) {

                    if (preg_match('/^\d+$/', $startAttrCode)) {
                        /** @var Attribute $endAttr */
                        $endAttr = $attributeContext->getById($endAttrCode);
                    }
                    else{
                        /** @var Attribute $endAttr */
                        $endAttr = $attributeContext->getItemByUid($endAttrCode);
                    }
                    $endAttrCode = $this->listViewManager->getListViewAttributeField($endAttrCode, $list_view);
                }
                if (!empty($titleAttrCode)) {
                    $titleAttrCode = $this->listViewManager->getListViewAttributeField($titleAttrCode, $list_view);
                }
                if (!empty($titleAttrCode2)) {
                    $titleAttrCode2 = $this->listViewManager->getListViewAttributeField($titleAttrCode2, $list_view);
                }
                if (!empty($titleAttrCode3)) {
                    $titleAttrCode3 = $this->listViewManager->getListViewAttributeField($titleAttrCode3, $list_view);
                }
                if (!empty($descriptionAttrCode)) {
                    $descriptionAttrCode = $this->listViewManager->getListViewAttributeField($descriptionAttrCode, $list_view);
                }
                if (!empty($colorAttrCode)) {
                    $colorAttrCode = $this->listViewManager->getListViewAttributeField($colorAttrCode, $list_view);
                }

                if (!empty($startAttrCode)) {
                    $tmp["start"] = explode("T", $data["start"])[0];
                    $tmp["start"] = strtotime('-1 month', strtotime($tmp["start"]));
                    //$tmp["start"] = strtotime($tmp["start"]);
                    $compositeFilter = new CompositeFilter();
                    $compositeFilter->setConnector("and");
                    $compositeFilter->addFilter(new SearchFilter($startAttrCode, "gt", date("Y-m-d", $tmp["start"])));
                    $pager->addFilter($compositeFilter);
                }
                if (!empty($endAttrCode)) {
                    $tmp["end"] = explode("T", $data["end"])[0];
                    $tmp["end"] = strtotime('+1 month', strtotime($tmp["end"]));
                    //$tmp["end"] = strtotime($tmp["end"]);
                    $compositeFilter = new CompositeFilter();
                    $compositeFilter->setConnector("and");
                    $compositeFilter->addFilter(new SearchFilter($endAttrCode, "lt", date("Y-m-d", $tmp["end"])));
                    $pager->addFilter($compositeFilter);
                }

                $currentDate = new \DateTime();

                $decodedFilters = (array)json_decode($list_view->getFilter());
                $entityStateFilterSet = false;

                $compositeFilters = $this->listViewManager->getListViewFilters($pager, $decodedFilters, $currentDate, $entityStateFilterSet);

                if (!empty($compositeFilters)) {
                    if (!$entityStateFilterSet) {
                        $this->listViewManager->includeEntityStateActiveFilter($compositeFilters);
                    }
                } else {
                    $compositeFilters = new CompositeFilter();
                    $this->listViewManager->includeEntityStateActiveFilter($compositeFilters);
                }

                $pager->addFilter($compositeFilters);

                if (!empty($startAttrCode)) {
                    $pager->setColumnOrder($startAttrCode);
                }

                $context = $this->factoryContext->getContext($list_view->getEntityType());
                $entities = $context->getItemsWithPaging($pager);

                foreach ($entities as $entity) {

                    /**
                     * Get title
                     */
                    $title = null;
                    $tooltip = "";
                    if (!empty($titleAttrCode)) {
                        $title = $this->entityManager->getValueFromAttributeChainAndEntity($entity, $titleAttrCode);
                    }
                    if (!empty($titleAttrCode2)) {
                        $title = $title." - ".$this->entityManager->getValueFromAttributeChainAndEntity($entity, $titleAttrCode2);
                    }
                    if (!empty($titleAttrCode3)) {
                        $title = $title." - ".$this->entityManager->getValueFromAttributeChainAndEntity($entity, $titleAttrCode3);
                    }
                    if (!empty($descriptionAttrCode)) {
                        $tooltip = $this->entityManager->getValueFromAttributeChainAndEntity($entity, $descriptionAttrCode);
                    }


                    $formType = "form";
                    if (!empty($content->form_type)) {
                        $formType = $content->form_type == 1 ? "view" : "form";
                    }

                    /**
                     * Get url
                     */
                    $url = null;
                    if ($this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN') || $this->user->hasPrivilege(3, $entity->getAttributeSet()->getUid())) {
                        if (!empty($content->open_modal)) {
                            $blockForm = $this->pageBlockContext->getOneBy(array("attributeSet" => $attributeSet, "type" => "edit_form"));
                            $url = StringHelper::format("/block/modal?id={0}&block_id={1}&action=reload&type={2}", $entity->getId(), $blockForm->getId(), $formType);
                        } else {
                            $url = StringHelper::format("/page/{0}/{1}/{2}", $entityTypeCode, $formType, $entity->getId());
                        }
                    }

                    /**
                     * Get color
                     */
                    $color = null;
                    if (!empty($colorAttrCode)) {
                        $color = $this->entityManager->getValueFromAttributeChainAndEntity($entity, $colorAttrCode);
                    }

                    $item = [
                        "id" => $entity->getId(),
                        "title" => $title,
                        "url" => $url,
                        "color" => $color,
                        "entityTypeId" => $entity->getEntityType()->getId(),
                        "entityTypeCode" => $entity->getEntityType()->getEntityTypeCode(),
                        "startAttributeCode" => $startAttrCode,
                        "endAttributeCode" => $endAttrCode,
                        "tooltip" => $tooltip
                    ];

                    if (!empty($startAttrCode) && !empty($endAttrCode)) {
                        $item["start"] = $entity->{EntityHelper::makeGetter($startAttrCode)}();
                        $item["end"] = $entity->{EntityHelper::makeGetter($endAttrCode)}();

                        if (!empty($startAttr)) {
                            if ($startAttr->getBackendType() == "datetime") {
                                $item["start"] = $item["start"]->format("Y-m-d H:i:s");
                            } else {
                                $item["start"] = $item["start"]->format("Y-m-d");
                            }
                        } else {
                            $item["start"] = $item["start"]->format("Y-m-d");
                        }

                        if (!empty($endAttr)) {
                            if ($endAttr->getBackendType() == "datetime") {
                                $item["end"] = $item["end"]->format("Y-m-d H:i:s");
                            } else {
                                $item["end"] = $item["end"]->format("Y-m-d");
                            }
                        } else {
                            $item["end"] = $item["end"]->format("Y-m-d");
                        }
                    } elseif (!empty($startAttrCode)) {
                        $item["start"] = $entity->{EntityHelper::makeGetter($startAttrCode)}();

                        if (!empty($startAttr)) {
                            if ($startAttr->getBackendType() == "datetime") {
                                $item["start"] = $item["start"]->format("Y-m-d H:i:s");
                            } else {
                                $item["start"] = $item["start"]->format("Y-m-d");
                            }
                        } else {
                            $item["start"] = $item["start"]->format("Y-m-d");
                        }
                    } elseif (!empty($endAttrCode)) {
                        $item["start"] = $entity->{EntityHelper::makeGetter($endAttrCode)}();

                        if (!empty($endAttr)) {
                            if ($endAttr->getBackendType() == "datetime") {
                                $item["start"] = $item["start"]->format("Y-m-d H:i:s");
                            } else {
                                $item["start"] = $item["start"]->format("Y-m-d");
                            }
                        } else {
                            $item["start"] = $item["start"]->format("Y-m-d");
                        }
                    }

                    if (isset($item)) {
                        $raw_data[] = $item;
                    }
                }
            }
        }

        return $raw_data;
    }

    /**
     * @param $data
     * @return CalendarResponse
     * @throws \Jsvrcek\ICS\Exception\CalendarEventException
     */
    public function generateIcal($data)
    {

        /** @var Factory $icalFactory */
        $icalFactory = $this->container->get('welp_ical.factory');

        //Create a calendar
        $calendar = $icalFactory->createCalendar();

        foreach ($data as $d) {
            $event = $icalFactory->createCalendarEvent();

            $startDate = \DateTime::createFromFormat('Y-m-d', $d["start"]);

            $event->setStart($startDate)
                ->setSummary($d["title"])
                ->setUid($d["id"]);

            if (!empty($d["end"])) {
                $endDate = \DateTime::createFromFormat('Y-m-d', $d["end"]);
                $event->setEnd($endDate);
            }

            $calendar->addEvent($event);
        }

        $headers = array();
        $calendarResponse = new CalendarResponse($calendar, 200, $headers);

        return $calendarResponse;
    }
}
