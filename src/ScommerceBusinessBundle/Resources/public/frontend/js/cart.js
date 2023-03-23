const formatter = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
jQuery.extend({
    updateCartNoButton: function (cartForm) {
        // Update cart without clicking update button
        return false;
    },
    addToCart: function (cartForm) {
        var data = cartForm.serializeArray();

        // Check for configurable
        var configurable = cartForm.parents(".product-information").find(".configuration")
        if (configurable.length) {
            var configurableItems = [];
            configurable.find(".option.active").each(function () {
                if ($(this).data("option-id")) {
                    configurableItems.push($(this).data("option-id"));
                }
                if ($(this).data("frame")) {
                    configurableItems.push($(this).data("frame"));
                }
                if ($(this).data("lens")) {
                    configurableItems.push($(this).data("lens"));
                }
            });
            data.push({name: "configurable", value: JSON.stringify(configurableItems)});
        }

        // Check for configurable bundle
        var configurableBundleGrid = cartForm.parents(".product-details").find(".configurable-bundle-grid")
        if (configurableBundleGrid.length) {
            var configurableBundle = {};
            configurableBundleGrid.find(".item").each(function () {
                var configurableOptionId = $(this).data("option-id");
                var selectedPid = $(this).find(".cb-selected").data("pid");
                configurableBundle[configurableOptionId] = {"product_id": selectedPid};
            });
            data.push({name: "configurable_bundle", value: JSON.stringify(configurableBundle)});
        }

        if (cartForm.hasClass("bundle-cart")) {
            var bundleRow = cartForm.parents(".bundle-row");
            var bundleOptions = [];
            bundleRow.find(".bundle-item-select").each(function () {
                if ($(this).is(":checked")) {
                    bundleOptions.push($(this).data("pid"));
                }
            });
            data.push({name: "bundle", value: JSON.stringify(bundleOptions)});
        }

        $.showAjaxLoader();
        $.ajax({
            url: cartForm.attr('action'),
            method: 'POST',
            data: $.param(data),
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                if (cartForm.parent().hasClass("product-display-grid")) {
                    $(document).trigger("tracking:add_to_cart", cartForm.parent());
                } else if (cartForm.parents(".product-details").length) {
                    $(document).trigger("tracking:add_to_cart_from_details");
                }
                var message = result.message ? result.message : '';
                message += '<br>';
                message += '<a href="' + urls.cart.url + '" class="button btn-type-1">' + urls.cart.title + '</a>';
                message += '<button class="growl-button button  btn-type-2">' + translations.continue + '</button>';
                $.growl.notice({
                    title: result.title ? result.title : '',
                    message: message,
                });
                if ($("#cart-display").length) {
                    $("#cart-display").replaceWith(result.minicart_html);
                }
                if ($('.cart-toggle').length) {
                    $('.cart-toggle').removeClass("active");

                    var minicartNumber = Math.ceil(result.minicart_num);

                    $('.cart-toggle').find('.amount').html(minicartNumber);
                    if (minicartNumber < 1) {
                        $('.cart-toggle').find('.amount').hide();
                    } else {
                        $('.cart-toggle').find('.amount').show();
                    }
                }
                if ($('.cart-toggle').find('.info .price-value').length) {
                    if ($('.cart-toggle').length && result.total_price) {
                        result.total_price = result.total_price.replaceAll(',', '.');
                        $('.cart-toggle .info .price-value strong').html(formatter.format(parseFloat(result.total_price)));
                        if ($('.cart-toggle .info .price-value.euro strong').length && $("#conversion-rate").length) {
                            $('.cart-toggle .info .price-value.euro strong').html(formatter.format(parseFloat(result.total_price) / $("#conversion-rate").data("rate")));
                        }
                    }
                } else {
                    $('.cart-toggle').find('.info').append(result.price_html);
                }

                if (cartForm.parents(".cart-add-reload-page").length) {
                    $(document).trigger('global:page-reload');
                }

                if (result.total_price !== undefined) {
                    $(".mini-cart-total .price-value").text(result.total_price);
                }
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    },
    removeAllItemsFromCart: function (removeButton, reload_page) {
        if (typeof reload_page == "undefined") {
            reload_page = false;
        }
        var minicartActive = $("#cart-display").hasClass("active");
        $.showAjaxLoader();
        $.ajax({
            url: '/cart/remove_all_items_from_cart',
            method: 'POST',
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                $.growl.notice({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });

                var isMinicart = removeButton.parents(".minicart-inner").length > 0;

                removeButton.parent().remove();

                var newCartDisplay = $(result.minicart_html);

                if (isMinicart) {
                    newCartDisplay.addClass("active");
                }

                $("#cart-display").replaceWith(newCartDisplay);
                if (minicartActive) {
                    $("#cart-display").addClass("active");
                    $(".cart-toggle").addClass("active");
                }

                if ($('.cart-toggle').length) {
                    var minicartNumber = Math.ceil(result.minicart_num);

                    $('.cart-toggle').find('.amount').html(minicartNumber);
                    if (minicartNumber < 1) {
                        $('.cart-toggle').find('.amount').hide();
                    } else {
                        $('.cart-toggle').find('.amount').show();
                    }
                }
                if ($('#cart').find('.product-display-grid').length == 0) {
                    $('#cart').remove();
                }

                if (result.total_price !== undefined) {
                    $(".mini-cart-total .price-value").text(result.total_price);
                }

                if (result.cart_html !== undefined && $("#cart").length && $("#cart").parents(".dashboard-element").length == 0) {
                    $("#cart").replaceWith(result.cart_html);
                }

                if (result.gifts_html !== undefined && $("#cart-gifts").length) {
                    $("#cart-gifts").replaceWith(result.gifts_html);
                }

                if ($('.cart-toggle').find('.info .price-value').length) {
                    if ($('.cart-toggle').length && result.total_price) {
                        result.total_price = result.total_price.replaceAll(',', '.');
                        $('.cart-toggle .info .price-value strong').html(formatter.format(parseFloat(result.total_price)));
                        if($('.cart-toggle .info .price-value.euro strong').length && $("#conversion-rate").length){
                            $('.cart-toggle .info .price-value.euro strong').html(formatter.format(parseFloat(result.total_price) / $("#conversion-rate").data("rate")));
                        }
                    }
                }

                $.loadLazyImages();

                if (reload_page) {
                    $.reloadPage();
                }
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    },
    removeItemFromCart: function (removeButton, reload_page) {
        if (typeof reload_page == "undefined") {
            reload_page = false;
        }
        var minicartActive = $("#cart-display").hasClass("active");

        var data = {};
        data.product_id = removeButton.data('product-id');
        if(removeButton.data('quote-item-id')){
            data.quote_item_id = removeButton.data('quote-item-id');
        }

        $.showAjaxLoader();
        $.ajax({
            url: '/cart/remove_from_cart',
            method: 'POST',
            data: data,
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                $.growl.notice({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
                removeButton.parent().remove();

                $("#cart-display").replaceWith(result.minicart_html);

                if ($('.cart-toggle').length) {
                    $('.cart-toggle').removeClass("active");

                    var minicartNumber = Math.ceil(result.minicart_num);

                    $('.cart-toggle').find('.amount').html(minicartNumber);
                    if (minicartNumber < 1) {
                        $('.cart-toggle').find('.amount').hide();
                    } else {
                        $('.cart-toggle').find('.amount').show();
                    }
                }
                if ($('#cart').find('.product-display-grid').length == 0) {
                    $('#cart').remove();
                }

                if (minicartActive) {
                    $("#cart-display").addClass("active");
                    $(".cart-toggle").addClass("active");
                }

                if ($('.cart-toggle').find('.info .price-value').length) {
                    if ($('.cart-toggle').length && result.total_price) {
                        result.total_price = result.total_price.replaceAll(',', '.');
                        $('.cart-toggle .info .price-value strong').html(formatter.format(parseFloat(result.total_price)));
                        if ($('.cart-toggle .info .price-value.euro strong').length && $("#conversion-rate").length) {
                            $('.cart-toggle .info .price-value.euro strong').html(formatter.format(parseFloat(result.total_price) / $("#conversion-rate").data("rate")));
                        }
                    }
                }

                if (result.total_price !== undefined) {
                    $(".mini-cart-total .price-value").text(result.total_price);
                }

                if (reload_page) {
                    $.reloadPage();
                }
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    },
    updateCart: function (updateButton) {
        var data = [];
        updateButton.parents('.cart-items').find('form.item-cart').each(function () {
            data.push($(this).serializeArray());
        });

        var minicartActive = $("#cart-display").hasClass("active");

        $.showAjaxLoader();
        $.ajax({
            url: '/cart/update_cart',
            method: 'POST',
            data: {'data': data},
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                $.growl.notice({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });

                var isMinicart = updateButton.parents(".minicart-inner").length > 0;

                if (isMinicart) {
                    $("#cart-display").addClass("active");
                }

                $("#cart-display").replaceWith(result.minicart_html);
                if (minicartActive) {
                    $("#cart-display").addClass("active");
                    $(".cart-toggle").addClass("active");
                }

                if ($('.cart-toggle').length) {
                    var minicartNumber = Math.ceil(result.minicart_num);

                    $('.cart-toggle').find('.amount').html(minicartNumber);
                    if (minicartNumber < 1) {
                        $('.cart-toggle').find('.amount').hide();
                    } else {
                        $('.cart-toggle').find('.amount').show();
                    }
                }
                if ($('#cart').find('.product-display-grid').length == 0) {
                    $('#cart').remove();
                }

                if (result.total_price !== undefined) {
                    $(".mini-cart-total .price-value").text(result.total_price);
                }

                if (result.cart_html !== undefined && $("#cart").length) {
                    $("#cart").replaceWith(result.cart_html);
                }

                if (result.gifts_html !== undefined && $("#cart-gifts").length) {
                    $("#cart-gifts").replaceWith(result.gifts_html);
                }

                if (result.total_price !== undefined && $(".mini-cart .cart-amount-price .total-value").length) {
                    $(".mini-cart .cart-amount-price .total-value").html(result.total_price);
                }

                if ($('.cart-toggle').find('.info .price-value').length) {
                    if ($('.cart-toggle').length && result.total_price) {
                        result.total_price = result.total_price.replaceAll(',', '.');
                        $('.cart-toggle .info .price-value strong').html(formatter.format(parseFloat(result.total_price)));
                        if ($('.cart-toggle .info .price-value.euro strong').length && $("#conversion-rate").length) {
                            $('.cart-toggle .info .price-value.euro strong').html(formatter.format(parseFloat(result.total_price) / $("#conversion-rate").data("rate")));
                        }
                    }
                }

                $.initializeFrontendForm();
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    },
});
jQuery(document).ready(function ($) {
    // Cart update
    $(document).on('click', '.minicart-update-cart, .update-cart, #update-cart', function () {
        $.updateCart($(this));
    });

    // Cart remove item
    $(document).on('click', '.cart-remove-item', function () {
        var reload_page = false;
        if ($(this).parents("#cart").length) {
            reload_page = true;
        }
        $.removeItemFromCart($(this), reload_page);
    });

    // Cart remove all items
    $(document).on('click', '.items-remove-all', function () {
        var reload_page = false;
        if ($(this).parents("#cart").length) {
            reload_page = true;
        }
        $.removeAllItemsFromCart($(this), reload_page);
    });

    $(document).on('submit', 'form.item-cart', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $.addToCart($(this));
    });

    // Cart reduce
    $(document).on('click', '.qty-minus', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        var qty = $(this).closest(".item-cart").find('input.qty');
        var step = 1;
        if (qty.data("step")) {
            step = parseFloat(qty.data("step"));
        }
        var val = parseFloat(qty.val());
        var min = parseFloat(qty.attr('min'));
        if (val > min) {
            var newVal = val - step;
            if (newVal % 1 === 0) {
                newVal = parseInt(newVal);
            } else {
                newVal = parseFloat(newVal).toFixed(2);
            }
            qty.val(newVal);

            if ($.updateCartNoButton()) {
                if ($(this).parents(".minicart-inner").length > 0) {
                    $(this).closest(".minicart-inner").find(".minicart-update-cart").click();
                } else if ($(this).parents(".items-grid.checkout-view").length > 0) {
                    $(this).closest(".cart-items").find("#update-cart").click();
                }
            }
        } else {
            qty.val(min);
        }
        qty.trigger("change");
    });

    // Cart increase
    $(document).on('click', '.qty-plus', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        var qty = $(this).closest(".item-cart").find('input.qty');
        var step = 1;
        if (qty.data("step")) {
            step = parseFloat(qty.data("step"));
        }
        var newVal = parseFloat(qty.val()) + step;
        var max = parseFloat(qty.attr('max'));
        if (newVal <= max) {
            if (newVal % 1 === 0) {
                newVal = parseInt(newVal);
            } else {
                newVal = parseFloat(newVal).toFixed(2);
            }
            qty.val(newVal);
            qty.trigger("change");

            if ($.updateCartNoButton()) {
                if ($(this).parents(".minicart-inner").length > 0) {
                    $(this).closest(".minicart-inner").find(".minicart-update-cart").click();
                } else if ($(this).parents(".items-grid.checkout-view").length > 0) {
                    $(this).closest(".cart-items").find("#update-cart").click();
                }
            }
        } else {
            $.growl.warning({
                title: translations.max_value_warning_title,
                message: translations.max_value_warning_message + ' ' + parseFloat(max) + '<br>' + translations.max_value_warning_contact,
            });
        }
    });
    $(document).on("change keyup", "input.qty", function () {
        if (parseInt($(this).val()) > parseInt($(this).attr("max"))) {
            $(this).val($(this).attr("max"));
        }
    });

    // Cart coupon toggle
    $(document).on('click', '.minicart-coupon-title,.cart-discount-title', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(this).parent().find(".coupon-wrapper").slideToggle();
    });

    // Cart remove coupon
    $(document).on('click', '.coupon-code-remove', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $.showAjaxLoader();
        $.ajax({
            url: '/api/apply_coupon',
            method: 'POST',
            data: {
                "discount_coupon_name": "",
                "step": $(".cart-step.current").data("step"),
            },
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                if (result.cart_html !== undefined && $("#cart").length) {
                    $("#cart").replaceWith(result.cart_html);
                }

                if (result.minicart_html !== undefined && $("#cart-display").length) {
                    $("#cart-display").replaceWith(result.minicart_html);
                }

                // $.reloadPage();
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    });

    // Add gift to cart
    $(document).on('click', '.product-gift-item .gift-select', function () {
        var giftSelect = $(this);
        var data = {};

        data.product_id = $(this).parent().data("product-id");
        data.qty = 1;

        if ($(this).parent().hasClass("selected")) {
            $.showAjaxLoader();
            $.ajax({
                url: '/cart/remove_from_cart',
                method: 'POST',
                data: data,
                cache: false
            }).done(function (result) {
                $.hideAjaxLoader();
                if (result.error == false) {
                    $.growl.notice({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : '',
                    });

                    giftSelect.parent().removeClass("selected");

                    var grid = giftSelect.closest(".items-grid.grid-view");
                    if (grid.find(".product-gift-item.selected").length < grid.data("available-gifts") && grid.hasClass("disabled")) {
                        grid.removeClass("disabled");
                    }
                    $(".used-gift-amount").text(parseInt(grid.find(".product-gift-item.selected").length));

                    if (result.cart_html !== undefined && $("#cart").length) {
                        $("#cart").replaceWith(result.cart_html);
                    }

                    if (result.minicart_html !== undefined && $("#cart-display").length) {
                        $("#cart-display").replaceWith(result.minicart_html);
                    }

                    if (result.total_price !== undefined && $(".mini-cart .cart-amount-price .total-value").length) {
                        $(".mini-cart .cart-amount-price .total-value").html(result.total_price);
                    }

                    // $.reloadPage();
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : '',
                    });
                }
            });
        } else {
            $.showAjaxLoader();
            $.ajax({
                url: "/cart/add_to_cart",
                method: 'POST',
                data: data,
                cache: false
            }).done(function (result) {
                $.hideAjaxLoader();
                if (result.error == false) {
                    $.growl.notice({
                        title: result.title ? result.title : '',
                        message: result.message,
                    });

                    giftSelect.parent().addClass("selected");

                    var grid = giftSelect.closest(".items-grid.grid-view");
                    if (grid.find(".product-gift-item.selected").length == grid.data("available-gifts")) {
                        grid.addClass("disabled");
                    }
                    $(".used-gift-amount").text(parseInt(grid.find(".product-gift-item.selected").length));

                    if (result.cart_html !== undefined && $("#cart").length) {
                        $("#cart").replaceWith(result.cart_html);
                    }

                    if (result.minicart_html !== undefined && $("#cart-display").length) {
                        $("#cart-display").replaceWith(result.minicart_html);
                    }

                    if (result.total_price !== undefined && $(".mini-cart .cart-amount-price .total-value").length) {
                        $(".mini-cart .cart-amount-price .total-value").html(result.total_price);
                    }

                    // $.reloadPage();
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : '',
                    });
                }
            });
        }
    });

    // Loyalty minicart - Add
    $(document).on('click', '#cart-display #apply_loyalty_discount', function() {
        var selectedLoyalty = $('#cart-display #loyalty_discounts').find(":selected").val();

        if (selectedLoyalty !== "null") {
            $.showAjaxLoader();
            $.ajax({
                url: '/cart/apply_loyalty',
                method: 'POST',
                data: {'data': selectedLoyalty},
                cache: false
            }).done(function (result) {
                $.hideAjaxLoader();
                $(".minicart-update-cart").click();
            });
        }
    });

    // Loyalty minicart - Remove
    $(document).on('click','#cart-display #remove_loyalty_discount', function() {
        $.showAjaxLoader();
        $.ajax({
            url: '/cart/remove_loyalty',
            method: 'POST',
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            $(".minicart-update-cart").click();
        });
    });
});
