<?php

namespace TaskBusinessBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Helpers\EntityHelper;
use TaskBusinessBundle\Entity\ActivityEntity;
use TaskBusinessBundle\Managers\ActivityManager;

class ActivityActionsField extends AbstractField
{

    /** @var ActivityManager $activityManager */
    protected $activityManager;

    /**
     * @return string
     */
    public function GetListViewTemplate()
    {
        $attribute = $this->GetAttribute();

        return 'TaskBusinessBundle:Fields/ListView:' . $attribute->getFrontendType() . '.html.twig';
    }

    /**
     * @return string
     */
    public function GetListViewHeaderTemplate()
    {
        $attribute = $this->GetAttribute();

        return 'TaskBusinessBundle:Fields/ListView/Header:' . $attribute->getFrontendType() . '.html.twig';
    }

    public function GetListViewHtml()
    {
        $entity = $this->GetEntity();

        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());
        $value = $entity->{$getter}();

        if ($entity->getEntityType()->getEntityTypeCode() != "activity") {
            $value = 1;
            if (empty($this->activityManager)) {
                $this->activityManager = $this->container->get("activity_manager");
            }

            /** @var ActivityEntity $currentActivity */
            $currentActivity = $this->activityManager->getCurrentActivity();
            if (!empty($currentActivity) && $currentActivity->getTaskId() == $entity->getId()) {
                $value = 0;
            }
        }

        return $this->twig->render($this->GetListViewTemplate(), array('value' => $value, 'fieldClass' => $this->GetFieldClass(), 'entity' => $this->GetEntity()));
    }

    /**
     * @return string
     */
    public function getBackendType()
    {
        return "bool";
    }
}