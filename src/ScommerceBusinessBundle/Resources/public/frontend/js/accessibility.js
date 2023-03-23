jQuery(document).ready(function ($) {
    if ($("#accessibility-tools")) {
        $("#accessibility-tools .accessibility-tools-toggle").on("click", function () {
            $("#accessibility-tools .accessibility-tools").toggleClass("active");
        });

        var cookie = $.getCookie('font-size');
        if (cookie) {
            $('html').css('font-size', cookie + 'px');
        } else {
            var size = parseInt($('html').css('font-size'));
            $.setCookie('font-size', size, 1);
        }
        $("#accessibility-tools .increase-font").on("click", function () {
            var size = parseInt($('html').css('font-size')) + 2;
            if (size <= 24) {
                $('html').css('font-size', size + 'px');
                $.setCookie('font-size', size, 1);
            }
        });
        $("#accessibility-tools .decrease-font").on("click", function () {
            var size = parseInt($('html').css('font-size')) - 2;
            if (size >= 10) {
                $('html').css('font-size', size + 'px');
                $.setCookie('font-size', size, 1);
            }
        });
        $("#accessibility-tools .reset-font").on("click", function () {
            $('html').css('font-size', '16px');
            $.setCookie('font-size', 16, 1);
        });
        $("#accessibility-tools .readable-font").on("click", function () {
            $('body').toggleClass("readable-font");
            if ($('html').hasClass("readable-font")) {
                $.setCookie('readableFont', "true", 1);
            } else {
                $.setCookie('readableFont', "false", 1);
            }
        });
        $("#accessibility-tools .underline-links").on("click", function () {
            $('body').toggleClass("underline-links");
            if ($('body').hasClass("underline-links")) {
                $.setCookie('underlineLinks', "true", 1);
            } else {
                $.setCookie('underlineLinks', "false", 1);
            }
        });
        $("#accessibility-tools .contrast-toggle").on("click", function () {
            $('body').toggleClass("contrast-toggle");
            if ($('body').hasClass("contrast-toggle")) {
                $.setCookie('contrastToggle', "true", 1);
            } else {
                $.setCookie('contrastToggle', "false", 1);
            }
        });
    }
});