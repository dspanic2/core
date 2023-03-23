jQuery(document).ready(function () {

    var quote_hash = getUrlParameter("q", window.location.href);

    if (jQuery('#quotepreview').length > 0 && quote_hash) {
        initQuotePreviewButtons(quote_hash);
    }
});

function initQuotePreviewButtons(quote_hash) {

    /**
     * Accept quote
     */
    jQuery('[data-action="accept"]').on('click', function (e) {
        jQuery.post(jQuery(this).data("url"), {hash: quote_hash},
            function (result) {
                if (result.error === false) {
                    jQuery.growl.notice({
                        title: result.title,
                        message: result.message
                    });

                    window.location.replace(result.url);
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }
        );
    });

    /**
     * Decline quote
     */
    jQuery('[data-action="cancel"]').on("click", function () {
        jQuery.confirm({
            title: translations.please_confirm,
            content: translations.yes_i_am_sure,
            buttons: {
                confirm: {
                    text: translations.yes_i_am_sure,
                    btnClass: 'sp-btn btn-primary btn-blue btn',
                    keys: ['enter'],
                    action: function () {
                        jQuery.post(jQuery(this).data("url"), {hash: quote_hash}, function (result) {
                            if (result.error === false) {
                                jQuery.growl.notice({
                                    title: result.title,
                                    message: result.message
                                });
                                window.location.reload();
                            } else {
                                jQuery.growl.error({
                                    title: result.title,
                                    message: result.message
                                });
                            }
                        });
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

    jQuery('[data-action="download_pdf"]').on('click', function (e) {
        jQuery.post("/quote-download-pdf", {hash: quote_hash}, function (result) {
            var win = window.open(result.file, '_blank');
            if (result.error === false) {
                if (win) {
                    //Browser has allowed it to be opened
                    win.focus();
                } else {
                    //Browser has blocked it
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

    return true;
}
