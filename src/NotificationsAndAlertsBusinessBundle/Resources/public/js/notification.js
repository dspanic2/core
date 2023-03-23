

jQuery(document).ready(function() {

    /**
     * Mark notification as read
     */
    jQuery('body').on('click','[data-action="mark_as_read"]', function(e){

        var notification = jQuery(this);
        var wrapper = jQuery(this).parents('.notifications');

        jQuery.post(notification.data("url"), { id: notification.data("id") }, function(result) {
            if(result.error == false){
                notification.removeClass('active').removeAttr('data-action');
                var unread = parseInt(wrapper.find('.sp-unread-number').text());
                if(unread > 0){
                    unread = unread - 1;
                    wrapper.find('.sp-unread-number').text(unread);
                }
            }
            else{
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    /**
     * Mark all notifications as read
     */
    jQuery('body').on('click','[data-action="mark_all_read"]', function(e){

        var wrapper = jQuery(this).parents('.notifications');

        jQuery.post(jQuery(this).data("url"), { }, function(result) {
            if(result.error == false){
                wrapper.find('.active').removeClass('active').removeAttr('data-action');
                wrapper.find('.sp-unread-number').text(0);
            }
            else{
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

});
