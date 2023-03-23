jQuery.extend({
    responsiveTabItemHandler: function (element, event) {
        if ($(window).width() < 768) {
            event.preventDefault();
            var isActive = element.hasClass("tab-active");
            $(".responsive-tab-item.tab-active").removeClass("tab-active");
            if (!isActive) {
                element.addClass("tab-active");
            }
            // if (!$(this).next(".tab-content").hasClass("tab-active")) {
            //     var index = $(this).next(".tab-content").attr("id");
            //     $(this).closest(".tabs").find('.tab-item[data-index="' + index + '"]').click();
            // }
        }
    },
    mainMenuToggleHandler: function (element, event) {
        var menuDisplay = $("#menu-display");
        event.preventDefault();
        if (!element.hasClass("active")) {
            $.disableOverlays();
        }
        element.toggleClass('active');
        if (element.hasClass('active')) {
            menuDisplay.addClass('active');
        } else {
            menuDisplay.removeClass('active');
        }
    },
    handleProductRemindMe: function (element) {
        var pid = null;
        if (element.data('pid')) {
            pid = element.data('pid');
        }
        var title = null;
        if (element.data('title')) {
            title = element.data('title');
        }
        $.productRemindMeOnAvailable(pid, title);
    },
    handleAutocompleteSearchResults: function (result) {
        // products
        if (result.grid_html !== undefined) {
            if (result.grid_html) {
                $('#search-autocomplete-overlay').find('.search-results-content .content.items-grid').html(result.grid_html);
                $('#search-autocomplete-overlay').find('.search-results-content .content.items-grid').closest(".search-results-content").show();
            } else {
                $('#search-autocomplete-overlay').find('.search-results-content .content.items-grid').closest(".search-results-content").hide();
            }
        }

        // product groups
        if (result.product_groups_html !== undefined) {
            if (result.product_groups_html) {
                $('#search-autocomplete-overlay').find('.search-suggestions').html(result.product_groups_html);
                $('#search-autocomplete-overlay').find('.search-suggestions').closest(".search-results-content").show();
            } else {
                $('#search-autocomplete-overlay').find('.search-suggestions').closest(".search-results-content").hide();
            }
        }

        //blogs
        if (result.posts_html !== undefined) {
            if (result.posts_html) {
                $('#search-autocomplete-overlay').find('.search-connected').html(result.posts_html);
                $('#search-autocomplete-overlay').find('.search-connected').closest(".search-results-content").show();
            } else {
                $('#search-autocomplete-overlay').find('.search-connected').closest(".search-results-content").hide();
            }
        }

        //brands
        if (result.brands_html !== undefined) {
            if (result.brands_html) {
                $('#search-autocomplete-overlay').find('.search-brands').html(result.brands_html);
                $('#search-autocomplete-overlay').find('.search-brands').closest(".search-results-content").show();
            } else {
                $('#search-autocomplete-overlay').find('.search-brands').closest(".search-results-content").hide();
            }
        }
    },
    handleMarketingMessagesPopup: function () {
        var popup = $("#marketing-message-popup-display");
        if (popup.length > 0) {
            setTimeout(function () {
                popup.addClass("active");

                var popupsShown = [];
                if ($.getCookie("popups_shown")) {
                    popupsShown = JSON.parse($.getCookie("popups_shown"));
                }
                popupsShown.push(popup.data("id"));

                $.setCookie("popups_shown", JSON.stringify(popupsShown));
            }, popup.data("delay"));
        }
    },
    handleMarketingMessagesFloater: function () {
        var floater = $("#marketing-message-floater-display");
        if (floater.length > 0) {
            setTimeout(function () {
                floater.addClass("active");

                floater.find(".overlay-close, .button").on("click", function () {
                    var floatersShown = [];
                    if ($.getCookie("floaters_shown")) {
                        floatersShown = JSON.parse($.getCookie("floaters_shown"));
                    }
                    floatersShown.push(floater.data("id"));

                    $.setCookie("floaters_shown", JSON.stringify(floatersShown));
                });
            }, floater.data("delay"));
        }
    },
});
jQuery(document).ready(function ($) {
    $.initializeHorizontalScroll();
    $.loadLazyImages();
    $.handleMarketingMessagesPopup();
    $.handleMarketingMessagesFloater();

    $(window).on("resize", function () {
        $.loadLazyImages();
    });

    // General after ajax tasks
    $(document).ajaxComplete(function (event, xhr, settings) {
        if (xhr.responseJSON !== undefined && xhr.responseJSON.javascript !== undefined) {
            eval(xhr.responseJSON.javascript);
        }

        $.hideAjaxLoader();
        $.initRecaptcha();
        $.loadLazyImages();
        $.initializeFrontendForm();
        $.initializeHorizontalScroll();
    });

    // Reload page global
    $(document).on('global:page-reload', function (e) {
        $.reloadPage();
    });

    // Reload page global
    $(document).on('possible-removal', function (e, data, form) {
        if (data.remove == 1) {
            $(form).remove();
        }
    });

    // Disable overlay on click
    $(document).on('click', function (e) {
        $.disableOverlays($(e.target));
    });

    // Disable overlays with ESC key
    $(document).keyup(function (e) {
        if (e.key === "Escape") { // escape key maps to keycode `27`
            $.disableOverlays();
        }
    });

    var headerHeight = $("header").height();
    $.extend({
        handleStickyHeader: function () {
            if ($(window).scrollTop() > headerHeight) {
                $("body").addClass("fixed");
                $(".back-to-top").addClass("visible");
            } else if ($(window).scrollTop() < headerHeight) {
                $("body").removeClass("fixed");
                $(".back-to-top").removeClass("visible");
            }
        },
    });

    // Toggle cart
    $(document).on('click', '.cart-toggle', function (event) {
        event.preventDefault();
        if (!$(this).hasClass("active")) {
            $.disableOverlays();
        }
        $(this).toggleClass('active');
        if ($(this).hasClass('active')) {
            $("#cart-display").addClass('active');
            $(".header-second").addClass("menu-open");
        } else {
            $("#cart-display").removeClass('active');
            $.disableOverlays();
        }
    });

    // Sticky header
    $.handleStickyHeader($(window));
    $(window).on("scroll", function () {
        $.handleStickyHeader($(this));
    });

    // Menu toggle
    $(document).on('click', ".main-menu-toggle-wrapper", function (event) {
        $.mainMenuToggleHandler($(this), event);
    });

    // Toogle account
    $(document).on('click', '.account-toggle', function (event) {
        event.preventDefault();
        var accountMenuDisplay = $('#account-display');
        if (!accountMenuDisplay.hasClass("active")) {
            $.disableOverlays();
        }
        $(this).toggleClass('active');
        if ($(this).hasClass('active')) {
            accountMenuDisplay.addClass('active');
            $("#search-autocomplete-overlay .search-autocomplete").focus();
        } else {
            accountMenuDisplay.removeClass('active');
        }
    });

    // Responsive search toggle
    $(".search-responsive-toggle").on('click', function (event) {
        event.preventDefault();
        var searchDisplay = $("#search-autocomplete-overlay");
        if (!searchDisplay.hasClass("active")) {
            $.disableOverlays();
        }
        $(this).toggleClass('active');
        if ($(this).hasClass('active')) {
            searchDisplay.addClass('active');
            $("#search-autocomplete-overlay .search-autocomplete").focus();
        } else {
            searchDisplay.removeClass('active');
        }
    });

    // Responsive filters toggle
    $(document).on('click', 'span.responsive-close-filters', function () {
        $(this).closest('.product-filters').removeClass('active');
    });

    // Back to top button
    $(".back-to-top").on("click", function () {
        $('html,body').animate({scrollTop: 0}, 'slow');
    });

    // Accordion.
    $(document).on('click', '.accordion-title', function () {
        if ($(this).parent().hasClass("active")) {
            $(this).siblings('.accordion-body').slideUp();
        } else {
            $(this).siblings('.accordion-body').slideDown();
        }
        $(this).parent().toggleClass("active");
    });

    // Logout customer
    var logoutCustomer = $('.link-logout-customer');
    if (logoutCustomer.length) {
        logoutCustomer.on('click', function (e) {
            e.preventDefault();
            $.showAjaxLoader();
            $.ajax({
                url: logoutCustomer.data('url'),
                method: 'POST',
                data: {},
                cache: false
            }).done(function (result) {
                $.hideAjaxLoader();
                if (result.error == false) {
                    if (result.redirect_url) {
                        window.location.href = result.redirect_url;
                    }
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : translations.selection_error,
                    });
                }
            });
        });
    }

    // Open login modal
    $(document).on('click', ".open-login", function (e) {
        $.showLoginModal();
    });

    // Close system message
    $(document).on('click', ".system-message-close", function (e) {
        $(this).parent().remove();
    });

    // Handle search autocomplete
    $(".search-autocomplete-overlay .overlay-close").on('click', function (e) {
        $('input.search-autocomplete').val("");
        $('input.search-autocomplete').trigger("input");
    });
    $(document).on('keyup', 'input.search-autocomplete', $.debounce(function (e) {
        var autocompleteField = $(this);
        var mainSearch = autocompleteField.data('main-search');
        var term = $(this).val();
        if (term.length >= $("body").data("search-length")) {
            $.showAjaxLoader();
            var data = {};
            data.query = term;
            if (autocompleteField.data('get-posts')) {
                data.get_posts = autocompleteField.data('get-posts');
            }
            if (autocompleteField.data('get-posts')) {
                data.get_categories = autocompleteField.data('get-categories');
            }
            if (autocompleteField.data('get-product-groups')) {
                data.get_product_groups = autocompleteField.data('get-product-groups');
            }
            if (autocompleteField.data('get-brands')) {
                data.get_brands = autocompleteField.data('get-brands');
            }
            data.get_all_products = 1;
            data.page_size = 100;
            if (autocompleteField.data("pre-filter")) {
                data.pre_filter = autocompleteField.data("pre-filter");
            }
            $.ajax({
                url: '/search_products_autocomplete',
                method: 'POST',
                data: data,
                cache: false
            }).done(function (result) {
                var showAllLInkElement = null;
                $.hideAjaxLoader();
                if (result.error === false) {
                    var resultsWrapper = autocompleteField.parent().find('.results-autocomplete-wrapper');
                    if (resultsWrapper.length) {
                        resultsWrapper.addClass('active');
                        resultsWrapper.find('.content').html(result.grid_html);
                        showAllLInkElement = resultsWrapper.find('.show-all');
                    } else if (mainSearch) {
                        $('#search-autocomplete-overlay').addClass("active");

                        $.handleAutocompleteSearchResults(result);

                        $('#search-autocomplete-overlay .search-autocomplete').val(term);
                        $('#search-autocomplete-overlay').find('.search-grid').addClass("visible");

                        showAllLInkElement = $('#search-autocomplete-overlay').find('.show-all');
                    } else {
                        $.growl.error({
                            title: result.title ? result.title : '',
                            message: result.message ? result.message : translations.error_message,
                        });
                    }
                    if (showAllLInkElement != null && showAllLInkElement.length && $('#search-autocomplete-overlay').find(".product-display-grid.item").length > 0) {
                        var showAllUrl = '/rezultati-pretrage?s=1&keyword=' + term;
                        showAllLInkElement.attr('href', showAllUrl);
                        showAllLInkElement.show();
                    }
                    if ($("#search-autocomplete-overlay .search-autocomplete").length > 0 && $("#search-autocomplete-overlay .search-autocomplete").is(":visible")) {
                        $("#search-autocomplete-overlay .search-autocomplete").focus();
                    }
                    $.loadLazyImages();
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : translations.search_error,
                    });
                }
            });
        } else {
            $.growl.error({
                title: "",
                message: translations.search_min_length_prefix + " " + $("body").data("search-length") + " " + translations.search_min_length_suffix,
            });
        }
    }, 1150));

    // Custom dropdown
    $(document).on('click', '.dropdown-open', function () {
        $(this).parent().removeClass("to-left");
        $(this).parent().addClass("clicked");
        $(".custom-dropdown:not(.clicked)").removeClass('active');
        $(this).parent().toggleClass('active');
        $(this).parent().removeClass("clicked");

        // check if outside viewport and move to other side
        // $(this).parent()
        if ($(this).parent().hasClass("active") && $(this).parent().find(".dropdown-options").offset().left + $(this).parent().find(".dropdown-options").width() > $(window).width()) {
            $(this).parent().addClass("to-left");
        }
    });

    // Tabs.
    $(document).on("click", ".tabs .tab-item", function (e) {
        e.preventDefault();
        if (!$(this).hasClass("tab-active")) {
            $(this).closest("ul").find(".tab-active").removeClass("tab-active");
            $(this).addClass("tab-active");

            var index = $(this).data("index");
            $(this).closest(".tabs").find(".tab-content.tab-active").removeClass("tab-active");
            $(this).closest(".tabs").find('.tab-content#' + index).addClass("tab-active");
            $(this).closest(".tabs").find('.responsive-tab-item.tab-active').removeClass("tab-active");
            $(this).closest(".tabs").find('.responsive-tab-item[data-index="' + index + '"]').addClass("tab-active");

            $.loadLazyImages();
        }
    });
    $(document).on("click", ".tabs .responsive-tab-item", function (e) {
        $.responsiveTabItemHandler($(this),e);
    });

    // Handle add new address button
    $(document).on('click', 'button.button.add-new-button', function (event) {
        event.preventDefault();
        $(this).parents(".dashboard-add-new").find('.overlay').addClass("active");
    });

    // Handle inquiry form
    $(document).on('click', '.send-inquiry', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        var pid = null;
        if ($(this).data('pid')) {
            pid = $(this).data('pid');
        }
        if (!pid && $(".sp-block-outer-product_details[data-pid]").length) {
            pid = $(".sp-block-outer-product_details[data-pid]").data('pid');
        }
        var title = null;
        if ($(this).data('title')) {
            title = $(this).data('title');
        }
        $.openInquiryForm(pid, title);
    });

    // Handle availability reminder form
    $(document).on('click', '.remind-me-available', function () {
        $.handleProductRemindMe($(this));
    });

    /**
     * Counter
     */
    var timer = $('[data-timer]');
    if (timer.length) {
        var seconds = timer.data('timer');
        var interval = setInterval(function () {
            seconds -= 1;
            timer.html(seconds);
            if (seconds <= 0) {
                window.location.href = '/';
                clearInterval(interval);
            }
        }, 1000);
    }

    /**
     * Quick order
     */
    $(document).on("click", ".quick-order-search-item .product-display-grid.item", function (e) {
        e.preventDefault();

        // clone to new before populating
        var row = $(this).closest(".form-row")

        row.find(".form-error").each(function () {
            $(this).removeClass("form-error");
        });

        if (row.is(":last-child")) {
            var newRow = row.clone();
            newRow.find(".results-autocomplete-wrapper").removeClass("active").find(".items-grid").html("");
            newRow.find('[name="product_search"]').val("");
            row.after(newRow);
        }

        var productId = $(this).data("product-id");
        var productTitle = $(this).find(".product-title").text();
        var maxQty = $(this).data("max-qty");
        var step = $(this).data("step");
        var unit = $(this).data("unit");
        var price = $(this).data("price");

        $(this).closest(".quick-order-search-item").find('[name="product_search"]').val($.trim(productTitle));
        $(this).closest(".quick-order-search-item").find('[name="product_id"]').val(productId);

        // Populate qty field
        $(this).closest(".form-row").find('[name="qty"]').removeAttr("disabled");
        $(this).closest(".form-row").find('[name="qty"]').attr("min", step);
        $(this).closest(".form-row").find('[name="qty"]').attr("max", maxQty);
        $(this).closest(".form-row").find('[name="qty"]').attr("step", step);
        $(this).closest(".form-row").find('[name="qty"]').data("step", step);
        $(this).closest(".form-row").find('[name="qty"]').val(step);
        $(this).closest(".form-row").find('[name="qty"]').data("price", price);

        // Populate unit label
        $(this).closest(".form-row").find('.unit').text(unit);

        $(this).closest(".results-autocomplete-wrapper").removeClass("active");
        row.find(".remove").show();
        $(this).closest(".results-autocomplete-wrapper").find(".grid-view").html("");
    });
    $(document).on("click", ".quick-order-row .remove", function (e) {
        // $(this).closest(".form-row").remove();

        $(this).closest(".form-row").find('[name="product_search"]').val("");
        $(this).closest(".form-row").find('[name="product_id"]').val(null);
        $(this).closest(".form-row").find('[name="qty"]').attr("disabled", "disabled");
        $(this).closest(".form-row").find('[name="qty"]').removeAttr("min");
        $(this).closest(".form-row").find('[name="qty"]').removeAttr("max");
        $(this).closest(".form-row").find('[name="qty"]').removeAttr("step");
        $(this).closest(".form-row").find('[name="qty"]').data("step", null);
        $(this).closest(".form-row").find('[name="qty"]').val(null);
        $(this).closest(".form-row").find('.unit').text("");
        $(this).hide();
    });
    $(document).on('input change', 'form#quick-order [name="qty"]', function () {
        if (parseInt($(this).val()) > parseInt($(this).attr("max"))) {
            $(this).val($(this).attr("max"));
            $.growl.error({
                title: translations.error_message,
                message: translations.max_value_warning_title,
            });
        } else {
            $(this).val($(this).val());
        }
    })
    $(document).on("submit", "form#quick-order", function (e) {
        e.preventDefault();
        var error = false;
        var items = [];
        $(this).find(".quick-order-row").each(function () {
            var product = $(this).find('[name="product_id"]');
            var qty = $(this).find('[name="qty"]');
            if (product.val() && qty.val()) {
                items.push({"product_id": product.val(), "qty": qty.val()});
            } else if (product.val() && qty.val()) {
                error = true;
                if (!product.val()) {
                    product.siblings('[name="product_search"]').addClass("form-error");
                }
                if (!qty.val()) {
                    qty.addClass("form-error");
                }
            } else {
            }
        });

        if (items.length > 0 && !error) {
            $.showAjaxLoader();
            $.ajax({
                url: "/cart/add_to_cart",
                method: 'POST',
                data: {"products": items},
                cache: false
            }).done(function (result) {
                $.hideAjaxLoader();
                if (result.error == false) {
                    var message = result.message ? result.message : '';
                    message += '<br>';
                    message += '<a href="' + urls.cart.url + '" class="button btn-type-1">' + urls.cart.title + '</a>';
                    message += '<button class="growl-button button  btn-type-2">' + translations.continue + '</button>';
                    $.growl.notice({
                        title: result.title ? result.title : '',
                        message: message,
                    });

                    $("#cart-display").replaceWith(result.minicart_html);

                    if ($('.cart-toggle').length) {
                        $('.cart-toggle').find('.amount').html(parseInt(result.minicart_num));
                        if (parseInt(result.minicart_num) < 1) {
                            $('.cart-toggle').find('.amount').hide();
                        } else {
                            $('.cart-toggle').find('.amount').show();
                        }
                    }
                    if ($('.cart-toggle').find('.info .price-value').length) {
                        if ($('.cart-toggle').length && result.total_price) {
                            $('.cart-toggle').find('.info .price-value strong').html(result.total_price);
                        }
                    } else {
                        $('.cart-toggle').find('.info').append(result.price_html);
                    }

                    if (result.total_price !== undefined) {
                        $(".mini-cart-total .price-value").text(result.total_price);
                    }

                    $("#cart-display").addClass("active");
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : '',
                    });
                }
            });
        } else {
            $.growl.error({
                title: "",
                message: translations.select_product,
            });
        }
    });

    // Start page builder
    if ($('[data-action="start-page-builder"]').length) {
        $(document).on("click", '[data-action="start-page-builder"]', function () {
            $.showAjaxLoader();
            $.ajax({
                url: "/api/start_page_builder",
                method: 'POST',
                data: {},
                cache: false
            }).done(function (result) {
                if (result.error == false) {
                    $.reloadPage();
                } else {
                    $.hideAjaxLoader();
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : translations.selection_error,
                    });
                }
            });
        });
    }
});
