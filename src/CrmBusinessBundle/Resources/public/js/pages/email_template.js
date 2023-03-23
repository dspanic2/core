jQuery(document).ready(function ($) {
    if ($('[data-type="email_template"]').length) {
        if ($('[data-type="email_template"] [name="id"]').val()) {
            $('[data-type="email_template"] [name="provided_entity_type_id"]').attr("disabled", "disabled");

            // Token selector
            var tokenButton = $("<button>").attr("class", "pull-right btn btn-inverse-alt btn-xs hidden-print").attr("id", "show-tokens").html('<i class="fas fa-question-circle"></i>');
            $("#back-to-top").after(tokenButton);
            $(document).on("click", "#show-tokens", function (e) {
                if ($(this).find("#attribute-list").length === 0) {
                    var entityTypeCode = $('[name="provided_entity_type_id"]').val();
                    $.showAjaxLoader();
                    $.ajax({
                        url: "/administrator/entity_type/get_attributes",
                        method: 'POST',
                        data: {
                            entity_type_code: entityTypeCode,
                        },
                        cache: false
                    }).done(function (result) {
                        $.hideAjaxLoader();
                        if (result.error == false) {
                            var tokenWrapper = $("<div>").attr("id", "attribute-list");
                            var searchField = $("<input>").attr("type", "text").attr("placeholder", "filter").attr("name", "token_filter");
                            tokenWrapper.append(searchField);

                            var tokenList = $("<ul>");
                            $.each(result.attributes, function (index, value) {
                                var tokenItem = $("<li>").text("{{ " + entityTypeCode + "." + value.code + " }}");

                                if (value.children.length != 0) {
                                    tokenItem.attr("class", "has-children");
                                    var tokenSecondaryList = $("<ul>");
                                    $.each(value.children, function (index, value) {
                                        var tokenSecondaryItem = $("<li>").text("{{ " + entityTypeCode + "." + value.code + " }}");
                                        tokenSecondaryList.append(tokenSecondaryItem);
                                    });
                                    tokenItem.append(tokenSecondaryList);
                                }

                                tokenList.append(tokenItem);
                            });
                            tokenWrapper.append(tokenList);
                            $("#show-tokens").append(tokenWrapper);
                        } else {
                            $.growl.error({
                                title: result.title ? result.title : '',
                                message: result.message ? result.message : translations.selection_error,
                            });
                        }
                    });
                } else {
                    $("#attribute-list").toggle();
                }
            });
            $(document).on("click", "#show-tokens ul>li:not(.has-children)", function (e) {
                e.stopImmediatePropagation();
                $.copyToClipboard($(this).text());
                $("#attribute-list").hide();
            });
            $(document).on("click", "#show-tokens ul>li.has-children", function (e) {
                e.stopImmediatePropagation();
                $(this).find("ul").slideToggle();
            });
            $(document).on("click", "#show-tokens input", function (e) {
                e.stopImmediatePropagation();
            });
            $(document).on("input", '#show-tokens input', function () {
                var val = $(this).val();
                if (val) {
                    $('#show-tokens li').addClass("hidden");
                    $('#show-tokens li').each(function () {
                        var value = $(this).text().toLowerCase();
                        console.log(value.indexOf(val.toLowerCase()));
                        if (value.indexOf(val.toLowerCase()) >= 0) {
                            $(this).removeClass("hidden");
                            $(this).parents("li").removeClass("hidden");
                        }
                    });
                } else {
                    $('#show-tokens li').removeClass("hidden");
                }
            });
        }
    }
});

