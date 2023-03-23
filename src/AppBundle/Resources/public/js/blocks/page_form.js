jQuery(document).ready(function() {

    if(jQuery('[data-type="page"]').length > 0){

        jQuery('body').on('switchChange.bootstrapSwitch', '[data-action="togglePrivilegesColumn"]', function (event, state) {
            var action_type = jQuery(this).data('holder-type');
            var holder = jQuery(this).parents('.panel-body');
            holder.find('[data-action-type="' + action_type + '"]').prop('checked', state).change();
        });

        jQuery('body').on('switchChange.bootstrapSwitch', '[data-action="toggleHiddenValue"]', function (event, state) {
            var hiddenInput = jQuery(this).parents('label').find('[data-type="hidden_value"]');
            if(state){
                hiddenInput.val(1);
            }
            else{
                hiddenInput.val(0);
            }
        });
    }

});