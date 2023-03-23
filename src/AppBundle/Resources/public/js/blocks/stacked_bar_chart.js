jQuery(document).ready(function () {
    /**
     * Stacked bar chart
     */
    if (jQuery('[data-type="stacked_bar_chart"]').length > 0) {
        jQuery('body').find('[data-type="stacked_bar_chart"]').each(function (e) {
            var categories = jQuery(this).data("categories");
            var columns = jQuery(this).data("columns");
            var groups = jQuery(this).data("groups");
            var sp_chart = jQuery(this);
            var format = jQuery(this).data("format");
            var currency_code = jQuery(this).data("currency_code");

            var chart = c3.generate({
                bindto: '#' + jQuery(this).attr('id'),
                data: {
                    columns: columns,
                    type: 'bar',
                    groups: groups,
                },
                axis: {
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
                },
                grid: {
                    x: {
                        show: true
                    },
                    y: {
                        show: true
                    }
                }
            });
            sp_chart.data('c3-chart', chart);
        });
    }
});
