<?php

namespace AppBundle\Helpers;

use AppBundle\Constants\SearchFilterOperations;
use AppBundle\Entity\Attribute;

class QuerybuilderHelper
{

    static function mapRuleToSearchOperation($operator)
    {
        switch ($operator) {
            case "equal":
                return SearchFilterOperations::EQUAL;
                break;
            case "not_equal":
                return SearchFilterOperations::NOT_EQUAL_STRING;
                break;
            case "in":
                return SearchFilterOperations::IN;
                break;
            case "not_in":
                return SearchFilterOperations::NOT_IN;
                break;
            case "begins_with":
                return SearchFilterOperations::START_WITH;
                break;
            case "contains":
                return SearchFilterOperations::LIKE;
                break;
            case "not_contains":
                return SearchFilterOperations::NOT_LIKE;
                break;
            case "is_null":
                return SearchFilterOperations::IS_NULL;
                break;
            case "greater":
                return SearchFilterOperations::GREATER_THEN;
                break;
            case "greater_or_equal":
                return SearchFilterOperations::GREATER_OR_EQUAL_THENž;
                break;
            case "less":
                return SearchFilterOperations::LESS_THEN;
                break;
            case "less_or_equal":
                return SearchFilterOperations::LESS_OR_EQUAL_THEN;
                break;
            case "between":
                return SearchFilterOperations::IN;
                break;
            case "not_between":
                return SearchFilterOperations::NOT_IN;
                break;
            case "is_not_null":
                return SearchFilterOperations::NOT_NULL;
                break;
            default:
                return SearchFilterOperations::EQUAL;
                break;
        }
    }
    /**
     * @param $rule
     * @param $compositeFilter
     *
     * Recursive method that iterates elements returned by querybuilder plugin to build core composite filter
     */
    static function buildCompositeFilter($rule, &$compositeFilter)
    {
        $subComposite = array("connector" => strtolower($rule["condition"]));
        foreach ($rule["rules"] as $subRule) {
            if (isset($subRule["condition"])) {
                QuerybuilderHelper::buildCompositeFilter($subRule, $subComposite);
            } else
                $subComposite["filters"][] = array("field" => $subRule["id"],
                    "operation" => QuerybuilderHelper::mapRuleToSearchOperation($subRule["operator"]), "value" => $subRule["value"]);
        }

        $compositeFilter["filters"][] = $subComposite;

    }

    static function mapAttributeType(Attribute $attribute)
    {
        $type = "string";

        if ($attribute->getBackendType() == "decimal")
            $type = "double";
        if ($attribute->getBackendType() == "integer")
            $type = "integer";
        if ($attribute->getBackendType() == "datetime")
            $type = "datetime";
        if ($attribute->getBackendType() == "date")
            $type = "date";

        return $type;

    }

}