jQuery(document).ready(function () {
    /**
     * Funnel chart
     */
    if (jQuery('[data-type="funnel_chart"]').length > 0) {
        jQuery('body').find('[data-type="funnel_chart"]').each(function (e) {

            const options = {
                block: {
                    dynamicHeight: true,
                    minHeight: 15,
                },
            };
            const funnel = new D3Funnel(this);
            funnel.draw(jQuery(this).data("values"), options);
        });
    }
});
