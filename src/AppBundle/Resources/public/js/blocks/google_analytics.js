jQuery(document).ready(function () {

    var elem = jQuery('body').find('.google-analytics-wrapper');
    if (elem.length > 0) {
        gapi.analytics.ready(function () {
            jQuery.post(elem.data('url'), {}, function (result) {
                if (result.error == false) {
                    gapi.analytics.auth.authorize({
                        'serverAuth': {
                            'access_token': result.access_token
                        }
                    });

                    dataChart1.execute();
                    dataChart2.execute();
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, 'json');

            var dataChart1 = new gapi.analytics.googleCharts.DataChart({
                query: {
                    'ids': 'ga:2698337', /////// hardcoded view id
                    'start-date': '90daysAgo',
                    'end-date': 'today',
                    'metrics': 'ga:sessions,ga:users',
                    'dimensions': 'ga:date'
                },
                chart: {
                    'container': 'chart-1-container',
                    'type': 'LINE',
                    'options': {
                        'width': '100%'
                    }
                }
            });

            var dataChart2 = new gapi.analytics.googleCharts.DataChart({
                query: {
                    'ids': 'ga:2698337', /////// hardcoded view id
                    'start-date': '90daysAgo',
                    'end-date': 'today',
                    'metrics': 'ga:pageviews,ga:uniquePageviews,ga:timeOnPage,ga:bounces,ga:entrances,ga:exits',
                    'sort': '-ga:pageviews',
                    'dimensions': 'ga:pagePath',
                    'max-results': 10
                },
                chart: {
                    'container': 'chart-2-container',
                    'type': 'PIE',
                    'options': {
                        'width': '100%',
                        'pieHole': 0.4,
                    }
                }
            });

        });
    }
});