function prepareProductAttributesFilter(table, custom_data) {

    var form = jQuery('[data-validate="true"]');
    var post = [];

    form.find('.sp-search-item').each(function (e) {
        var filter_options = {};
        var val = null;
        var elem = null;
        var search_type = null;
        var wrapper = jQuery(this).parents('.sp-search-group');

        if (jQuery(this).find('[data-multiple="true"]').length > 0) {
            elem = jQuery(this).find('select');
            val = null;
            if (jQuery(this).find('select').val()) {
                val = jQuery(this).find('select').val().join(',');
            }
            search_type = wrapper.find('[name="search_type"]').val();
        } else if (jQuery(this).find('input').length) {
            elem = jQuery(this).find('input');
            val = jQuery(this).find('input').val();
            if (wrapper.find('[name="search_type"]').length > 0) {
                search_type = wrapper.find('[name="search_type"]').val();
            }
        } else {
            elem = jQuery(this).find('select');
            val = jQuery(this).find('select').val();
            search_type = wrapper.find('[name="search_type"]').val();
        }

        if (!search_type) {
            search_type = jQuery(this).data('search_type');
        }

        if (val) {
            filter_options['value'] = val;
            var filter = {};
            filter['attributeId'] = elem.data('id');
            filter['data'] = elem.attr('name');
            filter['search_type'] = search_type;
            filter['search'] = filter_options;
            post.push(filter);
        }
    });

    if (custom_data) {
        custom_data['custom_data'] = JSON.stringify(post);
    } else {
        var tmp = JSON.stringify(post);
        form.find('[name="rules"]').val(tmp);
        form.find('.sp-search-group').closest('[data-field-wrapper="true"]').remove();
    }

    return false;
}

jQuery(document).ready(function () {

    var ajaxLoader = $("#ajax-loading");

    if (jQuery('[data-add="add_product_attribute_filter"]').length > 0) {

        jQuery('body').find('.sp-search-group').parent().removeClass('col-sm-4');

        jQuery('body').on('click', '[data-add="add_product_attribute_filter"]', function (e) {

            var form = jQuery('[data-validate="true"]');
            var attribute_id = form.find('[name="attribute_id"]').val();

            if (!attribute_id) {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: "Please select attribute"
                });
                return true;
            }

            if (jQuery('body').find('[data-attribute-id="' + attribute_id + '"]').length > 0) {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: "This attribute is allready added"
                });
                return true;
            }

            var fields_wrapper = jQuery('.attribute-fields');

            jQuery.post(jQuery(this).data('url'), {
                'attribute_id': attribute_id,
                'form_type': 'form'
            }, function (result) {
                if (result.error === false) {
                    var html = jQuery(result.html);

                    html.find('.sp-search-group').parent().removeClass('col-sm-4');

                    if (html.find('[data-type="lookup"]').length > 0) {
                        initializeLookup(html.find('[data-type="lookup"]'), form);
                    } else if (html.find('[data-type="multiselect"]').length > 0) {
                        html.find('[data-type="multiselect"]').multiSelect();
                    } else if (html.find('[data-type="bchackbox"]').length > 0) {
                        html.find('[data-type="bchackbox"]').bootstrapSwitch();
                    }

                    fields_wrapper.append(html);

                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, 'json');
        });

        jQuery('body').on('click', '[data-action="delete_product_attribute_filter"]', function (e) {
            var elem = jQuery(this);
            elem.parents('[data-field-wrapper="true"]').remove();

            refreshList(jQuery('body').find('[data-table="product"]'), null);
        });

        jQuery('body').on('click', '[data-action="reload_product_filter_results"]', function (e) {
            e.preventDefault();

            refreshList(jQuery('body').find('[data-table="product"]'), null);

            return false;
        });
    }
});