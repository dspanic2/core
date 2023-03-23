jQuery(document).ready(function ($) {

    var initCreateTourtip = function (selector) {
        var url = "/block/modal/?action=reload&attribute_set_code=tour_tip";
        $.post(url, {}, function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                var clone = $('#modal-container').clone(true, true).appendTo($('#page-content > div'));
                clone.html(result.html);
                var modal = clone.find('.modal');
                modal.modal('show');

                var form = modal.find('[data-validate="true"]');
                form.initializeValidation();
                form.forceBoostrapXs();

                if (form.find('[name="selector"]').length > 0) {
                    form.find('[name="selector"]').val(selector);
                    form.find('[name="selector"]').attr("readonly", "readonly");
                }

                if (form.find('[name="url"]').length > 0) {
                    form.find('[name="url"]').val(window.location.pathname);
                    form.find('[name="url"]').attr("readonly", "readonly");
                }
            } else {
                $.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    };

    $.extend({
        getPath: function (element) {
            var selector = "";
            while (element) {
                var s = "";
                var tagName = element.prop("tagName").toLowerCase();

                s += tagName;

                var siblings = element.siblings(tagName);

                if (typeof element.attr('data-action-type') !== 'undefined' && element.attr('data-action-type') !== false) {
                    s += '[data-action-type="' + element.attr('data-action-type') + '"]';
                } else if (siblings.length > 1) {
                    s += ':eq(' + element.index() + ')';
                }

                selector = s + (selector ? '>' + selector : '');

                if (tagName === "html") {
                    return selector;
                }

                element = element.parent();
            }
            return selector;
        },
    });

    // Start builder
    $(".tour-selector-generator").on('click', function (event) {
        $('*').unbind('click');
        event.preventDefault();
        $("html").toggleClass("tour-selector-generator-active");
        $(this).toggleClass("active");
    });

    // Highlight element
    $(document).on("mouseover", "html.tour-selector-generator-active *", function (e) {
        $(".tour-highlight").removeClass("tour-highlight");
        var target = $(e.target);
        if (target.parents(".modal").length == 0) {
            target.addClass("tour-highlight");
        }
    });

    // Initiate tour tip creator
    $(document).on('click', "html.tour-selector-generator-active *", function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        var target = $(event.target);
        if (!target.hasClass("tour-selector-generator") && target.parents(".tour-selector-generator").length == 0) {
            var selector = $.getPath($(event.target));
            $.copyToClipboard(selector);
            initCreateTourtip(selector);
            $("html.tour-selector-generator-active").removeClass("tour-selector-generator-active");
            $(".tour-selector-generator").removeClass("active");

            $.growl.notice({
                message: translations.copied
            });
        }
    });
});