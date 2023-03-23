/**
 * Update block once its form has been submitted.
 * Triggered by form in "src/AppBundle/Resources/views/Block/google_authenticator.html.twig".
 * @param form
 * @param result
 */
function googleAuthenticatorPostsave(result) {
    var qrCodeSection = jQuery('body').find('.google-authenticator-block-codes-section');
    var data = result.data.googleAuthenticator;
    if (data.enabled) {
        qrCodeSection.removeClass('hidden');
        qrCodeSection.find('img').prop('src', data.qrCodeUrl);
        qrCodeSection.find('code').text(data.secret);
    } else {
        qrCodeSection.addClass('hidden');
    }
}

jQuery(document).ready(function() {

    if(jQuery('[name="mfa_google_authenticator_enabled"]').length > 0) {

        var wrapper = jQuery('[data-form-group="mfa_google_authenticator_enabled"]');

        if(wrapper.parents('form').length == 0){

            wrapper.find('[data-type="bchackbox"]').each(function (e) {
                jQuery(this).bootstrapSwitch();
                if(jQuery(this).data('form-type') == 'view'){
                    jQuery(this).bootstrapSwitch('disabled',true);
                }
            });
            wrapper.on('switchChange.bootstrapSwitch', '[data-type="bchackbox"]', function (event, state) {
                if (state) {
                    jQuery(this).parents('.form-group').find('[data-action="checkbox-value"]').val(1);
                }
                else {
                    jQuery(this).parents('.form-group').find('[data-action="checkbox-value"]').val(0);
                }
            });
        }

        jQuery('body').on('switchChange.bootstrapSwitch','[name="mfa_google_authenticator_enabled_checkbox"]',function (e) {
            jQuery.post(wrapper.data('url'), { mfa_google_authenticator_enabled: jQuery('[name="mfa_google_authenticator_enabled"]').val() }, function(result) {
                if(result.error == false){
                    jQuery.growl.notice({
                        title: result.title,
                        message: result.message
                    });
                    googleAuthenticatorPostsave(result)
                }
                else{
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, "json");
        });
    }
});