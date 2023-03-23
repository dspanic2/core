$.extend({
    initializeProductSlider: function () {
        var productSlider = $('.product-slider:not(.slick-initialized)');
        var productSliderNavigation = $('.product-slider-navigation:not(.slick-initialized)');
        if (productSliderNavigation.length > 0 && productSliderNavigation.find(".image").length > 0) {
            var options = {
                slidesToShow: 4,
                dots: false,
                arrows: true,
                vertical: true,
                verticalSwiping: true,
                asNavFor: '.product-slider',
                swipeToSlide: true,
                focusOnSelect: true,
                infinite: true,
                prevArrow: '<button type="button" class="slick-prev"><i class="fi-xnslxl-chevron-solid"></i></button>',
                nextArrow: '<button type="button" class="slick-next"><i class="fi-xnsrxl-chevron-solid"></i></button>',
                // responsive: [
                //     {
                //         breakpoint: 768,
                //         settings: "unslick"
                //     }
                // ]
            };
            if (productSliderNavigation.find(".image").length < 4) {
                productSliderNavigation.addClass("disable-transform");
            }
            productSliderNavigation.slick(options);
        }

        if (productSlider.length > 0 && productSlider.find(".image").length > 0) {
            productSlider.each(function () {
                var slider = $(this);
                slider.slick({
                    lazyLoad: 'anticipated',
                    slidesToShow: 1,
                    slidesToScroll: 1,
                    dots: slider.find(".image").length > 1,
                    arrows: slider.find(".image").length > 1,
                    asNavFor: '.product-slider-navigation',
                    focusOnSelect: false,
                    infinite: true,
                    prevArrow: '<button type="button" class="slick-prev"><i class="fi-xnslxl-chevron-solid"></i></button>',
                    nextArrow: '<button type="button" class="slick-next"><i class="fi-xnsrxl-chevron-solid"></i></button>',
                });
                slider.slickLightbox({
                    itemSelector: 'a'
                });
            });
        }
    },
    setProductDetails: function (parent, data) {
        $.showAjaxLoader();
        $.ajax({
            url: '/product/get_product_details',
            method: 'POST',
            data: data,
            cache: false
        }).done(function (result) {
            if (typeof result == "string") {
                result = JSON.parse(result);
            }
            $.hideAjaxLoader();
            if (result.error == false) {
                if (result.gallery_html != undefined) {
                    parent.find(".product-gallery").replaceWith(result.gallery_html);
                    $.initializeProductSlider();
                    $.scrollToTop(parent.find(".product-gallery"));
                }
                if (result.prices_html != undefined) {
                    parent.find(".prices").replaceWith(result.prices_html);
                }
                // if (result.title_html != undefined) {
                //     parent.find("h1").replaceWith(result.title_html);
                // }
                if (result.cart_form_html != undefined) {
                    parent.find(".product-cart-wrapper").replaceWith(result.cart_form_html);
                }
                $.loadLazyImages();
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : translations.search_error,
                });
            }
        });
    },
    getProductConfigurableBundleOptions: function (configurableBundleGrid) {
        // Check for configurable bundle
        if (configurableBundleGrid.length) {
            var configurableBundle = {};
            configurableBundleGrid.find(".item").each(function () {
                var configurableOptionId = $(this).data("option-id");
                var selectedPid = $(this).find(".cb-selected").data("pid");
                configurableBundle[configurableOptionId] = {"product_id": selectedPid};
            });
            return JSON.stringify(configurableBundle);
            // data.push({name: "configurable_bundle", value: JSON.stringify(configurableBundle)});
        }
    },
    checkDependencies: function (option) {
        option.closest(".configurable-attribute").find(".configurable-attribute-title-value").text(option.attr("title"));
        var configurableOptions = option.parents(".configurable-options");
        var selectedAttributeId = option.parents(".configurable-attribute").data("attribute-id");
        var optionId = option.data("option-id");
        configurableOptions.find('[data-depends-' + selectedAttributeId + ']').removeClass("disabled");
        configurableOptions.find('[data-depends-' + selectedAttributeId + ']').each(function () {
            var allowedValues = $(this).data('depends-' + selectedAttributeId);
            if ($.inArray(optionId, allowedValues) === -1) {
                $(this).addClass("disabled");
                $(this).removeClass("active");
                if ($(this).data("option-id") == $(this).parent().data("selected")) {
                    $(this).parent().data("selected", null);
                    $(this).parent().removeData("selected");
                    $(this).parents(".configurable-attribute").find(".dropdown-open strong").text($(this).parents(".configurable-attribute").data("null-text"));
                }
                $.checkDependencies($(this));
            }
        });
    },
    setConfigurableOptionActive: function (option) {
        if (option.length === 0) {
            return false;
        }
        option.parents(".configurable-attribute").find('[data-action="configurable-product-set"].active').removeClass("active");
        option.addClass("active");
        option.parents(".product-information").find('.item-cart.main-cart [name="product_id"]').attr("value", "");
        $.checkDependencies(option);

        option.parents(".options").data("selected", option.data("option-id"));

        var text = option.text();
        option.parents(".custom-dropdown ").removeClass("active").find(".dropdown-open strong").text(text);
        var url = window.location.href.split('?')[0];
        // window.history.pushState('', '', url + '?' + $.param(params));
        var reloadDetails = true;

        option.parents(".configurable-options").find(".configurable-attribute").each(function () {
            if ($(this).find(".option.active").length == 0 && $(this).find(".option:not(.disabled)").length == 0) {
                reloadDetails = false;
            } else {
                if ($(this).find(".option.active").length == 0) {
                    reloadDetails = $.setConfigurableOptionActive($(this).find(".option:not(.disabled)").first());
                }
            }
        });

        return reloadDetails;
    },
    reloadConfigurableProductDetails: function (option) {
        var configurableOptions = option.parents(".configurable-options");
        var parentPid = configurableOptions.data("pid");
        var params = {};
        var data = {};
        var url = window.location.href.split('?')[0];

        data.pid = parentPid;
        data.configurable = {};
        configurableOptions.find(".configurable-attribute").each(function () {
            data.configurable[$(this).data("attribute-id")] = $(this).find(".options").data("selected");
        });
        params.configurable = data.configurable;
        window.history.replaceState('', '', url + '?' + $.param(params));
        data.url = window.location.pathname;
        $.setProductDetails(option.parents(".product-relative-container"), data);
    },
});

