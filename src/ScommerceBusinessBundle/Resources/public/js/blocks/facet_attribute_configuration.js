jQuery(document).ready(function () {

    // ADMIN

    // FRONTEND
    if (jQuery('[data-type="facet_attribute_configuration"]').length > 0) {
        jQuery(".facet-attribute-configuration-save").on("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            var options = {};
            options.url = jQuery("#facet-attribute-configuration-form").attr("action");
            options.method = 'POST';
            options.data = jQuery("#facet-attribute-configuration-form").serialize();
            $.ajax(options).done(function (result) {
                if (result.error == false) {
                    $.growl.notice({
                        title: result.title ? result.title : '',
                        message: result.message,
                    });
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : '',
                    });
                }
            });
        });
    }
});
