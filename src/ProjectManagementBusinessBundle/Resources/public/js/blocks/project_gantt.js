jQuery(document).ready(function () {

    // ADMIN - nema

    // FRONTEND


    jQuery('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        if (jQuery('#gantt_placeholder').length > 0) {
            loadGantt();
        }
    });



});

function loadGantt() {
    var source_url = jQuery('#gantt_placeholder').data("source-url");
    var tasks_edit = jQuery('#gantt_placeholder').data("tasks-edit");
    var workpackages_edit = jQuery('#gantt_placeholder').data("workpackages-edit");

    var g = new JSGantt.GanttChart(document.getElementById('gantt_placeholder'), 'day');
    g.setOptions({
        vCaptionType: 'Complete',  // Set to Show Caption : None,Caption,Resource,Duration,Complete,
        vQuarterColWidth: 36,
        vTotalHeight: 15,
        vUseToolTip:0,
        vDateTaskDisplayFormat: 'day dd month yyyy', // Shown in tool tip box
        vDayMajorDateDisplayFormat: 'mon yyyy - Week ww',// Set format to display dates in the "Major" header of the "Day" view
        vWeekMinorDateDisplayFormat: 'dd mon', // Set format to display dates in the "Minor" header of the "Week" view
        vLang: 'hr',
        //vEditable: true,
        vAdditionalHeaders: { // Add data columns to your table
            /*category: {
                title: 'Category'
            },*/
        },
        vShowTaskInfoLink: 0, // Show link in tool tip (0/1)
        vShowEndWeekDate: 0,  // Show/Hide the date for the last day of the week in header for daily view (1/0)
        vUseSingleCell: 10000, // Set the threshold at which we will only use one cell per table row (0 disables).  Helps with rendering performance for large charts.
        vFormatArr: ['Day', 'Week', 'Month', 'Quarter'], // Even with setUseSingleCell using Hour format on such a large chart can cause issues in some browsers
        vEvents: {
            taskname: console.log,
            res: console.log,
            dur: console.log,
            comp: console.log,
            start: console.log,
            end: console.log,
            planstart: console.log,
            planend: console.log,
            cost: console.log
        },
        vEventClickRow: console.log
    });


    jQuery.ajax({
        type: "GET",
        url: source_url,
        success: function (data) {
            if (data.error == false) {
                console.log(data);
                var jsonData = JSON.parse(data.data);
                addProjectTasks(jsonData, g);
                g.setEventClickRow(function (task) {
                    var pID = (task.getAllData().pDataObjec.pID);
                    var href = tasks_edit;


                    href = href + "&id=" + pID;

                    jQuery.post(href, {}, function (result) {
                        if (result.error == false) {
                            var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                            clone.html(result.html);
                            var modal = clone.find('.modal');
                            modal.modal('show');

                            var form = modal.find('[data-validate="true"]');
                            form.data('callback', ["custom"]);
                            form.data('custom-callback', ["refreshGantt"]);
                            form.initializeValidation();
                            form.forceBoostrapXs();
                        }
                        else {
                            jQuery.growl.error({
                                title: translations.error_message,
                                message: result.message
                            });
                        }
                    }, "json");

                });
                g.Draw();

            }
            else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            jQuery.growl.error({
                title: translations.error_message,
                message: translations.there_has_been_an_error_please_try_again
            });
        }
    });
}

function refreshGantt() {
    loadGantt();
}

function addProjectTasks(jsonData, g) {

    for (i = 0; i < jsonData.length; i++) {
        // Or Adding  Manually
        g.AddTaskItemObject({
            pID: jsonData[i].pID,
            pName: jsonData[i].pName,
            pStart: jsonData[i].pStart,
            pEnd: jsonData[i].pEnd,
            pClass: jsonData[i].pClass,
            pLink: jsonData[i].pLink,
            pMile: jsonData[i].pMile,
            pRes: jsonData[i].pRes,
            pComp: jsonData[i].pComp,
            pGroup: jsonData[i].pGroup,
            pParent: jsonData[i].pParent,
            pOpen: 1,
            pDepend: jsonData[i].pDepend,
            pCaption: "",
            pCost: 1000,
            pNotes: jsonData[i].pNotes
        });
    }

}


