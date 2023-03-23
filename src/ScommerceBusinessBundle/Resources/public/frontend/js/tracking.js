$.extend({
    handleProductListTracking: function () {
        // Default encian, ne mijenjati
        $(document).on("click", ".product-display-grid a,.product-display-grid .item-cart button", function () {
            var data = {};
            if ($(this).closest(".sp-block-outer").length > 0 && $(this).closest(".sp-block-outer").find(".section-title .title-text").length > 0)  {
                data.list_name = $(this).closest(".sp-block-outer").find(".section-title .title-text").text();
                data.list_id = $(this).closest(".sp-block-outer").data("block-id");
                data.list_index = $(this).closest(".product-display-grid").index();
            } else if ($(this).closest(".sp-block-outer-product_grid").length > 0 && $(".sp-block-outer-category_description").length > 0) {
                data.list_name = "Kategorija " + $(".sp-block-outer-category_description").find(".group-name").text();
                data.list_id = $(".sp-block-outer-category_description").data("category");
                data.list_index = $(this).closest(".product-display-grid").index();
            } else if ($("body.page-search_results")) {
                data.list_name = "Pretraga";
                data.list_index = $(this).closest(".product-display-grid").index();
            }
            var listTracking = {};
            if ($.getCookie("list_tracking")) {
                listTracking = JSON.parse($.getCookie("list_tracking"));
            }
            listTracking["product-" + $(this).closest(".product-display-grid").data("product-id")] = data;
            $.setCookie("list_tracking", JSON.stringify(listTracking));
        });
    }
});
jQuery(document).ready(function ($) {
    $.handleProductListTracking();

    $(document).on("click", ".checkout.cart-proceed", function () {
        $.ajax({
            url: "/api/tracking/gtag_begin_checkout",
            method: 'POST',
            data: {},
            cache: false
        });
    });

    var triggeredPromotion = $.getCookie('triggered_promotion');
    if (triggeredPromotion) {
        triggeredPromotion = JSON.parse(triggeredPromotion)
        $.ajax({
            url: "/api/tracking/gtag_select_promotion",
            method: 'POST',
            data: {
                "promotion_name": triggeredPromotion.promotion_name,
                "promotion_id": triggeredPromotion.promotion_id,
                "promotion_index": triggeredPromotion.promotion_index,
            },
            cache: false
        }).done(function () {
            $.setCookie("triggered_promotion", "");
        });
    }

    if ($(".sp-block-outer-slider").length > 0) {
        $(document).on("click", ".sp-block-outer-slider a", function () {
            if ($(this).data("name") && $(this).data("id")) {
                // Save to cookie to be triggered on page open
                $.setCookie("triggered_promotion",
                    JSON.stringify(
                        {
                            "promotion_name": $(this).data("name"),
                            "promotion_id": $(this).data("id"),
                            "promotion_index": $(this).data("index"),
                        }
                    )
                );
            } else {
                console.log("Missing promotion data");
            }
        });
    }
});