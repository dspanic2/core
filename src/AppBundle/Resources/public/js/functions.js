jQuery(document).ready(function ($) {
    // Custom jQuery functions.
    $.extend({
        reloadPage: function () {
            location.reload();
        },
        toggleAjaxOverlay: function () {
            $("#ajax-loading").toggleClass("active");
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
        setUrlParameter: function (key, value) {
            var url = window.location.href.split('?')[0];
            var params = {};
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
        emailIsValid: function (email) {
            var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
            if (!email.match(re)) {
                return false;
            }
            return true;
        },
        hashCode: function (str) {
            var hash = 0;
            if (str.length == 0) return hash;
            for (i = 0; i < str.length; i++) {
                var char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convert to 32bit integer
            }
            return hash;
        },
        copyToClipboard: function (text) {
            var dummy = document.createElement("textarea");
            // to avoid breaking orgain page when copying more words
            // cant copy when adding below this code
            // dummy.style.display = 'none'
            document.body.appendChild(dummy);
            //Be careful if you use texarea. setAttribute('value', value), which works with "input" does not work with "textarea". – Eduard
            dummy.value = text;
            dummy.select();
            document.execCommand("copy");
            document.body.removeChild(dummy);

            $.growl.notice({
                title: "",
                message: translations.added_to_clipboard
            });
        },
        showAjaxLoader: function () {
            $('#ajax-loading').addClass("active");
        },
        hideAjaxLoader: function () {
            $('#ajax-loading').removeClass("active");
        },
        redirectToUrl: function (url) {
            window.location.replace(url);
        },
        ckeditorConvertPastedImages: function (ckValue) {
            var regex = new RegExp(/src="data:image(.+?)"/g);

            while ((src = regex.exec(ckValue)) != null) {
                jQuery.ajax({
                    url: '/ckeditor/uploader/save',
                    type: 'POST',
                    data: {"base64image": src[1]},
                    async: false,
                }).done(function (result) {
                    if (result.error == false) {
                        ckValue = ckValue.replace(src[0], 'src="' + result.src + '"');
                    } else {
                        jQuery.growl.error({
                            title: translations.error_message,
                            message: result.message
                        });
                    }
                });
            }

            return ckValue;
        }
    });
});
