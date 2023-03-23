function refreshConfigurationBundleOptionSelectedProductsList(table, result) {

    jQuery('body').find('[data-table="product_configuration_bundle_option_product_link"]').DataTable().ajax.reload(null, false);

    return false;
}

function refreshConfigurationBundleOptionsList(table, result) {

    jQuery('body').find('[data-table="product_configuration_product_link"]').DataTable().ajax.reload(null, false);

    return false;
}

function refreshProductConfigurableAttributeList(table, result) {

    jQuery('body').find('[data-table="product_configurable_attribute"]').DataTable().ajax.reload(null, false);
    jQuery('body').find('[data-table="available_configurable_products"]').DataTable().ajax.reload(null, false);

    return false;
}

function refreshSelectedSimpleProductList(table, result) {

    jQuery('body').find('[data-table="product_configuration_product_link"]').DataTable().ajax.reload(null, false);

    return false;
}

function refreshListViewOnModalClose(table, result) {

    if (result.error == false) {
        var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
        clone.html(result.html);
        var modal = clone.find('.modal');
        modal.modal('show');

        var form = modal.find('[data-validate="true"]');
        form.initializeValidation();

        form.attr('data-refresh', table.attr('id'));
    }

    return false;
}

jQuery(document).ready(function () {

    /**
     * Product group form
     */
    if (jQuery('[data-type="product"]').length > 0) {

        /*if(jQuery('body').find('[name="id"]').val() != ""){
            jQuery('[data-type="product_group"]').find('[name="store_id"]').select2('destroy').attr('readonly','readonly');
        }*/
        jQuery('body').find('[name="auto_generate_url_checkbox"]').prop('checked', true).change();
        jQuery('body').find('[name="auto_generate_url_checkbox"]').bootstrapSwitch('disabled', true);

        /**
         * Toggle keep url
         */
        jQuery('body').on('switchChange.bootstrapSwitch', '[name="keep_url_checkbox"]', function (event, state) {
            if (!state) {
                jQuery('body').find('[name="auto_generate_url_checkbox"]').bootstrapSwitch('disabled', false);
            } else {
                jQuery('body').find('[name="auto_generate_url_checkbox"]').prop('checked', true).change();
                jQuery('body').find('[name="auto_generate_url_checkbox"]').bootstrapSwitch('disabled', true);
                jQuery('[data-type="product"]').find('[data-form-group="url"]').find('[data-type="text"]').attr('readonly', 'readonly');
            }
        });

        /**
         * Toggle auto generate url
         */
        jQuery('body').on('switchChange.bootstrapSwitch', '[name="auto_generate_url_checkbox"]', function (event, state) {
            if (!state) {
                jQuery('[data-type="product"]').find('[data-form-group="url"]').find('[data-type="text"]').removeAttr('readonly');
            } else {
                jQuery('[data-type="product"]').find('[data-form-group="url"]').find('[data-type="text"]').attr('readonly', 'readonly');
            }
        });

        /**
         * Toggle date_discount_from required field.
         */
        if (jQuery('[data-type="product"] [name="date_discount_from"][data-fv-notempty="true"]').length > 0) {
            var handleProductDiscountFromField = function () {
                var label = "";
                var discountPercentage = parseFloat($('[name="discount_percentage"]').val());
                if (discountPercentage > 0) {
                    $('form[data-type="product"]').formValidation('enableFieldValidators', 'date_discount_from', true, null);
                    if ($('[name="date_discount_from"]').closest(".form-group").find("label>a").length > 0) {
                        label = $('[name="date_discount_from"]').closest(".form-group").find("label>a").text().replace("*", "");
                        label = label + "*";
                        $('[name="date_discount_from"]').closest(".form-group").find("label>a").text(label);
                    } else {
                        label = $('[name="date_discount_from"]').closest(".form-group").find("label").text().replace("*", "");
                        label = label + "*";
                        $('[name="date_discount_from"]').closest(".form-group").find("label").text(label);
                    }
                } else {
                    $('form[data-type="product"]').formValidation('enableFieldValidators', 'date_discount_from', false, null);
                    if ($('[name="date_discount_from"]').closest(".form-group").find("label>a").length > 0) {
                        label = $('[name="date_discount_from"]').closest(".form-group").find("label>a").text().replace("*", "");
                        $('[name="date_discount_from"]').closest(".form-group").find("label>a").text(label);
                    } else {
                        label = $('[name="date_discount_from"]').closest(".form-group").find("label").text().replace("*", "");
                        $('[name="date_discount_from"]').closest(".form-group").find("label").text(label);
                    }
                }
            }
            handleProductDiscountFromField();
            $(document).on("change keyup", '[name="discount_percentage"]', handleProductDiscountFromField);
            $(document).on("change keyup", '[name="date_discount_from"]', function () {
                var discountPercentage = parseFloat($('[name="discount_percentage"]').val());
                if (!$(this).val() && discountPercentage > 0) {
                    $('form[data-type="product"]').formValidation('enableFieldValidators', 'date_discount_from', true, null);
                } else {
                    $('form[data-type="product"]').formValidation('enableFieldValidators', 'date_discount_from', false, null);
                }
            });
        }
    }
});

function refreshRelatedProductsList(table, result) {

    jQuery('body').find('[data-table="product_product_link"]').DataTable().ajax.reload(null, false);

    return false;
}