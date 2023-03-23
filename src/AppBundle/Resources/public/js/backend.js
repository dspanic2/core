jQuery(document).ready(function ($) {

    // Menu quick search
    $('[data-action="menu-quick-search"]').on("input", function () {
        $("#menu-quick-search-results").html("");
        var val = $(this).val();

        $('ul.nav a:not(.dropdown-toggle)').each(function () {
            var value = $(this).text().toLowerCase();
            if (value.indexOf(val.toLowerCase()) >= 0) {
                var listItem = $("<li>");
                listItem.append($(this).clone());
                listItem.appendTo("#menu-quick-search-results");
            }
        });
    });
    $('[data-action="menu-quick-search"]').focusout(function () {
        window.setTimeout(function () {
            $("#menu-quick-search-results").html("");
        }, 200);
    });
    $(document).keydown(function (e) {
        if ($('[data-action="menu-quick-search"]').is(":focus")) {
            if (e.which == 40) { // Down key
                if ($("#menu-quick-search-results>li.quick-search-active").length > 0) {
                    var activeIndex = $("#menu-quick-search-results>li.quick-search-active").index();
                    if (activeIndex < $("#menu-quick-search-results>li").length) {
                        $("#menu-quick-search-results>li.quick-search-active").removeClass("quick-search-active");
                        $("#menu-quick-search-results>li:eq(" + (parseInt(activeIndex) + 1) + ")").addClass("quick-search-active");
                    }
                } else {
                    $("#menu-quick-search-results>li:first-child").addClass("quick-search-active");
                }
            } else if (e.which == 38) { // Up key
                if ($("#menu-quick-search-results>li.quick-search-active").length > 0) {
                    var activeIndex = $("#menu-quick-search-results>li.quick-search-active").index();
                    if (activeIndex > 0) {
                        $("#menu-quick-search-results>li.quick-search-active").removeClass("quick-search-active");
                        $("#menu-quick-search-results>li:eq(" + (parseInt(activeIndex) - 1) + ")").addClass("quick-search-active");
                    }
                } else {
                    $("#menu-quick-search-results>li:first-child").addClass("quick-search-active");
                }
            } else if (e.which == 13) { // Enter key
                if ($("#menu-quick-search-results>li.quick-search-active").length > 0) {
                    window.location.href = $("#menu-quick-search-results>li.quick-search-active>a").attr("href");
                }
            }
        }
    });

    // Resp
    $(".navbar-toggle").on("click", function () {
        $("nav.navbar").toggleClass("open");
        if ($("nav.navbar").hasClass("open")) {
            $(this).find("i").attr("class", "fa fa-times");
        } else {
            $(this).find("i").attr("class", "fa fa-bars");
        }
    });

    // Minify menu
    $("#minify-menu").on("click", function () {
        $(this).parents(".main-layout").find(".columns").toggleClass("minified");
        $(this).toggleClass("minified");

        $.setCookie("minified-menu", $(this).hasClass("minified"));

        // Toggle logo
        var logo = $(".logo img");
        var logoSrc = logo.attr("src");
        var logoSecondary = logo.data("mini");
        logo.attr("src", logoSecondary);
        logo.data("mini", logoSrc);
    });

    // Show ajax loading
    $.ajaxPrefilter(function (options, _, jqXHR) {
        if (options.url !== window.location.href) {
            $("#ajax-loading").addClass("active");
            jqXHR.complete(function (result) {
                $("#ajax-loading").removeClass("active");
            });
        }
    });

    // Close modal on ESC
    $(document).keyup(function (e) {
        if (e.key === "Escape") { // escape key maps to keycode `27`
            if ($('[data-dismiss="modal"]').length) {
                $('[data-dismiss="modal"]').each(function () {
                    $(this).click();
                });
            }
            $(".overlay").removeClass("active");
        }
    });
    $(document).on('shown.bs.modal', '#default_modal', function (e) {
        $(this).data('bs.modal').options.backdrop = "static";
    });

    $(document).on("click", '[data-toggle="dropdown"]', function () {
        if ($(this).parent().hasClass("open")) {
            $(this).siblings("ul").slideDown();
        } else {
            $(this).siblings("ul").slideUp();
        }
    });

    // Hide open dropdown
    $(document).on("click", function (e) {
        if ($(e.target).parents(".sp-listview-dropdown-wrapper").length === 0) {
            var openDropdownList = $(".sp-listview-dropdown-wrapper");
            openDropdownList.find("ul").slideUp();
        }
    });

    // Set fixed main actions
    var fixedMainAcltions = function () {
        var mainActions = $(".layout-right>.sp-main-actions-wrapper");
        var leftMainName = $(".layout-left>nav");
        if ($(window).scrollTop() > 60) {
            mainActions.addClass('sp-main-actions-fixed');
            leftMainName.addClass('fixed');
        } else {
            mainActions.removeClass('sp-main-actions-fixed');
            leftMainName.removeClass('fixed');
        }
    };
    $(window).scroll(function () {
        fixedMainAcltions();
    });

    // Display as admin toggle
    $(".toggle-as-admin").on("click", function (e) {
        e.preventDefault();
        $.ajax({
            url: "/users/display-admin",
            method: "POST",
            async: false,
            cache: false
        }).done(function (result) {
            if (result.error == false) {
                $.reloadPage();
            }
        });
    });

    // Add tab to URL
    var url = window.location.href.split('#');
    $('[data-toggle="tab"]').on("click", function () {
        var currentUrl = window.location.href;
        if (currentUrl) {
            currentUrl = currentUrl.split('?');
            currentUrl = currentUrl[0];

            currentUrl = currentUrl.split('#');
            currentUrl = currentUrl[0];
        }

        var mainUrl = url[0];
        var tabHref = $(this).attr("href")

        window.history.pushState('', '', mainUrl);
        sessionStorage.setItem("tab-" + $.hashCode(currentUrl), tabHref);
    });
    if (typeof url[1] != 'undefined' && $('[href="#' + url[1] + '"]').length > 0) {
        $('[href="#' + url[1] + '"]').click();
    }

    // Custom accordion
    $(document).on("click", ".accordion-header", function () {
        if ($(this).hasClass("open")) {
            $(this).removeClass("open")
            $($(this).data("target")).slideUp();
        } else {
            $(this).addClass("open")
            $($(this).data("target")).slideDown();
        }
    });

    // Email reply
    $(document).on("click", '[data-action="email_reply"]', function (e) {
        e.stopPropagation();
        e.preventDefault();
        $.showAjaxLoader();

        var button = $(this);

        $.ajax({
            url: button.data("url"),
            method: 'POST',
            data: {
                email_id: button.data("email-id"),
            },
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                var clone = $('#modal-container').clone(true, true).appendTo($('body'));
                clone.html(result.html);
                clone.find('.modal').modal('show');
                initializeCkeditor(clone.find("form"));
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : translations.selection_error,
                });
            }
        });
    });
    $(document).on("click", '[data-action="send_email_reply"]', function (e) {
        e.stopPropagation();
        e.preventDefault();
        $.showAjaxLoader();
        var button = $(this);
        $.ajax({
            url: button.data("url"),
            method: 'POST',
            data: button.parents('form').serialize(),
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                $.growl.notice({
                    title: result.title ? result.title : '',
                    message: result.message,
                });
                $.reloadPage();
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : translations.selection_error,
                });
            }
        });
    });

    // Datatable upload files
    $(document).on("click", 'table.datatables td .image-holder .add-new-item', function (e) {
        e.preventDefault();
        $(this).parent().find(".dropzone").click();
    });
    $(document).on("click", 'table.datatables td .image-holder .sp-image-select', function (e) {
        e.preventDefault();
        $.showAjaxLoader();
        var removeButton = $(this);
        $.post($(this).data("selected_url"), {
            'image_id': removeButton.data("doc_id"),
            'parent_id': removeButton.data("parent_id"),
            'entity_type_code': removeButton.data("entity_type_code"),
            'parent_attribute_id': removeButton.data("parent_attribute_id")
        }, function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                removeButton.parents(".sortable-items").find(".sp-image-select.hidden").removeClass("hidden");
                removeButton.addClass("hidden");
            } else {
                $.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    // Populate modal fields
    $('body').on('shown.bs.modal', '#default_modal', function (e) {
        var modal = $(this);
        if ($(this).data("parameters")) {
            var parameters = $(this).data("parameters").split(';');
            $.each(parameters, function (key, value) {
                var data = value.split('=');
                if (modal.find('[name="' + data[0] + '"]').length) {
                    if (modal.find('[name="' + data[0] + '"]').is("select")) {
                        modal.find('[name="' + data[0] + '"]').select2('destroy').append($('<option/>', {
                            value: data[1],
                            text: data[1],
                        })).val(data[1]).attr('readonly', 'readonly');
                    } else {
                        modal.find('[name="' + data[0] + '"]').val(data[1]).trigger("change").attr('readonly', 'readonly');
                    }
                }
            });
        }
    });
});
