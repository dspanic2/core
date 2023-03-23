jQuery(document).ready(function () {

    var form = jQuery('body').find('[data-attribute-set-code="lead"]');
    if (form.length > 0) {

        jQuery('[data-action="lead_convert"]').on('click', function (e) {
            var id = form.find('[name="id"]').val();

            jQuery.post(jQuery(this).data('url'), {
                lead_id: id
            }, function (result) {
                if (result.error === false) {
                    window.location.replace(result.redirect_url);
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, 'json');
        });
    }
});
