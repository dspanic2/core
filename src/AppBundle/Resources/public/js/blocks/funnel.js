jQuery(document).ready(function () {

    // ADMIN

    // FRONTEND
    var funnel = jQuery('[data-type="funnel"]');
    if (funnel.length > 0) {

        var settings = {
            /*curved: {
             chart: {
             curve: {
             enabled: true,
             },
             },
             },*/
            chart: {
                bottomPinch: 1,
                animate: 200,
            },
            block: {
                dynamicHeight: true,
                fill: {
                    type: 'gradient',
                },
            },
            /*inverted: {
             chart: {
             inverted: true,
             },
             },
             hover: {
             block: {
             highlight: true,
             },
             },*/
            tooltip: {
                enabled: true,
            },
            /*click: {
             events: {
             click: {
             block(d) {
             alert(d.label.raw);
             },
             },
             },
             },*/
            /*barOverlay: {
             block: {
             barOverlay: true,
             },
             },*/
            label: {
                fontFamily: '"MarkPro", sans-serif',
                fontSize: '12px',
            }
        };

        funnel.each(function (e) {
            var data = jQuery(this).data("data");
            var chart = new D3Funnel('#' + jQuery(this).attr('id'));
            chart.draw(data, settings);
        });
    }

});
