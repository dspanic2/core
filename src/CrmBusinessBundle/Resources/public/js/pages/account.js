function toggleAccountLegalType(form){

    var type = form.find('[name="is_legal_entity_checkbox"]').bootstrapSwitch('state');

    if(type){
        if(form.find('[name="first_name"]').data('fv-notempty')){
            form.formValidation('enableFieldValidators','first_name', false, null);
        }
        form.find('[data-form-group="first_name"]').val('').hide();

        if(form.find('[name="last_name"]').data('fv-notempty')){
            form.formValidation('enableFieldValidators','last_name', false, null);
        }
        form.find('[data-form-group="last_name"]').val('').hide();

        if(form.find('[name="name"]').data('fv-notempty')){
            form.formValidation('enableFieldValidators','name', true, null);
        }
        form.find('[data-form-group="name"]').show();
        form.find('[data-form-group="employees"]').show();
        form.find('[data-form-group="industry_type_id"]').show();
    }
    else{
        if(form.find('[name="first_name"]').data('fv-notempty')){
            form.formValidation('enableFieldValidators','first_name', true, null);
        }
        form.find('[data-form-group="first_name"]').show();

        if(form.find('[name="last_name"]').data('fv-notempty')){
            form.formValidation('enableFieldValidators','last_name', true, null);
        }
        form.find('[data-form-group="last_name"]').show();

        if(form.find('[name="name"]').data('fv-notempty')){
            form.formValidation('enableFieldValidators','name', false, null);
        }
        form.find('[data-form-group="name"]').val('').hide();
        form.find('[data-form-group="employees"]').hide();
        form.find('[data-form-group="industry_type_id"]').hide();
    }

    return false;
}

jQuery(document).ready(function() {

    if(jQuery('body').find('[data-type="account"]').length > 0){
        var wrapper = jQuery('body').find('[data-type="account"]');

        if(wrapper.find('[name="is_legal_entity_checkbox"]').length > 0){
            toggleAccountLegalType(wrapper);

            wrapper.find('[name="is_legal_entity_checkbox"]').on('switchChange.bootstrapSwitch',function (e) {
                toggleAccountLegalType(wrapper);
            });
        }
    }

    /**
     * Activate on account modal
     */
    jQuery('body').on('shown.bs.modal', '#default_modal', function (e) {
        var wrapper = jQuery(this);
        if(wrapper.find('[data-type="account"]').length > 0){

            if(wrapper.find('[name="is_legal_entity_checkbox"]').length > 0){
                toggleAccountLegalType(wrapper.find('[data-validate="true"]'));

                wrapper.find('[name="is_legal_entity_checkbox"]').on('switchChange.bootstrapSwitch',function (e) {
                    toggleAccountLegalType(wrapper.find('[data-validate="true"]'));
                });
            }
        }

        return true;
    });

});
