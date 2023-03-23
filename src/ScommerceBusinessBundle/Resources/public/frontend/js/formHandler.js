$.extend({
    postFormSave: function (form, result) {},
});
jQuery(document).ready(function ($) {
    var lookupField = $('[data-type="lookup"]');
    var formRemoveItem = $('button.form-remove-item');
    var checkboxToggler = $('input[type="checkbox"]');
    var optionallyRequiredGroup = $('.optionally-required-group');

    // Handle required fields for group after one field is filled.
    if (optionallyRequiredGroup.length) {
        var checkOptionallyRequiredGroup = function () {
            optionallyRequiredGroup.each(function () {
                var setRequired = false;
                $(this).find("input:not([type='hidden']), select").each(function () {
                    if ($(this).val().length != 0) {
                        setRequired = true;
                        return;
                    }
                });
                if (setRequired) {
                    optionallyRequiredGroup.find("input:not([type='hidden']), select").prop("required", true);
                    if (optionallyRequiredGroup.find('[name="is_legal_entity"]').length) {
                        optionallyRequiredGroup.find('[name="is_legal_entity"]').val(1);
                    }
                } else {
                    optionallyRequiredGroup.find("input:not([type='hidden']), select").prop("required", false);
                    if (optionallyRequiredGroup.find('[name="is_legal_entity"]').length) {
                        optionallyRequiredGroup.find('[name="is_legal_entity"]').val(0);
                    }
                }
            });
        };
        checkOptionallyRequiredGroup();
        optionallyRequiredGroup.find("input:not([type='hidden']), select").on("input", function () {
            checkOptionallyRequiredGroup();
        });
    }

    // Form remove button handler.
    formRemoveItem.on('click', function (event) {
        event.preventDefault();
        var form = $(this).closest('form');
        form.find('[name="remove"]').val(1);
        form.trigger('submit');
    });

    $(document).on('change', 'input[type="checkbox"][data-toggle]', function () {
        if ($(this).data('toggle')) {
            var elementToToggle = $('#' + $(this).data('toggle'));
            if ($(this).is(':checked')) {
                elementToToggle.slideDown();
                elementToToggle.removeClass('optional');
            } else {
                elementToToggle.slideUp();
                elementToToggle.addClass('optional');
            }
        }
    });

    // Lookup field handler.
    if (lookupField.length) {
        lookupField.each(function () {
            $.initializeLookup($(this));
        });
    }

    // Submit handler.
    $.extend({
        initializeFrontendForm: function () {
            $('form:not(.initialized),.form:not(.initialized)').each(function () {
                var form = $(this);

                // Initialize select2
                form.find('[data-type="lookup"]').each(function () {
                    $.initializeLookup($(this));
                });

                // Initialize ckeditor
                form.find('[data-type="ckeditor"]').each(function () {
                    $.initializeCkeditor($(this));
                });

                // Initialize datetime pickers
                $.initializeDatetimepicker();

                // Create custom checkboxes
                $.createCustomCheckboxes();

                if ($(this).hasClass("ajax-submit")) {
                    $(this).on('submit', function (event) {
                        var form = $(this);
                        event.preventDefault();

                        $.initRecaptcha(function () {
                            if ($.formIsValid(form)) {
                                // Populate field by selector
                                form.find('[data-presubmit-populate]').each(function () {
                                    var field = $(this);
                                    var value = eval(field.data("presubmit-populate"));
                                    field.val(value);
                                });

                                var formData = new FormData(form[0]);

                                // Always add checkboxes
                                form.find("input[type=checkbox]").each(function (key, val) {
                                    formData.append($(val).attr('name'), $(this).is(':checked') ? 1 : 0);
                                });

                                var destinationParam = $.getUrlParam('destination');
                                if (destinationParam !== 0) {
                                    formData.append('destination', destinationParam);
                                }

                                $.showAjaxLoader();

                                $.ajax({
                                    url: form.attr('action'),
                                    method: 'POST',
                                    // data: formData,
                                    data: formData,
                                    cache: false,
                                    contentType: false,
                                    processData: false,
                                }).done(function (result) {
                                    $.hideAjaxLoader();
                                    if (result.error == false) {
                                        form.addClass("submitted");
                                        $.disableOverlays(form);
                                        if (result.message) {
                                            $.growl.notice({
                                                title: result.title ? result.title : '',
                                                message: result.message ? result.message : '',
                                            });
                                        }
                                        if (result.content) {
                                            // Replaces only form
                                            form.html('<span class="submitted">' + result.content + '</span>');
                                        }
                                        if (result.html && form.data("replace")) {
                                            // Replaces element by specified selector
                                            if (form.data("set-html") === 1) {
                                                $(form.data("replace")).html(result.html);
                                            } else {
                                                $(form.data("replace")).replaceWith(result.html);
                                            }
                                        }
                                        if (form.data("replace-pieces")) {
                                            // Example: data-replace-pieces="cart_html->#cart|minicart_html->#cart-display"
                                            var pieces = form.data("replace-pieces").split("|")
                                            $.each(pieces, function (key, value) {
                                                var data = value.split("->");
                                                if (result[data[0]] != undefined && $(data[1]).length > 0) {
                                                    $(data[1]).replaceWith(result[data[0]]);
                                                } else {
                                                    console.log("Wrong replace parameters!");
                                                }
                                            });
                                        }
                                        if (form.data('trigger-action')) {
                                            $(document).trigger(form.data('trigger-action'), [form.serializeAssoc(), form, result]);
                                        }
                                        if (result.redirect_url) {
                                            window.location.href = result.redirect_url;
                                        }
                                        if (result.reload) {
                                            $.reloadPage();
                                        }
                                        if ($("body.page-dashboard_profile").length == 0 && !form.hasClass("keep-values")) {
                                            form.find("input").not('.keep-value, :input[type=button], :input[type=submit], :input[type=reset], :input[type=checkbox], :input[type=radio]').val("");
                                            form.find("textarea").val("");
                                        }

                                        if (form.attr("id") == "favorite") {
                                            var favoritesAmount = $('header .favorites .amount');
                                            if (favoritesAmount.length) {
                                                favoritesAmount.show();
                                                favoritesAmount.html(result.count);
                                            }
                                        }

                                        if (form.parents(".overlay.active").length > 0) {
                                            form.parents(".overlay.active").removeClass("active");
                                        }

                                        $.postFormSave(form, result);
                                    } else {
                                        $.growl.error({
                                            title: result.title ? result.title : '',
                                            message: result.message ? result.message : '',
                                        });

                                        // Reset remove values.
                                        if (form.find('[name="remove"]').length) {
                                            form.find('[name="remove"]').val(0);
                                        }
                                    }

                                    if (result.open_login_modal != undefined && result.open_login_modal) {
                                        $(document).trigger("modal:login");
                                    }
                                });
                            } else {
                                $.growl.error({
                                    title: translations.form_invalid_title,
                                    message: translations.form_invalid_message,
                                });
                            }
                        });
                    });
                } else {
                    var formMethod = $(this).attr("method");
                    if (formMethod !== undefined && formMethod.toLowerCase() === "get") {
                        $(this).on('submit', function () {
                            var form = $(this);
                            form.find(":input").filter(function () {
                                return !this.value;
                            }).attr("disabled", "disabled");
                            return true;
                        });
                    }
                }
                $(this).addClass("initialized");
            });
        }
    });
    $.initializeFrontendForm();

    $(document).on("change", ".form-error", function () {
        $(this).removeClass("form-error");
    })
});
