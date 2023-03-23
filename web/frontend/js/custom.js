jQuery.extend({
    useAjaxOnFirstLoad: function () {
        // backwards compatibility
        return false;
    },
    isPager: function () {
        return false;
    },
    mainMenuToggleHandler: function (element, event) {
        event.preventDefault();
        var nav = $("nav");
        if (!element.hasClass("active")) {
            $.disableOverlays();
        }
        element.toggleClass('active');
        if (element.hasClass('active')) {
            nav.addClass('active');
        } else {
            nav.removeClass('active');
        }
    },
    reloadProductList: function (productList, firstLoad, isLoadMore) {
        if (typeof firstLoad == "undefined") {
            firstLoad = false;
        }
        if (typeof isLoadMore == "undefined") {
            isLoadMore = false;
        }
        if (firstLoad && !$.useAjaxOnFirstLoad()) {
            $.resetProductListSortOptions(productList);
            $.resetProductListGridListToggle(productList);
            $.resetProductListFilterOptions(productList);
            $.handleProductListShowHideClearFitlers();
            if ($.isPager()) {
                $.resetProductListPager(productList);
            } else {
                $.resetProductListLoadMore(productList);
            }
        } else {
            var productGroupId = $.getProductListGroupId();
            var customListFilter = productList.find(".product-list-content").data("filter");
            var s = $.getUrlParam('s');
            if ((productGroupId != null || customListFilter != null) || s) {
                var productNumItems = $.getProductListNumItems();
                var productSortBy = $.getProductListSortBy();
                var productPageNumber = $.getProductListCurrentPageNumber(firstLoad);
                var getAllProducts = 1;
                var options = {};

                if (!isLoadMore) {
                    $.resetProductListCurrentPageNumber();
                    getAllProducts = 1;
                }

                if (!firstLoad) {
                    getAllProducts = 0;
                    $.setProductListUrlParameters(productNumItems, productSortBy, productPageNumber);
                }

                options.get_all_products = getAllProducts;
                if (productGroupId) {
                    options.product_group = productGroupId;
                }
                if (customListFilter) {
                    options.c = true;
                    $.each(customListFilter, function (key, val) {
                        options[key] = val;
                    });

                    var customListSort = productList.find(".product-list-content").data("sort");
                    if (customListSort) {
                        $.each(customListSort, function (key, val) {
                            options[key] = val;
                        });
                    }

                    var customListPageSize = productList.find(".product-list-content").data("page-size");
                    if (customListPageSize) {
                        options["page_size"] = customListPageSize;
                    }
                }
                $.each($.getAllUrlParams(), function (key, val) {
                    options[key] = val;
                });

                if ($.isPager()) {
                    options.get_all_products = 0;
                }

                $.showAjaxLoader();
                $.post('/get_products', options, function (result) {
                    $("#missing-keyword").remove();
                    if (result.error === false) {
                        // Set grid HTML.
                        if (getAllProducts || $.isPager() || (!$.isPager() && !firstLoad && !isLoadMore)) {
                            productList.find('.content .items-grid').html(result.grid_html);
                            // $.scrollToTop(productList);
                        } else {
                            var lastScrollFromTop = $(window).scrollTop();
                            productList.find('.content .items-grid').append(result.grid_html);
                            $(window).scrollTop(lastScrollFromTop);
                        }

                        if (productList.find("#no-results").length > 0 && $("body.page-search_results").length > 0) {
                            $(".left-sidebar").parent().addClass("hidden-column");
                        } else {
                            $(".left-sidebar").parent().removeClass("hidden-column");
                        }

                        $('.items-options').html(result.sort_html);

                        if (firstLoad) {
                            productList.find('.content .items-options').html(result.sort_html);

                            $.resetProductListGridListToggle(productList);
                            $.resetProductListLoadMore(productList);

                            $('.product-filters.active').removeClass('active');
                        }
                        $.resetProductListSortOptions(productList);
                        $.productListPresetOptionsFromUrl(productList);

                        productList.find('.content .items-pager').html(result.pager_html);
                        if ($.isPager()) {
                            $.resetProductListPager(productList);
                        } else {
                            $.resetProductListLoadMore(productList);
                        }

                        // Set filters HTML.
                        if ($('.sp-block-outer.product-filters').length) {
                            $('.sp-block-outer.product-filters').find('.content').html(result.filter_html);
                            $.resetProductListFilterOptions(productList);
                            $.handleProductListShowHideClearFitlers();
                        } else {
                            $(".responsive-filters-toggle").remove();
                        }

                        if ($(".active-filters-wrapper").length && result.active_filters !== undefined) {
                            $(".active-filters-wrapper").html(result.active_filters);
                        }

                        if (!firstLoad && !isLoadMore) {
                            $.scrollToTop(productList);
                        }

                        if (!result.has_next_page) {
                            $('.products-load-more').hide();
                        } else {
                            $('.products-load-more').show();
                        }

                        var dataCallback = $('.main-content').data('callback-grid');
                        if (dataCallback) {
                            $.each(dataCallback, function (key, f) {
                                var func = eval(f);
                                if ($.isFunction(func)) {
                                    func(productList, result);
                                }
                            });
                        }

                        if (result.total === 0) {
                            productList.find(".items-grid").addClass("empty");
                            $("body").addClass("empty-product-results");
                        } else {
                            productList.find(".items-grid").removeClass("empty");
                            $("body").removeClass("empty-product-results");
                        }

                        // Facets title swap
                        var h1 = $("body").find("h1");
                        if (result.facet_title !== undefined) {
                            if (result.facet_title !== "") {
                                if (!h1.data("original")) { // if backend did not prepare original
                                    h1.data("original", h1.text());
                                }
                                h1.text(result.facet_title);
                            } else {
                                if (h1.data("original")) {
                                    h1.text(h1.data("original"));
                                }
                            }
                        }

                        // Facets meta title swap
                        var metaTitle = $('head').find('meta[name="title"]');
                        var headTitle = $('head').find("title");
                        var ogTitle = $('head').find('[property="og:title"]');
                        var twitterTitle = $('head').find('[name="twitter:title"]');
                        if (result.facet_meta_title !== undefined) {
                            if (result.facet_meta_title !== "") {
                                if (!metaTitle.data("original")) { // if backend did not prepare original
                                    metaTitle.data("original", metaTitle.attr("content"));
                                }
                                metaTitle.attr("content", result.facet_meta_title);
                                ogTitle.attr("content", result.facet_meta_title);
                                twitterTitle.attr("content", result.facet_meta_title);
                                headTitle.text(result.facet_meta_title);
                            } else {
                                if (metaTitle.data("original")) {
                                    metaTitle.attr("content", metaTitle.data("original"));
                                    ogTitle.attr("content", metaTitle.data("original"));
                                    twitterTitle.attr("content", metaTitle.data("original"));
                                    headTitle.text(metaTitle.data("original"));
                                }
                            }
                        }

                        // Facets meta description swap
                        var metaDescription = $('head').find('meta[name="description"]');
                        if (result.facet_meta_description !== undefined) {
                            if (result.facet_meta_description !== "") {
                                if (!metaDescription.data("original")) { // if backend did not prepare original
                                    metaDescription.data("original", metaDescription.attr("content"));
                                }
                                metaDescription.attr("content", result.facet_meta_description);
                            } else {
                                if (metaDescription.data("original")) {
                                    metaDescription.attr("content", metaDescription.data("original"));
                                }
                            }
                        }

                        if (productList.find(".results-info").length) {
                            var startFrom = (productList.find('[name="product-num-items"]').val() * ($.getProductListCurrentPageNumber(firstLoad) - 1)) + 1;
                            var to = (startFrom - 1) + productList.find(".product-display-grid").length;
                            productList.find(".results-info .from").html(startFrom);
                            productList.find(".results-info .to").html(to);
                            productList.find(".results-info .total").html(result.total);
                        }

                        $.setActiveFavorites();
                        $.createCustomCheckboxes();
                        $.loadLazyImages();
                    } else {
                        var error = '<div id="missing-keyword"><div class="system-message error-message"><span class="system-message-close">×</span>' + result.message + '</div></div>';
                        productList.before(error);
                        $.growl.error({
                            title: translations.error_message,
                            message: result.message,
                        });
                    }
                    $.hideAjaxLoader();
                }, "json");
            }
        }
    },
    resetProductListSortOptions: function (productList) {
        // Load product on sort by change.
        var productSortBy = $('[name="product-sort-by"]');
        if (productSortBy.length) {
            $(".sort-dropdown .option").on("click", function () {
                productSortBy.val($(this).data("value")).trigger("change");
            });
            productSortBy.on('change', function () {
                $.reloadProductList(productList);
            });
        }

        // Load product on page size change.
        var productNumItems = $('[name="product-num-items"]');
        if (productNumItems.length) {
            $(".num-items-dropdown .option").on("click", function () {
                productNumItems.val($(this).data("value")).trigger("change");
            });
            productNumItems.on('change', function () {
                $.reloadProductList(productList);
            });
        }
    },
    getProductListSortBy: function () {
        var productSortBy = 'created_desc';
        var productSortByElement = $('[name="product-sort-by"]');
        if (productSortByElement.length) {
            productSortBy = productSortByElement.val();
        }

        return productSortBy;
    },
    getProductListNumItems: function () {
        var productNumItems = 30;
        var productNumItemsElement = $('[name="product-num-items"]');
        if (productNumItemsElement.length) {
            productNumItems = productNumItemsElement.val();
        }

        return productNumItems;
    },
    initializeProductSlider: function () {
        var productSlider = $('.product-slider:not(.slick-initialized)');
        var productSliderNavigation = $('.product-slider-navigation:not(.slick-initialized)');
        if (productSliderNavigation.length > 0 && productSliderNavigation.find(".image").length > 0) {
            var options = {
                slidesToShow: 4,
                dots: false,
                arrows: false,
                vertical: true,
                verticalSwiping: true,
                asNavFor: '.product-slider',
                swipeToSlide: true,
                focusOnSelect: true,
                infinite: true,
                prevArrow: '<button type="button" class="slick-prev"><i class="fi-xnslxl-chevron-solid"></i></button>',
                nextArrow: '<button type="button" class="slick-next"><i class="fi-xnsrxl-chevron-solid"></i></button>',
                responsive: [
                    {
                        breakpoint: 1199,
                        settings: {
                            vertical: false,
                            verticalSwiping: false,
                        }
                    },
                    {
                        breakpoint: 500,
                        settings: {
                            slidesToShow: 2,
                            vertical: false,
                            verticalSwiping: false,
                        }
                    }
                ]
            };
            if (productSliderNavigation.find(".image").length < 4) {
                productSliderNavigation.addClass("disable-transform");
            }
            productSliderNavigation.slick(options);
        }

        if (productSlider.length > 0 && productSlider.find(".image").length > 0) {
            productSlider.slick({
                lazyLoad: 'anticipated',
                slidesToShow: 1,
                slidesToScroll: 1,
                dots: productSlider.find(".image").length > 1,
                arrows: productSlider.find(".image").length > 1,
                asNavFor: '.product-slider-navigation',
                focusOnSelect: false,
                infinite: true,
                prevArrow: '<button type="button" class="slick-prev"><i class="fi-xnslxl-chevron-solid"></i></button>',
                nextArrow: '<button type="button" class="slick-next"><i class="fi-xnsrxl-chevron-solid"></i></button>',
            });
            productSlider.slickLightbox({
                itemSelector: 'a'
            });
        }
    },
});

