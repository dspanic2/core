function changeView(elem) {
    var parent = elem.parents(".sp-list-view-block").parent();

    jQuery.post(elem.data("url"), {
        view_id: elem.val(),
        block_id: elem.data("block-id")
    }, function (result) {
        if (result.error == false) {
            parent.find(".sp-list-view-block").replaceWith(result.html);
            if (parent.find('.datatables').length > 0) {
                parent.find('.datatables').each(function (e) {
                    jQuery('#' + jQuery(this).attr('id')).createDatatable();
                });
            }
            parent.find(".sp-select-view").change(function () {
                changeView(jQuery(this));
            });

            jQuery('[data-action="standard_mass_action"]').bind('click', function () {
                bindStandardMassActions(jQuery(this));
            });
        }
        else {
            jQuery.growl.error({
                title: translations.error_message,
                message: result.message
            });
        }
    }, "json");
}

jQuery(document).ready(function () {

    jQuery(".sp-select-view").change(function () {
        changeView(jQuery(this));
    });

    if (jQuery('#btn_show_builder').length > 0) {
        jQuery('#btn_show_builder').on('click', function () {

            initialize_querybuilder();
        });
    }
});

function initialize_querybuilder() {

    var $filters = jQuery('#builder-basic').data('filters');

    if ($filters != "") {

        jQuery('#builder-basic').queryBuilder({
            plugins: ['bt-tooltip-errors'],
            filters: $filters,
        });

        jQuery('#btn-reset').on('click', function () {
            jQuery('#builder-basic').queryBuilder('reset');
        });


        jQuery('#btn-get').on('click', function () {
            var result = jQuery('#builder-basic').queryBuilder('getRules');
            jQuery('#filter-builder').val(JSON.stringify(result, null, 2));

            jQuery.post("/administrator/list_view/querybuilder/build", {
                query: result,
            }, function (result) {
                if (result.error == false) {
                    jQuery("[name='filter']").val(JSON.stringify(result.composite_filter));

                }
                else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, "json");

        });

        jQuery("[name='querybuilder_panel']").show();
    }
    else {
        jQuery.growl.error({
            title: translations.error_message,
            message: "No attributes selected"
        });
    }
}

jQuery(document).ready(function () {


    if (jQuery('[data-action="dropbox"]').length > 0) {


        jQuery('body').on('click', '[data-action="dropbox"]', function (e) {

            url = jQuery(this).data("url");

            options = {

                // Required. Called when a user selects an item in the Chooser.
                success: function (files) {
                    jQuery.post(url, {
                        dropboxFile: files[0]
                    }, function (result) {
                        if (result.error == false) {
                            jQuery.growl.notice({
                                title: result.title,
                                message: result.message
                            });

                            var resets = jQuery.find('[data-action="filter-reset"]');
                            jQuery(resets).each(function () {
                                jQuery(this).trigger("click");
                            });
                        }
                        else {
                            jQuery.growl.error({
                                title: translations.error_message,
                                message: result.message
                            });
                        }
                    }, "json");
                },

                // Optional. Called when the user closes the dialog without selecting a file
                // and does not include any parameters.
                cancel: function () {
                },
                linkType: "preview", // or "direct"
                multiselect: false, // or true

                // Optional. This is a list of file extensions. If specified, the user will
                // only be able to select files with these extensions. You may also specify
                // file types, such as "video" or "images" in the list. For more information,
                // see File types below. By default, all extensions are allowed.
                //extensions: ['.pdf', '.doc', '.docx'],

                // Optional. A value of false (default) limits selection to files,
                // while true allows the user to select both folders and files.
                // You cannot specify `linkType: "direct"` when using `folderselect: true`.
                folderselect: true, // or true

                // Optional. A limit on the size of each file that may be selected, in bytes.
                // If specified, the user will only be able to select files with size
                // less than or equal to this limit.
                // For the purposes of this option, folders have size zero.
                // sizeLimit: 1024, // or any positive number
            };

            Dropbox.choose(options);
        });
    }
});