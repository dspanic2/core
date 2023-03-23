jQuery(document).ready(function () {
    /**
     * Bar chart
     */
    if (jQuery('[data-type="bar_chart"]').length > 0) {
        jQuery('body').find('[data-type="bar_chart"]').each(function (e) {
            var columns = jQuery(this).data("columns");
            var categories = jQuery(this).data("categories");
            var sp_chart = jQuery(this);
            var format = jQuery(this).data("format");
            var currency_code = jQuery(this).data("currency_code");

            var chart = c3.generate({
                bindto: '#' + jQuery(this).attr('id'),
                data: {
                    columns: columns,
                    type: 'bar',
                    onclick: function (d, i) {
                        console.log("onclick", d, i);
                    },
                    onmouseover: function (d, i) {
                        console.log("onmouseover", d, i);
                    },
                    onmouseout: function (d, i) {
                        console.log("onmouseout", d, i);
                    }
                }, axis: {
                    x: {
                        type: 'category',
                        categories: categories,
                    },
                    y: {
                        tick: {
                            format: function (d) {
                                if (format == "currency") {
                                    if (typeof accounting !== "undefined")
                                        return accounting.formatMoney(d, currency_code+" ", 2, ".", ",");
                                } else if (format == "time") {
                                    return secondsTimeSpanToHMS(d);
                                }
                                return d;
                            },
                        }
                    }
                }
            });
            sp_chart.data('c3-chart', chart);
        });
    }
});
