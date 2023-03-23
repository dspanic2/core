jQuery(document).ready(function () {
    jQuery('body').on('click', '[data-add="s_product_attributes_link"]', function (e) {
        var elem = jQuery(this);
        var sp_block = elem.closest('.sp-block');
        var attributes_form_group_wrapper = sp_block.find('.s-product-attributes-form-group-wrapper');
        var configuration_id = jQuery('[name="s_product_attribute_configuration"]').val();

        var initializeCheckboxValueSwitcher = function (checkbox) {
            checkbox.parents('.form-group').find('[data-action="checkbox-value"]').val(checkbox.val());
            checkbox.on('switchChange.bootstrapSwitch', function (event, state) {
                if (state) {
                    jQuery(this).parents('.form-group').find('[data-action="checkbox-value"]').val(1);
                } else {
                    jQuery(this).parents('.form-group').find('[data-action="checkbox-value"]').val(0);
                }
            });
        };

        if (jQuery('body').find('[name="s_product_attributes_link[' + configuration_id + ']"]').length > 0 ||
            jQuery('body').find('[name="s_product_attributes_link[' + configuration_id + '][]"]').length > 0) {
            jQuery.growl.error({
                title: translations.error_message,
                message: "This attribute is already added"
            });
            return true;
        }

        jQuery.post(elem.data('url'), {
            configuration_id: configuration_id
        }, function (result) {
            if (result.error == false) {
                attributes_form_group_wrapper.append(result.html);
                if (result.configuration_type_id == 1 || result.configuration_type_id == 2) {
                    var lookup = attributes_form_group_wrapper.find('[data-type="lookup"]').last();
                    initializeLookup(lookup, jQuery('[data-type="product"]'));
                } else if (result.configuration_type_id == 4) {
                    var checkbox = attributes_form_group_wrapper.find('[data-type="bchackbox"]').last();
                    checkbox.bootstrapSwitch();
                    initializeCheckboxValueSwitcher(checkbox);
                }
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, 'json');
    });

    jQuery('body').on('click', '[data-delete="s_product_attribute"]', function (e) {
        jQuery(this).closest('.form-group').remove();
    });

});