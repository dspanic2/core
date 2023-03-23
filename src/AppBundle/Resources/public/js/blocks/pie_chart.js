jQuery(document).ready(function () {
    /**
     * Pie chart
     */
    if (jQuery('[data-type="pie_chart"]').length > 0) {

        jQuery('body').find('[data-type="pie_chart"]').each(function (e) {
            var columns = jQuery(this).data("columns");
            var sp_chart = jQuery(this);

            var chart = c3.generate({
                bindto: '#' + jQuery(this).attr('id'),
                data: {
                    columns: columns,
                    type: 'pie',
                    onclick: function (d, i) {
                        console.log("onclick", d, i);
                    },
                    onmouseover: function (d, i) {
                        console.log("onmouseover", d, i);
                    },
                    onmouseout: function (d, i) {
                        console.log("onmouseout", d, i);
                    }
                }
            });
            sp_chart.data('c3-chart', chart)

        });
    }
});
