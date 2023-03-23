jQuery.extend({
    initializeSortable: function () {
        if ($(".builder-blocks").length > 0) {
            // https://api.jqueryui.com/sortable/#option-cursorAt
            $('.builder-blocks, .builder-blocks .columns').sortable({
                connectWith: ['.builder-blocks', '.builder-blocks .columns'],
                cursor: "move",
                tolerance: "intersect",
                opacity: 0.4,
                items: '>[data-block-id]',
                distance: 20,
                // forcePlaceholderSize: true,
                start: function (event, ui) {
                    $("body").addClass("page-builder-sorting");
                },
                stop: function (event, ui) {
                    $("body").removeClass("page-builder-sorting");
                    var layoutData = $.pageBuilderSavePositions($('.builder-blocks'));
                    $.ajax({
                        url: "/api/page_builder_save_positions",
                        method: 'POST',
                        data: {"data": layoutData},
                        cache: false
                    }).done(function (result) {
                        $.hideAjaxLoader();
                        if (result.error === false) {
                            console.log("Saved...");
                        } else {
                            $.growl.error({
                                title: result.title ? result.title : '',
                                message: result.message ? result.message : translations.selection_error,
                            });
                        }
                    });
                },
            }).disableSelection();
        }
    },
    refreshSortable: function () {
        $('.builder-blocks, .builder-blocks .columns').sortable("refresh");
    },
    startPageBuilder: function (blockId) {
        $.showAjaxLoader();

        var data = {};
        if (blockId !== undefined) {
            data.block = blockId;
        }

        $.ajax({
            url: "/api/page_builder_get_settings",
            method: 'POST',
            data: data,
            cache: false
        }).done(function (result) {
            if (result.error == false) {
                if ($("#page-builder").length) {
                    $("#page-builder").replaceWith(result.html);
                } else {
                    $(".main-content").append(result.html);
                }

                $("body").addClass("page-builder-active");

                // Populate file fields
                if ($('[type="file"][data-prepopulate]').length > 0) {
                    $('[type="file"][data-prepopulate]').each(function () {
                        loadURLToInputFiled($(this));
                    });
                }

                // Unset block not supporting page builder
                $(".is-admin.sp-block-outer:not(.page-builder-on)").each(function () {
                    $(this).removeClass("is-admin").find(".sp-frontend-edit-block-wrapper").remove();
                });

                // Add inline block settings
                $(".is-admin.sp-block-outer").each(function () {
                    // Move options
                    if ($(this).find(".move-block-settings").length == 0) {
                        var moveSettings = $("<ul>").addClass("move-block-settings");
                        var moveSettingUp = $("<li>").attr("title", translations.page_builder_up).addClass("move-block-setting up").html('<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 15L12 9L6 15" stroke="#000" stroke-linecap="round"/></svg>');
                        var moveSettingDown = $("<li>").attr("title", translations.page_builder_up).addClass("move-block-setting down").html('<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 9L12 15L18 9" stroke="#000" stroke-linecap="round"/></svg>');
                        moveSettings.append(moveSettingUp);
                        moveSettings.append(moveSettingDown);
                        $(this).append(moveSettings);
                    }

                    // Between options
                    if ($(this).find(".between-block-settings").length == 0) {
                        var betweenSettings = $("<ul>").addClass("between-block-settings");
                        var betweenSettingAdd = $("<li>").attr("title", translations.page_builder_add_before).addClass("between-block-setting add").html('<span class="icons-vertical"><svg role="img" xmlns="http://www.w3.org/2000/svg" width="1000mm" height="1000mm" viewBox="0 0 1000 1000" style="max-width:1.6em; height: auto;"><path id="path" style="opacity:1;vector-effect:none;fill:#000000;fill-opacity:1;" d=" M 499 476C 508 476 518 480 525 487C 525 487 825 787 825 787C 840 801 840 825 825 840C 810 855 786 855 772 840C 772 840 498 566 498 566C 498 566 225 840 225 840C 210 855 186 855 171 840C 156 825 157 801 172 787C 172 787 472 487 472 487C 479 480 488 476 498 476C 498 476 498 476 499 476C 499 476 499 476 499 476M 500 149C 500 149 500 149 500 149C 510 149 520 153 527 160C 527 160 827 460 827 460C 842 474 842 498 827 513C 812 528 788 528 773 513C 773 513 500 239 500 239C 500 239 227 513 227 513C 212 528 188 528 173 513C 158 498 158 474 173 460C 173 460 473 160 473 160C 480 153 490 149 499 149C 500 149 500 149 500 149C 500 149 500 149 500 149" transform=""></path></svg><svg style="width: 15px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M416 208H32c-17.67 0-32 14.33-32 32v32c0 17.67 14.33 32 32 32h384c17.67 0 32-14.33 32-32v-32c0-17.67-14.33-32-32-32z"/></svg></span>');
                        betweenSettings.append(betweenSettingAdd);
                        $(this).append(betweenSettings);
                    }

                    // Right container options
                    if ($(this).hasClass("sp-block-outer-container") && $(this).find(".left-block-settings").length == 0) {
                        var leftSettings = $("<ul>").addClass("left-block-settings");
                        var leftSettingAdd = $("<li>").attr("title", translations.page_builder_add_to_container).addClass("left-block-setting add").html('<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 12H4" stroke="#676767" stroke-linecap="round"/><path d="M12 4V20" stroke="#676767" stroke-linecap="round"/></svg>');
                        leftSettings.append(leftSettingAdd);
                        $(this).append(leftSettings);
                    }
                });

                $.initializeSortable();
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : translations.selection_error,
                });
            }
        });
    },
    pageBuilderSavePositions: function (layoutWrapper) {
        var layoutData = {};
        if (layoutWrapper.hasClass("builder-blocks")) {
            layoutData.type = "builder_blocks";
            layoutData.id = null;
        } else {
            layoutData.type = "container";
        }
        layoutData.items = [];
        layoutWrapper.find(">.ui-sortable-handle").each(function () {
            var item = {};
            item.id = $(this).data("block-id");
            item.type = $(this).data("block-type");

            if ($(this).find(">.ui-sortable").length > 0) {
                $(this).find(">.ui-sortable").each(function () {
                    item.items = $.pageBuilderSavePositions($(this));
                });
            } else if ($(this).find(">.sp-block-outer-container>.ui-sortable").length > 0) {
                $(this).find(">.sp-block-outer-container>.ui-sortable").each(function () {
                    item.items = $.pageBuilderSavePositions($(this));
                });
            }
            layoutData.items.push(item);
        });
        if (layoutData.items.length > 0) {
            if (layoutWrapper.hasClass("builder-blocks")) {
                return layoutData;
            } else {
                return layoutData.items;
            }
        }
        return null;
    },
    pageBuilderRemoveBlock: function (blockId) {
        $.showAjaxLoader();

        var data = {};
        if (blockId !== undefined) {
            data.block = blockId;
        }

        $.ajax({
            url: "/api/page_builder_remove_block",
            method: 'POST',
            data: data,
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                $.startPageBuilder();

                if ($(".builder-blocks").length > 0) {
                    $('[data-block-id="' + blockId + '"]').remove();
                } else {
                    $('[data-block-id="' + blockId + '"]').parent().remove();
                }
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : translations.selection_error,
                });
            }
        });
    },
    pageBuilderMoveBlock: function (blockId, direction, parentBlockId) {
        $.showAjaxLoader();

        var data = {};
        data.block = blockId;
        data.direction = direction;
        data.parentBlockId = parentBlockId;

        $.ajax({
            url: "/api/page_builder_move_block",
            method: 'POST',
            data: data,
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                $.startPageBuilder();

                var blockRow = $('[data-block-id="' + blockId + '"]').parent();
                if ($(".builder-blocks").length > 0) {
                    blockRow = $('[data-block-id="' + blockId + '"]');
                }
                if (direction === 1) {
                    blockRow.insertBefore(blockRow.prev());
                } else if (direction === 2) {
                    blockRow.insertAfter(blockRow.next());
                }
                // $.reloadPage();
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : translations.selection_error,
                });
            }
        });
    },
    pageBuilderRemoveImage: function (removeButton) {
        $.showAjaxLoader();

        var imageId = removeButton.data("id");

        var data = {};
        data.id = imageId;

        $.ajax({
            url: "/api/page_builder_remove_image",
            method: 'POST',
            data: data,
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                removeButton.closest(".image").remove();
                $(".page-gallery").find('[data-image="' + imageId + '"]').remove();
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : translations.selection_error,
                });
            }
        });
    },
});
jQuery(document).ready(function ($) {
    $.startPageBuilder();

    $(document).on("page-builder-new-block", function (e) {
        if ($(".between-block-setting.add.between-active").length > 0) {
            $("#page-builder").find('[name="before_block"]').val($(".between-block-setting.add.between-active").closest(".is-admin.sp-block-outer").parent().data("block-id"));
        }
        if ($(".left-block-setting.add.left-active").length > 0) {
            $("#page-builder").find('[name="to_container"]').val($(".left-block-setting.add.left-active").closest(".is-admin.sp-block-outer").parent().data("block-id"));
        }
    });

    $(document).on("reset-page-builder", function (e, formData, form, result) {
        $.startPageBuilder();

        $(".between-block-setting.add.between-active").removeClass("between-active");
        $(".left-block-setting.add.left-active").removeClass("left-active");

        if (result.is_new == 1 && result.to_container == undefined) {
            var tmpWrapper = $("<div>").addClass("row");
            var tmpWrapperInner = $("<div>").addClass("col-xs-12").attr("data-block-id", result.block_id).attr("data-block-type", result.block_type);
            tmpWrapperInner.html(result.html);
            if ($(".builder-blocks").length > 0) {
                tmpWrapper = tmpWrapperInner;
            } else {
                tmpWrapper.append(tmpWrapperInner);
            }

            if (formData.before_block) {
                if ($(".builder-blocks").length > 0) {
                    $('[data-block-id="' + formData.before_block + '"]').before(tmpWrapper);
                } else {
                    $('[data-block-id="' + formData.before_block + '"]').parent().before(tmpWrapper);
                }
            } else {
                if ($(".builder-blocks").length > 0) {
                    $(".builder-blocks").append(tmpWrapper);
                } else {
                    var lastEditable = $(".is-admin.sp-block-outer").last();
                    if (lastEditable.length > 0) {
                        lastEditable.parent().parent().after(tmpWrapper);
                    } else {
                        $.reloadPage();
                    }
                }
            }
        } else if (result.to_container !== undefined) {
            $('[data-block-id="' + result.to_container + '"]').html(result.html);
        }

        $.refreshSortable();
    });

    $(document).on("click", "body.page-builder-active button.page-builder-cancel", function (e) {
        e.preventDefault();
        $.startPageBuilder();
    });

    $(document).on("click", "body.page-builder-active .is-admin.sp-block-outer", function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $.startPageBuilder($(this).parent().data("block-id"));
    });

    $(document).on("click", "body.page-builder-active .page-builder-remove-block", function (e) {
        e.preventDefault();
        var blockId = $(this).data("block-id");
        $.confirm({
            title: translations.delete_block,
            content: '',
            buttons: {
                confirm: {
                    text: translations.yes,
                    btnClass: 'button btn-type-1',
                    keys: ['enter'],
                    action: function () {
                        $.pageBuilderRemoveBlock(blockId);
                    }
                },
                cancel: {
                    text: translations.no,
                    btnClass: 'button btn-type-2',
                    action: function () {
                    }
                }
            }
        });
    });

    $(document).on("click", ".move-block-setting.up", function (e) {
        e.preventDefault();
        e.stopPropagation();

        var block = $(this).closest(".sp-block-outer").parent();
        $.pageBuilderMoveBlock(block.data("block-id"), 1, block.closest(".sp-block-outer-container").length > 0 ? block.closest(".sp-block-outer-container").parent().data("block-id") : 0);
    });
    $(document).on("click", ".move-block-setting.down", function (e) {
        e.preventDefault();
        e.stopPropagation();

        var block = $(this).closest(".sp-block-outer").parent();
        $.pageBuilderMoveBlock(block.data("block-id"), 2, block.closest(".sp-block-outer-container").length > 0 ? block.closest(".sp-block-outer-container").parent().data("block-id") : 0);
    });
    $(document).on("click", ".between-block-setting.add", function (e) {
        e.preventDefault();
        e.stopPropagation();

        var isActive = $(this).hasClass("between-active");
        $(".between-block-setting.add.between-active").removeClass("between-active");
        $("#page-builder").find('[name="before_block"]').val("");
        if (!isActive) {
            $(this).addClass("between-active");
            $("#page-builder").find('[name="before_block"]').val($(this).closest(".is-admin.sp-block-outer").parent().data("block-id"));
        }
    });
    $(document).on("click", ".left-block-setting.add", function (e) {
        e.preventDefault();
        e.stopPropagation();

        var isActive = $(this).hasClass("left-active");
        $(".left-block-setting.add.left-active").removeClass("left-active");
        $("#page-builder").find('[name="to_container"]').val("");
        if (!isActive) {
            $(this).addClass("left-active");
            $("#page-builder").find('[name="to_container"]').val($(this).closest(".is-admin.sp-block-outer").parent().data("block-id"));
        }
    });
    $(document).on("click", ".form-group.images .remove", function (e) {
        e.preventDefault();
        e.stopPropagation();
        $.pageBuilderRemoveImage($(this));
    });
});

