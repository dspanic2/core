jQuery(document).ready(function () {

    if (jQuery('[data-action="collapse_block"]').length > 0) {
        jQuery('[data-action="collapse_block"]').click(function () {
            if (jQuery(this).hasClass("collapsed")) {
                jQuery(this).html(jQuery(this).data("collapse-text"));
            }else{
                jQuery(this).html(jQuery(this).data("open-text"));
            }
        });
    }

});