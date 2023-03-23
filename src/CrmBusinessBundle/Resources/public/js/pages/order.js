jQuery(document).ready(function ($) {
    var ajaxLoader = $("#ajax-loading");

    /**
     * Generate order PDF
     */
    if (jQuery(document).find('[name="id"]').val()) {
        jQuery('[data-action="order_generate_pdf"]').on('click', function (e) {
            var elem = jQuery(this);
            jQuery.post(elem.data('url'), {
                id: jQuery('[name="id"]').val()
            }, function (result) {
                var win = window.open(result.file, '_blank');
                if (result.error === false) {
                    if (win) {
                        // Browser has allowed it to be opened
                        win.focus();
                    } else {
                        // Browser has blocked it
                        alert('Please allow popups for this website');
                    }
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            });
        });
    } else {
        $('[data-action="order_generate_pdf"]').remove();
    }

    /**
     * Order item change
     */
    jQuery('body').on('click', '[data-action="order_item_change"]', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var elem = jQuery(this);
        var table = elem.parents('.dataTables_wrapper').find('.datatables');
        var id = elem.data('id');
        ajaxLoader.addClass("active");

        $.post(elem.data("url"), {
            order_item_id: id,
        }, function (result) {
            ajaxLoader.removeClass("active");
            if (result.error == false) {

                var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                clone.html(result.html);
                var modal = clone.find('.modal');
                modal.modal('show');

                var form = modal.find('[data-validate="true"]');
                form.initializeValidation();

            } else {
                $.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });
});

