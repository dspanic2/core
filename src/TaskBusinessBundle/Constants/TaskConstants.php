<?php

namespace TaskBusinessBundle\Constants;

class TaskConstants
{
    const TASK_TYPE_SUPPORT = 1;
    const TASK_TYPE_PROJECT = 4;

    const TASK_STATUS_NEW = 1;
    const TASK_STATUS_DEFERRED = 2;
    const TASK_STATUS_IN_PROGRESS = 3;
    const TASK_STATUS_COMPLETED = 4;
    const TASK_STATUS_WAITING_FOR_INPUT = 5;

    const TASK_PRIORITY_LOW = 3;
    const TASK_PRIORITY_MEDIUM = 2;
    const TASK_PRIORITY_HIGH = 1;

    const ACTIVITY_JS_FORMAT = "m-d-Y H:i:s";
}