/**
 * Created by Davor on 13.6.2017..
 */
function initializeElementCkeditor(elem, enableItems, overrideDefault) {
    if (!elem.hasClass("ckeditor-initialized")) {

        if (enableItems == undefined) {
            enableItems = [];
        }
        if (overrideDefault == undefined) {
            overrideDefault = false;
        }

        var items;
        if (overrideDefault) {
            items = enableItems;
        } else {
            items = jQuery.merge([
                'Quote',
                'Paste',
                'Source',
                'Print',
                'Find',
                'Bold',
                'Italic',
                'Underline',
                'NumberedList',
                'BulletedList',
                'JustifyLeft',
                'JustifyCenter',
                'JustifyRight',
                'JustifyBlock',
                'Image',
                'Shape Upload',
                'Link',
                'Youtube',
                'Iframe',
                'Table',
                'HorizontalRule',
                'Styles',
                'Format',
                'Font',
                'FontSize',
                'TextColor',
                'BGColor',
                'Maximize',
                'Print - add page break'
            ], enableItems);
        }

        if (CKEDITOR.instances[elem.attr('name')] !== undefined) {
            CKEDITOR.instances[elem.attr('name')].destroy();
        }
        CKEDITOR.replace(elem.attr('name'), {
            allowedContent: true,
            removeFormatAttributes: '',
            height: elem.data("expand-on-focus") == true ? 100 : 300,
            toolbar: [
                {
                    name: 'document',
                    items: items
                }
            ],
            on: {
                instanceReady: function (evt) {
                    evt.editor.setData(elem.val());
                    evt.editor.on('change', function () {
                        elem.val(evt.editor.getData())
                    });
                    if (elem.data("expand-on-focus") == true) {
                        evt.editor.on('focus', function () {
                            evt.editor.resize('100%', '200', true);
                        });
                        evt.editor.on('blur', function () {
                            evt.editor.resize('100%', '100', true);
                        });
                    }
                }
            }
        });
        elem.addClass("ckeditor-initialized");
    }
}

function initializeCkeditor(form, enableItems, overrideDefault) {
    if (enableItems == undefined) {
        enableItems = [];
    }
    if (overrideDefault == undefined) {
        overrideDefault = false;
    }
    form.find('[data-type="ckeditor"]').each(function (e) {
        initializeElementCkeditor(jQuery(this), enableItems, overrideDefault)
    });
}

function removeFieldValidation(field) {
    if (field.data('fv-field')) {
        jQuery('[data-validate="true"]').data('formValidation').removeField(field);
    }
    return false;
}

function removeFieldsValidation(wrapper) {
    wrapper.find(':input').each(function () {
        removeFieldValidation(jQuery(this));
    });
}

function checkIfFileFieldNeeded() {
    var val = jQuery('[data-action="change_frontend_type"]').val();
    var options = jQuery('[data-action="change_frontend_type"]').data('options');
    var input = options[val].input;

    if (input == "file") {
        jQuery('[name="folder"]').parents('.form-group').show();
        jQuery('[name="folder"]').removeAttr('disabled');
    } else {
        jQuery('[name="folder"]').parents('.form-group').hide();
        jQuery('[name="folder"]').attr('disabled', 'disabled');
    }

    return false;
}

function checkIfLookupFieldsNeeded() {

    var val = jQuery('[data-action="change_frontend_type"]').val();
    var options = jQuery('[data-action="change_frontend_type"]').data('options');
    var input = options[val].input;


    if (input == "select" || input == "lookup" || input == "reverse_lookup") {
        jQuery('[name="lookupEntityType"]').parents('.form-group').show();
        jQuery('[name="lookupEntityType"]').removeAttr('disabled');
        jQuery('[name="lookupAttributeSet"]').parents('.form-group').show();
        jQuery('[name="lookupAttributeSet"]').removeAttr('disabled');
        jQuery('[name="lookupAttribute"]').parents('.form-group').show();
        jQuery('[name="lookupAttribute"]').removeAttr('disabled');
        jQuery('[name="enableModalCreate"]').parents('.form-group').show();
        jQuery('[name="enableModalCreate"]').removeAttr('disabled');
        jQuery('[name="modalPageBlockId"]').parents('.form-group').show();
        jQuery('[name="modalPageBlockId"]').removeAttr('disabled');
    } else {
        jQuery('[name="lookupEntityType"]').parents('.form-group').hide();
        jQuery('[name="lookupEntityType"]').attr('disabled', 'disabled');
        jQuery('[name="lookupAttributeSet"]').parents('.form-group').hide();
        jQuery('[name="lookupAttributeSet"]').attr('disabled', 'disabled');
        jQuery('[name="lookupAttribute"]').parents('.form-group').hide();
        jQuery('[name="lookupAttribute"]').attr('disabled', 'disabled');
        jQuery('[name="enableModalCreate"]').parents('.form-group').hide();
        jQuery('[name="enableModalCreate"]').attr('disabled', 'disabled');
        jQuery('[name="modalPageBlockId"]').parents('.form-group').hide();
        jQuery('[name="modalPageBlockId"]').attr('disabled', 'disabled');
    }

    return false;
}

function checkIfFrontendModelFieldNeeded() {
    var val = jQuery('[data-action="change_frontend_type"]').val();
    var options = jQuery('[data-action="change_frontend_type"]').data('options');
    var input = options[val].input;

    if (input == "lookup") {
        jQuery('[name="frontendModel"]').parents('.form-group').show();
        jQuery('[name="frontendModel"]').removeAttr('disabled');
    } else {
        jQuery('[name="frontendModel"]').parents('.form-group').hide();
        jQuery('[name="frontendModel"]').attr('disabled', 'disabled');
    }

    return false;
}