jQuery(document).ready(function ($) {

    // Remove quote item query param
    var qiParam = $.getUrlParam("qi");
    if (qiParam && qiParam > 0) {
        $.removeUrlParams();
    }

    $.initializeProductSlider();

    $(document).on('click', '.add-to-favorite', function (e) {
        e.preventDefault();
        $.productAddToFavorites($(this));
    });

    $(document).on('click', '.add-to-compare', function (e) {
        e.preventDefault();
        $.productAddToCompare($(this).data('pid'), true, $(this).hasClass('active') ? 0 : 1, $(this));
    });

    // Configurable bundle
    var data = {};
    data.url = window.location.pathname;

    $(document).on("click", ".configurable-bundle-grid .cb-options .dropdown-option", function () {
        var productName = $(this).text();
        var selectedItem = $(this).parents(".item").find(".cb-selected");
        selectedItem.find(".option-value").html(productName).removeClass("opacity");
        selectedItem.find(".cb-remove").removeClass("hidden");
        selectedItem.data("pid", $(this).data("pid"));

        $(this).parents(".custom-dropdown").removeClass("active");

        data.configurable_bundle = $.getProductConfigurableBundleOptions($(this).parents(".configurable-bundle-grid"));
        data.pid = $(this).parents(".configurable-bundle-grid").data("pid");
        $.setProductDetails($(this).parents(".product-relative-container"), data);
    });
    $(document).on("click", ".configurable-bundle-grid .cb-remove", function () {
        var selectedItem = $(this).parents(".item").find(".cb-selected");
        selectedItem.find(".option-value").html(selectedItem.data("empty")).addClass("opacity");
        selectedItem.find(".cb-remove").addClass("hidden");
        selectedItem.data("pid", "");

        data.configurable_bundle = $.getProductConfigurableBundleOptions($(this).parents(".configurable-bundle-grid"));
        data.pid = $(this).parents(".configurable-bundle-grid").data("pid");
        $.setProductDetails($(this).parents(".product-relative-container"), data);
    });

    $(document).on('click', '[data-action="configurable-product-set"]:not(.disabled)', function () {
        var option = $(this);
        $.setConfigurableOptionActive(option);
        $.reloadConfigurableProductDetails(option)
    });

    // Calculate bundle savings
    $(document).on("click", "input.bundle-item-select", function () {
        $.showAjaxLoader();
        var bundleRow = $(this).parents(".bundle-row");
        var data = {};
        data.pid = bundleRow.find(".bundle-item-select.main").data("pid");
        data.include = [];

        if (bundleRow.hasClass("single-product-select")) {
            var selectedPid = $(this).data("pid");
            bundleRow.find(".bundle-item-select:not(.main):checked").each(function () {
                if ($(this).data("pid") !== selectedPid) {
                    $(this).prop("checked", false).parent().removeClass("checked");
                }
            });
        }

        bundleRow.find(".bundle-item-select:checked").each(function () {
            data.include.push($(this).data("pid"));
        });
        $.showAjaxLoader();
        $.ajax({
            url: '/product/get_bundle_saving_prices',
            method: 'POST',
            data: data,
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                if (result.savings_html != undefined) {
                    bundleRow.find(".total-saved").html(result.savings_html);
                }
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : translations.search_error,
                });
            }
        });
    });

    // Bulk prices handle
    $(document).on('click', '.product-bulk-prices .bulk-price', function () {
        var qty = $(this).data("qty");
        var form = $(this).parents("form");
        form.find('[name="qty"]').val(qty);
        form.trigger("submit");
    });

    // Bulk prices handle
    $(document).on('added-to-favorite', function (e, data, form) {
        $.postFavoriteHandler(data);
    });
});