function pageBuilderShowUploadFileType(fileInput) {
    var previewElement = $(fileInput).closest(".form-group").find(".images-preview");
    previewElement.html("");

    $.each(fileInput.files, function (index, file) {
        if (file.type === "image/jpeg" || file.type === "image/jpg" || file.type === "image/png" || file.type === "image/webp") {
            $("<img>").attr("src", URL.createObjectURL(file)).appendTo(previewElement);
        }
    });
}

function loadURLToInputFiled(element) {
    var urls = element.data("prepopulate").toString().split(";");
    var filenames = element.data("filename").toString().split(";");
    let container = new DataTransfer();

    document.querySelector('#' + element.attr("id")).files = container.files;

    $.each(urls, function (key, value) {
        getImgURL(value, (imgBlob) => {
            let fileName = filenames[key];
            let file = new File([imgBlob], fileName, {type: "image/jpeg", lastModified: new Date().getTime()}, 'utf-8');
            container.items.add(file);
            document.querySelector('#' + element.attr("id")).files = container.files;
            element.trigger("change");
        });
    });
}

// xmlHTTP return blob respond
function getImgURL(url, callback) {
    var xhr = new XMLHttpRequest();
    xhr.onload = function () {
        callback(xhr.response);
    };
    xhr.open('GET', url);
    xhr.responseType = 'blob';
    xhr.send();
}