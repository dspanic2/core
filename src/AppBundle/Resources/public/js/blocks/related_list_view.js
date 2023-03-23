jQuery(document).ready(function() {

    // ADMIN
    jQuery('body').on('change','fieldset.related-list-view-settings [name="listView"]',function (e) {
        var fieldset = jQuery("fieldset.related-list-view-settings");
        var listView = fieldset.find('[name="listView"]');

        jQuery.post(
            fieldset.find('[name="prepopulateLookupAttributes"]').data("url"),
            {
                type: fieldset.find('[name="type"]').val(),
                attributeSetFromListView: true,
                listView: listView.val()
            },
            function(result) {
                if(result.error == false){
                    fieldset.find('[name="prepopulateLookupAttributes"]').removeAttr('readonly').html(result.html).parents('.form-group').show();
                }
                else{
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            },
            "json"
        );
    });

    // FRONTEND

});
