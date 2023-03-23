jQuery(document).ready(function() {

    // ADMIN
    jQuery('body').on('change','[data-action="html_preview"]',function (e) {
        var form = jQuery(this).parents('form');
        var elem = jQuery(this);

        var backendTypes = new Array();
        backendTypes.push("text");
        backendTypes.push("textarea");

        jQuery.post(elem.data("url"), { type: form.find('[name="type"]').val(), attributeSet: elem.val(), backendTypes: backendTypes }, function(result) {
            if(result.error == false){
                form.find('[name="attr"]').html(result.html);
            }
            else{
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });
});