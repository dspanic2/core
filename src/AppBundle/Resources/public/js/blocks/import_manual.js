jQuery(document).ready(function ($) {

    /**
     * Import manual
     */
    if (jQuery('[data-type="import_manual"]').length > 0) {

        var form = jQuery('[data-type="import_manual"]');

        if(form.find('[name="id"]').val() != "" && form.find('[name="import_manual_status_id"]').val() == 1){
            $(document).find('[data-action="import_manual_run"]').removeClass("hidden");
        }

        jQuery('[data-action="import_manual_run"]').on('click',function (e) {
            e.preventDefault();
            e.stopPropagation();

            var button = jQuery(this);
            var id = form.find('[name="id"]').val();

            jQuery.confirm({
                title: translations.please_confirm,
                content: translations.yes_i_am_sure,
                buttons: {
                    confirm: {
                        text: translations.yes_i_am_sure,
                        btnClass: 'sp-btn btn-primary btn-blue btn',
                        keys: ['enter'],
                        action: function () {
                            button.attr("disabled","disabled");

                            jQuery.post(button.data("url"), {id: id}, function (result) {
                                if (result.error == false) {
                                    button.removeAttr("disabled");
                                    location.reload(true);
                                }
                                else {
                                    button.removeAttr("disabled");
                                    jQuery.growl.error({
                                        title: translations.error_message,
                                        message: result.message
                                    });
                                }
                            }, "json");
                        }
                    },
                    cancel: {
                        text: translations.cancel,
                        btnClass: 'sp-btn btn-default btn-red btn',
                        action: function () {
                        }
                    }
                }
            });
        });
    }
});