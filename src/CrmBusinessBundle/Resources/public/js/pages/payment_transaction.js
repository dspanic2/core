jQuery(document).ready(function ($) {

    function resetButtons() {

        var form = jQuery('[data-type="payment_transaction"]');

        if (form.find('[name="id"]').val() != "") {

            var payment_transaction_status = form.find('[name="transaction_status_id"]').val();

            /**
             * Preauthorized
             */
            if (payment_transaction_status == 1) {
                jQuery('[data-action="complete_transaction"]').removeClass('hidden');
                jQuery('[data-action="cancel_transaction"]').removeClass('hidden');
            }
            /**
             * Completed
             */
            else if (payment_transaction_status == 2) {
                jQuery('[data-action="refund_transaction"]').removeClass('hidden');
            }
        }
    }

    if (jQuery('[data-type="payment_transaction"]').length > 0) {

        resetButtons();

        var actions = '[data-action="complete_transaction"], ' +
            '[data-action="cancel_transaction"], ' +
            '[data-action="refund_transaction"]';

        jQuery('body').on('click', actions, function (e) {

            e.stopPropagation();
            var elem = jQuery(this);
            var form = jQuery('[data-type="payment_transaction"]');
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
                            jQuery.post(elem.data('url'), {
                                id: id
                            }, function (result) {
                                if (result.error == false) {
                                    jQuery.growl.notice({
                                        title: result.title,
                                        message: result.message
                                    });

                                    location.reload(true);
                                } else {
                                    jQuery.growl.error({
                                        title: translations.error_message,
                                        message: result.message
                                    });
                                }
                            }, 'json');
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
