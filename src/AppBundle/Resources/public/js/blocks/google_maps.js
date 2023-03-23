jQuery(document).ready(function() {

    // ADMIN
    jQuery('body').on('change','[data-action="gmaps_attribute_set"]',function (e) {
        var form = jQuery(this).parents('form');
        var elem = jQuery(this);

        var backendTypes = new Array();
        backendTypes.push("text");
        backendTypes.push("decimal");

        jQuery.post(elem.data("url"), { type: form.find('[name="type"]').val(), attributeSet: elem.val(), backendTypes: backendTypes }, function(result) {
            if(result.error == false){
                form.find('[name="lat"]').html(result.html);
                form.find('[name="lng"]').html(result.html);
                form.find('[name="gmaps_title"]').html(result.html);
            }
            else{
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    // FRONTEND
    if(jQuery('body').find('.gmap-wrapper').length){
        jQuery('body').find('.gmap-wrapper').each(function (e) {
            var gmap_wrapper = jQuery(this);

            new Maplace({
                locations: gmap_wrapper.data('locations'),
                map_div: '#'+gmap_wrapper.attr('id'),
                controls_type: 'list',
                controls_title: translations.choose_location
            }).Load();
        });
    }


});