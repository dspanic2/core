jQuery(document).ready(function () {
    /**
     * Timeseries chart
     */
    if (jQuery('[data-type="time_chart"]').length > 0) {
        jQuery('body').find('[data-type="time_chart"]').each(function (e) {
            var columns = jQuery(this).data("columns");
            var categories = jQuery(this).data("categories");
            var sp_chart = jQuery(this);

            var chart = c3.generate({

                bindto: '#' + jQuery(this).attr('id'),
                data: {
                    x: 'x',
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
                        type: 'timeseries',
                        tick: {
                            format: "%b-%d"
                        }
                    }
                }
            });
            sp_chart.data('c3-chart', chart)
        });
    }
});
