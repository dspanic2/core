/*function changeStore(store_id){

    var form = jQuery('[data-type="s_page"]');

    form.find('[name="canonical_id"]').val('').trigger('change');
    form.find('[name="menu_id"]').val('').trigger('change');

    return false;
}*/

jQuery(document).ready(function() {

    /**
     * S page form
     */
    if (jQuery('[data-type="s_page"]').length > 0) {

        /*if(jQuery('body').find('[name="id"]').val() != ""){
            jQuery('[data-type="s_page"]').find('[name="store_id"]').select2('destroy').attr('readonly','readonly');
        }*/
        jQuery('body').find('[name="auto_generate_url_checkbox"]').prop('checked', true).change();
        jQuery('body').find('[name="auto_generate_url_checkbox"]').bootstrapSwitch('disabled', true);

        /**
         * Toggle keep url
         */
        jQuery('body').on('switchChange.bootstrapSwitch', '[name="keep_url_checkbox"]', function (event, state) {
            if(!state){
                jQuery('body').find('[name="auto_generate_url_checkbox"]').bootstrapSwitch('disabled', false);
            }
            else{
                jQuery('body').find('[name="auto_generate_url_checkbox"]').prop('checked', true).change();
                jQuery('body').find('[name="auto_generate_url_checkbox"]').bootstrapSwitch('disabled', true);
                jQuery('[data-type="s_page"]').find('[data-form-group="url"]').find('[data-type="text"]').attr('readonly','readonly');
            }
        });

        /**
         * Toggle auto generate url
         */
        jQuery('body').on('switchChange.bootstrapSwitch', '[name="auto_generate_url_checkbox"]', function (event, state) {
            if(!state){
                jQuery('[data-type="s_page"]').find('[data-form-group="url"]').find('[data-type="text"]').removeAttr('readonly');
            }
            else{
                jQuery('[data-type="s_page"]').find('[data-form-group="url"]').find('[data-type="text"]').attr('readonly','readonly');
            }
        });

        /*jQuery('body').on('change','[name="store_id"]',function (e) {
            changeStore(jQuery(this).val());
        });*/
    }
});
