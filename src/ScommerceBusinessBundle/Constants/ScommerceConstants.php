<?php

namespace ScommerceBusinessBundle\Constants;

class ScommerceConstants
{
    const DEFAULT_PRODUCT_TEMPLATE_ID = 5;
    const DEFAULT_PRODUCT_GROUP_TEMPLATE_ID = 4;

    const EVENT_TYPE_PAGE_VIEW = "page_view";
    const EVENT_TYPE_FAVORITE = "favorite";
    const EVENT_TYPE_REMIND_ME = "remind_me";
    const EVENT_TYPE_GENERAL_QUESTION = "general_question";
    const EVENT_TYPE_PRODUCT_TO_CART = "product_cart";

    const EVENT_NAME_PAGE_VIEWED = "page_viewed";
    const EVENT_NAME_ADDED = "added";
    const EVENT_NAME_UPDATED = "updated";
    const EVENT_NAME_REMOVED = "removed";
    const EVENT_NAME_SENT = "sent";

    const S_REDIRECT_TYPE_301 = 1;
    const S_REDIRECT_TYPE_404 = 2;
}