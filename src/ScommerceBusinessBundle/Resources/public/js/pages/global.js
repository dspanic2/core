jQuery(document).ready(function ($) {
    // Toggle store fields
    var showOnStore = $('[data-form-group="show_on_store"]');
    if (showOnStore.length) {
        var setStoreVisibility = function (storeId, state, form) {
            var fieldWrapper = form.find('[data-wrapper="store_value"][data-store="' + storeId + '"]')
            if (state) {
                fieldWrapper.slideDown();
                fieldWrapper.find('input').removeAttr('disabled');
                fieldWrapper.find('select').removeAttr('disabled');
                fieldWrapper.find('textarea').removeAttr('disabled');
                fieldWrapper.each(function () {
                    if (CKEDITOR !== undefined && CKEDITOR.instances[$(this).find('textarea[data-type="ckeditor"]').attr("id")] !== undefined) {
                        CKEDITOR.instances[$(this).find('textarea[data-type="ckeditor"]').attr("id")].setReadOnly(false);
                    }
                });
            } else {
                fieldWrapper.slideUp();
                fieldWrapper.find('input').attr('disabled', 'disabled');
                fieldWrapper.find('select').attr('disabled', 'disabled');
                fieldWrapper.find('textarea').attr('disabled', 'disabled');
                fieldWrapper.each(function () {
                    if (CKEDITOR !== undefined && CKEDITOR.instances[$(this).find('textarea[data-type="ckeditor"]').attr("id")] !== undefined) {
                        CKEDITOR.instances[$(this).find('textarea[data-type="ckeditor"]').attr("id")].setReadOnly(true);
                    }
                });
            }
        };

        showOnStore.find('[name^="show_on_store_checkbox"]').each(function () {
            var storeId = $(this).parents(".store-switcher").data("store");
            setStoreVisibility(storeId, $(this).is(":checked"), $(this).parents("form"));
        });
        $('body').on('switchChange.bootstrapSwitch', '[name^="show_on_store_checkbox"]', function (event, state) {
            var storeId = $(this).parents(".store-switcher").data("store");
            setStoreVisibility(storeId, state, $(this).parents("form"));
        });
    }
});
