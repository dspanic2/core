jQuery(document).ready(function ($) {
    var handleWebformFieldTypeChange = function (fieldTypeElement) {
        if (fieldTypeElement.val() == 1 || fieldTypeElement.val() == 3 || fieldTypeElement.val() == 5) { // Checkbox, radio, select
            fieldTypeElement.parents("form").find('[data-form-group="options"]').removeAttr('disabled').show().removeClass("hidden");
        } else {
            fieldTypeElement.parents("form").find('[data-form-group="options"]').attr('disabled', 'disabled').hide().addClass("hidden");
        }
        if (fieldTypeElement.val() == 6) { // File
            fieldTypeElement.parents("form").find('[data-form-group="allowed_extensions"]').removeAttr('disabled').show().removeClass("hidden");
        } else {
            fieldTypeElement.parents("form").find('[data-form-group="allowed_extensions"]').attr('disabled', 'disabled').hide().addClass("hidden");
        }
        if (fieldTypeElement.val() == 8) { // Autocomplete
            fieldTypeElement.parents("form").find('[data-form-group="entity_type_code_id"]').removeAttr('disabled').show().removeClass("hidden");
        } else {
            fieldTypeElement.parents("form").find('[data-form-group="entity_type_code_id"]').attr('disabled', 'disabled').hide().addClass("hidden");
        }
        if (fieldTypeElement.val() == 9) { // Autocomplete
            fieldTypeElement.parents("form").find('[data-form-group="content"]').removeAttr('disabled').show().removeClass("hidden");
        } else {
            fieldTypeElement.parents("form").find('[data-form-group="content"]').attr('disabled', 'disabled').hide().addClass("hidden");
        }
    }

    if ($('[name="webform_field_type_id"]').length > 0) {
        $('[name="webform_field_type_id"]').each(function () {
            handleWebformFieldTypeChange($(this));
        });
    }
    $(document).on('change', 'select[name="webform_field_type_id"]', function () {
        handleWebformFieldTypeChange($(this));
    });

    $(document).on('switchChange.bootstrapSwitch', '[data-action="useContextEntity"]', function (event, state) {
        var form = $(this).parents("form");
        form.find(".webform-select").slideToggle();
    });

    jQuery(window).on('shown.bs.modal', function() {
        if ($('[name="webform_field_type_id"]').length > 0) {
            $('[name="webform_field_type_id"]').each(function () {
                console.log($(this));
                handleWebformFieldTypeChange($(this));
            });
        }
    });
});