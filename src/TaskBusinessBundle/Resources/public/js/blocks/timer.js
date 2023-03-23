jQuery(document).ready(function ($) {

    function timestampToTimer(s) {

        var seconds = Math.floor(s % 60);
        var minutes = Math.floor((s / 60) % 60);
        var hours = Math.floor((s / (60 * 60)) % 24);

        return (hours < 10 ? '0' + hours : hours) + ':' +
            (minutes < 10 ? '0' + minutes : minutes) + ':' +
            (seconds < 10 ? '0' + seconds : seconds);
    }

    function resetButtons(time_start, time_end, time_elapsed, timer) {

        var interval = null;

        if (!time_start) {
            jQuery('.sp-activity-tracking-start').removeClass('hidden');
            jQuery('.sp-activity-tracking-stop').addClass('hidden');
        } else {
            if (!time_end) {
                interval = setInterval(function () {
                    if (time_elapsed > 86399) {
                        clearInterval(interval);
                    } else {
                        timer.text(timestampToTimer(++time_elapsed));
                    }
                }, 1000);

                jQuery('.sp-activity-tracking-start').addClass('hidden');
                jQuery('.sp-activity-tracking-stop').removeClass('hidden');
            } else {
                jQuery('.sp-activity-tracking-start').addClass('hidden');
                jQuery('.sp-activity-tracking-stop').addClass('hidden');
                jQuery('.sp-activity-tracking-message').removeClass('hidden');
            }
        }

        return interval;
    }

    var interval = null;
    var getActivitysTimerBlockHtml = function (startStopBtn) {
        var startStop = function () {
            if (jQuery('.sp-activity-tracking').length) {
                var block = jQuery('.sp-activity-tracking');
                var timer = block.find('.sp-activity-tracking-timer');
                var time_start = block.find('[data-name="time_start"]').val();
                var time_end = block.find('[data-name="time_end"]').val();
                var time_elapsed = block.find('[data-name="time_elapsed"]').val();

                interval = resetButtons(time_start, time_end, time_elapsed, timer);

                if (startStopBtn != undefined) {
                    jQuery.post(startStopBtn.data('url'), {
                        // nothing
                    }, function (result) {
                        if (result.error == false) {
                            clearInterval(interval);
                            interval = resetButtons(result.time_start, result.time_end, 0, timer);
                            var resets = jQuery.find('[data-action="filter-reset"]');
                            jQuery(resets).each(function () {
                                jQuery(this).trigger("click");
                            });
                        } else {
                            jQuery.growl.error({
                                title: translations.error_message,
                                message: result.message
                            });
                        }
                    }, 'json');
                }
            }
        }

        if (startStopBtn == undefined) {
            startStop()
        } else {
            jQuery.post("/activity/get_activity_timer_block_html", {
                "activity": startStopBtn.data("activity-id")
            }, function (result) {
                if (result.error == false) {
                    $(".timer-wrapper").replaceWith(result.html);
                    startStop();
                } else {
                    jQuery.growl.error({
                        title: translations.error_message
                    });
                }
            }, 'json');
        }

    }

    getActivitysTimerBlockHtml();
    $(document).on("click", '[data-action="activity_tracking_start"],[data-action="activity_tracking_stop"]', function (e) {
        e.stopPropagation();
        getActivitysTimerBlockHtml(jQuery(this));
    });

    $(document).on("click", '[data-action="list_activity_tracking_start"],[data-action="list_activity_tracking_stop"]', function (e) {
        e.stopPropagation();
        var button = jQuery(this);
        var list = button.parents('.datatables');
        jQuery.post(button.data('url'), {
            "activity": button.data("id")
        }, function (result) {
            if (result.error == false) {
                refreshList(list, null);
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, 'json');
    });
});
