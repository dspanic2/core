/**
 * Callback function
 * @param elem
 * @param spBlock
 * @param result
 */
function removeTaskItem(elem, spBlock, result) {
    elem.closest(".task-item").remove();
}

jQuery(document).ready(function ($) {
    if ($(".sp-block-task_agile_kanban").length > 0) {
        $(document).on("click", '[data-action="task_agile_kanban_hide_all"]', function (e) {
            $(".sp-block-user_agile_kanban").each(function () {
                var kanban = $(this);
                kanban.slideUp(function () {
                    $.setCookie("kanban_list_hidden_" + kanban.data("user-id"), kanban.is(":visible") ? 0 : 1);
                });
            });
        });
        $(document).on("click", '[data-action="task_agile_kanban_show_all"]', function (e) {
            $(".sp-block-user_agile_kanban").each(function () {
                var kanban = $(this);
                kanban.slideDown(function () {
                    $.setCookie("kanban_list_hidden_" + kanban.data("user-id"), kanban.is(":visible") ? 0 : 1);
                });
            });
        });
        $(document).on("click", '[data-action="task_agile_kanban_schedule_mode"]', function (e) {
            $(".sp-block-task_agile_kanban").toggleClass("schedule-mode");
        });
        $(document).on("click", ".sp-block-task_agile_kanban:not(.current-user) h5", function (e) {
            e.stopPropagation();
            var kanban = $(this).closest(".panel-body").find(".sp-block-user_agile_kanban");
            $(this).closest(".panel-body").find(".sp-block-user_agile_kanban").slideToggle(function () {
                $.setCookie("kanban_list_hidden_" + kanban.data("user-id"), kanban.is(":visible") ? 0 : 1);
            });
        });

        $(".sp-block-task_agile_kanban .kanban-column .items").sortable({
            connectWith: ".sp-block-task_agile_kanban .kanban-column .items",
            cursor: "move",
            update: function (event, ui) {
                if (this === ui.item.parent()[0]) {
                    var taskItem = $(ui.item);

                    $.ajax({
                        url: "/kanban/update_task_item",
                        method: 'POST',
                        data: {
                            task: taskItem.data("task-id"),
                            priority: taskItem.closest(".kanban-column").data("priority-id"),
                            user: taskItem.closest(".sp-block-user_agile_kanban").data("user-id"),
                        },
                        cache: false
                    }).done(function (result) {
                        if (result.error == false) {
                            $.growl.notice({
                                title: result.title ? result.title : '',
                                message: result.message ? result.message : '',
                            });
                        } else {
                            $.growl.error({
                                title: result.title ? result.title : '',
                                message: result.message ? result.message : '',
                            });
                        }
                    });
                }
            }
        });

        $(document).on("click", "[data-action='kanban_activity_tracking_start']", function (e) {
            e.stopPropagation();
            var button = $(this);
            $.ajax({
                url: button.data('url'),
                method: 'POST',
                data: {
                    "activity": button.data("id")
                },
                cache: false
            }).done(function (result) {
                if (result.error == false) {
                    $("[data-action='kanban_activity_tracking_start'].active").removeClass("active");
                    button.addClass("active");
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : '',
                    });
                }
            });
        });

        $(document).on("click", "[data-action='kanban_activity_tracking_stop']", function (e) {
            e.stopPropagation();
            var button = $(this);
            $.ajax({
                url: button.data('url'),
                method: 'POST',
                data: {
                    "activity": button.data("id")
                },
                cache: false
            }).done(function (result) {
                if (result.error == false) {
                    $("[data-action='kanban_activity_tracking_start'].active").removeClass("active");
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : '',
                    });
                }
            });
        });

        if ($("#kanban-filters").length > 0) {
            // Kanban filters
            var tokenButton = $("<button>").attr("class", "pull-right btn btn-inverse-alt btn-xs hidden-print").attr("id", "show-kanban-filters").html('<i class="fas fa-question-circle"></i>');
            $("#back-to-top").after(tokenButton);
            $(document).on("click", "#show-kanban-filters", function (e) {
                $("#kanban-filters").toggle();
            });
            $(document).on("change", "#kanban-filters input:not(.show-all)", function (e) {
                if ($("#kanban-filters input:checked").length > 0) {
                    $('.sp-block-task_agile_kanban .task-item').hide();
                    $("#kanban-filters input:checked").each(function () {
                        var projectId = $(this).val();
                        $('.sp-block-task_agile_kanban .task-item[data-project-id="' + projectId + '"]').show();
                    })
                } else {
                    $(".sp-block-task_agile_kanban .task-item").show();
                }
            });
            $(document).on("change", "#kanban-filters input.show-all", function (e) {
                if ($(this).is(":checked")) {
                    $("#kanban-filters input:not(.show-all)").prop('checked', true);
                    $(".sp-block-task_agile_kanban .task-item").show();
                } else {
                    $("#kanban-filters input:not(.show-all)").prop('checked', false);
                    $(".sp-block-task_agile_kanban .task-item").hide();
                }
            });
        }
    }
});
