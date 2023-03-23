jQuery.extend({
    openStepByNumber: function (step_number, deactivate_step) {
        if (typeof deactivate_step == "undefined") {
            deactivate_step = false;
        }
        $.setUrlParameter('step', step_number);
        $('.cart-step-title ').removeClass('active').removeClass('current');
        $('#cart-step-' + step_number + '-title').addClass('current');
        $('.cart-step').hide();

        var step = $('#cart-step-' + step_number);
        $(".cart-step.current").removeClass('current');
        step.show().addClass('current');

        $.scrollToTop($('#cart'));
        var i;
        for (i = 1; i <= step_number; i++) {
            $('#' + step.attr('id') + '-title').addClass('active');
            if (i < step_number) {
                $('#cart-step-' + i + '-title').addClass('active');
            }
        }
        if (deactivate_step) {
            $('#cart-step-' + deactivate_step + '-title').removeClass('active');
            $('#cart-step-' + deactivate_step + 1 + '-title').removeClass('active');
        }
        if (step_number == 2) {
            $(".cart-products").addClass("hidden");
        } else {
            $(".cart-products").removeClass("hidden");
        }

        $(document).find('[data-type="lookup"]:not(.select2-hidden-accessible)').each(function () {
            $.initializeLookup($(this));
        });
    },
    recalculateTotalPrice: function () {
        var checkoutForm = $('#cart form');
        $.ajax({
            url: "/cart/recalculate_totals",
            method: 'GET',
            data: {
                "form": checkoutForm.serialize()
            },
            cache: false,
        }).done(function (result) {
            if (result.error == false) {
                $("#cart-step-2 .cart-totals").replaceWith(result.html);
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
            $.hideAjaxLoader();
        });
    },
    postDeliveryReset: function () {
    },
    handleOnPaymentChange: function (element) {
        var description = element.find(":selected").data("description");
        if (!description) {
            description = "";
        }
        element.closest(".form-group").find(".description").html(description);

        $.recalculateTotalPrice();
    },
    postPaymentReset: function () {
    },
    handleCartProceed: function (next_step_number) {
        $.openStepByNumber(next_step_number);
    },
    checkQuoteStatus: function () {
        $.ajax({
            url: '/api/kekspay_check_transaction',
            method: 'POST',
            data: {"quote_hash": $('[data-keks-hash]').data("keks-hash")},
            cache: false
        }).done(function (result) {
            if (result.order_created) {
                $(document).trigger("stop-check-quote-status");
                if (result.quoteSuccessUrl != undefined) {
                    window.location.href = result.quoteSuccessUrl;
                } else {
                    window.location.href = "/";
                }
            }
            $.hideAjaxLoader();
        });
    },
    cartRegister: function (registerSection) {
        var data = {};
        data.recaptcha_response = registerSection.find('[name="recaptcha_response"]').val();
        data.is_legal_entity = registerSection.find('[name="is_private_r1"]').is(":checked") ? 1 : 0;
        data.email = registerSection.find('[name="email"]').val();
        data.create_account = registerSection.find('[name="create_account"]').is(":checked") ? 1 : 0;
        data.password = registerSection.find('[name="password"]').val();
        data.repeat_password = registerSection.find('[name="repeat_password"]').val();
        data.first_name = registerSection.find('[name="first_name"]').val();
        data.last_name = registerSection.find('[name="last_name"]').val();
        data.country_id = registerSection.find('[name="country_id"]').val();
        if (registerSection.find('[name="city_id"]').length) {
            data.city_id = registerSection.find('[name="city_id"]').val();
        }
        if (registerSection.find('[name="city_name"]').length) {
            data.city_name = registerSection.find('[name="city_name"]').val();
        }
        if (registerSection.find('[name="postal_code"]').length) {
            data.postal_code = registerSection.find('[name="postal_code"]').val();
        }
        data.street = registerSection.find('[name="street"]').val();
        data.phone = registerSection.find('[name="phone"]').val();
        data.birth_day = registerSection.find('[name="birth_day"]').val();
        data.birth_month = registerSection.find('[name="birth_month"]').val();
        data.birth_year = registerSection.find('[name="birth_year"]').val();
        data.name = registerSection.find('[name="company_name"]').val();
        data.oib = registerSection.find('[name="oib"]').val();
        data.newsletter_signup = $('#cart [name="newsletter_signup"]').is(":checked") ? 1 : 0;
        $.ajax({
            url: '/cart/update_cart_customer_data', method: 'POST', data: data, cache: false
        }).done(function (result) {
            if (result.error == false) {
                $.cartUpdateDeliveryAddress(registerSection);
                if (result.open_login_modal != undefined && result.open_login_modal && $("#login-form-overlay").length) {
                    $("#login-form-overlay").addClass("active");
                }
            } else {
                $.hideAjaxLoader();
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
        return false;
    },
    cartUpdateDeliveryAddress: function (registerSection) {
        var data = {};
        data.recaptcha_response = registerSection.find('[name="recaptcha_response"]').val();
        data.shipping_address_same = registerSection.find('[name="shipping_address_same"]').is(":checked") ? 1 : 0;
        if (registerSection.find('[name="account_shipping_address_id"]').length) {
            data.shipping_address_id = registerSection.find('[name="account_shipping_address_id"]').val();
        } else if (registerSection.find('[name="shipping_country_id"]').length) {
            data.shipping_city_id = registerSection.find('[name="shipping_city_id"]').val();
            data.shipping_city_name = registerSection.find('[name="shipping_city_name"]').val();
            data.shipping_postal_code = registerSection.find('[name="shipping_postal_code"]').val();
            data.shipping_street = registerSection.find('[name="shipping_street"]').val();
            data.shipping_country_id = registerSection.find('[name="shipping_country_id"]').val();
            data.shipping_first_name = registerSection.find('[name="shipping_first_name"]').val();
            data.shipping_last_name = registerSection.find('[name="shipping_last_name"]').val();
            data.shipping_phone = registerSection.find('[name="shipping_phone"]').val();
        } else {
            // Use headquaters address
        }
        $.ajax({
            url: '/cart/update_cart_delivery_address', method: 'POST', data: data, cache: false
        }).done(function (result) {
            if (result.error == false) {
                $.cartUpdatePaymentAndDelivery(registerSection);
            } else {
                $.hideAjaxLoader();
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
        return false;
    },
    cartUpdatePaymentAndDelivery: function (registerSection) {
        var data = {};
        data.delivery_type_id = registerSection.find('[name="delivery_type_id"]').val();
        data.payment_type_id = registerSection.find('[name="payment_type_id"]').val();
        $.ajax({
            url: '/cart/update_cart_payment_and_delivery', method: 'POST', data: data, cache: false
        }).done(function (result) {
            if (result.error == false) {
                $.cartUpdateMessage(registerSection);
            } else {
                $.hideAjaxLoader();
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
        return false;
    },
    cartUpdateMessage: function (registerSection) {
        var data = {};
        data.message = registerSection.find('[name="message"]').val();
        $.ajax({
            url: '/cart/update_cart_message', method: 'POST', data: data, cache: false
        }).done(function (result) {
            if (result.error == false) {
                $.generateConfirmModal();
            } else {
                $.hideAjaxLoader();
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
        return false;
    },
    generateConfirmModal: function () {
        $.ajax({
            url: '/cart/generate_confirm_modal', method: 'POST', data: {}, cache: false
        }).done(function (result) {
            if (result.error == false) {
                $(".overlay").removeClass("active");
                if (result.redirect_url) {
                    window.location.href = result.redirect_url;
                } else {
                    var cartConfirm = $("#cart-confirm");
                    if (cartConfirm.length == 0) {
                        cartConfirm = $("<div class='overlay' id='cart-confirm'></div>")
                        $("body").append(cartConfirm);
                    }
                    cartConfirm.html(result.html);
                    cartConfirm.addClass("active");
                    $("body").addClass("disable-scroll");

                    if ($('[data-keks-hash]').length) {
                        $(document).trigger("check-quote-status");
                    }
                }
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
            $.hideAjaxLoader();
        });
        return false;
    },
    handleSubmitDisable: function () {
        var enable = true;
        $('input[type="checkbox"][data-submit-enable="true"]').each(function () {
            if (!$(this).is(":checked")) {
                enable = false;
                return;
            }
        });
        if (enable) {
            $(".button.toggleable").removeClass("disabled").prop("disabled", false);
        } else {
            $(".button.toggleable").addClass("disabled").prop("disabled", true);
        }
    },
});
jQuery(document).ready(function ($) {
    // Expand configurable items
    $(document).on('click', '.checkout-view .expand', "change", function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(this).parents(".product-display-grid").toggleClass("expanded").next().slideToggle();
    });

    // Submit marketing signup
    var isEmail = function (email) {
        var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
        return regex.test(email);
    }
    var submitMarketing = function () {
        if (!$('#cart').hasClass("marketing-on") && isEmail($('#cart').find('[name="email"]').val()) && $('#cart').find('[data-id="accept_terms"]').prop("checked")) {
            $('#cart').addClass("marketing-on");
            $.ajax({
                url: '/cart/update_cart_customer_marketing_data',
                method: 'POST',
                data: $("#cart-step-2").find(".basic-information").parents("form").serialize(),
                cache: false
            }).done(function (result) {
            });
        }
    }
    $(document).on("change", '#cart [name="email"]', "change", function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        submitMarketing();
    });
    $(document).on("change", '#cart [data-id="accept_terms"]', function (e) {
        // ne stavljati prevent
        submitMarketing();
    });

    // Disable links on cart preview modal.
    $(document).on('click', "#cart-confirm a", function (e) {
        if ($(this).closest(".cart-final-buttons").length == 0) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    });

    // Handle sending to multiple addresses
    var handleSendMultipleDisplay = function (checkbox) {
        if (checkbox.is(":checked")) {
            $(".send-multiple-form-group").slideDown().removeClass('optional');
        } else {
            $(".send-multiple-form-group").slideUp().addClass('optional');
        }
    };
    if ($('#cart input#send_multiple').length) {
        handleSendMultipleDisplay('#cart input#send_multiple');
    }
    $(document).on("change", '#cart input#send_multiple', function () {
        handleSendMultipleDisplay($(this));
    });

    // Toggle cart confirm button enabled/disabled
    if ($('input[type="checkbox"][data-submit-enable="true"]').length) {
        $.handleSubmitDisable();
    }
    $(document).on("change", 'input[type="checkbox"][data-submit-enable="true"]', function () {
        var isChecked = $(this).prop("checked");

        // Change same IDs
        var id = $(this).data("id");
        $('[data-id="' + id + '"]').each(function () {
            $(this).prop("checked", isChecked);
            if (isChecked) {
                $(this).closest(".custom-checkbox").addClass("checked");
            } else {
                $(this).closest(".custom-checkbox").removeClass("checked");
            }
        });

        $.handleSubmitDisable();
    });

    // Live email validate
    var validateEmailInBackend = function (email, element) {
        if ($.emailIsValid(email)) {
            $.ajax({
                url: '/cart/validate_customer_email', method: 'POST', data: {
                    "email": email,
                    "recaptcha_response": element.parents("form, .form").find('[name="recaptcha_response"]').val()
                }, cache: false
            }).done(function (result) {
                if (result.error == false) {
                    element.removeClass('not-valid');
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : '',
                    });
                    element.addClass('not-valid');
                }
            });
        } else {
            $.growl.error({
                title: translations.invalid_email,
                message: '',
            });
            element.addClass('not-valid');
        }
    };

    // Validate email
    $(document).on("change", '#cart [name="email"]', $.debounce(function () {
        if ($('#cart [name="create_account"]').length && $('#cart [name="create_account"]').is(":checked")) {
            var email = $(this).val();
            validateEmailInBackend(email, $(this));
        }
    }, 500));
    $(document).on("change", '#cart [name="create_account"]', $.debounce(function () {
        if ($(this).is(":checked")) {
            var email = $(this).parents("form").find('[name="email"]').val();
            validateEmailInBackend(email, $(this));
        }
    }, 500));

    // Handle delivery visibility
    var handleDeliveryAddressDisplay = function (checkbox) {
        if (checkbox.is(":checked")) {
            $(".delivery-address-data").slideUp().addClass('optional');
        } else {
            $(".delivery-address-data").slideDown().removeClass('optional');
        }
    };
    if ($('input#delivery_address').length) {
        handleDeliveryAddressDisplay($('input#delivery_address'));
    }
    $(document).on("change", 'input#delivery_address', function () {
        handleDeliveryAddressDisplay($(this));
    });

    // Handle account creation password visibility
    var handlePasswordDisplay = function (checkbox) {
        if (checkbox.is(":checked")) {
            $(".form-row.passwords").slideDown().removeClass('optional');
        } else {
            $(".form-row.passwords").slideUp().addClass('optional');
        }
    };
    if ($('#cart input#create_account').length) {
        handlePasswordDisplay($('#cart input#create_account'));
    }
    $(document).on("change", '#cart input#create_account', function () {
        handlePasswordDisplay($(this));
    });

    // Toggle delivery address visibility
    if ($('[name="delivery_type_id"]').length) {
        var handleDeliveryAdress = function () {
            if ($('[name="delivery_type_id"]').hasClass("select2-hidden-accessible")) {
                $('[name="delivery_type_id"]').on('select2:select', function (e) {
                    var data = e.params.data;
                    if (data.is_delivery != '' && data.is_delivery == 1) {
                        $('.form-section.delivery-address').removeClass('hidden').removeClass('optional');
                    } else {
                        $('.form-section.delivery-address').addClass('hidden').addClass('optional');
                    }
                });
            } else {
                if ($('[name="delivery_type_id"] option:selected').data("is_delivery") == 1) {
                    $('.form-section.delivery-address').removeClass('hidden').removeClass('optional');
                } else {
                    $('.form-section.delivery-address').addClass('hidden').addClass('optional');
                }
            }
        };
        handleDeliveryAdress();
        $('[name="delivery_type_id"]').on('change', function () {
            handleDeliveryAdress();
        });
    }

    var stepParam = $.getUrlParam('step');
    if (stepParam !== 0) {
        $.openStepByNumber(stepParam);
    }

    // Step back
    $(document).on('click', '.cart-back', function () {
        var step = $(this).closest('.cart-step');
        var previous_step_number = parseInt(step.data('step')) - 1;
        if (!$('#cart-step-' + previous_step_number).length) {
            previous_step_number--;
        }
        if ($('#cart-step-' + previous_step_number).length) {
            $.openStepByNumber(previous_step_number, previous_step_number + 1);
        }
        $.scrollToTop($("#cart"));
    });

    $(document).on('click', '#cart-step-1-title', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(".cart-back").trigger("click");
    });

    // Step forward
    $(document).on('click', '.cart-proceed', function (e) {
        e.preventDefault();
        var step = $('.cart-step.current');
        if (stepIsValid(step)) {
            var next_step_number = parseInt(step.data('step')) + 1;
            if (!$('#cart-step-' + next_step_number).length) {
                next_step_number++;
            }
            if ($('#cart-step-' + next_step_number).length) {
                $.handleCartProceed(next_step_number);
            }
        }
    });

    // Finish checkout and generate confirm modal
    $(document).on('click', '.cart-finish', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        var step = $('.cart-step.current');
        if (stepIsValid(step)) {
            $.showAjaxLoader();

            var registerSection = $('#cart').find(".register-section");
            if (registerSection.length) {
                if (registerSection.hasClass("send-data")) {
                    $.cartRegister(registerSection);
                } else {
                    cartPrivateR1(registerSection);
                }
            }
        }
    });

    var cartConfirm = function (confirmButton) {
        $.showAjaxLoader();
        var form_id = confirmButton.data('id');
        $.ajax({
            url: '/cart/cart_confirm', method: 'POST', data: {}, cache: false
        }).done(function (result) {
            if (result.error == false) {
                if (result.redirect_url) {
                    window.location.href = result.redirect_url;
                } else if ($("#" + form_id).length) {
                    $("#" + form_id).submit();
                } else {
                    alert("MISSING ACTION");
                }
            } else {
                $.hideAjaxLoader();
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    }

    // Trigger checkout finish other than card payment
    $(document).on('click', '.button.cart-confirmation', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $.showAjaxLoader();
        cartConfirm($(this));
    });

    // Trigger card payment
    $(document).on('click', '.button.card-payment', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $.showAjaxLoader();
        cartConfirm($(this));
    });

    // Trigger keks payment
    $(document).on('click', '.button.kekspay-button', function () {
        window.location.href = $(this).attr("href");
    });

    function cartPrivateR1(registerSection) {
        var data = {};
        data.recaptcha_response = registerSection.find('[name="recaptcha_response"]').val();
        if (registerSection.find('[name="company_name"]').length) {
            data.is_legal_entity = registerSection.find('[name="is_private_r1"]').is(":checked") ? 1 : 0;
            data.name = registerSection.find('[name="company_name"]').val();
            data.oib = registerSection.find('[name="oib"]').val();
        }
        $.ajax({
            url: '/cart/update_cart_legal_data', method: 'POST', data: data, cache: false
        }).done(function (result) {
            if (result.error == false) {
                $.cartUpdateDeliveryAddress(registerSection);
            } else {
                $.hideAjaxLoader();
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
        return false;
    }

    // Validate checkout step
    function stepIsValid(step) {
        var valid = true;
        step.find('[required]').each(function () {
            var inputValid = true;

            // Skip if row optional
            if ($(this).parents('.optional').length > 0) {
                return;
            }
            // Skip if row hidden
            if ($(this).parents('.form-row.hidden').length > 0) {
                return;
            }

            if (!$(this).val()) {
                inputValid = false;
            }

            if ($(this).is(':checkbox') || $(this).is(':radio')) {
                if ($(this).closest(".form-group").length === 0) {
                    if ($(this).closest("form").find("input:checked").length === 0) {
                        inputValid = false;
                    }
                } else {
                    if ($(this).closest(".form-group").find("input:checked").length === 0) {
                        inputValid = false;
                    }
                }
            } else if ($(this).is('select')) {
                if (!$(this).val()) {
                    inputValid = false;
                }
            } else if (!$(this).val()) {
                inputValid = false;
            }

            if ($(this).attr("pattern")) {
                if (!$(this).val().match(new RegExp($(this).attr("pattern")))) {
                    inputValid = false;
                }
            }

            if (!inputValid) {
                var errorText = $(this).parent().find('.label-text').text();
                if ($(this).data("label")) {
                    errorText = $(this).data("label");
                }
                if (errorText == null || errorText == "" || errorText == undefined) {
                    errorText = $(this).attr("placeholder");
                }
                if (errorText == null || errorText == "" || errorText == undefined) {
                    errorText = $(this).data("placeholder");
                }
                if (errorText) {
                    $.growl.error({
                        title: translations.form_error_message,
                        message: errorText.replace('*', '') + ' ' + translations.is_required,
                    });
                }
                $(this).addClass("invalid");
                $(this).closest(".custom-checkbox").addClass("form-error");
                $(this).on("keypress keydown keyup change", function () {
                    $(this).removeClass("invalid");
                })
                valid = inputValid;
            }
        });

        return valid;
    }

    // Rebuild delivery types
    var resetDeliveryType = function () {
        var checkoutDeliveryTypeField = $('#cart [name="delivery_type_id"]');
        if (checkoutDeliveryTypeField.length) {
            checkoutDeliveryTypeField.closest(".form-group").find(".description").html("");
            var checkoutForm = $('#cart form');
            $.ajax({
                url: checkoutDeliveryTypeField.data('search-url'),
                method: 'GET',
                data: {"form": $.getSerializedFormData(checkoutForm)},
                cache: false
            }).done(function (result) {
                checkoutDeliveryTypeField.find('option:not([value=""])').remove();
                if (result.error == false) {
                    var currentDeliveryType = checkoutDeliveryTypeField.val();
                    var selectedIsValid = false;
                    var firstAvailable = null;
                    $.each(result.ret, function (key, value) {
                        if (checkoutDeliveryTypeField.hasClass("select2-hidden-accessible")) {
                            if (key == 0) {
                                firstAvailable = value.id;
                            }
                            if (parseInt(currentDeliveryType) == parseInt(value.id)) {
                                selectedIsValid = true;
                            }
                        } else {
                            if (value.description === undefined) {
                                value.description = "";
                            }
                            var option = $('<option>').attr("value", value.id).data("description", value.description).data("is_delivery", value.is_delivery).html(value.html);
                            if (!currentDeliveryType && value.use_as_default == 1) {
                                option.attr("selected", "selected");
                                selectedIsValid = true;
                            } else {
                                if (value.id == parseInt(currentDeliveryType)) {
                                    option.attr("selected", "selected");
                                    selectedIsValid = true;
                                }
                            }
                            checkoutDeliveryTypeField.append(option);
                        }
                    });
                    if (selectedIsValid) {
                        checkoutDeliveryTypeField.val(firstAvailable).trigger("change");
                    }
                    // checkoutDeliveryTypeField.trigger("change");

                    $.postDeliveryReset();
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : '',
                    });
                }
            });
        }
    }

    // Rebuild payment types
    var resetPaymentType = function () {
        var checkoutPaymentTypeField = $('#cart [name="payment_type_id"]');
        if (checkoutPaymentTypeField.data('search-url')) {

            checkoutPaymentTypeField.closest(".form-group").find(".description").html("");
            var checkoutForm = $('#cart form');
            $.ajax({
                url: checkoutPaymentTypeField.data('search-url'),
                method: 'GET',
                data: {"form": $.getSerializedFormData(checkoutForm)},
                cache: false
            }).done(function (result) {
                checkoutPaymentTypeField.find('option:not([value=""])').remove();
                if (result.error == false) {
                    if (result.ret) {
                        checkoutPaymentTypeField.find('option:first-child').text(checkoutPaymentTypeField.data("placeholder"));
                        var currentPaymentType = checkoutPaymentTypeField.val();
                        var selectedIsValid = false;
                        var firstAvailable = null;
                        $.each(result.ret, function (key, value) {
                            if (checkoutPaymentTypeField.hasClass("select2-hidden-accessible")) {
                                if (key == 0) {
                                    firstAvailable = value.id;
                                }
                                if (parseInt(currentPaymentType) == parseInt(value.id)) {
                                    selectedIsValid = true;
                                }
                            } else {
                                if (value.description === undefined) {
                                    value.description = "";
                                }
                                var option = $('<option>').attr("value", value.id).data("description", value.description).html(value.html);

                                if (!currentPaymentType && value.use_as_default == 1) {
                                    option.attr("selected", "selected");
                                    selectedIsValid = true;
                                } else {
                                    if (value.id == parseInt(currentPaymentType)) {
                                        option.attr("selected", "selected");
                                        selectedIsValid = true;
                                    }
                                }
                                checkoutPaymentTypeField.append(option);
                            }
                        });
                        if (selectedIsValid) {
                            checkoutPaymentTypeField.val(firstAvailable);
                        }
                        // checkoutPaymentTypeField.trigger("change");
                    } else {
                        checkoutPaymentTypeField.find('option:first-child').text(checkoutPaymentTypeField.data("missing-delivery-placeholder"));
                    }
                    $.postPaymentReset();
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : '',
                    });
                }
            });
        }
    }

    $(document).on("change", '#cart [name="country_id"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if ($(this).parents(".overlay").length == 0) {
            resetDeliveryType();
        }
    });
    $(document).on("change", '#cart [name="city_name"][data-type="lookup"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if ($(this).parents(".overlay").length == 0) {
            resetDeliveryType();
        }
    });
    $(document).on("change", '#cart [name="city_id"][data-type="lookup"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if ($(this).parents(".overlay").length == 0) {
            resetDeliveryType();
            $.recalculateTotalPrice();
        }
    });
    $(document).on("change", '#cart [name="postal_code"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if ($(this).parents(".overlay").length == 0) {
            resetDeliveryType();
        }
    });
    $(document).on("change", '#cart [name="shipping_address_same"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        resetPaymentType();
    });
    $(document).on("change", '#cart [name="delivery_type_id"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        resetPaymentType();

        var description = $(this).find(":selected").data("description");
        if (!description) {
            description = "";
        }
        $(this).closest(".form-group").find(".description").html(description);

        $.recalculateTotalPrice();
    });
    $(document).on("change", '#cart [name="payment_type_id"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $.handleOnPaymentChange($(this));
    });
    $(document).on("change", '#cart [name="account_shipping_address_id"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        resetPaymentType();
        $.recalculateTotalPrice();
    });
    $(document).on("change", '#cart [name="shipping_country_id"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        resetPaymentType();
    });
    $(document).on("change", '#cart [name="shipping_city_name"][data-type="lookup"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        resetPaymentType();
    });
    $(document).on("change", '#cart [name="shipping_postal_code"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        resetPaymentType();
    });

    var checkStatusInterval = 0;
    $(document).on("check-quote-status", function (e) {
        if ($("#cart-confirm .kekspay-button").length == 0) {
            checkStatusInterval = setInterval($.checkQuoteStatus, 1500);
        }
    });
    $(document).on("stop-check-quote-status", function (e) {
        if (checkStatusInterval !== 0) {
            clearInterval(checkStatusInterval);
            checkStatusInterval = 0;
        }
    });

    $(document).on('click', '.checkout-view .item-bulk-option', function (event) {
        event.preventDefault();
        var qtyInput = $(this).parents(".product-display-grid").find('.item-cart [name="qty"]');
        qtyInput.val(parseFloat(qtyInput.val()) + parseInt($(this).data("to-add")));
        $(this).parents(".cart-items").find("#update-cart").trigger("click");
    });

    $(document).on("click", ".add-new-button-missing-address", function (e) {
        $(".delivery-address .add-new-button").click();
    });

    // Loyalty checkout - Add
    $(document).on('click', '.sp-block-outer-cart #apply_loyalty_discount', function() {
        var selectedLoyalty = $('.sp-block-outer-cart #loyalty_discounts').find(":selected").val();

        if (selectedLoyalty !== "null") {
            $.showAjaxLoader();
            $.ajax({
                url: '/cart/apply_loyalty',
                method: 'POST',
                data: {'data': selectedLoyalty},
                cache: false
            }).done(function (result) {
                $.hideAjaxLoader();
                $("#update-cart").click();
            });
        }
    });

    // Loyalty checkout - Remove
    $(document).on('click','.sp-block-outer-cart #remove_loyalty_discount', function() {
        $.showAjaxLoader();
        $.ajax({
            url: '/cart/remove_loyalty',
            method: 'POST',
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            $("#update-cart").click();
        });
    });
});
