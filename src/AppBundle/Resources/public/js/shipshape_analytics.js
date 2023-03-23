/**
 * Definicija quartera
 * @type {number}
 */
var quarterAdjustment = (moment().month() % 3) + 1;
var lastQuarterEndDate = moment().subtract({months: quarterAdjustment}).endOf('month');
var lastQuarterStartDate = lastQuarterEndDate.clone().subtract({months: 2}).startOf('month');

/**
 * LIVE
 */
jQuery(document).ready(function () {
    jQuery('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {

        if (jQuery(".sp-chart-container").length > 0) {
            jQuery(".sp-chart-container").each(function () {
                jQuery(this).data("c3-chart").resize();
            });
        }
    });
});

/**
 * Get Chart Data
 * @param chart
 * @param barChart
 * @param parentKey
 */
function getChartData(form, result) {

    chart_id = form.data("chart-id");
    chart = jQuery("#chart_" + chart_id);

    var c3Chart = chart.data('c3-chart');

    jQuery.ajax({
        url: chart.data('url'),
        method: "POST",
        async: false,
        data: {
            id: chart.data('page-id'),
        },
        cache: false
    }).done(function (result) {
        if (result.error === false) {
            c3Chart.load({
                unload: true,
                columns: JSON.parse(result.data.columns),
                categories: JSON.parse(result.data.categories)
            });
            if (result.data.is_filtered == true) {
                jQuery('i[data-filter-chart="' + chart_id + '"]').addClass('active');
            } else {
                jQuery('i[data-filter-chart="' + chart_id + '"]').removeClass('active');
            }
        } else {
            jQuery.growl.error({
                title: translations.error_message,
                message: result.message
            });
        }
    });
}


function secondsTimeSpanToHMS(s) {
    var h = Math.floor(s / 3600); //Get whole hours
    s -= h * 3600;
    var m = Math.floor(s / 60); //Get remaining minutes
    s -= m * 60;
    return h + ":" + (m < 10 ? '0' + m : m); //zero padding on minutes and seconds
}
