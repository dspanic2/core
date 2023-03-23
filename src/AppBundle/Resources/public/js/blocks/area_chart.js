jQuery(document).ready(function () {
    /**
     * Line chart
     */
    if (jQuery('[data-type="area_chart"]').length > 0) {
        jQuery('body').find('[data-type="area_chart"]').each(function (e) {
            var columns = jQuery(this).data("columns");
            var categories = jQuery(this).data("categories");
            var sp_chart = jQuery(this);
            var format = jQuery(this).data("format");
            var currency_code = jQuery(this).data("currency_code");
            //var dataset = result.dataset_prices;

            var chart = c3.generate({
                bindto: '#' + jQuery(this).attr('id'),
                data: {
                    columns: columns,
                    type: 'area',
                },
                axis: {
                    x: {
                        type: 'category',
                        categories: categories
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
                point: {
                    r: 2
                },
                color: {
                    pattern: ["#004182", "#da5c53", "#ffcc00", "#449187", "#f8aa27", "#ffe371", "#4aa3ba", "#2f3c4f", "#118df0", "#61529f", "#4a2377", "#91e4a6", "#bd1c10", "#fac55b", "#306d75", "#506f86", "#ba69de", "#20655f", "#e23c30", "#de703c"]
                },
                transition: {
                    duration: 200
                },
                size: {
                    height: 300,
                },
                zoom: {
                    enabled: false
                },
                legend: {
                    position: 'right'
                },
                tooltip: {
                    show: true,
                    grouped: true
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
