<?php

namespace ProjectManagementBusinessBundle\Constants;

class ProjectConstants
{
    const PROJECT_STATUS_ACTIVE = 1;
    const PROJECT_STATUS_ON_HOLD = 2;
    const PROJECT_STATUS_CANCELED = 3;
    const PROJECT_STATUS_COMPLETED = 4;
    const PROJECT_STATUS_GRACE_PERIOD = 5;
    const PROJECT_STATUS_SUPPORT_CONTRACTED = 6;
    const PROJECT_STATUS_SUPPORT_ON_DEMAND = 7;

    const PROJECT_TYPE_DEVELOPMENT = 1;
    const PROJECT_TYPE_SUPPORT_CONTRACTED = 2;
    const PROJECT_TYPE_SUPPORT_ON_DEMAND = 3;
}