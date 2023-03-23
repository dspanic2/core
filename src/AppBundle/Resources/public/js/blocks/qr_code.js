jQuery(document).ready(function() {

    // ADMIN
    jQuery('body').on('change','[data-action="change_qr_code_parent"]',function (e) {
        var form = jQuery(this).parents('form');
        var elem = jQuery(this);

        var backendTypes = new Array();
        backendTypes.push("varchar");
        backendTypes.push("integer");
        backendTypes.push("lookup");
        backendTypes.push("decimal");

        jQuery.post(elem.data("url"), { type: form.find('[name="type"]').val(), entityType: elem.find(':selected').data('id'), backendTypes: backendTypes, return_type: "id" }, function(result) {
            if(result.error == false){
                form.find('[name="attributeForQrCode"]').html(result.html);
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