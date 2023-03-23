jQuery(document).ready(function () {

    // ADMIN
    jQuery('body').on('change', 'fieldset.quote-descriptions-preview-settings [name="use_secondary_entity"]', function (e) {
        var elem = jQuery(this);
        var form = elem.parents('form');
        if(elem.is(":checked")){
            form.find('[name="secondary_entity"]').parents(".form-group").removeClass("hidden");
            form.find('[name="attribute"]').parents(".form-group").removeClass("hidden");
        }else{
            form.find('[name="secondary_entity"]').parents(".form-group").addClass("hidden");
            form.find('[name="attribute"]').parents(".form-group").addClass("hidden");
        }
    });

    jQuery('body').on('change', 'fieldset.quote-descriptions-preview-settings [name="secondary_entity"]', function (e) {
        var form = jQuery(this).parents('form');
        var elem = jQuery(this);

        loadAttributes(form, elem.data("url"), elem.val());
    });

    var loadAttributes = function(form, url, attribute_set){

        var backendTypes = new Array();
        backendTypes.push("text");
        backendTypes.push("decimal");
        backendTypes.push("int");
        backendTypes.push("varchar");
        backendTypes.push("lookup");

        jQuery.post(
            url,
            {
                type: form.find('[name="type"]').val(),
                entityType: attribute_set,
                backendTypes: backendTypes
            },
            function (result) {
                if (result.error == false) {
                    form.find('[name="attribute"]').html(result.html);
                }
                else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            },
            "json"
        );
    };

    // FRONTEND
});
