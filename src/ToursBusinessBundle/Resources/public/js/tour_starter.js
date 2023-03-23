jQuery(document).ready(function ($) {
    var showTip = function (index) {
        var rawData = $(".tour-starter").data("data").tips;
        if (index == undefined) {
            index = 0;
        }
        var data = rawData[index];
        var element = $(data.selector).first();

        $(".tip-popup").remove();

        var popup = $("<div>").addClass("tip-popup").data("id", data.id).data("index", index);
        var title = $("<div>").addClass("tip-title").text(data.name);
        var body = $("<div>").addClass("tip-body").html(data.body);
        var closeBtn = $("<span>").addClass("tip-close").text("×").attr("title", "Close tour");

        popup.append(title);
        popup.append(body);
        popup.append(closeBtn);

        if (rawData[index + 1] != undefined) {
            var nextBtn = $("<button>").addClass("tip-next btn").text(data.next_label);
            popup.append(nextBtn);
        } else if ($(".tour-starter").data("data").next_page != undefined) {
            var nextPageBtn = $("<button>").addClass("tip-next-page btn").text(data.next_page_label).data("url", $(".tour-starter").data("data").next_page);
            popup.append(nextPageBtn);
        } else {
            var endBtn = $("<button>").addClass("tip-end btn").text(data.close_label);
            popup.append(endBtn);
        }

        var pos = element.offset();
        popup.css("top", (pos.top + element.height() + 20) + "px");
        if (pos.left > $(window).width() / 2) {
            popup.css("left", pos.left + element.width() - (390 - element.width() / 2) + "px");
            popup.addClass("right");
        } else {
            popup.css("left", (pos.left - 20) + "px");
        }

        $("body").append(popup);

        $.scrollToTop(popup, 200);
    }
    var startTour = function (tourId) {
        $.ajax({
            url: "/tour/get_tips",
            method: "POST",
            data: {"id": tourId, "url": window.location.pathname}
        }).done(function (result) {
            if (result.error == false) {
                $(".tour-starter").data("data", result.data);
                showTip();
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        });
    };

    var endTour = function () {
        $.ajax({
            url: "/tour/stop",
            method: "POST",
        }).done(function () {
            $(".tour-starter.active").removeClass("active");
            $(".tip-popup").remove();
        });
    };

    if ($(".tour-starter").hasClass("active")) {
        startTour($(".tour-starter").data("running"));
    }

    $(document).on("click", ".tip-popup .tip-close", function () {
        endTour();
    });

    $(document).on("click", ".tour-starter", function () {
        if ($(this).hasClass("active")) {
            endTour();
        } else {
            startTour(0);
        }
    });

    $(document).on("click", ".tip-next", function () {
        var currentIndex = $(".tip-popup").data("index");
        showTip(currentIndex + 1);
    });

    $(document).on("click", ".tip-next-page", function () {
        $.redirectToUrl($(this).data("url"));
    });

    $(document).on("click", ".tip-end", function () {
        endTour();
    });
});