jQuery.extend({
    horizontalScrollPrevArrow: function () {
        /* jshint ignore:start */
        return '<svg width="11" height="21" viewBox="0 0 11 21" fill="none" xmlns="http://www.w3.org/2000/svg"><mask id="mask0_0_2452" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="11" height="21"><path fill-rule="evenodd" clip-rule="evenodd" d="M11 20.5H0L0 0.5H11L11 20.5Z" fill="white"/></mask><g mask="url(#mask0_0_2452)"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.898386 12.4987C2.74793 14.2788 4.59615 16.0602 6.4443 17.8415L8.74393 20.0579C8.79476 20.1069 8.85225 20.1625 8.91995 20.2168C9.4057 20.6066 10.1045 20.5926 10.5819 20.1837C10.8308 19.9704 10.9791 19.6571 10.9889 19.324C10.9995 18.9594 10.8468 18.6065 10.5587 18.3302C8.57881 16.4314 6.57243 14.4939 4.63213 12.62C4.00649 12.0159 3.38085 11.4117 2.75512 10.8078C2.70909 10.7634 2.66374 10.7182 2.6191 10.6722C2.58436 10.6363 2.54776 10.5879 2.55449 10.466C2.55751 10.4119 2.58552 10.3553 2.71969 10.2259C5.33917 7.69998 7.95742 5.17301 10.575 2.64536C10.8505 2.37931 11.0014 2.04476 11 1.70336C10.9986 1.38601 10.8644 1.08444 10.6219 0.85421C10.3728 0.617573 10.0639 0.5 9.74924 0.5C9.41205 0.5 9.06812 0.635073 8.78432 0.903355C8.70416 0.979156 8.62485 1.05592 8.54562 1.13262L6.48601 3.11819C4.62207 4.91485 2.75822 6.71152 0.892816 8.50676C0.326285 9.05189 0.000850677 9.71534 0 10.3268V10.3279C0.000850677 11.2198 0.28636 11.9095 0.898386 12.4987Z" fill="white"/></g></svg>'
        /* jshint ignore:end */
    },
    horizontalScrollNextArrow: function () {
        /* jshint ignore:start */
        return '<svg width="11" height="21" viewBox="0 0 11 21" fill="none" xmlns="http://www.w3.org/2000/svg"><mask id="mask0_0_2494" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="11" height="21"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 20.5H11L11 0.5H0L0 20.5Z" fill="white"/></mask><g mask="url(#mask0_0_2494)"><path fill-rule="evenodd" clip-rule="evenodd" d="M10.1016 12.4987C8.25207 14.2788 6.40385 16.0602 4.5557 17.8415L2.25607 20.0579C2.20524 20.1069 2.14775 20.1625 2.08005 20.2168C1.5943 20.6066 0.895456 20.5926 0.418137 20.1837C0.169226 19.9704 0.0209005 19.6571 0.011074 19.324C0.000473798 18.9594 0.15321 18.6065 0.441272 18.3302C2.42119 16.4314 4.42757 14.4939 6.36787 12.62C6.99351 12.0159 7.61915 11.4117 8.24488 10.8078C8.29091 10.7634 8.33626 10.7182 8.3809 10.6722C8.41564 10.6363 8.45224 10.5879 8.44551 10.466C8.44249 10.4119 8.41448 10.3553 8.28031 10.2259C5.66083 7.69998 3.04258 5.17301 0.425023 2.64536C0.149496 2.37931 -0.00138317 2.04476 9.55641e-06 1.70336C0.00140228 1.38601 0.135646 1.08444 0.378135 0.85421C0.627201 0.617573 0.936077 0.5 1.25076 0.5C1.58795 0.5 1.93188 0.635073 2.21568 0.903355C2.29584 0.979156 2.37515 1.05592 2.45438 1.13262L4.51399 3.11819C6.37793 4.91485 8.24178 6.71152 10.1072 8.50676C10.6737 9.05189 10.9991 9.71534 11 10.3268V10.3279C10.9991 11.2198 10.7136 11.9095 10.1016 12.4987Z" fill="white"/></g></svg>'
        /* jshint ignore:end */
    },
    preventCustomCheckbox: function (checkboxElement) {
        return checkboxElement.parents('#cconsent-modal').length > 0 || checkboxElement.parents('#CybotCookiebotDialog').length > 0;
    },
    removeOverlayClasses: function (target) {
        if (target === undefined) {
            $(".overlay.active").removeClass("active");
        } else {
            target.closest(".overlay.active").removeClass("active");
        }

        $("#search-autocomplete-overlay").removeClass("active");

        $(".overlay-toggle.active").removeClass("active");

        $(".local-overlay.active").removeClass("active");
        $(".local-overlay-toggle.active:not(.option)").removeClass("active");

        $("body").removeClass("disable-scroll");
        $(".main-menu .active").removeClass("active");

        $(".custom-dropdown.active").removeClass("active");

        $(".main-menu li.expanded").removeClass("expanded");

        $("#menu-display").removeClass("active");
        $(".header-second").removeClass("menu-open");

        $(document).trigger("stop-check-quote-status");
    },
    createCustomCheckboxes: function () {
        $('input[type="checkbox"]:not(".is-custom")').each(function () {
            if (!$.preventCustomCheckbox($(this))) {
                $(this).wrap("<span class='custom-checkbox is-checkbox'></span>");
                $(this).parent().append('<span class="custom-checkbox-icon"></span>');
                $(this).addClass("is-custom");
                if ($(this).is(":checked")) {
                    $(this).closest(".custom-checkbox").addClass("checked");
                }
                $(this).on("change", function () {
                    if ($(this).is(":checked")) {
                        $(this).closest(".custom-checkbox").addClass("checked");
                    } else {
                        $(this).closest(".custom-checkbox").removeClass("checked");
                    }
                });
            }
        });
        $('input[type="radio"]:not(".is-custom")').each(function () {
            if (!$.preventCustomCheckbox($(this))) {
                $(this).wrap("<span class='custom-checkbox is-radio'></span>");
                $(this).parent().append('<span class="custom-checkbox-icon"></span>');
                $(this).addClass("is-custom");
                if ($(this).is(":checked")) {
                    $(this).closest(".custom-checkbox").addClass("checked");
                }
                $(this).on("change", function () {
                    $('[name="' + $(this).attr("name") + '"]').each(function () {
                        if ($(this).closest(".custom-checkbox").hasClass("checked")) {
                            $(this).closest(".custom-checkbox").removeClass("checked");
                        }
                    });
                    if ($(this).is(":checked")) {
                        $(this).closest(".custom-checkbox").addClass("checked");
                    } else {
                        $(this).closest(".custom-checkbox").removeClass("checked");
                    }
                });
            }
        });
    },
    formIsValid: function (form) {
        form.find('.form-error-note').remove();
        form.find('.form-error').removeClass('form-error');
        form.find('.not-valid').removeClass('not-valid');
        var isValid = true;
        if (form.find('[type="password"]:not(.no-validate)').length) {
            form.find('[type="password"]:not(.no-validate)').each(function () {
                var elem = $(this);
                if (elem.val().length < 6) {
                    elem.addClass('not-valid');
                    elem.parent().append('<span class="form-error-note">' + translations.form_error_pass_length + '</span>');
                    isValid = false;
                }
            });
        }
        if (form.find('[name="oib"][required]').length) {
            form.find('[name="oib"][required]').each(function () {
                var elem = $(this);
                if (!$.oibIsValid(elem.val())) {
                    elem.addClass('not-valid');
                    elem.parent().append('<span class="form-error-note">' + translations.form_error_invalid_iob + '</span>');
                    isValid = false;
                }
            });
        }

        if (form.find('[pattern]').length) {
            form.find('[pattern]').each(function () {
                var elem = $(this);
                if (!(new RegExp(elem.attr("pattern"))).test(elem.val())) {
                    elem.addClass('not-valid');
                    isValid = false;
                }
            });
        }

        form.find('[required]').each(function () {
            var inputValid = true;

            // Skip if row optional
            if ($(this).parents('.optional').length > 0) {
                return;
            }
            // Skip if row hidden
            if ($(this).parents('.form-row.hidden').length > 0) {
                return;
            }

            if ($(this).is(':checkbox') || $(this).is(':radio')) {
                if ($(this).closest(".form-group").length === 0) {
                    if ($(this).closest("form").find("input:checked").length === 0) {
                        inputValid = false;
                    }
                } else {
                    if ($(this).closest(".form-group").find("input:checked").length === 0) {
                        inputValid = false;
                    }
                }
            } else if ($(this).is('select')) {
                if (!$(this).val()) {
                    inputValid = false;
                }
            } else if (!$(this).val()) {
                inputValid = false;
            }
            if ($(this).attr("pattern")) {
                if (!$(this).val().match(new RegExp($(this).attr("pattern")))) {
                    inputValid = false;
                }
            }
            if (!inputValid) {
                if ($(this).is(':checkbox')) {
                    $(this).parent().addClass("form-error");
                } else if ($(this).is(':radio')) {
                    $(this).parent().addClass("form-error");
                } else if ($(this).is(':hidden')) {
                    $(this).parent().addClass("form-error");
                } else if ($(this).is(':file')) {
                    $(this).parent().addClass("form-error");
                } else {
                    $(this).addClass("form-error");
                }
                isValid = false;
            }
        });

        return isValid;
    },
    setCookie: function (cname, cvalue, exdays) {
        if (typeof exdays == "undefined") {
            exdays = 30;
        }
        var d = new Date();
        d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
        var expires = "expires=" + d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    },
    getCookie: function (cname) {
        var name = cname + "=";
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length);
            }
        }
        return "";
    },
    initRecaptcha: function (callback) {
        if (typeof grecaptcha != "undefined" && $("body").data("recaptcha-site-key")) {
            grecaptcha.ready(function () {
                grecaptcha.execute($("body").data("recaptcha-site-key"), {action: 'contact'}).then(function (token) {
                    $('[name="recaptcha_response"]:not(".validated")').each(function () {
                        $(this).val(token);
                        $(this).addClass("validated");
                    });

                    if (callback !== undefined) {
                        callback();
                    }
                });
            });
        } else {
            if (callback !== undefined) {
                callback();
            }
        }
    },
    reloadPage: function () {
        location.reload();
    },
    scrollToTop: function (element, offset) {
        if (offset == undefined) {
            offset = 50;
        }
        if (typeof element != "undefined" && element.length) {
            $("html, body").animate({
                scrollTop: element.offset().top - offset
            }, "slow");
        } else {
            $("html, body").animate({
                scrollTop: 0
            }, "slow");
        }
    },
    setUrlParameters: function (parametersArray) {
        var url = window.location.href.split('?')[0];
        var params = {};
        $.each(parametersArray, function (key, value) {
            params[key] = value;
        });
        window.history.pushState('', '', url + '?' + $.param(params));
    },
    setUrlParameter: function (key, value) {
        var url = window.location.href.split('?')[0];
        var params = $.getAllUrlParams();
        params[key] = value;
        window.history.pushState('', '', url + '?' + $.param(params));
    },
    getUrlParam: function (name) {
        var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
        return (results && results[1]) || 0;
    },
    getAllUrlParams: function () {
        var params = {};
        var parser = document.createElement('a');
        parser.href = window.location.href;
        var query = parser.search.substring(1);
        var vars = query.split('&');
        vars = vars.filter(Boolean);
        if (vars.length > 0) {
            for (var i = 0; i < vars.length; i++) {
                var pair = vars[i].split('=');
                params[pair[0]] = decodeURIComponent(pair[1].replace(/\+/g, ' '));
            }
        }
        return params;
    },
    removeUrlParam: function (key) {
        var url = window.location.href.split('?')[0];
        var params = $.getAllUrlParams();
        if (params[key] !== undefined) {
            delete params[key];
        }
        window.history.pushState('', '', url + '?' + $.param(params));
    },
    removeUrlParams: function () {
        var url = window.location.href.split('?')[0];
        window.history.pushState('', '', url);
    },
    debounce: function (func, wait, immediate) {
        var timeout;
        return function () {
            var context = this, args = arguments;
            var later = function () {
                timeout = null;
                if (!immediate) {
                    func.apply(context, args);
                }
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) {
                func.apply(context, args);
            }
        };
    },
    initializeDatetimepicker: function (elem) {
        // https://air-datepicker.com/docs
        $('.datetimepicker:not([readonly="readonly"])').each(function () {
            var element = $(this);
            var options = {
                timepicker: element.data("timepicker") != false,
                autoClose: true,
                language: {
                    days: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                    daysShort: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                    daysMin: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                    months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                    monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    today: 'Today',
                    clear: 'Clear',
                    dateFormat: 'dd.mm.yyyy',
                    timeFormat: 'hh:ii',
                    firstDay: 1
                },
                onSelect: function onSelect(fd, date) {
                    element.trigger("change");
                }
            };
            element.datepicker(options);
        });
    },
    initializeLookup: function (elem) {
        if (!elem.hasClass("select2-hidden-accessible")) {
            var form = elem.closest('form');
            var searchedFor = '';
            var singleclick = true;
            var attr = elem.attr('readonly');
            if (typeof attr !== typeof undefined && attr !== false) {
                return false;
            }
            if (!elem.parent().hasClass('has-select2')) {
                elem.parent().addClass('has-select2');
            }
            var dependentField = form.find('[name="' + elem.data('dependent-field') + '"]');
            if (dependentField.val()) {
                dependentField.prop('disabled', false);
            }
            elem.on('change', function () {
                if (dependentField.length) {
                    if (dependentField.length && dependentField.data('type') == 'lookup') {
                        dependentField.val('').trigger('change');
                        if (elem.val()) {
                            dependentField.prop('disabled', false);
                        } else {
                            dependentField.prop('disabled', true);
                        }
                    }
                }
            });
            var options = {};
            options.minimumInputLength = elem.data('min-len');
            options.width = '100%';
            options.dropdownParent = elem.parent();

            if (elem.data('search-url')) {
                options.ajax = {
                    url: elem.data('search-url'),
                    dataType: 'json',
                    quietMillis: 100,
                    data: function (term) {
                        searchedFor = term.term;
                        var ret = {};
                        ret['q'] = term;
                        ret['id'] = elem.data('id');
                        ret['template'] = elem.data('template');

                        var formData = $.getSerializedFormData(form);
                        if (elem.parents(".delivery-address").length > 0) {
                            formData = formData + '&is_shipping=1';
                        }

                        ret['form'] = formData;
                        return ret;
                    },
                    processResults: function (data, params) {
                        singleclick = true;
                        if (data.error == false) {
                            var res = {
                                results: $.map(data.ret, function (item) {
                                    return {
                                        id: item.id,
                                        is_delivery: item.is_delivery,
                                        text: item.html,
                                        title: $($.parseHTML(item.html)).text(),
                                        description: item.description != null ? item.description : ''
                                    };
                                })
                            };

                            return res;
                        } else {
                            $.growl.error({
                                title: data.title ? data.title : translations.no_results_fund,
                                message: data.message ? data.message : '',
                            });
                        }
                    },
                    cache: true
                };
            }

            options.language = {
                inputTooShort: function () {
                    return translations.select2_too_short;
                },
                noResults: function () {
                    return translations.no_results_fund;
                },
                searching: function () {
                    return translations.select2_searching;
                },
                errorLoading: function () {
                    return translations.select2_error_loading;
                }
            };
            options.templateResult = function select2FormatResult(item) {
                return "<li data-is_delivery='" + item.is_delivery + "' data-description='" + item.description + "' title='" + $($.parseHTML(item.html)).text() + "' data-id='" + item.id + "' class='sp-select-2-result'>" + item.text + "<li>";
            };
            options.escapeMarkup = function (m) {
                return m;
            };
            elem.select2(options);

            elem.on('select2:select', function (e) {
                var data = e.params.data;
                if (data.description != '') {
                    $(this).parent().find('.description').html(data.description);
                }
                if (data.is_delivery != '') {
                    elem.data("is_delivery", data.is_delivery);
                }
                var element = $(this)[0];
                element.setCustomValidity('');

                elem.select2("close");
            });

            $(document).on("click", function () {
                if ($('.select2-container--open .select2-search__field').length) {
                    document.querySelector('.select2-container--open .select2-search__field').focus();
                }
            });
        }
        return true;
    },
    initializeCkeditor: function (elem) {
        var items = [
            'Quote',
            'Paste',
            'Source',
            'Print',
            'Find',
            'Bold',
            'Italic',
            'Underline',
            'NumberedList',
            'BulletedList',
            'JustifyLeft',
            'JustifyCenter',
            'JustifyRight',
            'JustifyBlock',
            'Image',
            'Shape Upload',
            'Link',
            'Youtube',
            'Iframe',
            'Table',
            'HorizontalRule',
            'Styles',
            'Format',
            'Font',
            'FontSize',
            'TextColor',
            'BGColor',
            'Maximize',
            'Print - add page break'
        ];

        if (CKEDITOR.instances[elem.attr('name')] !== undefined) {
            CKEDITOR.instances[elem.attr('name')].destroy();
        }

        CKEDITOR.replace(elem.attr('name'), {
            allowedContent: true,
            removeFormatAttributes: '',
            height: 300,
            toolbar: [
                {
                    name: 'document',
                    items: items
                }
            ],
            on: {
                instanceReady: function (evt) {
                    evt.editor.setData(elem.val());
                    evt.editor.on('change', function () {
                        elem.val(evt.editor.getData())
                    });
                    if (elem.data("expand-on-focus") == true) {
                        evt.editor.on('focus', function () {
                            evt.editor.resize('100%', '200', true);
                        });
                        evt.editor.on('blur', function () {
                            evt.editor.resize('100%', '100', true);
                        });
                    }
                }
            }
        });
    },
    productRequestUserData: function (pid) {
        var favoriteFormOverlay = $('#favorite-form-overlay');
        var ajaxLoadingOverlay = $('#ajax-loading');

        favoriteFormOverlay.addClass('active');
        ajaxLoadingOverlay.removeClass('active');
        favoriteFormOverlay.find('[name="product_id"]').val(pid);
    },
    postFavoriteHandler: function (result, addElement) {
        if (addElement === undefined && result.product_id !== undefined) {
            addElement = $('.add-to-favorite[data-pid="' + result.product_id + '"]');
        }
        var isFavorite = result.is_favorite == 1;
        var favoritesAmount = $('header .favorites .amount');

        if (favoritesAmount.length) {
            favoritesAmount.show();
            favoritesAmount.html(result.count);
        }

        addElement.toggleClass('active');

        if (addElement.data("active-title")) {
            var secondaryTitle = addElement.data("active-title");
            var title = addElement.attr("title");
            addElement.attr("title", secondaryTitle);
            addElement.data("active-title", title);
        }

        if (result.message !== undefined) {
            var message = result.message ? result.message : '';
            message += '<br>';
            message += '<a href="' + translations.favorites_url + '" class="button btn-type-1">' + translations.favorites + '</a>';
            message += '<button class="growl-button button btn-type-2 favorite-success">' + translations.continue + '</button>';
            $.growl.notice({
                title: result.title ? result.title : '',
                message: message,
            });
        }

        // Set active favorites
        if (result.favorited != undefined) {
            $("body").data("favorites", result.favorited);
            $.setActiveFavorites();
        }

        if (!isFavorite && $('.main-content.delete-on-remove').length) {
            addElement.closest(".product-display-grid").remove();
        }
    },
    productAddToFavorites: function (addElement) {
        $.showAjaxLoader();

        var pid = addElement.data('pid');
        var data = {};
        var isFavorite = addElement.hasClass('active') ? 0 : 1;

        data.product_id = pid;
        data.is_favorite = isFavorite;

        // Check for configurable
        var configurable = $(".configuration")
        if (configurable.length) {
            var configurableItems = [];
            configurable.find(".option.active").each(function () {
                if ($(this).data("option-id")) {
                    configurableItems.push($(this).data("option-id"));
                }
                if ($(this).data("frame")) {
                    configurableItems.push($(this).data("frame"));
                }
                if ($(this).data("lens")) {
                    configurableItems.push($(this).data("lens"));
                }
            });
            data.configurable = JSON.stringify(configurableItems);
        }

        $.ajax({
            url: '/api/favorite',
            method: 'POST',
            data: data,
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                if (result.request_data && result.request_data == true) {
                    if (isFavorite) {
                        $.productRequestUserData(pid);
                    }
                } else {
                    $.postFavoriteHandler(result, addElement);
                }
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    },
    productAddToCompareViaIcon: function (addElement) {
        $.productAddToCompare(addElement.data('pid'), true, addElement.hasClass('active') ? 0 : 1, addElement);
    },
    productAddToCompare: function (pid, async, isCompare, addElement) {
        if (typeof async == "undefined") {
            async = true;
        }
        if (typeof isCompare == "undefined") {
            isCompare = 0;
        }
        if (typeof addElement == "undefined") {
            addElement = null;
        }
        $.showAjaxLoader();

        var options = {};
        options.url = '/compare/toggle';
        options.method = 'POST';
        options.data = {
            'is_compare': isCompare,
            'product_id': pid
        };
        options.cache = false;
        if (!async) {
            options.async = async;
        }

        $.ajax(options).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                if (addElement != null) {
                    addElement.toggleClass('active');
                }

                var comparisonAmount = $('.comparison .amount');
                if (result.count && comparisonAmount.length) {
                    comparisonAmount.show();
                    comparisonAmount.html(result.count);
                } else {
                    comparisonAmount.hide();
                }

                var message = result.message ? result.message : '';
                message += '<br>';
                message += '<a href="' + translations.comparison_url + '" class="button btn-type-1">' + translations.comparison + '</a>';
                message += '<button class="growl-button button btn-type-2">' + translations.continue + '</button>';
                $.growl.notice({
                    title: result.title ? result.title : '',
                    message: message,
                });

                if (isCompare == 1 && $(".sp-block-outer-compare_products").length == 1) {
                    window.location.reload();
                }
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    },
    productRemoveFromCompare: function (el) {
        $.showAjaxLoader();

        var pid = el.data('pid');

        $.productAddToCompare(pid, false, 0, el);
    },
    openInquiryForm: function (pid, title) {
        if (typeof title == "undefined") {
            title = "";
        }
        var inquiryFormOverlay = $('#inquiry-form-overlay');

        inquiryFormOverlay.addClass('active');
        inquiryFormOverlay.find('[name="product_id"]').val(pid);
        inquiryFormOverlay.find('.title').text(title);
    },
    productRemindMeOnAvailable: function (pid, title) {
        if (typeof title == "undefined") {
            title = "";
        }
        $.showAjaxLoader();

        var data = {};
        data.product_id = pid;

        $.ajax({
            url: '/api/remind_me',
            method: 'POST',
            data: data,
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                if (result.request_data && result.request_data == true) {
                    $.openAvailabilityReminderForm(pid, title);
                } else {
                    $.growl.notice({
                        title: result.title ? result.title : '',
                        message: result.message,
                    });
                }
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    },
    openAvailabilityReminderForm: function (pid, title) {
        if (typeof title == "undefined") {
            title = "";
        }
        var formOverlay = $('#availability-reminder-form-overlay');

        formOverlay.addClass('active');
        formOverlay.find('[name="product_id"]').val(pid);
        formOverlay.find('.title').text(title);
    },
    openWarehouseOrderForm: function (pid, wid) {
        var inquiryFormOverlay = $('#warehouse-inquiry-form-overlay');

        inquiryFormOverlay.addClass('active');
        inquiryFormOverlay.find('[name="product_id"]').val(pid);
        inquiryFormOverlay.find('[name="warehouse_id"]').val(wid);
    },
    openAppointmentForm: function () {
        var formOverlay = $('#appointment-form-overlay');

        formOverlay.addClass('active');
    },
    loadLazyImages: function () {
        if ($('.b-lazy:not(.b-loaded)').length) {
            var bLazy = new Blazy({
                offset: 100 // Loads 100px before they're visible
            });
        }
    },
    oibIsValid: function (oib) {
        if (oib.replace(/\s+/g, '').length !== 11 || !/^\d+$/.test(oib)) {
            return false;
        }
        return true;
    },
    emailIsValid: function (email) {
        var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
        if (!email.match(re)) {
            return false;
        }
        return true;
    },
    showAjaxLoader: function () {
        $('#ajax-loading').addClass("active");
    },
    hideAjaxLoader: function () {
        $('#ajax-loading').removeClass("active");
    },
    showLoginModal: function () {
        $("#login-form-overlay").addClass("active");
    },
    isOverlay: function (target) {
        if ($("#page-builder").length) {
            return true;
        }
        if (target.hasClass("search-autocomplete") || target.parents(".search-autocomplete").length) {
            return true;
        }
        if (target.hasClass("overlay-close") || target.parents(".overlay-close").length) {
            return false;
        }
        if (target.hasClass("overlay-toggle") || target.parents(".overlay-toggle").length) {
            return true;
        }
        if (target.hasClass("local-overlay") || target.parents(".local-overlay").length) {
            return true;
        }
        if (target.hasClass("local-overlay-toggle") || target.parents(".local-overlay-toggle").length) {
            return true;
        }
        if (target.parents(".overlay").length) {
            return true;
        }
        if (target.hasClass("dropdown-option")) {
            return true;
        }
        if (target.hasClass("button")) {
            return true;
        }
        if (target.hasClass("dropdown-open") || target.parents(".custom-dropdown").length) {
            return true;
        }
        if (target.hasClass("menu-toggle") || target.parents(".menu-toggle").length) {
            return true;
        }
        if (target.hasClass("responsive-submenu-toggle") || target.parents(".responsive-submenu-toggle").length || target.parents(".main-menu").length) {
            return true;
        }
        if (target.hasClass("add-to-compare") || target.parents(".add-to-compare").length) {
            return true;
        }
        if (target.hasClass("add-to-favorite") || target.parents(".add-to-favorite").length) {
            return true;
        }
        if (target.hasClass("ui-slider-handle") || target.parents(".ui-slider-handle").length) {
            return true;
        }
        if (target.parents("#menu-display").length) {
            return true;
        }
        if ((target.attr("class") != undefined && target.attr("class").indexOf("datepicker") >= 0) || (target.parent().attr("class") != undefined && target.parent().attr("class").indexOf("datepicker") >= 0)) {
            // ovo bi trebalo samo provjeriti sa target.parents(".datepicker").length ali ne radi...
            return true;
        }
        return false;
    },
    disableOverlays: function (target) {
        if (target === undefined) {
            // e.g Escape key
            $.removeOverlayClasses();
        } else if (target.hasClass("overlay")) {
            // e.g click on overlay itself
            $.removeOverlayClasses();
        } else if (!$.isOverlay(target)) {
            $.removeOverlayClasses();
        }
    },
    isInternetExplorer: function () {
        var ua = window.navigator.userAgent;
        var msie = ua.indexOf("MSIE ");
        if (msie > 0 || !!navigator.userAgent.match(/Trident.*rv\:11\./))  // If Internet Explorer, return version number
        {
            return true;
        }
        return false;
    },
    getSerializedFormData: function (form) {
        var indexedArray = {};

        $.map(form.serializeArray(), function (val) {
            var formElement = form.find('[name="' + val.name + '"]');

            if (formElement.parents(".optional").length > 0) {
                var optionalParent = formElement.parents(".optional");
                if ($.contains(form, optionalParent)) {
                    return false;
                }
            }

            indexedArray[val['name']] = val['value'];
        });

        return $.param(indexedArray);
    },
    checkIfScrollable: function (horizontalScrollGridPadding) {
        if (horizontalScrollGridPadding == undefined) {
            horizontalScrollGridPadding = 80;
        }
        $(".horizontal-scroll").each(function () {
            var grid = $(this).find(".items-grid:not(.empty)");
            if (grid.length && grid[0].scrollWidth > (grid.width() + horizontalScrollGridPadding)) {
                $(this).addClass("is-scrollable");
            } else {
                $(this).removeClass("is-scrollable");
            }
        });
    },
    initializeHorizontalScroll: function () {
        $(".horizontal-scroll:not(.initialized) .items-grid:not(.empty)").each(function () {
            const horizontalSlider = $(this);
            horizontalSlider.closest(".horizontal-scroll").addClass("initialized");

            let isDown = false;
            let isScrolling = false;
            let startX;
            let scrollLeft;

            horizontalSlider.on('mousedown', function (e) {
                isDown = true;
                isScrolling = false;
                startX = e.pageX - horizontalSlider.offset().left;
                scrollLeft = horizontalSlider.scrollLeft();
            });
            horizontalSlider.on('mouseleave', function () {
                isDown = false;
                horizontalSlider.removeClass('active');
            });
            horizontalSlider.on('mouseup', function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                if (horizontalSlider.hasClass('active')) {
                    isDown = false;
                    horizontalSlider.removeClass('active');
                }
            });
            horizontalSlider.on('mousemove', function (e) {
                e.preventDefault();
                if (!isDown) {
                    return;
                }
                const x = e.pageX - horizontalSlider.offset().left;
                const walk = (x - startX); //scroll-fast
                if (scrollLeft != (scrollLeft - walk)) {
                    horizontalSlider.addClass('active');
                    isScrolling = true;
                }
                horizontalSlider.scrollLeft(scrollLeft - walk);
            });

            // Prevent click while scrolling
            horizontalSlider.find("a").on("mouseup", function (e) {
                e.preventDefault();
            });
            horizontalSlider.find("a").on("click", function (e) {
                if (isScrolling) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    isDown = false;
                    horizontalSlider.removeClass('active');
                }
            });

            var prev = $('<span class="horizontal-scroll-prev">' + $.horizontalScrollPrevArrow() + '</span>');
            var next = $('<span class="horizontal-scroll-next">' + $.horizontalScrollNextArrow() + '</span>');
            horizontalSlider.before(prev).after(next);
        });
        $(document).on("click", ".horizontal-scroll-prev", function (e) {
            e.preventDefault();
            $(this).parents(".horizontal-scroll").find(".items-grid").animate({
                scrollLeft: "-=300px"
            }, "slow");
        });
        $(document).on("click", ".horizontal-scroll-next", function (e) {
            e.preventDefault();
            $(this).parents(".horizontal-scroll").find(".items-grid").animate({
                scrollLeft: "+=300px"
            }, "slow");
        });
    },
    setActiveFavorites: function () {
        if ($("body").data("favorites")) {
            $('.add-to-favorite.active').removeClass("active");
            var favorites = ($("body").data("favorites") + '').split(',');
            $.each(favorites, function (key, id) {
                $('.add-to-favorite[data-pid="' + id + '"]').addClass("active");
            });
        }
    },
    findMod: function (a, b) {
        a = a.replace(/,/g, '.');
        b = b.replace(/,/g, '.');

        let mod;
        // Handling negative values
        if (a < 0) {
            mod = -a;
        } else {
            mod = a;
        }

        if (b < 0) {
            b = -b;
        }
        // Finding mod by
        // repeated subtraction
        while (mod >= b) {
            mod = (mod - b).toFixed(3);
        }
        // Sign of result typically
        // depends on sign of a.
        if (a < 0) {
            return -mod;
        }

        return mod;
    },
});

