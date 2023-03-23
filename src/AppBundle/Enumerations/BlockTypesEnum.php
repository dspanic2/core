<?php

namespace AppBundle\Enumerations;

class BlockTypesEnum
{
    static function values()
    {
        return array(
            "edit_form" => array(
                "attribute-set" => true,
                "content" => true,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "view_form" => array(
                "attribute-set" => true,
                "content" => true,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "attribute_group" => array(
                "attribute-set" => true,
                "related-id" => true,
                "relatedType" => "attribute_group",
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "list_view" => array(
                "attribute-set" => true,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "library_view" => array(
                "attribute-set" => true,
                "related-id" => true,
                "relatedType" => "list_view",
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "gallery" => array(
                "attribute-set" => true,
                "related-id" => true,
                "relatedType" => "attribute",
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "text_block" => array(
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "multiselect" => array(
                "attribute-set" => true,
                "related-id" => true,
                "is_available_in_block" => 1,
                "is_available_in_page" => 0
            ),
            "related_list_view" => array(
                "attribute-set" => true,
                "related-id" => true,
                "relatedType" => "list_view",
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "checkbox_list" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 0
            ),
            "calendar" => array(
                "attribute-set" => true,
                "related-id" => false,
                "list-view" => 1,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "kanban" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "kanban_attribute_columns" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1,
                "kanban-columns" => 1,
                "load-more" => 1
            ),
            "kanban_custom_columns" => array(
                "attribute-set" => false,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1,
                "kanban-columns" => 1
            ),
            "funnel" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "line_chart" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "pie_chart" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "donut_chart" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "stacked_bar_chart" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "bar_chart" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "bubble_chart" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "google_doc_preview" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "google_maps" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "working_hours" => array(
                "attribute-set" => true,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "quote_preview" => array(
                "attribute-set" => false,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 0
            ),
            "text" => array(
                "attribute-set" => false,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "google_authenticator" => array(
                "attribute-set" => false,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "product_calculation" => array(
                "attribute-set" => false,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "project_gantt" => array(
                "attribute-set" => false,
                "related-id" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "ical_export" => array(
                "attribute-set" => true,
                "related-id" => false,
                "list-view" => 1,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "tabs" => array(
                "attribute-set" => false,
                "related-id" => false,
                "content" => true,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
        );
    }
}
