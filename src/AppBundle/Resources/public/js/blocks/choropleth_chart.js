jQuery(document).ready(function () {
    /**
     * Line chart
     */
    if (jQuery('[data-type="choropleth_chart"]').length > 0) {
        
        jQuery('[data-type="choropleth_chart"]').each(function () {

            var elem = jQuery(this);

            var drawChoroplethMap = function () {
                /** EXAMPLE DATA **/
                /*var data = google.visualization.arrayToDataTable([
                    ['Country', 'Popularity'],
                    ['Germany', 200],
                    ['United States', 300],
                    ['Brazil', 400],
                    ['Canada', 500],
                    ['France', 600],
                    ['RU', 700]
                ]);*/

                var data = google.visualization.arrayToDataTable(elem.data('items'));

                var options = {};

                var chart = new google.visualization.GeoChart(elem.get(0));

                chart.draw(data, options);
            }


            google.charts.load('current', {
                'packages': ['geochart'],
                'mapsApiKey': elem.data("gmaps-key")
            });
            google.charts.setOnLoadCallback(drawChoroplethMap);
        });
    }
});
