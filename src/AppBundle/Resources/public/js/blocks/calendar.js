function printCalendarPreview(block) {
  var headerElements = document.getElementsByClassName('fc-header');//.style.display = 'none';
  for(var i = 0, length = headerElements.length; i < length; i++) {
    headerElements[i].style.display = 'none';
  }
  var toPrint = block.find('.calendar-init').clone(true);//document.getElementById('cal857').cloneNode(true);

  for(var i = 0, length = headerElements.length; i < length; i++) {
        headerElements[i].style.display = '';
  }

  var linkElements = document.getElementsByTagName('link');
  var link = '';
  for(var i = 0, length = linkElements.length; i < length; i++) {
    link = link + linkElements[i].outerHTML;
  }

  var styleElements = document.getElementsByTagName('style');
  var styles = '';
  for(var i = 0, length = styleElements.length; i < length; i++) {
    styles = styles + styleElements[i].innerHTML;
   }

  var additionalTitle = "";
  if(jQuery('body').find('[data-validate="true"]').length > 0){
      var from = jQuery('body').find('[data-validate="true"]');
      if(from.find('[name="full_name"]').length > 0){
          additionalTitle = from.find('[name="full_name"]').val();
      }
      else if(from.find('[name="name"]').length > 0){
          additionalTitle = from.find('[name="name"]').val();
      }

      if(additionalTitle){
          var prev = toPrint.find('.fc-center > h2').text();
          toPrint.find('.fc-center > h2').text(additionalTitle+" - "+prev);
      }
  }

  toPrint.find('.fc-view-container').addClass('sp-calendar-print');
  toPrint.find('.fc-button').remove();
  toPrint.find('.fc-bg').remove();
  toPrint.find('a').each(function (e) {
      var elem = jQuery(this);
      elem.find('.fc-time').remove();
     jQuery(this).parent().html('<span>'+elem.text()+'</span>');
  });
  toPrint.find('.fc-future').each(function (e) {
      var index = jQuery(this).index();
      var table = jQuery(this).parent().parent().parent();
      table.find('tbody > tr').each(function (e) {
          //jQuery(this).find('td').eq( index ).html('');
      });

  });


  var popupWin = window.open('', '_blank');
  popupWin.document.open();
  popupWin.document.write('<html><title>'+translations.calendar_export+'</title>'+link
 +'<style>'+styles+'</style></head><body class="sp-print-body" ">');
  popupWin.document.write(toPrint.html());
  popupWin.document.write('</html>');
  popupWin.document.close();

  return true;
}

jQuery(document).ready(function() {

    // ADMIN - nema

    // FRONTEND

    jQuery('body').on('click','[data-action="print_calendar"]', function (){
        printCalendarPreview(jQuery(this).parents('.sp-block'));
    });

    if(jQuery('.sp-calendar-wrapper').length > 0){
        jQuery('.sp-calendar-wrapper').each(function (e) {
            var wrapper = jQuery(this);
            var wrapper_id = wrapper.find('.calendar-init').attr('id');
            var calendar = new FullCalendar.Calendar(document.getElementById(wrapper_id), {
                plugins: [ 'interaction', 'dayGrid', 'list', 'bootstrap' ],
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,listWeek,dayGridWeek'
                },
                locale: wrapper.data('locale'),
                firstDay: wrapper.data('first-day'),
                editable: true,
                droppable: true,
                eventDrop: function(info) {
                    if(wrapper.data('drop')){
                        jQuery.post(wrapper.data('drop-url'), { id: info.event.id, start: moment(info.event.start).format("DD-MM-YYYY HH:mm"), block_id: wrapper.find('.calendar-init').data('id'), entity_type_id: info.event.extendedProps.entityTypeId, start_attribute_code: info.event.extendedProps.startAttributeCode, end_attribute_code: info.event.extendedProps.endAttributeCode }, function(result) {
                            if(result.error == false){
                                jQuery.growl.notice({
                                    title: result.title,
                                    message: result.message
                                });
                            }
                            else{
                                jQuery.growl.error({
                                    title: translations.error_message,
                                    message: result.message
                                });
                                info.revert();
                            }
                        }, "json");
                    }
                    else{
                        info.revert();
                    }
                },
                height: 700,
                width: 700,
                eventRender: function(info) {
                    if(wrapper.data('open-modal') == 1){
                        jQuery(info.el).addClass('calendar-open-modal');
                    }
                    if(info.event.extendedProps.tooltip != "" && info.event.extendedProps.tooltip != null){
                        info.el.title = info.event.extendedProps.tooltip;
                        jQuery(info.el).tooltip();
                    }
                    if (info.event.extendedProps.entityTypeCode == 'holidays') {
                        var dateString = moment(info.event.start).format("YYYY-MM-DD");
                        jQuery(info.el).addClass('hidden');
                        jQuery(info.el).parents('.fc-event-container').addClass('hidden');
                        wrapper.find('[data-date="'+dateString+'"]').css("background-color", "#FAA732");
                        if(wrapper.find('[data-date="'+dateString+'"]').find('.sp-calendar-td-title').length < 1){
                            wrapper.find('[data-date="'+dateString+'"]').append('<span class="sp-calendar-td-title" style="padding: 2px 0; display: inline-block; text-align: center; width: 100%;">'+info.event.title+'</span>')
                        }
                    }
                },
                eventSources: [
                  {
                    url: '/calendar/fetch/data?block_id='+wrapper.find('.calendar-init').data("id"),
                    type: 'POST' // Send post data
                  },
                ],

            });

            calendar.render();
        });
    }

    jQuery('[data-open-modal="1"]').on('click','.calendar-open-modal',function (e) {
        e.preventDefault();
        e.stopPropagation();
        var href = jQuery(this).attr("href");
        if(!href){
            href = jQuery(this).find('a').attr("href");
        }
        jQuery.post(href, { }, function(result) {
            if(result.error == false){
                var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                clone.html(result.html);
                var modal = clone.find('.modal');
                modal.modal('show');

                var form = modal.find('[data-validate="true"]');
                form.initializeValidation();
                form.forceBoostrapXs();
            }
            else{
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    jQuery('[data-open-modal="1"]').on('dblclick','.fc-day-number,.fc-day,.fc-day-top',function (e) {
        e.preventDefault();
        e.stopPropagation();

        var date = null;
        if(jQuery(this).hasClass('fc-day') || jQuery(this).hasClass('fc-day-top')){
            date = new Date(jQuery(this).data('date'));
        }
        else{
            date = new Date(jQuery(this).parents('td').data('date'));
        }

        var d = date.getDate();
        var m =  date.getMonth();
        m += 1;  // JavaScript months are 0-11
        var y = date.getFullYear();
        var dateText = d+"/"+m+"/"+y;

        jQuery.post(jQuery(this).parents('.sp-calendar-wrapper').data('url'), { }, function(result) {
            if(result.error == false){
                var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                clone.html(result.html);
                var modal = clone.find('.modal');
                modal.modal('show');

                var form = modal.find('[data-validate="true"]');
                form.initializeValidation();
                form.forceBoostrapXs();

                form.find('[data-type="datesingle"]').val(dateText);
                form.data('date-start',dateText);
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
