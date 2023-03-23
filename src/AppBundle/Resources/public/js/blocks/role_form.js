jQuery(document).ready(function() {

    if(jQuery('[data-type="role"]').length > 0){

        jQuery('body').on('switchChange.bootstrapSwitch', '[data-action="togglePrivilegesColumn"]', function (event, state) {
            var action_type = jQuery(this).data('holder-type');
            jQuery('body').find('[data-action-type="' + action_type + '"]').prop('checked', state).change();
        });

        jQuery('body').on('switchChange.bootstrapSwitch', '[data-action="togglePrivilegesAll"]', function (event, state) {
            jQuery('body').find('[data-action="togglePrivilegesColumn"]').prop('checked', state).change();
        });

    }

});