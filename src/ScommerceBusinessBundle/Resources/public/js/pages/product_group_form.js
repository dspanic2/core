jQuery(document).ready(function () {

    /**
     * Product group form
     */
    if (jQuery('[data-type="product_group"]').length > 0) {

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
                jQuery('[data-type="product_group"]').find('[data-form-group="url"]').find('[data-type="text"]').attr('readonly', 'readonly');
            }
        });

        /**
         * Toggle auto generate url
         */
        jQuery('body').on('switchChange.bootstrapSwitch', '[name="auto_generate_url_checkbox"]', function (event, state) {
            if (!state) {
                jQuery('[data-type="product_group"]').find('[data-form-group="url"]').find('[data-type="text"]').removeAttr('readonly');
            } else {
                jQuery('[data-type="product_group"]').find('[data-form-group="url"]').find('[data-type="text"]').attr('readonly', 'readonly');
            }
        });
    }
});