function checkIfSelectOptionsNeeded() {
    var select_options_wrapper = jQuery('[data-wrapper="select_options"]');
    var select_options_holder = jQuery('[data-holder="select_options"]');
    var id = null;

    var val = jQuery('[data-action="change_frontend_type"]').val();
    var options = jQuery('[data-action="change_frontend_type"]').data('options');
    var input = options[val].input;

    if (jQuery('[name="id"]').length > 0) {
        id = jQuery('[name="id"]').val();
    }

    if (input == "select") {

        jQuery.post(select_options_holder.data("url"), {id: id}, function (result) {
            if (result.error == false) {
                select_options_holder.html(result.html);
                select_options_wrapper.show();
            } else {
                select_options_holder.html('');
                select_options_wrapper.hide();
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    } else {
        select_options_holder.html('');
        select_options_wrapper.hide();
    }
    return false;
}

/**
 * changeBlockType
 */
function changeBlockType(form, elem) {

    var selected_type = elem.val();

    /*var attributeSet = elem.find(':selected').data('attribute-set');
     if(attributeSet){
     form.find('select[name="attributeSet"]').removeAttr('disabled').parents('.form-group').show();
     form.find('input[name="attributeSet"]').parents('.form-group').show();
     }
     else{
     form.find('[name="attributeSet"]').parents('.form-group').hide();
     form.find('[name="attributeSet"]').val('').attr('disabled','disabled');
     }*/

    /*var relatedId = elem.find(':selected').data('related-id');
     if(relatedId){
     form.find('select[name="relatedId"]').removeAttr('disabled').parents('.form-group').show();
     form.find('input[name="relatedId"]').parents('.form-group').show();
     }
     else{
     form.find('[name="relatedId"]').parents('.form-group').hide();
     form.find('[name="relatedId"]').val('').attr('disabled','disabled');
     }*/

    /*var listView = elem.find(':selected').data('list-view');
     if(listView){
     form.find('[name="listView[]"]').removeAttr('disabled').parents('.form-group').show();
     form.find('[name="listView[]"]').parents('.form-group').show();
     }
     else{
     form.find('[name="listView[]"]').parents('.form-group').hide();
     }*/

    /*var content = elem.find(':selected').data('content');
     if(content){
     form.find('[data-wrapper="content"]').show();
     getAvailableBlocks('attribute_set','page_block',form.find('select[name="attributeSet"]').val(),form.find('[name="id"]').val());
     getAvailableBlocks('other','page_block',form.find('select[name="attributeSet"]').val(),form.find('[name="id"]').val());
     }
     else{
     form.find('[data-wrapper="content"]').hide();
     }*/

    return false;
}

/**
 *
 * @param type
 * @param source
 * @param attribute_set
 * @param block_id
 */

/*function getAvailableBlocks(type,source,attribute_set,block_id) {

 jQuery.post(jQuery('.sp-available-blocks-wrapper').data("url"), { type: type, source: source, attribute_set: attribute_set, id: block_id  }, function(result) {
 if(result.error == false){
 if(type == "attribute_set"){
 jQuery('.sp-same-attrset-block-wrapper').html(result.html);
 }
 else{
 jQuery('.sp-other-attrset-block-wrapper').html(result.html);
 }
 }
 else{
 jQuery.growl.error({
 title: translations.error_message,
 message: result.message
 });
 }
 }, "json");
 }*/

/**
 *
 * @param list_view_id
 * @param attribute_type
 * @param destination
 */
function getListViewAttributes(list_view_id, attribute_type, destination, defaultOption) {
    jQuery.post(
        jQuery('.sp-available-attributes-wrapper').data("url"),
        {
            list_view_id: list_view_id,
            attribute_type: attribute_type,
            is_custom: 1,
            default_option: defaultOption
        },
        function (result) {
            if (result.error == false) {
                jQuery(destination).html(result.html);
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        },
        "json"
    );
}

/**
 * @param child
 * @param reset
 * @returns {boolean}
 */
function setGridStackHeight(child, reset) {
    if (reset === undefined) {
        reset = false;
    }
    var gridElem = child.closest('.grid-stack');
    var grid = gridElem.data('gridstack');
    var gridItem = child.closest('.grid-stack-item');

    var newHeight = 2;
    if (!reset) {
        if (child.data("new-height")) {
            newHeight = child.data("new-height");
        } else {
            newHeight = Math.round((child.outerHeight(true) + grid.opts.verticalMargin) / (grid.cellHeight() + grid.opts.verticalMargin));
            newHeight += child.find('>.grid-stack').data("gs-current-height") + 1;
        }
    }

    grid.resize(gridItem, null, newHeight);

    if (gridElem.closest('.grid-stack-inner-wrapper').length > 0) {
        setGridStackHeight(gridElem.closest('.grid-stack-inner-wrapper'));
    }

    return true;
}

/**
 *
 * @param id
 * @param parent_id
 * @returns {boolean}
 */
function getBlock(id, parent_id) {

    var grid = null;

    var is_front = false;
    var url = null;
    if (jQuery('body').find('.sp-block-group-wrapper').length > 0) {
        is_front = true;
        url = jQuery('body').find('.sp-block-group-wrapper').data("url");
    } else {
        if (parent_id) {
            if (jQuery('body').find('[data-block-id="' + parent_id + '"]').length > 0) {
                grid = jQuery('body').find('[data-block-id="' + parent_id + '"]').find('.grid-stack');
            }
        }

        if (!grid) {
            grid = jQuery('body').find('.sp-grid-wrapper-inner').find('.grid-stack:first');
        }

        url = grid.data("url");
    }

    jQuery.post(url, {id: id, is_front: is_front}, function (result) {
        if (result.error == false) {

            var el = jQuery(result.html);
            var new_id = el.data('block-id');

            if (is_front) {
                jQuery('body').find('.sp-block-group-wrapper > .row:last-child').first().append(result.html);
            } else {
                var grids = grid.data('gridstack');
                grids.addWidget(result.html, 10, 10, 4, 4, true);
            }

            var block = jQuery('body').find('[data-block-id="' + new_id + '"]');

            if (is_front) {
                block.find('[data-action="edit-block-modal-front"]').trigger("click");
            } else {
                block.find('[data-action="edit-block-modal"]').trigger("click");
            }
        } else {
            jQuery.growl.error({
                title: translations.error_message,
                message: result.message
            });
        }
    }, "json");

    return false;
}

/**
 *
 * @param form
 * @param result
 */
function addBlock(form, result) {
    getBlock(result.entity.id, form.find('[name="parent_id"]').val());
    return false;
}

function reloadPage(form, result) {
    location.reload(true);
    return false;
}

/**
 * getRelatedIdList
 */
function getRelatedIdList(form, elem) {

    var related_type = 1;

    jQuery.post(elem.data("url"), {
        type: form.find('[name="type"]').val(),
        attributeSet: form.find('[name="attributeSet"]').val(),
        related_type: related_type
    }, function (result) {
        if (result.error == false) {
            form.find('[name="relatedId"]').html(result.html);
        } else {
            jQuery.growl.error({
                title: translations.error_message,
                message: result.message
            });
        }
    }, "json");

    return false;
}

/**
 * getLookupAttributes
 */
function getLookupAttributes(form, elem) {

    var related_type = 1;

    jQuery.post(
        elem.data("url"),
        {
            type: form.find('[name="type"]').val(),
            attributeSet: form.find('[name="attributeSet"]').val(),
            related_type: related_type
        },
        function (result) {
            if (result.error == false) {
                elem.html(result.html);
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        },
        "json"
    );

    return false;
}

/**
 * remove unavabile block types
 * @param form
 * @param type
 */
/*function setAvailableBlockTypes(form,type) {

 form.find('[name="type"] > option').each(function (e) {
 if(jQuery(this).data('is_available_in_'+type) === 0){
 jQuery(this).remove();
 }
 });
 }*/

/**
 * LIVE
 */
var url_params = {};

jQuery(document).ready(function () {

    /**
     * Prepopulate form fields
     */
    if (window.location.search) {
        var sPageURL = decodeURIComponent(window.location.search.substring(1)),
            sURLVariables = sPageURL.split('&'),
            sParameterName,
            tmpVal,
            i;

        var form = jQuery('body').find('[data-validate="true"]');

        for (i = 0; i < sURLVariables.length; i++) {
            sParameterName = sURLVariables[i].split('=');

            //url_params[sParameterName[0]] = sParameterName[1];

            if (form.find('[name="' + sParameterName[0] + '"]').length > 0) {

                tmpVal = sParameterName[1].split('\\');
                if (!tmpVal[1]) {
                    tmpVal[1] = "";
                }

                var elem = form.find('[name="' + sParameterName[0] + '"]');
                if (elem.data('type') == "lookup") {
                    elem.html("<option value='" + tmpVal[0] + "'>" + tmpVal[1] + "</option>");
                    elem.val(tmpVal[0]).trigger('change');
                } else {
                    elem.val(tmpVal[0]).trigger('change');
                }

                //elem.parents(".form-group").hide();
            }

            /*if (jQuery("input[name='" + sParameterName[0] + "']").length) {
                jQuery("input[name='" + sParameterName[0] + "']").val(sParameterName[1]).parents(".form-group").hide();
            }*/
            /*if (jQuery("select[name='" + sParameterName[0] + "']").length) {
                jQuery("select[name='" + sParameterName[0] + "']").val(sParameterName[1]).trigger('change');
                if (jQuery("select[name='" + sParameterName[0] + "']").find("[value='" + sParameterName[1] + "']").length == 0) {
                    jQuery("select[name='" + sParameterName[0] + "']").html("<option value='" + sParameterName[1] + "'></option>");
                }
                //jQuery("select[name='" + sParameterName[0] + "']").val(sParameterName[1]).parents(".form-group").hide();
            }*/
        }
    }

    if (jQuery('.sort-wrapper').length > 0) {
        jQuery('.sort-wrapper').sortable();
    }

    /**
     * Block from
     */

    // var initBlockFormBehaviours = function(modalForm) {
    //     var form;
    //     if(modalForm){
    //         form = modalForm;
    //     }else{
    //         form = jQuery('[name="type"]').parents('form');
    //     }

    /*changeBlockType(form, form.find('[name="type"]'));

     form.on('change','[name="type"]',function (e) {
     var form = jQuery(this).parents('form');
     changeBlockType(form,jQuery(this));
     getRelatedIdList(form,form.find('[name="attributeSet"]'));
     });*/

    // form.on('change','[name="attributeSet"]',function (e) {
    //     var form = jQuery(this).parents('form');
    //     getRelatedIdList(form,jQuery(this));
    //     if(jQuery('[name="type"]').val() == "related_list_view"){
    //         if(jQuery(this).val()){
    //             getLookupAttributes(form, jQuery('[name="prepopulateLookupAttributes"]'));
    //             form.find('select[name="prepopulateLookupAttributes"]').removeAttr('disabled').parents('.form-group').show();
    //             form.find('input[name="prepopulateLookupAttributes"]').parents('.form-group').show();
    //         }
    //         else{
    //             form.find('[name="prepopulateLookupAttributes"]').parents('.form-group').hide();
    //             form.find('[name="prepopulateLookupAttributes"]').val('').attr('disabled','disabled');
    //         }
    //     }
    // });

    /*form.on('click','[data-action="add-existing-block"]',function (e) {
     getBlock(jQuery(this).data('id'));
     jQuery(this).hide();
     });*/
    // };

    /**
     * Automatically set title if type is container
     */
    jQuery('body').on('change', '[data-action="block_type"]', function (e) {
        var selected_val = jQuery(this).val();
        var form = jQuery(this).closest('[data-type="page_block"]');
    });

    jQuery('body').on('click', '[data-action="edit-block-modal"]', function (e) {
        jQuery.post(jQuery(this).data("url"), {}, function (result) {
            if (result.error == false) {
                var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                clone.html(result.html);
                var modal = clone.find('.modal');
                modal.modal('show');

                var form = modal.find('[data-validate="true"]');
                form.initializeValidation();
                //initBlockFormBehaviours(form);
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    /**
     * Mark block to be moved
     */
    jQuery('body').on('click', '[data-action="move-block"]', function (e) {
        var elem = jQuery(this).closest('.grid-stack-item');
        elem.toggleClass('sp-move-selected');
        toggleDropBlock();
    });

    /**
     * Mark block for editing
     */
    jQuery('body').on('click', '[data-action="edit-block"]', function (e) {
        var elem = jQuery(this).closest('.grid-stack-item');
        elem.toggleClass('sp-edit-selected');
        elem.find(".sp-inline-edit-wrapper").slideToggle(function () {
            setGridStackHeight($(this), !elem.hasClass("sp-edit-selected"));
            if (elem.hasClass("sp-edit-selected")) {
                $.scrollToTop(elem);
            }
        });
    });

    /**
     * Place block in grid
     */
    jQuery('body').on('click', '[data-action="drop-block"]', function (e) {

        var parent = jQuery(this).closest('.grid-stack-item');
        var newGridsWrapper = null;

        if (parent.length > 0) {
            newGridsWrapper = parent.children('.grid-stack-item-content').children('.grid-stack-inner-wrapper').children('.grid-stack');
        } else {
            newGridsWrapper = jQuery('.sp-grid-wrapper-inner').find('.grid-stack:first');
        }

        jQuery('body').find('.sp-move-selected').each(function (e) {
            var block = jQuery(this);
            if (parent.length == 0 && block.closest('.grid-stack-inner-wrapper').length == 0) {
                block.removeClass('sp-move-selected');
                jQuery.growl.error({
                    title: translations.error_message,
                    message: "Selected block already has the same parent"
                });
                return true;
            } else if (parent.data('block-id') == block.data('block-id')) {
                block.removeClass('sp-move-selected');
                jQuery.growl.error({
                    title: translations.error_message,
                    message: "Selected block same as parent"
                });
                return true;
            } else if (parent.data('block-id') == block.closest('.grid-stack-item').parent().closest('.grid-stack-item').data('block-id')) {
                block.removeClass('sp-move-selected');
                jQuery.growl.error({
                    title: translations.error_message,
                    message: "Selected block already has the same parent"
                });
                return true;
            } else if (block.find('[data-block-id="' + parent.data('block-id') + '"]').length > 0) {
                block.removeClass('sp-move-selected');
                jQuery.growl.error({
                    title: translations.error_message,
                    message: "Selected parent is a child of this block"
                });
                return true;
            }

            /** Remove block **/
            var oldGrids = block.closest('.grid-stack').data('gridstack');
            oldGrids.removeWidget(block);

            /** Add block to new grid */
            block.appendTo(newGridsWrapper);

            var newGrids = newGridsWrapper.data('gridstack');
            newGrids.makeWidget(block);

            block.removeClass('sp-move-selected');
        });

        toggleDropBlock();
    });

    function toggleDropBlock() {
        if (jQuery('body').find('.sp-move-selected').length > 0) {
            jQuery('body').find('[data-action="drop-block"]').show();
        } else {
            jQuery('body').find('[data-action="drop-block"]').hide();
        }
        return true;
    }

    jQuery('body').on('click', '[data-action="remove-block"]', function (e) {

        var elem = jQuery(this).closest('.grid-stack-item');

        jQuery.post(jQuery(this).data("url"), {}, function (result) {
            if (result.error == false) {
                var grids = elem.closest('.grid-stack').data('gridstack');
                grids.removeWidget(elem);
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    jQuery('body').on('click', '[data-action="add-block"]', function (e) {
        jQuery.post(jQuery(this).data("url"), {}, function (result) {
            if (result.error == false) {
                var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                clone.html(result.html);
                var modal = clone.find('.modal');
                modal.modal('show');

                var form = modal.find('[data-validate="true"]');
                form.initializeValidation();
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    /**
     * Initialize page and page block from
     */
    if (jQuery('body').find('[data-action="grid_stack"]').length > 0) {
        jQuery('body').find('[data-action="grid_stack"]').each(function (e) {
            var options = {
                cellHeight: 50,
                verticalMargin: 15,
                animate: false,
                removable: true,
                removeTimeout: 2000,
                resizable: {
                    autoHide: true,
                    handles: 'w,e,s,sw'
                }
            };
            jQuery(this).gridstack(options);
        });
    }

    /**
     * Initialize inner gridstack
     */
    if (jQuery('body').find('[data-action="grid_stack_inner"]').length > 0) {
        var grids = [];
        jQuery('body').find('[data-action="grid_stack_inner"]').each(function (e) {
            var options = {
                cellHeight: 50,
                verticalMargin: 15,
                animate: false,
                removable: true,
                removeTimeout: 2000,
                resizable: {
                    autoHide: true,
                    handles: 'w,e,s,sw'
                }
            };
            jQuery(this).gridstack(options);
            grids.push(jQuery(this));
        });
        $.each(grids.reverse(), function (index, grid) {
            setGridStackHeight(grid.closest('.grid-stack-inner-wrapper'));
        });


        jQuery('.grid-stack').on('gsresizestop', function (event, elem) {
            if (jQuery(elem).closest('.grid-stack-inner-wrapper').length > 0) {
                setGridStackHeight(jQuery(elem).closest('.grid-stack-inner-wrapper'));
            }
        });

        jQuery('.grid-stack').on('change', function (event, items) {
            var elem = event.target;
            if (jQuery(elem).closest('.grid-stack-inner-wrapper').length > 0) {
                setGridStackHeight(jQuery(elem).closest('.grid-stack-inner-wrapper'));
            }
        });

        jQuery('.grid-stack').on('resizestart', function (event, elem) {
            if (jQuery(elem).closest('.grid-stack-inner-wrapper').length > 0) {
                setGridStackHeight(jQuery(elem).closest('.grid-stack-inner-wrapper'));
            }
        });

        jQuery('.grid-stack').on('dragstop', function (event, ui) {
            var elem = event.target;
            if (jQuery(elem).closest('.grid-stack-inner-wrapper').length > 0) {
                setGridStackHeight(jQuery(elem).closest('.grid-stack-inner-wrapper'));
            }
        });

        jQuery('body').find('.grid-stack').each(function (e) {
            jQuery(this).trigger('dragstop');
            if (jQuery(this).closest('.grid-stack').length > 0) {
                jQuery(this).closest('.grid-stack').trigger('dragstop');
            }
        });
    }

    /**
     * Page from
     */
    /*if (jQuery('#page').length > 0) {

        if (jQuery('.grid-stack').length > 0) {

            var grid = jQuery('.grid-stack');

            var options = {
                //cellHeight: 50,
                verticalMargin: 15,
                animate: true,
                removable: true,
                removeTimeout: 2000,
                resizable: {autoHide: true, handles: 'se,sw,e,s'},
            };
            grid.gridstack(options);
        }
    }*/

    /**
     * Navigation
     */
    if (jQuery('#navigation_link').length > 0) {
        var iconPickerOpt = {cols: 5, searchText: "Search...", labelHeader: '{0} of {1} pagess.', footer: false};
        var options = {
            hintCss: {'border': '1px dashed #13981D'},
            placeholderCss: {'background-color': 'gray'},
            ignoreClass: 'btn',
            opener: {
                active: true,
                as: 'html',
                close: '<i class="fa fa-minus"></i>',
                open: '<i class="fa fa-plus"></i>',
                openerCss: {'margin-right': '10px'},
                openerClass: 'btn btn-success btn-xs'
            }
        };
        var editor = new MenuEditor('menuEditor', {
            listOptions: options,
            iconPicker: iconPickerOpt,
            labelEdit: 'Edit',
            labelRemove: 'Remove'
        });
        editor.setForm(jQuery('#frmEdit'));
        editor.setUpdateButton(jQuery('#btnUpdate'));

        jQuery('#btnOut').on('click', function () {
            var str = editor.getString();
            jQuery('[name="navigation_json"]').text(str);
        });
        jQuery("#btnUpdate").click(function () {
            editor.update();
        });
        jQuery('#btnAdd').click(function () {
            editor.add();
        });

        editor.setData(jQuery('[name="navigation_json"]').text());
    }

    /**
     * Toggle dropzone
     */
    jQuery('body').on('click', '[data-action="toggle-column"]', function (e) {
        var wrapper = jQuery(this).parents('.sp-sortable-group-inner');
        if (wrapper.hasClass('open')) {
            wrapper.toggleClass('open');
            jQuery(this).children('i').toggleClass('fa-arrow-down').toggleClass('fa-arrow-up');
        } else {
            wrapper.toggleClass('open');
            jQuery(this).children('i').toggleClass('fa-arrow-down').toggleClass('fa-arrow-up');
        }

        return false;
    });

    /**
     * Entity forma
     */
    /**
     * Change column size
     */
    jQuery('body').on('click', '[data-action="regenerate_all_entity_types"]', function (e) {
        jQuery.post(jQuery(this).data("url"), {}, function (result) {
            if (result.error == false) {
                jQuery.growl.notice({
                    title: result.title,
                    message: result.message
                });
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    /*jQuery('body').on('click','[data-action="change-column-size"]', function(e){
     var wrapper = jQuery(this).parents('.sp-sortable-group');
     var val = jQuery(this).val();

     var classes = wrapper.attr("class").split(' ');
     jQuery.each(classes, function(i, c) {
     if (c.indexOf("col-md") == 0 || c.indexOf("col-sm") == 0) {
     wrapper.removeClass(c);
     }
     });
     wrapper.addClass('col-md-'+val+' col-sm-'+val);

     return false;
     });*/

    /**
     * Attribute forma
     */
    if (jQuery('[data-type="attribute"]').length > 0) {

        /**
         * Double click to add validator
         */
        jQuery('body').on('dblclick', '.sp-validator-help', function (e) {
            var form = jQuery(this).parents('form');
            form.find('[name="validator"]').text(jQuery(this).text());
        });

        jQuery('body').on('change', '[data-action="change_frontend_type"]', function (e) {
            var val = jQuery(this).val();
            var related_select = jQuery('[name="' + jQuery(this).data('related') + '"]');
            var options = jQuery(this).data('options');
            var attributeId = jQuery(this).data("attribute-id");

            jQuery.post(jQuery(this).data("custom-admin-url"), {
                type: val,
                attributeId: attributeId
            }, function (result) {
                if (result != "") {
                    jQuery('.custom-admin').html(result);
                    jQuery('.sp-attribute-additional').show();
                } else {
                    jQuery('.sp-attribute-additional').hide();
                }
            }, "html");

            var options_html = "";
            if (options[val]) {
                options_html += '<option value="' + options[val].input + '">' + options[val].input + '</option>';
            }

            related_select.html(options_html);

            checkIfFileFieldNeeded();
            checkIfLookupFieldsNeeded();
            checkIfFrontendModelFieldNeeded();
            checkIfSelectOptionsNeeded();

            return false;
        });

        jQuery('[data-action="change_frontend_type"]').trigger('change');

        jQuery('body').on('click', '[data-action="add_option"]', function (e) {
            e.stopPropagation();
            var select_options_holder = jQuery('[data-holder="select_options"]');

            jQuery.post(jQuery(this).data("url"), {}, function (result) {
                if (result.error == false) {
                    select_options_holder.append(result.html);
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, "json");

            return false;
        });

        jQuery('body').on('change', '[data-action="change-lookup-type"]', function (e) {
            var val = jQuery(this).val();
            var related_select = jQuery('[name="' + jQuery(this).data('related') + '"]');
            var options = jQuery(this).data('options');

            var options_html = "";
            if (options[val]) {
                jQuery.each(options[val].attribute_sets, function (key, value) {
                    options_html += '<option value="' + key + '">' + value.name + '</option>';
                });
            }
            //options_html = '<option value="">Select lookup attribute set</option>'+options_html;

            related_select.html(options_html).trigger('change');

            return false;
        });

        jQuery('body').on('change', '[data-action="change-lookup-set"]', function (e) {
            var val = jQuery(this).val();
            var related_select = jQuery('[name="' + jQuery(this).data('related') + '"]');
            var related_select2 = jQuery('[name="' + jQuery(this).data('related2') + '"]');
            var options = jQuery('[data-action="change-lookup-type"]').data('options');

            var options_html = "";
            if (val && options[jQuery('[data-action="change-lookup-type"]').val()]) {
                jQuery.each(options[jQuery('[data-action="change-lookup-type"]').val()].attribute_sets[val].attributes, function (key, value) {
                    options_html += '<option value="' + key + '">' + value.name + '</option>';
                });
            }
            related_select.html(options_html);

            options_html = "";
            //options_html = '<option value="">Select page block id or leave empty for default</option>'+options_html;
            if (val && options[jQuery('[data-action="change-lookup-type"]').val()]) {
                jQuery.each(options[jQuery('[data-action="change-lookup-type"]').val()].attribute_sets[val].blocks, function (key, value) {
                    options_html += '<option value="' + key + '">' + value.name + '</option>';
                });
            }
            related_select2.html(options_html);

            return false;
        });

        function setBackendModel() {
            var input_type = jQuery('[name="frontendInput"]').val();
            var entity_type = jQuery('[name="entityType"]').val();
            var eav = 0;
            var options_html = "";
            var related_select = jQuery('[name="backendModel"]');

            if (input_type == 0 || entity_type == 0) {
                options_html = '<option value="">Select backend model</option>' + options_html;
                related_select.html(options_html);
                return false;
            }

            var options = related_select.data('options');
            if (options[entity_type][input_type][eav]) {
                jQuery.each(options[entity_type][input_type][eav], function (key, value) {
                    options_html += '<option value="' + value + '">' + value + '</option>';
                });
            } else {
                //jQuery('[name="isEav"]').val(0).trigger('change');
                return false;
            }

            //options_html = '<option value="">Select backend model</option>'+options_html;

            related_select.html(options_html);

            return false;
        }

        function setBackendType() {
            var input_type = jQuery('[name="frontendInput"]').val();
            var eav = 0;
            var backend_model = jQuery('[name="backendModel"]').val();
            var options_html = "";
            var related_select = jQuery('[name="backendType"]');

            if (input_type == 0 || backend_model == 0) {
                options_html = '<option value="">Select backend type</option>' + options_html;
                related_select.html(options_html);
                return false;
            }

            var options = related_select.data('options');
            /*if(eav > 0){
             backend_model = backend_model.split('Entity');
             backend_model = backend_model[1];
             if(options[input_type][eav]){
             jQuery.each(options[input_type][eav][backend_model],function (key, value) {
             options_html+='<option value="'+value+'">'+value+'</option>';
             });
             }
             }
             else{*/
            if (options[input_type][eav]) {
                jQuery.each(options[input_type][eav], function (key, value) {
                    options_html += '<option value="' + value + '">' + value + '</option>';
                });
            }
            //}

            //options_html = '<option value="">Select backend type</option>'+options_html;

            related_select.html(options_html);

            return false;
        }

        function setDefaultAttributeSet() {
            var entity_type = jQuery('[name="entityType"]').val();
            var options_html = "";
            var related_select = jQuery('[name="attributeSet"]');

            jQuery.post(related_select.data("url"), {id: entity_type}, function (result) {
                if (result.error == false) {
                    related_select.html(result.html);
                } else {
                    related_select.html(options_html);
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
                setDefaultAttributeGroup();
            }, "json");


            return false;
        }

        function setDefaultAttributeGroup() {
            var attribute_set = jQuery('[name="attributeSet"]').val();
            var options_html = "";
            var related_select = jQuery('[name="attributeGroup"]');

            jQuery.post(related_select.data("url"), {id: attribute_set}, function (result) {
                if (result.error == false) {
                    related_select.html(result.html);
                } else {
                    related_select.html(options_html);
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, "json");


            return false;
        }

        jQuery('body').on('change', '[data-action="change_entity_type"]', function (e) {
            /*            setBackendModel();
                        setBackendType();*/
            if (jQuery('[data-action="change_default_attribute_set"]').length > 0) {
                setDefaultAttributeSet();
            }
        });

        jQuery('body').on('change keydown paste input', '[name="frontendLabel"]', function () {
            var code = jQuery('[name="frontendLabel"]').val().toLowerCase();
            code = code.replace(/ /g, '_');
            jQuery('[name="attributeCode"]').val(code);
        });


        jQuery('body').on('change', '[data-action="change_backend_model"]', function (e) {
            setBackendType();
        });

        jQuery('body').on('change', '[data-action="change_default_attribute_set"]', function (e) {
            setDefaultAttributeGroup();
        });

        jQuery('body').on('click', '[data-action="remove_option"]', function (e) {
            e.stopPropagation();
            var option = jQuery(this).parents('.sp-sortable-group');

            if (window.confirm(jQuery(this).data('confirm'))) {
                option.remove();
            }
        });
    }

    /**
     * Log view
     */
    jQuery('body').on('click', '[data-action="restore"]', function (e) {
        e.stopPropagation();
        var id = jQuery(this).data('id');

        if (window.confirm(jQuery(this).data('confirm'))) {
            jQuery.post(jQuery(this).data("url"), {id: id}, function (result) {
                if (result.error == false) {
                    jQuery.growl.notice({
                        title: result.title,
                        message: result.message
                    });

                    location.reload();
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, "json");
        }
    });

    /**
     * List view edit/create
     */
    if (jQuery('[data-type="list_view"]').length > 0 || jQuery('[data-type="attribute_group"]').length > 0) {

        function showHideListViewAvilableAttributes() {
            var used_list = [];
            jQuery('[data-holder="list_attributes"]').find('.sp-sortable-group').each(function () {
                used_list.push(jQuery(this).data('attribute-id'));
            });

            jQuery.each(used_list, function (key, id) {
                jQuery('[data-holder="available_attributes"]').find('[data-attribute-id="' + id + '"]').hide();
            });

            return false;
        }

        function reloadDefaultSortOptions() {
            var select = jQuery('[name="defaultSort"]');
            var selected = select.val();
            select.html('');
            var regex = /(<([^>]+)>)/ig


            var options_html = "";
            jQuery.each(jQuery('[data-holder="list_attributes"]').find('.sp-sortable-group'), function () {
                var text = jQuery(this).find('.sp-column-name').text();
                text = text.replace(regex, "");
                options_html += '<option value="' + jQuery(this).data('attribute-id') + '">' + jQuery.trim(text) + '</option>';
            });

            select.html(options_html);
            select.val(selected);
            if (!select.val()) {
                select.val(select.find("option:first").val());
            }

            return false;
        }

        showHideListViewAvilableAttributes();

        jQuery('body').on('click', '[data-action="remove_attribute"]', function (e) {
            e.stopPropagation();
            var id = jQuery(this).parents('.sp-sortable-group').data('attribute-id');

            if (window.confirm(jQuery(this).data('confirm'))) {
                jQuery('[data-holder="available_attributes"]').find('[data-attribute-id="' + id + '"]').show();
                removeFieldsValidation(jQuery(this).parents('.sp-sortable-group'));
                jQuery(this).parents('.sp-sortable-group').remove();
                reloadDefaultSortOptions();
            }
        });

        function reloadAvailableAttributes() {
            jQuery.post(jQuery('[data-action="change_attribute_set"]').data("url"), {id: jQuery('[data-action="change_attribute_set"]').val()}, function (result) {
                if (result.error == false) {
                    jQuery('[data-holder="available_attributes"]').html(result.html);
                    jQuery('[data-holder="list_attributes"]').html('');
                    jQuery('#builder-basic').data("filters", result.filters);

                    reloadDefaultSortOptions();
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, "json");
        }

        jQuery('body').on('change', '[data-action="change_attribute_set"]', function (e) {
            e.stopPropagation();
            reloadAvailableAttributes();
        });

        jQuery('body').on('change', '[data-action="change_entity_type"]', function (e) {
            e.stopPropagation();
            jQuery.post(jQuery(this).data("url"), {id: jQuery(this).val()}, function (result) {
                if (result.error == false) {
                    jQuery('[data-action="change_attribute_set"]').html(result.html);
                    reloadAvailableAttributes();
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, "json");
        });

        jQuery('body').on('click', '[data-action="add_attribute"]', function (e) {
            e.stopPropagation();
            var wrapper = jQuery(this).parents('.sp-sortable-group');
            var id = wrapper.data('attribute-id');

            jQuery.post(jQuery(this).parents('[data-holder="available_attributes"]').data("url"), {id: id}, function (result) {
                if (result.error == false) {
                    jQuery('[data-holder="list_attributes"]').append(result.html);
                    wrapper.hide();
                    jQuery('[data-holder="list_attributes"]').find('[data-type="bchackbox"]').bootstrapSwitch();
                    reloadDefaultSortOptions();
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, "json");
        });
    }

    /**
     * List view edit/create
     */
    if (jQuery('[data-type="attribute_set"]').length > 0 || jQuery('[data-type="entity_type"]').length > 0) {

        jQuery('body').on('click', '[data-action="remove_attribute_group"]', function (e) {
            e.stopPropagation();

            var attribute_group_wrapper = jQuery(this).parents('[data-holder="attribute_group"]');
            var id = attribute_group_wrapper.data('id');
            var unused_wrapper = jQuery(this).parents('[data-holder="layout_type"]').find('[data-holder="unused_attribute_groups"]');

            if (window.confirm(jQuery(this).data('confirm'))) {
                removeFieldsValidation(jQuery(this).parents('[data-holder="attribute_group"]'));
                attribute_group_wrapper.remove();
                unused_wrapper.find('[data-id="' + id + '"]').show();
            }

            return false;
        });

        jQuery('body').on('click', '[data-action="add_attribute_group"]', function (e) {
            e.stopPropagation();

            var attribute_group_wrapper = jQuery(this).parents('[data-holder="attribute_group"]');
            var id = attribute_group_wrapper.data('id');
            var attribute_set_id = jQuery(this).parents('[data-holder="attribute_set"]').data('id');
            var type = jQuery(this).parents('[data-holder="layout_type"]').data('type');
            var unused_wrapper = jQuery(this).parents('[data-holder="unused_attribute_groups"]');
            var used_wrapper = jQuery(this).parents('[data-holder="layout_type"]').find('[data-holder="used_attribute_groups"]');
            var count = used_wrapper.find('.sort-wrapper').first().children().length;


            jQuery.post(unused_wrapper.data("url"), {
                id: id,
                attribute_set_id: attribute_set_id,
                type: type,
                count: count
            }, function (result) {
                if (result.error == false) {
                    attribute_group_wrapper.hide();
                    used_wrapper.find('.sort-wrapper:first-child').append(result.html);
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, "json");

            return false;
        });

        function showHideListViewAvilableAttributeGroups() {

            jQuery('body').find('[data-holder="attribute_set"]').each(function (e) {
                jQuery(this).find('[data-holder="layout_type"]').each(function (e) {
                    var wrapper = jQuery(this);

                    var used_list = [];
                    wrapper.find('[data-holder="used_attribute_groups"]').find('[data-holder="attribute_group"]').each(function () {
                        used_list.push(jQuery(this).data('id'));
                    });

                    jQuery.each(used_list, function (key, id) {
                        wrapper.find('[data-holder="unused_attribute_groups"]').find('[data-id="' + id + '"]').hide();
                    });
                });
            });

            return false;
        }

        showHideListViewAvilableAttributeGroups();

    }


    /**
     * POS
     */
    var pos = jQuery("#page_131");
    if (pos.length > 0) {
        jQuery('[data-action="pos_generate"]').on('click', function (e) {
            posGenerateQuote();
        });
    }
    /**
     * END POS
     */

    //initGridResize();
    // if(jQuery("body .grid-stack").length == 0){
    //     jQuery(".loading-bar-wrapper").hide();
    //     jQuery("body").removeClass("loading");
    // }
});

function posCreateLead() {
    var form = jQuery("form.account-creation-form");
    var searchFieldVal = form.find('#acc_search').val();
    var toReturn = null;

    if (searchFieldVal === 'new') {
        jQuery.ajax({
            url: form.attr("action"),
            method: "POST",
            async: false,
            data: form.serialize(),
            cache: false
        }).done(function (result) {
            if (result.error == false) {
                toReturn = {
                    id: result.entity.id,
                    account_attr_set: "lead"
                };
            }
        });
    } else {
        toReturn = {
            id: searchFieldVal,
            account_attr_set: "account"
        };
    }
    return toReturn;

}

function posGenerateQuote() {
    var acc = posCreateLead();
    if (acc) {
        // create opportunity
        var data =
            {
                account_id: acc.id,
                account_attr_set: acc.account_attr_set,
                total_qty: jQuery("#calc-total-quantity").val(),
                price_base_total: jQuery("#calc-total-price-no-tax").val(),
                price_tax_total: jQuery("#calc-total-price-tax").val(),
                price_total: jQuery("#calc-total-price").val()
            };

        var products = [];
        jQuery("#editable-table").find("tbody").find("tr").each(function () {
            if (jQuery(this).data("product-id")) {
                var row = jQuery(this);
                var product = {
                    "id": row.data("product-id"),
                    "qty": row.find('[data-attr-code="qty"]').html(),
                    "fixed_discount": row.find('[data-attr-code="fixed_discount"]').html(),
                    "percent_discount": row.find('[data-attr-code="percent_discount"]').html()
                };
                products.push(product);
            }
        });

        jQuery.post(
            "/pos/opportunity/save",
            {
                data: data,
                products: products,
                price_attr: jQuery('.sp-grid-view-wrapper').data("price-attr-code")
            },
            function (result) {
                if (result.error == false) {
                    if (result.error == false) {
                        window.open(result.url, '_blank');
                        location.reload();
                    } else {
                        jQuery.growl.error({
                            title: translations.error_message,
                            message: result.message
                        });
                    }
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            },
            "json"
        );
    }
}

jQuery(document).ready(function () {

    if (jQuery('[name="remote_url"]').length > 0) {
        var remoteURL = sessionStorage.getItem("sync_tool_remote_url");
        if (remoteURL) {
            jQuery('[name="remote_url"]').val(remoteURL);
        }
    }

    jQuery(document).on("dblclick", ".sync-compare-table td", function (e) {
        var clickedTd = jQuery(this);
        var clickedIndex = clickedTd.index();
        var tdValue = clickedTd.text();
        var tr = clickedTd.parent();
        var tbody = tr.parent();
        var checked = tr.find('input[type="checkbox"]').is(':checked');
        jQuery(tbody).find("td:contains(" + tdValue + ")").each(function () {
            var listTr = jQuery(this).parent();
            if (jQuery(this).index() == clickedIndex && jQuery(this).text() == tdValue && tr.attr("class") == listTr.attr("class")) {
                if (checked) {
                    listTr.find('input[type="checkbox"]').prop("checked", false);
                } else {
                    listTr.find('input[type="checkbox"]').prop("checked", true);
                }
            }
        });
    });

    jQuery(".sync_compare").click(function (e) {
        // jQuery.loader({
        //   className: "blue-with-image-2",
        //   content: translations.please_wait
        // });
        $("#ajax-loading").addClass("active");

        var remoteURL = jQuery('[name="remote_url"]').val();

        // Save entered remote URL
        sessionStorage.setItem('sync_tool_remote_url', remoteURL);

        jQuery.ajax({
            type: 'POST',
            url: jQuery(this).data("url"),
            async: true,
            data: {
                compare_type: jQuery('[name="compare_type"]').val(),
                remote_url: remoteURL,
                showChangesOnly: jQuery('[name="showChangesOnly"]').is(":checked")
            }
        }).done(function (result) {
            if (result.error === false) {
                jQuery(".compare_results_container").html(result.html);
                jQuery(".compare_results_container").find("table").DataTable({
                    fixedHeader: true
                });

            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
            // jQuery("#jquery-loader-background").remove();
            // jQuery("#jquery-loader").remove();
            $("#ajax-loading").removeClass("active");
        });
    });

    jQuery(".run_sync").click(function (e) {
        // jQuery.loader({
        //   className: "blue-with-image-2",
        //   content: translations.please_wait
        // });
        $("#ajax-loading").addClass("active");

        var checked = [];
        jQuery("input:checkbox[name='checked_items']:checked").each(function () {
            checked.push($(this).val());
        });

        var remoteURL = jQuery('[name="remote_url"]').val();

        jQuery.ajax({
            type: 'POST',
            url: jQuery(this).data("url"),
            async: true,
            data: {
                compare_type: jQuery('[name="compare_type"]').val(),
                remote_url: remoteURL,
                showChangesOnly: jQuery('[name="showChangesOnly"]').is(":checked"),
                checked_items: checked
            }
        }).done(function (result) {
            if (result.error === false) {
                jQuery(".compare_results_container").html(result.html);
                jQuery(".compare_results_container").find("table").DataTable({
                    fixedHeader: true
                });
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
            // jQuery("#jquery-loader-background").remove();
            // jQuery("#jquery-loader").remove();
            $("#ajax-loading").removeClass("active");
        });
    });
});

jQuery(document).ready(function ($) {
    // Bundle tab toggle
    if ($('form[data-type="product"]').length) {
        if ($('select[name="product_type_id"]').length) {
            var handleBundleTab = function (showContent) {

                var product_bundle_tab_id = $('.tab-content').find('[data-type="product_bundle"]').closest('[data-type="container"]').attr("id");
                var product_configurable_tab_id = $('.tab-content').find('[data-type="product_configurable"]').closest('[data-type="container"]').attr("id");
                var product_configuration_bundle_option_tab_id = $('.tab-content').find('[data-type="product_configration_bundle_option"]').closest('[data-type="container"]').attr("id");
                var val = $('select[name="product_type_id"]').val();
                if (val == 1) { // Simple
                    $('.nav-tabs').find('[href="#' + product_configurable_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_configurable_tab_id + '').hide();

                    $('.nav-tabs').find('[href="#' + product_bundle_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_bundle_tab_id + '').hide();

                    $('.nav-tabs').find('[href="#' + product_configuration_bundle_option_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_configuration_bundle_option_tab_id + '').hide();
                } else if (val == 2) { // Configurable
                    $('.nav-tabs').find('[href="#' + product_configurable_tab_id + '"]').show();
                    if (showContent) {
                        $('.tab-content').find('#' + product_configurable_tab_id + '').show();
                    }

                    $('.nav-tabs').find('[href="#' + product_bundle_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_bundle_tab_id + '').hide();

                    $('.nav-tabs').find('[href="#' + product_configuration_bundle_option_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_configuration_bundle_option_tab_id + '').hide();
                } else if (val == 3) { // Bundle
                    $('.nav-tabs').find('[href="#' + product_configurable_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_configurable_tab_id + '').hide();

                    $('.nav-tabs').find('[href="#' + product_bundle_tab_id + '"]').show();
                    if (showContent) {
                        $('.tab-content').find('#' + product_bundle_tab_id + '').show();
                    }

                    $('.nav-tabs').find('[href="#' + product_configuration_bundle_option_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_configuration_bundle_option_tab_id + '').hide();
                } else if (val == 4) { // Bundle wand
                    $('.nav-tabs').find('[href="#' + product_configurable_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_configurable_tab_id + '').hide();

                    $('.nav-tabs').find('[href="#' + product_bundle_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_bundle_tab_id + '').hide();

                    $('.nav-tabs').find('[href="#' + product_configuration_bundle_option_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_configuration_bundle_option_tab_id + '').hide();
                } else if (val == 5) { // Ne postoji
                    $('.nav-tabs').find('[href="#' + product_configurable_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_configurable_tab_id + '').hide();

                    $('.nav-tabs').find('[href="#' + product_bundle_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_bundle_tab_id + '').hide();

                    $('.nav-tabs').find('[href="#' + product_configuration_bundle_option_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_configuration_bundle_option_tab_id + '').hide();
                } else if (val == 6) { // Configurable bundle
                    $('.nav-tabs').find('[href="#' + product_configurable_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_configurable_tab_id + '').hide();

                    $('.nav-tabs').find('[href="#' + product_bundle_tab_id + '"]').hide();
                    $('.tab-content').find('#' + product_bundle_tab_id + '').hide();

                    $('.nav-tabs').find('[href="#' + product_configuration_bundle_option_tab_id + '"]').show();
                    if (showContent) {
                        $('.tab-content').find('#' + product_configuration_bundle_option_tab_id + '').show();
                    }
                }
            };

            handleBundleTab(false);
            $('select[name="product_type_id"]').on("change", function () {
                handleBundleTab(true);
            });
        }
        if ($('select[name="configurable_attribute"]').length) {
            var handleConfigurableListing = function () {
                var val = $('select[name="configurable_attribute"]').val();
                if (val != 0) {
                    $('.tab-content').find('.configurable-product-listing').slideDown();
                } else {
                    $('.tab-content').find('.configurable-product-listing').slideUp();
                }
            };

            handleConfigurableListing();
            $('select[name="configurable_attribute"]').on("change", function () {
                handleConfigurableListing();
            });
        }
    }
});