jQuery(document).ready(function ($) {
    $.fn.serializeAssoc = function () {
        var data = {};
        $.each(this.serializeArray(), function (key, obj) {
            var a = obj.name.match(/(.*?)\[(.*?)\]/);
            if (a !== null) {
                var subName = a[1];
                var subKey = a[2];

                if (!data[subName]) {
                    data[subName] = [];
                }

                if (!subKey.length) {
                    subKey = data[subName].length;
                }

                if (data[subName][subKey]) {
                    if ($.isArray(data[subName][subKey])) {
                        data[subName][subKey].push(obj.value);
                    } else {
                        data[subName][subKey] = [];
                        data[subName][subKey].push(obj.value);
                    }
                } else {
                    data[subName][subKey] = obj.value;
                }
            } else {
                if (data[obj.name]) {
                    if ($.isArray(data[obj.name])) {
                        data[obj.name].push(obj.value);
                    } else {
                        data[obj.name] = [];
                        data[obj.name].push(obj.value);
                    }
                } else {
                    data[obj.name] = obj.value;
                }
            }
        });
        return data;
    };

    $.fn.domChanged = function (callback) {
        MutationObserver = window.MutationObserver || window.WebKitMutationObserver;

        var observer = new MutationObserver(function (mutations, observer) {
            callback();
        });
        observer.observe(this[0], {
            childList: true,
            characterData: true,
            attributes: true,
            subtree: true
        });
    };
});