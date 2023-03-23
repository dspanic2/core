jQuery(document).ready(function () {

    var lastMonthRange = function () {
        var today = new Date();
        var dd = String(today.getDate()).padStart(2, '0');
        var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
        var yyyy = today.getFullYear();

        var lastMonth = new Date();
        lastMonth.setMonth(lastMonth.getMonth() - 1);
        var lastdd = String(lastMonth.getDate()).padStart(2, '0');
        var lastmm = String(lastMonth.getMonth() + 1).padStart(2, '0'); //January is 0!
        var lastyyyy = lastMonth.getFullYear();

        var defaultRange = lastdd + '/' + lastmm + '/' + lastyyyy + " - " + dd + '/' + mm + '/' + yyyy;

        return defaultRange;
    };
    var todayRange = function () {
        var today = new Date();
        var dd = String(today.getDate()).padStart(2, '0');
        var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
        var yyyy = today.getFullYear();

        var defaultRange = dd + '/' + mm + '/' + yyyy + " - " + dd + '/' + mm + '/' + yyyy;

        return defaultRange;
    };
    var yestardayRange = function () {
        // var today = new Date();
        var yesterday = new Date(new Date().setDate(new Date().getDate()-1));
        var dd = String(yesterday.getDate()).padStart(2, '0');
        var mm = String(yesterday.getMonth() + 1).padStart(2, '0'); //January is 0!
        var yyyy = yesterday.getFullYear();

        var defaultRange = dd + '/' + mm + '/' + yyyy + " - " + dd + '/' + mm + '/' + yyyy;

        return defaultRange;
    };
    var last3MonthRange = function () {
        var today = new Date();
        var dd = String(today.getDate()).padStart(2, '0');
        var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
        var yyyy = today.getFullYear();

        var lastMonth = new Date();
        lastMonth.setMonth(lastMonth.getMonth() - 3);
        var lastdd = String(lastMonth.getDate()).padStart(2, '0');
        var lastmm = String(lastMonth.getMonth() + 1).padStart(2, '0'); //January is 0!
        var lastyyyy = lastMonth.getFullYear();

        var defaultRange = lastdd + '/' + lastmm + '/' + lastyyyy + " - " + dd + '/' + mm + '/' + yyyy;

        return defaultRange;
    };
    var last6MonthRange = function () {
        var today = new Date();
        var dd = String(today.getDate()).padStart(2, '0');
        var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
        var yyyy = today.getFullYear();

        var lastMonth = new Date();
        lastMonth.setMonth(lastMonth.getMonth() - 6);
        var lastdd = String(lastMonth.getDate()).padStart(2, '0');
        var lastmm = String(lastMonth.getMonth() + 1).padStart(2, '0'); //January is 0!
        var lastyyyy = lastMonth.getFullYear();

        var defaultRange = lastdd + '/' + lastmm + '/' + lastyyyy + " - " + dd + '/' + mm + '/' + yyyy;

        return defaultRange;
    };
    var last12MonthRange = function () {
        var today = new Date();
        var dd = String(today.getDate()).padStart(2, '0');
        var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
        var yyyy = today.getFullYear();

        var lastMonth = new Date();
        lastMonth.setMonth(lastMonth.getMonth() - 12);
        var lastdd = String(lastMonth.getDate()).padStart(2, '0');
        var lastmm = String(lastMonth.getMonth() + 1).padStart(2, '0'); //January is 0!
        var lastyyyy = lastMonth.getFullYear();

        var defaultRange = lastdd + '/' + lastmm + '/' + lastyyyy + " - " + dd + '/' + mm + '/' + yyyy;

        return defaultRange;
    };

    // Check for default values
    if (!getCookie("report_range")) {
        setCookie("report_range", lastMonthRange());
    }

    // ADMIN

    // FRONTEND
    var dateRangeBlock = jQuery(".report-filter-block");
    if (dateRangeBlock.length) {

        var stickyTop = dateRangeBlock.offset().top;

        $(window).scroll(function () {
            if ($(this).scrollTop() > dateRangeBlock.height() && !dateRangeBlock.hasClass("fixed")) {
                dateRangeBlock.addClass("fixed");
            }
            if ($(this).scrollTop() < dateRangeBlock.height() && dateRangeBlock.hasClass("fixed")) {
                dateRangeBlock.removeClass("fixed");
            }
        });

        var dateRangeDropdown = dateRangeBlock.find(".date-range-dropdown");
        if (!dateRangeDropdown.data("range")) {
            dateRangeDropdown.data("range", lastMonthRange());
        }

        var dateRangePicker = dateRangeBlock.find('[data-type="daterange"]');
        dateRangePicker.on("focus", function () {
            initializeDaterange(dateRangePicker, false, true);
        });

        dateRangeBlock.find('[name="report_store"]').on("change", function () {
            var selectedStore = jQuery(this).val();

            setCookies(dateRangePicker.val(), selectedStore);
        });

        // dateRangeBlock.find('input:not([name="report_date_range"]),select').on("change", function () {
        //     setCookies(dateRangePicker.val(), dateRangeBlock.find('[name="report_store"]').val());
        // });

        var clearButton = dateRangeBlock.find('[data-action="clear-date"]');
        clearButton.on("click", function () {
            $("#ajax-loading").addClass('active');
            setCookie("report_range", "");
            location.reload();
        });

        var applyButton = dateRangeBlock.find(".apply-filter");
        applyButton.on("click", function () {
            setCookies(dateRangePicker.val(), dateRangeBlock.find('[name="report_store"]').val());
        });

        var setCookies = function (range, store) {
            $("#ajax-loading").addClass('active');
            setCookie("report_range", range);
            setCookie("report_store", store);
            location.reload();
        }

        var resetButton = dateRangeBlock.find(".apply-show-all");
        resetButton.on("click", function () {
            setCookies(todayRange(), dateRangeBlock.find('[name="report_store"] option.default-stores').attr("value"));
        });

        $(document).on("click", ".date-range-values .range-today", function () {
            setCookies(todayRange(), dateRangeBlock.find('[name="report_store"]').val());
        });
        $(document).on("click", ".date-range-values .range-yesterday", function () {
            setCookies(yestardayRange(), dateRangeBlock.find('[name="report_store"]').val());
        });
        $(document).on("click", ".date-range-values .range-last-month", function () {
            setCookies(lastMonthRange(), dateRangeBlock.find('[name="report_store"]').val());
        });
        $(document).on("click", ".date-range-values .range-last-3-months", function () {
            setCookies(last3MonthRange(), dateRangeBlock.find('[name="report_store"]').val());
        });
        $(document).on("click", ".date-range-values .range-last-6-months", function () {
            setCookies(last6MonthRange(), dateRangeBlock.find('[name="report_store"]').val());
        });
        $(document).on("click", ".date-range-values .range-last-12-months", function () {
            setCookies(last12MonthRange(), dateRangeBlock.find('[name="report_store"]').val());
        });
        $(document).on("click", ".date-range-values .range-custom .drp-buttons .cancelBtn", function () {
            setCookies(lastMonthRange(), dateRangeBlock.find('[name="report_store"]').val());
        });
        $(document).on("click", ".date-range-values .range-custom .drp-buttons .applyBtn", function () {
            setCookies(dateRangePicker.val(), dateRangeBlock.find('[name="report_store"]').val());
        });
    }
});