jQuery(document).ready(function ($) {
    $.loadLazyImages();

    if ($(".sp-block-outer-slider").length > 0) {
        $(".sp-block-outer-slider .slider").each(function () {
            var slider = $(this);
            slider.slick({
                lazyLoad: 'anticipated',
                slidesToShow: 1,
                slidesToScroll: 1,
                dots: slider.find(".slide").length > 1,
                arrows: false,
                focusOnSelect: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 3000,
            });
        });
    }

    if ($(".sp-block-outer-filtered_brands").length > 0) {
        $(".sp-block-outer-filtered_brands .items").each(function () {
            var slider = $(this);
            slider.slick({
                lazyLoad: 'anticipated',
                slidesToShow: 5,
                slidesToScroll: 1,
                dots: false,
                arrows: false,
                autoplay: true,
                autoplaySpeed: 3000,
                // arrows: slider.find(".slide").length > 5,
                focusOnSelect: false,
                infinite: true,
                prevArrow: '<button type="button" class="slick-prev"><svg width="20" height="34" viewBox="0 0 20 34" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="angle-left copy"><path id="Path" d="M17.6074 34L3.12726 19.5199L3.12629 19.5209L0.733658 17.1282L0.734142 17.1273L0.733658 17.1263L3.12629 14.7337L3.12726 14.7341L17.6074 0.254513L20 2.64714L5.51989 17.1273L20 31.6074L17.6074 34Z" fill="black"/></g></svg></button>',
                nextArrow: '<button type="button" class="slick-next"><svg width="10" height="17" viewBox="0 0 10 17" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.19631 17L8.43637 9.75994L8.43686 9.76043L9.63317 8.56411L9.63293 8.56363L9.63317 8.56314L8.43686 7.36683L8.43637 7.36707L1.19631 0.127256L0 1.32357L7.24006 8.56363L0 15.8037L1.19631 17Z" fill="black"/></svg></button>',
                responsive: [
                    {
                        breakpoint: 767,
                        settings: {
                            slidesToShow: 2,
                        }
                    }
                ]
            });
        });
    }

    if ($(".sp-block-outer-home_categories").length > 0) {
        $(".sp-block-outer-home_categories .items").each(function () {
            var slider = $(this);
            slider.slick({
                lazyLoad: 'anticipated',
                slidesToShow: 3,
                slidesToScroll: 1,
                dots: false,
                // arrows: true,
                arrows: slider.find(".item").length > 3,
                focusOnSelect: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 3000,
                prevArrow: '<button type="button" class="slick-prev"><svg width="20" height="34" viewBox="0 0 20 34" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="angle-left copy"><path id="Path" d="M17.6074 34L3.12726 19.5199L3.12629 19.5209L0.733658 17.1282L0.734142 17.1273L0.733658 17.1263L3.12629 14.7337L3.12726 14.7341L17.6074 0.254513L20 2.64714L5.51989 17.1273L20 31.6074L17.6074 34Z" fill="black"/></g></svg></button>',
                nextArrow: '<button type="button" class="slick-next"><svg width="10" height="17" viewBox="0 0 10 17" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.19631 17L8.43637 9.75994L8.43686 9.76043L9.63317 8.56411L9.63293 8.56363L9.63317 8.56314L8.43686 7.36683L8.43637 7.36707L1.19631 0.127256L0 1.32357L7.24006 8.56363L0 15.8037L1.19631 17Z" fill="black"/></svg></button>',
                responsive: [
                    {
                        breakpoint: 767,
                        settings: {
                            slidesToShow: 2,
                        }
                    },
                    {
                        breakpoint: 500,
                        settings: {
                            slidesToShow: 1,
                        }
                    }
                ]
            });
        });
    }

    if ($(".timer-counter").length) {
        $(".timer-counter").each(function () {
            var countdown = $(this);
            var countdownInterval = setInterval(function () {
                var seconds = parseInt(countdown.find(".seconds .number").text()) - 1;
                if (seconds < 0) {
                    countdown.find(".seconds .number").text(59);

                    var minutes = parseInt(countdown.find(".minutes .number").text()) - 1;
                    if (minutes < 0) {
                        countdown.find(".minutes .number").text(59);

                        var hours = parseInt(countdown.find(".hours .number").text()) - 1;
                        if (hours < 0) {
                            countdown.find(".hours .number").text(59);

                            var days = parseInt(countdown.find(".days .number").text()) - 1;
                            if (days < 0) {
                                clearInterval(countdownInterval);
                                $.reloadPage();
                            } else {
                                if (days < 10) {
                                    days = "0" + days;
                                }
                                countdown.find(".days .number").text(days);
                            }
                        } else {
                            if (hours < 10) {
                                hours = "0" + hours;
                            }
                            countdown.find(".hours .number").text(hours);
                        }
                    } else {
                        if (minutes < 10) {
                            minutes = "0" + minutes;
                        }
                        countdown.find(".minutes .number").text(minutes);
                    }
                } else {
                    if (seconds < 10) {
                        seconds = "0" + seconds;
                    }
                    countdown.find(".seconds .number").text(seconds);
                }
            }, 1000);
        });
    }

    $(document).on("click", ".submenu-toggle-icon", function (e) {
        e.preventDefault();
        $(this).parent().toggleClass("active");
    });

    $(document).on("click", ".shop-items li.search", function (e) {
        e.preventDefault();
        $("#search-form").toggleClass("active");
    });

    $(document).on("click", ".tabs .tab-item", function (e) {
        e.preventDefault();
        if ($(this).parents(".sp-block-outer-home_categories").length > 0) {
            $('.sp-block-outer-home_categories .items').slick('refresh');
        }
    });

    $("body").addClass("js-initialized");
});
