<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Abstracts\AbstractEntity;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\RepeatEventInfo;
use AppBundle\Helpers\EntityHelper;
use Doctrine\Common\Inflector\Inflector;

class RepeatEventManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get('entity_manager');
    }

    public function generateRepeatingEvents(AbstractEntity $entity, RepeatEventInfo $repeat_info, Attribute $fromAttribute, Attribute $toAttribute = null)
    {
        $counter = 0;
        $getterFrom = EntityHelper::makeGetter($fromAttribute->getAttributeCode());

        $startDate = $entity->$getterFrom();
        $repeat_end_date = \DateTime::createFromFormat("d/m/Y", $repeat_info->repeat_end_date);


        if ($repeat_info->repeat_type == "everyWeekDay") {
            $newDueDate = $startDate->add(new \DateInterval('P1D'));
            if ($repeat_info->ending_condition == "after") {
                while ($counter < $repeat_info->repeat_number_of_occurances) {
                    if ($newDueDate->format('N') < 6) {
                        $this->createRepeatInstance($entity, $newDueDate, $fromAttribute, $toAttribute);
                        $counter++;
                    }

                    $newDueDate = $newDueDate->add(new \DateInterval('P1D'));
                }
            } else {
                while ($newDueDate <= $repeat_end_date) {
                    if ($newDueDate->format('N') < 6) {
                        $this->createRepeatInstance($entity, $newDueDate, $fromAttribute, $toAttribute);
                    }
                    $newDueDate = $newDueDate->add(new \DateInterval('P1D'));
                }
            }
        }

        if ($repeat_info->repeat_type == "daily") {
            $newDueDate = $startDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'D'));
            if ($repeat_info->ending_condition == "after") {
                while ($counter < $repeat_info->repeat_number_of_occurances) {
                    $this->createRepeatInstance($entity, $newDueDate, $fromAttribute, $toAttribute);
                    $counter++;
                    $newDueDate = $newDueDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'D'));
                }
            } else {
                while ($newDueDate <= $repeat_end_date) {
                    $newTask = $this->createRepeatInstance($entity, $newDueDate, $fromAttribute, $toAttribute);
                    $newDueDate = $newDueDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'D'));
                }
            }
        }

        if ($repeat_info->repeat_type == "yearly") {
            $newDueDate = $startDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'Y'));
            if ($repeat_info->ending_condition == "after") {
                while ($counter < $repeat_info->repeat_number_of_occurances) {
                    $newTask = $this->createRepeatInstance($entity, $newDueDate, $fromAttribute, $toAttribute);
                    $counter++;
                    $newDueDate = $newDueDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'Y'));
                }
            } else {
                while ($newDueDate <= $repeat_end_date) {
                    $newTask = $this->createRepeatInstance($entity, $newDueDate, $fromAttribute, $toAttribute);
                    $newDueDate = $newDueDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'Y'));
                }
            }
        }

        if ($repeat_info->repeat_type == "weekly") {
            $newDueDate = clone($startDate);
            $newDueDate->modify('previous monday');
            $repeat_end_date = \DateTime::createFromFormat("d/m/Y", $repeat_info->repeat_end_date);
            $flag = true;

            if ($repeat_info->ending_condition == "after") {
                while ($flag) {
                    foreach ($repeat_info->repeat_on_day as $day) {
                        $newDueDate->modify('next '.$day);
                        if ($newDueDate > $startDate) {
                            $this->createRepeatInstance($entity, $newDueDate, $fromAttribute, $toAttribute);
                            $counter++;
                        }
                        if ($counter == $repeat_info->repeat_number_of_occurances) {
                            $flag = false;
                            break;
                        }
                    }
                    $newDueDate = $newDueDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'W'));
                    $newDueDate->modify('previous sunday');
                }
            } else {
                while ($flag) {
                    foreach ($repeat_info->repeat_on_day as $day) {
                        $newDueDate->modify('next '.$day);
                        if ($newDueDate >= $repeat_end_date) {
                            $flag = false;
                            break;
                        }
                        if ($newDueDate > $startDate) {
                            $this->createRepeatInstance($entity, $newDueDate, $fromAttribute, $toAttribute);
                            $counter++;
                        }
                    }
                    $newDueDate = $newDueDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'W'));
                    $newDueDate->modify('previous sunday');
                }
            }
        }

        if ($repeat_info->repeat_type == "monthly") {
            if ($repeat_info->repeat_by == "repeat_by_day_of_the_month") {
                $newDueDate = $startDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'M'));
                if ($repeat_info->ending_condition == "after") {
                    while ($counter < $repeat_info->repeat_number_of_occurances) {
                        $this->createRepeatInstance($entity, $newDueDate, $fromAttribute, $toAttribute);
                        $counter++;
                        $newDueDate = $newDueDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'M'));
                    }
                } else {
                    while ($newDueDate <= $repeat_end_date) {
                        $this->createRepeatInstance($entity, $newDueDate, $fromAttribute, $toAttribute);
                        $newDueDate = $newDueDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'M'));
                    }
                }
            } else {
                $newDueDate = $startDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'M'));
                $startDayName = strtolower($startDate->format('l'));

                $newDueDate->modify('next '.$startDayName);

                if ($repeat_info->ending_condition == "after") {
                    while ($counter < $repeat_info->repeat_number_of_occurances) {
                        $this->createRepeatInstance($entity, $newDueDate, $fromAttribute, $toAttribute);
                        $counter++;
                        $newDueDate = $newDueDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'M'));
                        $newDueDate->modify('next '.$startDayName);
                    }
                } else {
                    while ($newDueDate <= $repeat_end_date) {
                        $this->createRepeatInstance($entity, $newDueDate, $fromAttribute, $toAttribute);
                        $newDueDate = $newDueDate->add(new \DateInterval('P'.$repeat_info->repeat_interval.'M'));
                        $newDueDate->modify('next '.$startDayName);
                    }
                }
            }
        }



        return true;
    }

    protected function createRepeatInstance(AbstractEntity $entity, \DateTime $newDate, Attribute $fromAttribute, Attribute $toAttribute = null)
    {

        $setterFrom = EntityHelper::makeSetter($fromAttribute->getAttributeCode());


        /** @var AbstractEntity $newEntity */
        $newEntity = $this->entityManager->cloneEntity($entity, $entity->getEntityType()->getEntityTypeCode(), array(), true, array());

        $newEntity->$setterFrom($newDate);

        if ($toAttribute != null) {
            $setterTo = EntityHelper::makeSetter($toAttribute->getAttributeCode());

            $getterFrom = EntityHelper::makeGetter($fromAttribute->getAttributeCode());
            $getterTo = EntityHelper::makeGetter($toAttribute->getAttributeCode());

            $dateFrom = $entity->$getterFrom();
            $dateTo = $entity->$getterTo();

            $difference = $dateFrom->diff($dateTo);
            $difference = $difference->d;

            $newDateTo = $newDate->add(new \DateInterval('P'.$difference.'D'));
            $newEntity->$setterTo($newDateTo);
        }

        $this->entityManager->saveEntityWithoutLog($newEntity);

        return $newEntity;
    }
}
