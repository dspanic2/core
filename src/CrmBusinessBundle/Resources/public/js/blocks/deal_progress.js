jQuery(document).ready(function () {

    jQuery('[data-action="deal_update_stage"]').on('click', function (e) {
        var button = jQuery(this);
        var wrapper = button.parent();

        jQuery.post(jQuery(this).data('url'), {
            deal_id: jQuery(this).data('deal-id'),
            deal_stage_id: jQuery(this).data('deal-stage-id')
        }, function (result) {
            if (result.error === false) {
                wrapper.children().removeClass('active');
                button.addClass('active');
                window.location.reload(true);
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, 'json');
    });
});
