jQuery(document).ready(function() {

    // ADMIN
    jQuery('body').on('change','fieldset.timeline-settings [name="listView"]',function (e) {
        var fieldset = jQuery("fieldset.timeline-settings");
        var listView = fieldset.find('[name="listView"]');
        getListViewAttributes(listView.val(), 'lookup', "[name='date']", "Select date attribute");
        getListViewAttributes(listView.val(), 'lookup', "[name='description']", "Select description attribute");
        if (listView.find(":selected")) {
            listView.find('select[name="date"]').removeAttr('disabled').show();
            listView.find('select[name="description"]').removeAttr('disabled').show();
        } else {
            listView.find('select[name="date"]').attr('disabled', 'disabled').hide();
            listView.find('select[name="description"]').attr('disabled', 'disabled').hide();
        }
    });

    // FRONTEND
    var timeline = jQuery('.timeline');
    if(timeline.length > 0){
        timeline.timeline();
    }

});
