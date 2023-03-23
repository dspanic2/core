jQuery.extend({
    useAjaxOnFirstLoad: function () {
        // backwards compatibility
        return true;
    },
    isPager: function () {
        return true;
    },
    filterTitleCollapsible: function () {
        return true;
    },
    reloadProductList: function (productList, firstLoad, isLoadMore, resetPager, skipPushState, activateFilters) {
        if (typeof firstLoad == "undefined") {
            firstLoad = false;
        }
        if (typeof isLoadMore == "undefined") {
            isLoadMore = false;
        }
        if (typeof resetPager == "undefined") {
            resetPager = true;
        }
        if (typeof skipPushState == "undefined") {
            skipPushState = false;
        }
        if (typeof activateFilters == "undefined") {
            activateFilters = false;
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
                    // $.resetProductListCurrentPageNumber();
                    getAllProducts = 1;
                }

                if (resetPager) {
                    productPageNumber = 1;
                }

                if (!firstLoad) {
                    getAllProducts = 0;
                    $.setProductListUrlParameters(productNumItems, productSortBy, productPageNumber, skipPushState);
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
                        if (getAllProducts || $.isPager() || (!$.isPager() && !isLoadMore)) {
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

                        // Always refresh html to rebuild item status
                        productList.find('.content .items-options').html(result.sort_html);

                        $.resetProductListGridListToggle(productList);
                        $.productListPresetOptionsFromUrl(productList);
                        $('.product-filters.active').removeClass('active');

                        productList.find('.content .items-pager').html(result.pager_html);
                        if ($.isPager()) {
                            $.resetProductListPager(productList);
                        } else {
                            $.resetProductListLoadMore(productList);
                        }

                        $.resetProductListSortOptions(productList);

                        if ($('.active-filters-wrapper').length && result.active_filters !== undefined) {
                            $('.active-filters-wrapper').html(result.active_filters);
                        }

                        // Set filters HTML.
                        if ($('.sp-block-outer.product-filters').length) {
                            $('.sp-block-outer.product-filters').find('.content').html(result.filter_html);
                            $.resetProductListFilterOptions(productList);
                            $.handleProductListShowHideClearFitlers();
                            if (activateFilters) {
                                $('.sp-block-outer.product-filters').addClass("active");
                            }
                        } else {
                            $(".responsive-filters-toggle").remove();
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
        var productSortBy = productList.find('[name="product-sort-by"]');
        if (productSortBy.length) {

            // Custom dropdown sorting
            if ($(".sort-dropdown .option").length > 0) {
                $(".sort-dropdown .option").on("click", function () {
                    $(".sort-dropdown").find('[name="product-sort-by"]').val($(this).data("value")).trigger("change");
                });
            }

            productSortBy.on('change', function () {
                $.reloadProductList($(this).closest('.sp-block-outer.product-list'));
            });
        }

        // Load product on page size change.
        var productNumItems = productList.find('[name="product-num-items"]');
        if (productNumItems.length) {

            // Custom dropdown page size
            if ($(".num-items-dropdown .option").length > 0) {
                $(".num-items-dropdown .option").on("click", function () {
                    $(".num-items-dropdown").find('[name="product-num-items"]').val($(this).data("value")).trigger("change");
                });
            }

            productNumItems.on('change', function () {
                $.reloadProductList($(this).closest('.sp-block-outer.product-list'));
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
    resetProductListFilterOptions: function (productList) {
        // pretpostavka da imaju smao jedni filteri
        if ($(".product-filters.active").length) {
            $(".product-filters.active").removeClass("active");
        }

        if ($(".apply-all-filters").length > 0) {
            $(".apply-all-filters").on("click", function () {
                $.handleProductListShowHideClearFitlers();
                $('.sp-block-outer.product-list').each(function () {
                    $.reloadProductList($(this));
                });
            });
        } else {
            var filter = $('.category-filter input[type="checkbox"], .category-filter select');
            if (filter.length) {
                filter.on('change', function () {
                    $.handleProductListShowHideClearFitlers();
                    $.reloadProductList(productList, false, false, true, false, true);
                });
            }
        }

        var filterShowMore = $('.category-filter-show-toggler > span');
        if (filterShowMore.length) {
            filterShowMore.on('click', function (e) {
                var filterValues = $(this).closest('.category-filter');
                filterValues.addClass('expanded');
                filterValues.find("ul.show-more-wrapper").slideToggle().toggleClass("visible");
                filterValues.closest(".category-filter").toggleClass("visible");
            });
        }

        var filterClearAll = $('button.clear-all-filters.button');
        if (filterClearAll.length) {
            filterClearAll.on('click', function () {
                filterClearAll.hide();
                $('.category-filter input[type="checkbox"]').prop('checked', false);
                $('.category-filter select').val("");
                if ($('.category-filter [name="min_price"]').length > 0) {
                    $('.category-filter [name="min_price"]').val($('.category-filter [name="min_price"]').attr("min"));
                }
                if ($('.category-filter [name="max_price"]').length > 0) {
                    $('.category-filter [name="max_price"]').val($('.category-filter [name="max_price"]').attr("max"));
                }
                $('.sp-block-outer.product-list').each(function () {
                    $.reloadProductList($(this));
                });
            });
        }

        // Price filter
        var priceFilter = $(".filter-values.price")
        if (priceFilter.length) {
            priceFilter.each(function () {
                var priceFilterItem = $(this);
                var min = parseInt(priceFilterItem.find('[name="min_price"]').attr("min"));
                var max = parseInt(priceFilterItem.find('[name="min_price"]').attr("max"));
                priceFilterItem.find(".price-filter-range").slider({
                    range: true,
                    orientation: "horizontal",
                    min: min,
                    max: max,
                    values: [min, max],
                    step: 1,
                    slide: function (event, ui) {
                        if (ui.values[0] == ui.values[1]) {
                            return false;
                        }
                        priceFilterItem.find(".min-price").val(ui.values[0]);
                        priceFilterItem.find(".max-price").val(ui.values[1]);
                    }
                });
                // priceFilterItem.find(".min-price").val(priceFilterItem.find(".price-filter-range").slider("values", 0));
                // priceFilterItem.find(".max-price").val(priceFilterItem.find(".price-filter-range").slider("values", 1));
                if ($(".apply-all-filters").length == 0) {
                    var priceFilterPressed = false;
                    $(".filter-values.price .ui-slider-handle").mousedown(function () {
                        if ($(window).width() > 991) {
                            priceFilterPressed = true;
                        }
                    });
                    $(document).mouseup(function () {
                        if (priceFilterPressed) {
                            priceFilterPressed = false;
                            $.reloadProductList(productList);
                        }
                    });
                }

                var convertInputToSlider = function (minEl, maxEl) {
                    var min_price_range = parseInt(minEl.attr("value"));
                    var max_price_range = parseInt(maxEl.attr("value"));

                    if (min_price_range > max_price_range) {
                        maxEl.val(min_price_range);
                    }

                    if (min_price_range === max_price_range) {
                        max_price_range = min_price_range + 100;

                        minEl.val(min_price_range);
                        maxEl.val(max_price_range);
                    }

                    priceFilterItem.find(".price-filter-range").slider({
                        values: [min_price_range, max_price_range]
                    });
                }

                // Set initial
                convertInputToSlider(priceFilterItem.find(".min-price"), priceFilterItem.find(".max-price"));

                priceFilterItem.find(".min-price").on('change paste keyup', function () {
                    convertInputToSlider($(this), priceFilterItem.find(".max-price"));
                });
                priceFilterItem.find(".max-price").on('change paste keyup', function () {
                    convertInputToSlider(priceFilterItem.find(".min-price"), $(this));
                });
                priceFilterItem.find("button.apply").on("click", function () {
                    $('.sp-block-outer.product-list').each(function () {
                        $(this).find('.category-list-pager li.activated').removeClass("activated");
                        $(this).find('.category-list-pager [data-page-number="1"]').addClass("activated");
                        $.reloadProductList($(this));
                    });
                });
                priceFilterItem.find("button.reset").on("click", function () {
                    priceFilterItem.find(".min-price").val(priceFilterItem.find(".min-price").attr("min"));
                    priceFilterItem.find(".max-price").val(priceFilterItem.find(".max-price").attr("max"));

                    $('.sp-block-outer.product-list').each(function () {
                        $(this).find('.category-list-pager li.activated').removeClass("activated");
                        $(this).find('.category-list-pager [data-page-number="1"]').addClass("activated");
                        $.reloadProductList($(this));
                    });
                });
            });
        }
    },
    handleActiveFilterRemove: function (activeFilterElement) {
        var dataFilterId = activeFilterElement.data("id");

        if (activeFilterElement.hasClass("price-remove-filtered-value")) {
            $('.filter-values.price [name="min_price"]').val($('.filter-values.price [name="min_price"]').attr("min"));
            $('.filter-values.price [name="max_price"]').val($('.filter-values.price [name="max_price"]').attr("max"));
            $(".price-filter-input .apply").click();
        } else {
            if ($(".category-filter").find('#' + dataFilterId).closest("label").length > 0) {
                $(".category-filter").find('#' + dataFilterId).closest("label").click();
            } else if ($(".category-filter").find('#' + dataFilterId).find("button.reset")) {
                $(".category-filter").find('#' + dataFilterId).find("button.reset").click();
            }
        }
    },
});
jQuery(document).ready(function ($) {
    $.extend({
        productListPresetOptionsFromUrl: function (productList) {
            // Preset sorting
            var sortParam = $.getUrlParam('sort');
            if (sortParam !== 0) {
                productList.find('[name="product-sort-by"]').val(sortParam);
            }

            // Preset page_size
            var pageSizeParam = $.getUrlParam('page_size');
            if (pageSizeParam !== 0) {
                productList.find('[name="product-num-items"]').val(pageSizeParam);

                if ($(".num-items-dropdown").length > 0) {
                    $('.num-items-dropdown [data-value="' + pageSizeParam + '"]').addClass("activated");
                }
            }

            // Preset current page
            var pageParam = $.getUrlParam('page_number');
            if (pageParam !== 0) {
                $('.products-load-more').data('current-page', pageParam);
            }
        },
        setProductListUrlParameters: function (productNumItems, productSortBy, productPageNumber, skipPushState) {
            var url = window.location.href.split('?')[0];
            var params = {};
            params.page_size = productNumItems;
            params.sort = productSortBy;
            params.page_number = productPageNumber;
            if ($.getUrlParam('s')) {
                params.s = 1;
                var found_s = false;
                $.each($.getAllUrlParams(), function (filterKey, val) {
                    if (filterKey === "f") {
                        return false;
                    }
                    if (filterKey === "s") {
                        found_s = true;
                        return;
                    }
                    if (!found_s) {
                        return;
                    }
                    params[filterKey] = val;//values.join(';');
                });
            }
            var filterValues = $.getProductListFilterValuesForUrl();
            if (Object.keys(filterValues).length > 0) {
                params.f = 1;
                $.each(filterValues, function (filterKey, values) {
                    params[filterKey] = values.join(';');
                });
            }

            if (!skipPushState) {
                window.history.pushState({}, '', url + '?' + $.param(params));
            }
        },
        getProductListFilterValuesForUrl: function () {
            var filterElements = $(document).find('.category-filter');
            var filters = {};
            filterElements.each(function () {
                var filterElement = $(this);

                var activeFilters = filterElement.find('input[type="checkbox"]:checked');
                if (activeFilters.length) {
                    var key = filterElement.data('filter-key');
                    if (key !== undefined) {
                        filters[key] = [];
                        activeFilters.each(function () {
                            filters[key].push(encodeURIComponent($(this).val()));
                        });
                    }
                }

                // Select filters
                var selectFilters = filterElement.find('select');
                if (selectFilters.length) {
                    selectFilters.each(function () {
                        var key = filterElement.data('filter-key');
                        selectFilters.each(function () {
                            if ($(this).val()) {
                                if (filters[key] === undefined) {
                                    filters[key] = [];
                                }
                                filters[key].push(encodeURIComponent($(this).val()));
                            }
                        });
                    });
                }

                // Other input filters
                var otherFilters = filterElement.find('input:not([type="checkbox"])');
                if (otherFilters.length) {
                    otherFilters.each(function () {
                        if ($(this).attr("name") !== undefined) {
                            filters[$(this).attr("name")] = [];
                            filters[$(this).attr("name")].push(encodeURIComponent($(this).val()));
                        }
                    });
                }
            });

            return filters;
        },
        getProductListGroupId: function () {
            var productGroup = $('[data-entity-type="product_group"]');
            var productGroupId = null;
            if (productGroup.length) {
                productGroupId = productGroup.data('entity-id');
            }

            return productGroupId;
        },
        resetProductListCurrentPageNumber: function () {
            var loadMoreBtn = $('.products-load-more');
            if (loadMoreBtn.length) {
                loadMoreBtn.data('current-page', 1);
            }
        },
        getProductListCurrentPageNumber: function (firstLoad) {
            if (firstLoad) {
                var pageParam = $.getUrlParam('page_number');
                if (pageParam !== 0 && pageParam !== "undefined") {
                    return pageParam;
                }
            }
            var loadMoreBtn = $('.products-load-more');
            if (loadMoreBtn.length) {
                if (loadMoreBtn.data('current-page') === $.getUrlParam('page_number')) {
                    // filter or something applied, reset page to first
                    return 1;
                }
                return loadMoreBtn.data('current-page');
            }
            var pager = productList.find('.category-list-pager');
            if (pager.length) {
                if (pager.find("li.active").length > 0) {
                    return pager.find("li.active").data('page-number');
                } else if (pager.find("li.activated").length > 0) {
                    return pager.find("li.activated").data('page-number');
                }
            }
            return 1;
        },
        handleProductListShowHideClearFitlers: function () {
            var filterClearAll = $('button.clear-all-filters.button');
            if ($('.category-filter input[type="checkbox"]:checked').length) {
                filterClearAll.show();
            } else if ($('.category-filter select').length && $('.category-filter select').val() !== "") {
                filterClearAll.show();
            } else if (($('.category-filter [name="min_price"]').val() !== $('.category-filter [name="min_price"]').attr("min")) || $('.category-filter [name="max_price"]').val() !== $('.category-filter [name="max_price"]').attr("max")) {
                filterClearAll.show();
            } else {
                filterClearAll.hide();
            }
        },
        resetProductListLoadMore: function (productList) {
            var loadMoreBtn = productList.find('.products-load-more');
            if (loadMoreBtn.length) {
                var pageParam = $.getUrlParam('page_number');
                if (pageParam !== 0) {
                    loadMoreBtn.data('current-page', pageParam);
                }
                loadMoreBtn.on('click', function () {
                    var currentPage = $(this).data('current-page');
                    $(this).data('current-page', parseInt(currentPage) + 1);
                    $.reloadProductList($(this).closest('.sp-block-outer.product-list'), false, true, false);
                });
            }
        },
        resetProductListPager: function (productList) {
            var pagers = productList.find('.category-list-pager');
            if (pagers.length) {
                pagers.each(function () {
                    var pager = $(this);
                    pager.find('.page:not(.active) a, .previous a, .next a').on('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        $(this).closest("li").click();
                    });
                    pager.find('.page:not(.active)').on('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        pager.find('.page').removeClass("activated");
                        pager.find('.page').removeClass("active");
                        $(this).addClass("activated");
                        $(this).addClass("active");
                        $.reloadProductList($(this).closest('.sp-block-outer.product-list'), false, false, false);
                    });
                    pager.find(".previous").on("click", function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var prev = pager.find(".active,.activated").first().prev();
                        if (prev.hasClass("page")) {
                            prev.click();
                        }
                    });
                    pager.find(".next").on("click", function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var next = pager.find(".active,.activated").first().next();
                        if (next.hasClass("page")) {
                            next.click();
                        }
                    });

                    // Minify pages
                    if (pager.find("li.page").length > 10) {
                        // Hide all except active
                        pager.find("li.page:not(.active)").hide();
                        pager.find("li.page:not(.activated)").hide();

                        var pagerLength = pager.find("li.page").length;
                        var activeIndex = pager.find("li.page.active").index();
                        if (pager.find("li.page.activated").length > 0) {
                            activeIndex = pager.find("li.page.activated").index();
                        }

                        if (pager.find("li.page:eq(" + (activeIndex - 1) + ")").length) {
                            pager.find("li.page:eq(" + (activeIndex - 1) + ")").show();
                        }
                        if (pager.find("li.page:eq(" + (activeIndex - 2) + ")").length) {
                            pager.find("li.page:eq(" + (activeIndex - 2) + ")").show();
                            if (activeIndex - 2 > 2) {
                                pager.find("li.page:eq(" + (activeIndex - 2) + ")").before("<li class='ellipsis'>...</li>");
                            }
                        }

                        if (pager.find("li.page:eq(" + (activeIndex) + ")").length) {
                            pager.find("li.page:eq(" + (activeIndex) + ")").show();
                        }

                        if (pager.find("li.page:eq(" + (activeIndex + 1) + ")").length) {
                            pager.find("li.page:eq(" + (activeIndex + 1) + ")").show();
                        }
                        if (pager.find("li.page:eq(" + (activeIndex + 2) + ")").length) {
                            pager.find("li.page:eq(" + (activeIndex + 2) + ")").show().after("<li class='ellipsis'>...</li>");
                        }

                        pager.find("li.page:eq(0)").show();
                        pager.find("li.page:eq(1)").show();
                        pager.find("li.page:eq(" + (pagerLength - 1) + ")").show();
                        pager.find("li.page:eq(" + (pagerLength - 2) + ")").show();
                    }

                    pager.addClass("ready");
                });
            }
        },
        resetProductListGridListToggle: function (productList) {
            var toggleListView = productList.find('.toggle-list-view');
            var toggleGridView = productList.find('.toggle-grid-view');

            // Category list toggles
            if (toggleListView.length) {
                toggleListView.on('click', function () {
                    var grid = $(this).closest('.section').parent().find('.items-grid');
                    if (!grid.hasClass('list-view')) {
                        grid.addClass('list-view');
                        var pathnames = {};
                        if ($.getCookie("list_views")) {
                            pathnames = JSON.parse($.getCookie("list_views"));
                        }
                        pathnames[location.pathname] = 2;
                        $.setCookie("list_views", JSON.stringify(pathnames));
                    }
                    if (!$(this).hasClass('active')) {
                        $(this).addClass('active');
                    }
                    toggleGridView.removeClass('active');
                });
            }
            if (toggleGridView.length) {
                toggleGridView.on('click', function () {
                    var grid = $(this).parents('.section').parent().find('.items-grid');
                    if (grid.hasClass('list-view')) {
                        grid.removeClass('list-view');
                        var pathnames = {};
                        if ($.getCookie("list_views")) {
                            pathnames = JSON.parse($.getCookie("list_views"));
                        }
                        pathnames[location.pathname] = 1;
                        $.setCookie("list_views", JSON.stringify(pathnames));
                    }
                    if (!$(this).hasClass('active')) {
                        $(this).addClass('active');
                    }
                    toggleListView.removeClass('active');
                });
            }

            if ($.getCookie("list_views")) {
                var pathnames = JSON.parse($.getCookie("list_views"));
                if (pathnames[location.pathname] == 2) {
                    toggleListView.click();
                }
            }
        }
    });

    var productList = $('.sp-block-outer.product-list');

    // Show item specs
    $(document).on('click', ".specification .expand", function () {
        $(this).parents(".product-display-grid").find(".specification-full").addClass("active");
    });

    // Hide item specs
    $(document).on('click', ".specification-full .close", function () {
        $(this).parents(".product-display-grid").find(".specification-full").removeClass("active");
    });

    // Handle responsive show/hide filters
    $(document).on('click', 'span.responsive-filters-toggle', function () {
        $('.category-products.product-filters').toggleClass('active');
    });

    // Product filter collapsible
    $(document).on('click', '.category-filter .filter-title', function () {
        if ($.filterTitleCollapsible()) {
            $(this).closest(".category-filter").toggleClass("expanded");
        }
    });

    // Active filters
    $(document).on("click", ".active-filters-wrapper .selected", function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $.handleActiveFilterRemove($(this));
    });

    // Load product on page load.
    if (productList.length) {
        productList.each(function () {
            $.reloadProductList($(this), true, false);
        });
        $(window).on('popstate', function () {
            var previousPageNumber = $.getUrlParam("page_number");
            $('.category-list-pager li.active').removeClass("active");
            $('.category-list-pager li.activated').removeClass("activated");
            $('.category-list-pager li[data-page-number="' + previousPageNumber + '"]').addClass("active");
            $('.category-list-pager li[data-page-number="' + previousPageNumber + '"]').addClass("activated");
            productList.each(function () {
                $.reloadProductList($(this), false, false, false, true);
            });
        });
    }
});
