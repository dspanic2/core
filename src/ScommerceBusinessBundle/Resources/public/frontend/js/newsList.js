jQuery(document).ready(function ($) {
    $.extend({
        reloadNewsList: function (blogContainer, firstLoad, isLoadMore) {
            if (typeof firstLoad == "undefined") {
                firstLoad = false;
            }
            if (typeof isLoadMore == "undefined") {
                isLoadMore = false;
            }
            var blogCategoryId = $.getBlogCategoryId(blogContainer);


            if (!isLoadMore) {
                $.resetBlogCurrentPageNumber();
            }

            var productPageNumber = $.getBlogCurrentPageNumber(firstLoad);
            var getAllBlogPosts = 1;

            if (!firstLoad) {
                getAllBlogPosts = 0;
                $.setUrlParameters({"page_number": productPageNumber});
            }

            if (!isLoadMore) {
                getAllBlogPosts = 1;
            }

            var options = {};
            if (blogCategoryId !== "undefined") {
                options.blog_category = blogCategoryId;
            }
            options.page_number = productPageNumber;
            options.get_all_blog_posts = getAllBlogPosts;
            options.page_size = 10;

            $.showAjaxLoader();
            $.post('/get_blog_posts', options, function (result) {
                $.hideAjaxLoader();
                if (result.error == false) {
                    if (getAllBlogPosts) {
                        blogContainer.find('.content .blog-grid-content').html(result.grid_html);
                    } else {
                        var lastScrollFromTop = $(window).scrollTop();
                        blogContainer.find('.content .blog-grid-content').append(result.grid_html);
                        $(window).scrollTop(lastScrollFromTop);
                    }

                    $.loadLazyImages();

                    if (firstLoad) {
                        blogContainer.find('.content .items-pager').html(result.pager_html);

                        $.presetBlogOptionsFromUrl();
                        $.resetBlogLoadMore(blogContainer);
                    }

                    if (!result.has_next_page) {
                        $('.news-load-more').hide();
                    } else {
                        $('.news-load-more').show();
                    }
                } else {
                    console.log("ERROR");
                }
            }, "json");
        },
        presetBlogOptionsFromUrl: function () {
            var pageParam = $.getUrlParam('page_number');
            if (pageParam !== 0) {
                $('.news-load-more').data('current-page', pageParam);
            }
        },
        getBlogCategoryId: function (blogContainer) {
            var blogCategoryId = null;
            if (blogContainer.length) {
                blogCategoryId = blogContainer.data('category-id');
            }
            return blogCategoryId;
        },
        resetBlogCurrentPageNumber: function () {
            var loadMoreBtn = $('.news-load-more');
            if (loadMoreBtn.length) {
                loadMoreBtn.data('current-page', 1);
            }
        },
        getBlogCurrentPageNumber: function (firstLoad) {
            if (firstLoad) {
                var pageParam = $.getUrlParam('page_number');
                if (pageParam !== 0) {
                    return pageParam;
                }
            }
            var loadMoreBtn = $('.news-load-more');
            if (loadMoreBtn.length) {
                return loadMoreBtn.data('current-page');
            }
            return 1;
        },
        resetBlogLoadMore: function (blogContainer) {
            var loadMoreBtn = blogContainer.find('.news-load-more');
            if (loadMoreBtn.length) {
                var pageParam = $.getUrlParam('page_number');
                if (pageParam !== 0) {
                    loadMoreBtn.data('current-page', pageParam);
                }
                loadMoreBtn.on('click', function () {
                    var currentPage = $(this).data('current-page');
                    $(this).data('current-page', parseInt(currentPage) + 1);
                    $.reloadNewsList($(this).closest('.sp-block-outer.blog-grid'), false, true);
                });
            }
        },
    });

    var newsList = $('.sp-block-outer.blog-grid.ajax');

    // Load product on page load.
    if (newsList.length) {
        newsList.each(function () {
            if ($(this).find(".blog-item").length == 0) {
                $.reloadNewsList($(this), true);
            }
        });
    }
});
