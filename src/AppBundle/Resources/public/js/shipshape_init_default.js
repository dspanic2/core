/**
 * Set cookie
 * @param cname
 * @param cvalue
 * @param exdays
 */
function setCookie(cname, cvalue, exdays) {
    if (typeof exdays == "undefined") {
        exdays = 30;
    }
    var d = new Date();
    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
    var expires = "expires=" + d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

/**
 * Get cookie value
 * @param cname
 */
function getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}

/**
 * Refresh list
 * @param table
 * @param result
 * @returns {boolean}
 */
function refreshList(table, result) {
    setTimeout(function () {
        jQuery.showAjaxLoader();
        table.DataTable().ajax.reload(function () {
            jQuery.hideAjaxLoader();
        });
    }, 100);

    if (table.find('[data-type="bchackbox"]').length) {
        table.find('[data-type="bchackbox"]').bootstrapSwitch();
    }

    return false;
}

/**
 * Refreshes list of entities when modal form add is used
 * @param form
 * @param result
 * @returns {boolean}
 */
function refreshParentListAfterModalSave(form, result) {
    if(form.data('type')){
        jQuery('body').find('[data-table="'+form.data('type')+'"]').each(function () {
            refreshList(jQuery(this), result);
        });
    }

    return false;
}

/**
 * Prepare multiselect post
 * @param select
 * @returns {Array}
 */
function serealizeSelects(select) {
    var array = [];
    select.each(function () {
        array.push(jQuery(this).val());
    });
    return array[0];
}


/**
 * Sets single value of lookup dynamically
 * @param select
 * @param entity_id
 * @returns {boolean}
 */
function setSingleValueForLookup(select, entity_id) {

    jQuery.post('/autocomplete/get_value_by_id', {
        template: select.data('template'),
        id: entity_id,
        attribute_id: select.data('id')
    }, function (result) {
        if (result.error == false) {

            var option = new Option(result.value, result.id, true, true);
            select.append(option).trigger('change');

            if (select.data('select2')) {
                select.trigger({
                    type: 'select2:select',
                    params: {
                        data: result.id
                    }
                });
                select.select2('close');
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
 * Sets lookup value after modal save
 * @param form
 * @param result
 * @returns {boolean}
 */
function modalFormAfterSave(form, result) {

    var form_group = form.data('parent-form-group');
    var select = jQuery('body').find('[data-form-group="' + form_group + '"]').find('select');

    setSingleValueForLookup(select, result.entity.id);

    return false;
}

/**
 *
 * @param drop
 * @param result
 * @returns {boolean}
 */
function validateImport(elem, drop, result, file) {

    var form = elem.parents('form');
    form.find('[name="path"]').val(result.path);

    jQuery.post(elem.data('validate-url'), {path: result.path}, function (result) {
        if (result.error == false) {
            form.find('.sp-matching-container').html(result.html).show();
            form.find('.sp-dropzone-container').hide();

            if (result.attributeMatched == true) {
                form.find('.btn-primary').show();
            }
        } else {
            drop.removeFile(file);

            jQuery.growl.error({
                title: translations.error_message,
                message: result.message
            });
        }
    }, "json");
    return false;
}

/**
 * Prepare current url for setting selected menu item
 * @param url
 * @returns {string}
 */
function prepareUrl(url) {
    url = url.split('/');
    return '/' + url[1] + '/' + url[2];
}

/**
 * Reset form
 * @param form
 * @returns {boolean}
 */
function resetFrom(form) {
    form[0].reset();
    form.formValidation('resetForm', true);
    form.formValidation('disableSubmitButtons', false);
    return true;
}

function resetFromBasic(form) {
    form.find('input[type="text"]').val('');
    form.find('select').val('');
    //form.find('select').prop('selectedIndex',0);
    form.find('input:checkbox').removeAttr('checked');
    form.find('[data-action="select2"]').trigger('change');

    form.find('.sp-range-slider').each(function () {
        jQuery(this).slider('values', [jQuery(this).data('initial-from-val'), jQuery(this).data('initial-to-val')]).trigger('slidechange');
        form.find("#slider-range-amount").text(jQuery(this).slider("values", 0) + " - " + jQuery(this).slider("values", 1));
    });

    form.find('[data-type="bchackbox"]').bootstrapSwitch('state', false);

    return true;
}

var selectList = {};

/**
 * Multiselect items
 */
function toggleSelectItem(element, table) {
    element.children('i').toggleClass('fa-square').toggleClass('fa-check-square');
    element.closest('tr').toggleClass('sp-row-active');

    var id = element.data('select-id');
    var type = element.data('type');

    if (element.children('i').hasClass('fa-square') && selectList[table] && selectList[table][type]) {
        selectList[table][type] = jQuery.grep(selectList[table][type], function (value) {
            return value != id;
        });

        if (selectList[table][type].length == 0) {
            delete selectList[table][type];
        }

        var count = 0;
        jQuery.each(selectList[table], function (index, obj) {
            count++;
        });

        if (!count) {
            delete selectList[table];
        }
    } else if (element.children('i').hasClass('fa-check-square')) {
        if (!selectList[table]) {
            selectList[table] = {};
        }
        if (!selectList[table][type]) {
            selectList[table][type] = [];
        }
        selectList[table][type].push(id);
    }

    if (selectList[table]) {
        jQuery('#' + table).parents('.sp-block').find('.sp-listview-dropdown-control').removeClass('hidden');
    } else {
        jQuery('#' + table).parents('.sp-block').find('.sp-listview-dropdown-control').addClass('hidden');
    }

    var tabId = sessionStorage.getItem("tab");
    if ((typeof tabId === 'undefined' || !tabId) && window.sessionStorage) {
        tabId = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        sessionStorage.setItem("tab", tabId);
    }
    var parent_id = 9999;
    if (jQuery('body').find('[name="id"]').length > 0) {
        parent_id = jQuery('body').find('[name="id"]').val();
    }
    sessionStorage.setItem(tabId + "_multi_" + parent_id, JSON.stringify(selectList));

    return false;
}

/**
 * Get select list from session on page load
 */
function initializeSelectedItmes() {

    var tabId = sessionStorage.getItem("tab");
    if ((typeof tabId === 'undefined' || !tabId) && window.sessionStorage) {
        tabId = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        sessionStorage.setItem("tab", tabId);
    }

    var parent_id = 9999;
    if (jQuery('body').find('[name="id"]').length > 0) {
        parent_id = jQuery('body').find('[name="id"]').val();
    }
    selectList = sessionStorage.getItem(tabId + "_multi_" + parent_id);
    if (!selectList) {
        selectList = {};
    } else {
        selectList = jQuery.parseJSON(selectList);
    }

    return false;
}

/**
 * Toggle header select all checkbox
 * @param table
 * @param state
 * @returns {boolean}
 */
function toggleHeader(table, state) {

    if (state == 0) {
        let check_all = jQuery('#' + table).find('.sp-list-checkbox-all .fa-check-square');
        if (check_all.length > 0) {
            check_all.removeClass('fa-check-square').addClass('fa-square');
        }
    } else {
        let check_all = jQuery('#' + table).find('.sp-list-checkbox-all .fa-square');
        if (check_all.length > 0) {
            check_all.removeClass('fa-square').addClass('fa-check-square');
        }
    }
}

/**
 * Toggle select all items
 * @param table
 * @param state
 * @returns {boolean}
 */
function toggleAllItems(table, state) {

    if (state == 0) {
        jQuery('#' + table).find('.sp-list-checkbox > .fa-check-square').each(function (e) {
            toggleSelectItem(jQuery(this).parent(), table);
        });
    } else {
        jQuery('#' + table).find('.sp-list-checkbox > .fa-square').each(function (e) {
            toggleSelectItem(jQuery(this).parent(), table);
        });
    }

    return false;
}

/**
 * Check if table needs a scroller
 */
function checkifneedscrolling(wrapper) {

    if (wrapper.find('.dataTables_wrapper').length > 0) {
        wrapper.find('.dataTables_wrapper').each(function (e) {
            if (jQuery(this).find('table').width() - jQuery(this).width() < 10) {
                jQuery(this).css({
                    'overflow': 'hidden'
                });
                jQuery(this).find('.sp-filter-holder').prop("scrollLeft", 0);
                jQuery(this).find('.sp-table-header').prop("scrollLeft", 0);
                jQuery(this).prop("scrollLeft", 0);
            } else {
                jQuery(this).css({
                    'overflow-x': 'auto'
                });
            }
        });
    }
    return false;
}

/**
 *
 * @param filter_holder
 * @param table_header
 */
function fixTableHeader(filter_holder, table_header) {
    return;
    var table_bottom = jQuery(window).height() - table_header.parents('.dataTable').offset().top - table_header.parents('.dataTable').height() - 320;
    //&& jQuery(window).scrollTop() < table_bottom
    if (jQuery(window).scrollTop() > 60) {
        filter_holder.css({
            'position': 'fixed',
            'top': '60px'
        }).addClass('sp-filter-holder-fixed');
        table_header.css({
            'position': 'fixed',
            'top': '125px'
        }).addClass('sp-table-header-fixed');
    } else {
        filter_holder.css({
            'position': 'absolute',
            'top': '11px'
        }).removeClass('sp-filter-holder-fixed');
        table_header.css({
            'position': 'absolute',
            'top': '43px'
        }).removeClass('sp-table-header-fixed');
    }
    return false;
}

/**
 * Toggle dropzone
 * @param limit
 * @returns {boolean}
 */
function showHideDropzone(limit) {
    if (jQuery('.images-wrapper').children().length >= limit) {
        jQuery('.dropzone').hide();
    } else {
        jQuery('.dropzone').show();
    }
    return false;
}

/**
 * Get related entity form
 * @param entity_type
 * @param entitiy_id
 * @param form_type
 * @param wrapper
 * @returns {boolean}
 * TRENUTNO SE NE KORISTI
 */

/*function getRelatedEntity(entity_type,entitiy_id,form_type,wrapper){
    jQuery.post(jQuery('[data-base-url="true"]').data('url')+'api/get/single_entity/', { entity_type: entity_type, form_type: form_type, entitiy_id: entitiy_id }, function(result) {
        if(result.error == false){
            var helper = jQuery('<div />').html(result.html);
            helper.find('.form-control').attr('disabled','disabled');
            wrapper.append(helper.html());
            helper.remove();
        }
        else{
            jQuery.growl.error({
                title: translations.error_message,
                message: result.message
            });
        }
    }, "json");

    return false
}*/

/**
 * Mass Callbacks
 */
function massRemoveSelected(table, result) {

    toggleHeader(table.parents('.dataTables_wrapper').attr('id'), 0);

    toggleAllItems(table.parents('.dataTables_wrapper').attr('id'), 0);

    return true;
}

/**
 * Mass go to cloned entity
 * @param table
 * @param result
 * @returns {boolean}
 */
function goToCloned(table, result) {
    if (result.entity && result.entity.id) {
        window.location.href = "/page/" + result.entity.attribute_set_code + "/form/" + result.entity.id;
    }
    return false;
}


/**
 * Sets selected gallery item
 * @param wrapper
 * @param image_id
 * @returns {boolean}
 */
function setSelectedGalleryItem(wrapper, image_id) {

    jQuery.post(wrapper.data("selected_url"), {
        'image_id': image_id,
        'parent_id': jQuery('body').find('[name="id"]').val(),
        'entity_type_code': wrapper.data('entity_type_code'),
        'parent_attribute_id': wrapper.data('parent_attribute_id')
    }, function (result) {
        if (result.error == false) {
            wrapper.find('.sp-gallery-item').removeClass('sp-primary');
            wrapper.find('[data-action="set-gallery-item-primary"]').removeClass('hidden');
            wrapper.find('[data-gallery-id="' + image_id + '"]').addClass('sp-primary');
            wrapper.find('[data-gallery-id="' + image_id + '"]').find('[data-action="set-gallery-item-primary"]').addClass('hidden');
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
 * Remove gallery item on delete
 * @param elem
 * @param wrapper
 * @param result
 * @returns {boolean}
 */
function removeGalleryItem(elem, wrapper, result) {
    elem.parents('.sp-gallery-item').remove();
    if (wrapper.find('.sp-gallery-item').length === 0) {
        wrapper.find('[data-action="toggle-hidden"]').toggleClass('hidden');
        if (wrapper.find('[data-action="toggle-library-dropzone"]').length > 0) {
            wrapper.find('[data-action="toggle-library-dropzone"]').toggleClass('hidden');
        }
        if (wrapper.find('[data-action="download-files"]').length > 0) {
            wrapper.find('[data-action="download-files"]').toggleClass('hidden');
        }
        if (wrapper.find('[data-action="remove-all-items"]').length > 0) {
            wrapper.find('[data-action="remove-all-items"]').toggleClass('hidden');
        }
    }

    // Reload list on image delete
    if (elem.parents(".dataTables_wrapper").length > 0) {
        refreshList(elem.parents(".dataTables_wrapper").find('.datatables'), null);
    }

    return false;
}

/**
 *
 * @param elem
 */
function initializeDropzone(elem) {

    Dropzone.autoDiscover = false;

    var sp_block = elem.parents('.sp-block');

    var options = {
        url: elem.data('url'),
        paramName: 'file',
        maxFilesize: 10000000000, // MB
        maxFiles: elem.data('limit'),
        acceptedFiles: elem.data('acceptedfiles'),
        dictDefaultMessage: translations.dropzone,
        init: function () {

            var drop = this;

            this.on('success', function (file, result) {

                if (result.error == true) {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });

                    drop.removeFile(file);
                } else {
                    var wrapper;
                    if (sp_block.find(".store-file-field").length > 0) {
                        wrapper = elem.parents('.store-file-field');
                    } else {
                        wrapper = jQuery('[name="' + result.attributeCode + '"]').parents('.form-group');
                    }

                    var table = null;
                    if (sp_block.find('.datatables').length) {
                        table = sp_block.find('.dataTables_wrapper').find('.datatables');
                        refreshList(table, result);
                    }
                    if (wrapper) {
                        if (sp_block.find(".store-file-field").length > 0) {
                            wrapper.find('.file-store-holder').val(result.path);
                        } else {
                            wrapper.find('[name="' + result.attributeCode + '"]').val(result.path);
                        }
                        wrapper.find('.options').removeClass('hidden');
                        wrapper.find('[data-action="toggle-dropzone"]').removeClass('hidden');
                        wrapper.find('[data-action="remove-image"]').removeClass('hidden');
                        wrapper.find('.sp-dropzone-container').find('.dropzone-wrapper').addClass('hidden');
                        wrapper.find('.sp-dropzone-container').find('.image-holder').remove();
                    }

                    if (wrapper) {
                        if (result.is_image == true) {
                            wrapper.find('.sp-dropzone-container').append('<div class="image-holder" data-action="toggle-hidden"><img src="' + result.web_path + '"/></div>');
                        } else {
                            wrapper.find('.sp-dropzone-container').append('<div class="image-holder" data-action="toggle-hidden"><span class="sp-document sp-icon-' + result.ext + '"><a target="_blank" href="' + result.web_path + '">' + result.web_path + '</a></span></div>');
                        }
                    }
                    if (sp_block.find('.sp-document-list').length) {
                        var doc_list_wrapper = sp_block.find('.sp-document-list');
                        doc_list_wrapper.append('<input type="hidden" name="document_list[' + result.entity.attribute_set_code + '][]" value="' + result.entity.id + '"/>');
                    }

                    if (sp_block.find('.sp-gallery')) {
                        sp_block.find('.sp-gallery').append(result.html);
                        sp_block.find('[data-action="toggle-library-dropzone"]').removeClass('hidden');
                    }

                    if (elem.data('callback')) {
                        var functions = elem.data('callback');
                        if (typeof functions == "string") {
                            functions = [functions];
                        }
                        jQuery.each(functions, function (key, f) {
                            if (eval("typeof " + f + " === 'function'")) {
                                window[f](elem, drop, result, file);
                            }
                        });
                    }
                }
            });
        }
    };
    elem.dropzone(options);

    /**
     * Gallery sort
     */
    if (sp_block.find('.sortable-gallery').length > 0) {
        sp_block.find('.sortable-gallery').sortable({
            update: function (event, ui) {
                var data = [];
                jQuery.each(sp_block.find('[name="image_sort_id[]"]'), function (e) {
                    data.push(jQuery(this).val());
                });

                jQuery.post(sp_block.data("sortable_url"), {
                    'data': data,
                    'parent_id': jQuery('body').find('[name="id"]').val(),
                    'entity_type_code': sp_block.data('entity_type_code'),
                    'parent_attribute_id': sp_block.data('parent_attribute_id')
                }, function (result) {
                    if (result.error == false) {
                    } else {
                        jQuery.growl.error({
                            title: translations.error_message,
                            message: result.message
                        });
                    }
                }, "json");
            }
        });
    }


    return false;
}


/**
 *ON WINDOW LOAD EVENTS
 */

jQuery(document).ready(function () {
    jQuery(window).on('load', function () {
        jQuery('.main-content').show();
    });
});

jQuery(document).ready(function () {

    /**
     * Copy to clipboard
     */
    jQuery('body').on('click', '[data-action="copy_to_clipboard"]', function (e) {
        e.stopImmediatePropagation();
        $.copyToClipboard($(this).text());
    });

    /**
     * Remove 0,00 on mouse click decimal
     */
    jQuery('body').on('click', '[data-type="decimal"]:not([readonly])', function (e) {
        if (jQuery(this).val() == "0,00") {
            jQuery(this).val("");
        }
    });
    jQuery('body').on('focusout', '[data-type="decimal"]:not([readonly])', function (e) {
        if (jQuery(this).val() == "") {
            jQuery(this).val("0,00");
        }
    });
    /**
     * Remove 0 on mouse click integer
     */
    jQuery('body').on('click', '[data-type="integer"]:not([readonly])', function (e) {
        if (jQuery(this).val() == "0") {
            jQuery(this).val("");
        }
    });
    jQuery('body').on('focusout', '[data-type="integer"]:not([readonly])', function (e) {
        if (jQuery(this).val() == "") {
            jQuery(this).val("0");
        }
    });

    initializeSelectedItmes();

    /**
     * Dropzone
     */
    if (jQuery('body').find('.dropzone').length) {

        jQuery('body').find('.dropzone').each(function (e) {
            var elem = jQuery(this);
            initializeDropzone(elem);
            //showHideDropzone(elem.find('.dropzone').data('limit'));
        });

    }

    /**
     * Color picker
     */
    if (jQuery('body').find('.sp-color-picker').length) {

        jQuery('body').find('.sp-color-picker').each(function (e) {
            var elem = jQuery(this);
            elem.colorpicker();
        });

    }

    jQuery('body').on('click', '[data-action="set-gallery-item-primary"]', function (e) {
        var gallery_item = jQuery(this).parents('.sp-gallery-item');
        var sp_block = gallery_item.parents('.sp-block');
        setSelectedGalleryItem(sp_block, gallery_item.data('gallery-id'));
    });

    function toggleSaveButtons(type) {

        if (type) {
            jQuery('.sp-main-actions-wrapper').find('[type="submit"][data-url=""]').show();
        } else {
            jQuery('.sp-main-actions-wrapper').find('[type="submit"][data-url=""]').hide();
        }

        return false;
    }

    /**
     * Disable save buttons if view is on
     */
    if (jQuery('[data-page-subtype="view"]').length) {
        toggleSaveButtons(false);
    }

    function toggleViewEditMode(wrapper, mode) {

        var form = wrapper.parents('form');

        if (mode == "edit") {
            wrapper.addClass('sp-edit-mode');
            wrapper.find('input[data-type="text"]').not('[data-readonly="force"]').removeAttr('readonly');
            wrapper.find('input[data-type="integer"]').not('[data-readonly="force"]').removeAttr('readonly');
            wrapper.find('input[data-type="decimal"]').not('[data-readonly="force"]').removeAttr('readonly');

            wrapper.find('input[data-type="timepicker"]').not('[data-readonly="force"]').each(function (e) {
                initializeTimepicker(jQuery(this), false);
                jQuery(this).removeAttr('readonly');
            });

            wrapper.find('input[data-type="daterange"]').not('[data-readonly="force"]').each(function (e) {
                initializeDaterange(jQuery(this), false);
                jQuery(this).removeAttr('readonly');
                jQuery(this).siblings('[data-action="clear-date"]').removeClass('hidden');
            });

            wrapper.find('input[data-type="datesingle"]').not('[data-readonly="force"]').each(function (e) {
                initializeDatesingle(jQuery(this), false);
                jQuery(this).removeAttr('readonly');
                jQuery(this).siblings('[data-action="clear-date"]').removeClass('hidden');
            });

            wrapper.find('input[data-type="datetimesingle"]').not('[data-readonly="force"]').each(function (e) {
                initializeDateTimesingle(jQuery(this), false);
                jQuery(this).removeAttr('readonly');
                jQuery(this).siblings('[data-action="clear-date"]').removeClass('hidden');
            });

            wrapper.find('textarea[data-type="textarea"]').not('[data-readonly="force"]').removeAttr('readonly');
            wrapper.find('textarea[data-type="ckeditor"]').not('[data-readonly="force"]').each(function (e) {
                jQuery(this).removeAttr('readonly');
                CKEDITOR.instances[jQuery(this).attr('name')].setReadOnly(false);
            });

            wrapper.find('[data-type="bchackbox"]').not('[data-readonly="force"]').bootstrapSwitch('readonly', false);
            wrapper.find('[data-type="lookup"]').not('[data-readonly="force"]').each(function (e) {
                var elem = jQuery(this);
                elem.removeAttr('disabled');
            });
            wrapper.find('[data-type="multiselect"]').not('[data-readonly="force"]').each(function (e) {
                var elem = jQuery(this);
                elem.removeAttr('disabled');
            });

            /**
             * Image field and gallery block
             */
            if (wrapper.find('.dropzone-wrapper').length > 0) {
                wrapper.find('.dropzone-wrapper').each(function (e) {
                    if (jQuery(this).siblings('.image-holder').length > 0) {
                        jQuery(this).siblings('.image-holder').find('.image-holder-options').removeClass('hidden');
                    } else {
                        if (wrapper.find('[data-action="toggle-library-dropzone"]').length > 0) {
                            wrapper.find('[data-action="toggle-library-dropzone"]').removeClass('hidden');
                            wrapper.find('[data-action="download-files"]').removeClass('hidden');
                            wrapper.find('[data-action="remove-all-items"]').removeClass('hidden');

                            wrapper.find('.sp-gallery-item').each(function (e) {
                                jQuery(this).find('[data-action="standard_action"]').removeClass('hidden');
                                jQuery(this).find('[data-action="rotate-image"]').removeClass('hidden');

                                if (!jQuery(this).hasClass('sp-primary')) {
                                    jQuery(this).find('[data-action="set-gallery-item-primary"]').removeClass('hidden');
                                }
                            });
                        } else {
                            jQuery(this).removeClass('hidden');
                        }
                    }
                });
            }

            /**
             * Checkbox list block
             */
            if (wrapper.find('[data-checkbox-list="deselect_all"]').length > 0) {
                wrapper.find('[data-checkbox-list="deselect_all"]').removeClass('disabled');
            }
            if (wrapper.find('[data-checkbox-list="select_all"]').length > 0) {
                wrapper.find('[data-checkbox-list="select_all"]').removeClass('disabled');
            }
            if (wrapper.find('[data-action="checkbox-value"]').length > 0) {
                wrapper.find('[data-action="checkbox-value"]').each(function (e) {
                    jQuery(this).removeAttr('disabled');
                });
            }

            toggleSaveButtons(true);
        } else {
            wrapper.removeClass('sp-edit-mode');
            wrapper.find('input[data-type="text"]').not('[data-readonly="force"]').attr('readonly', 'readonly');
            wrapper.find('input[data-type="integer"]').not('[data-readonly="force"]').attr('readonly', 'readonly');
            wrapper.find('input[data-type="decimal"]').not('[data-readonly="force"]').attr('readonly', 'readonly');

            wrapper.find('input[data-type="daterange"]').not('[data-readonly="force"]').each(function (e) {
                removeDatepicker(jQuery(this));
                jQuery(this).attr('readonly', 'readonly');
                jQuery(this).siblings('[data-action="clear-date"]').addClass('hidden');
            });

            wrapper.find('input[data-type="datesingle"]').not('[data-readonly="force"]').each(function (e) {
                removeDatepicker(jQuery(this));
                jQuery(this).attr('readonly', 'readonly');
                jQuery(this).siblings('[data-action="clear-date"]').addClass('hidden');
            });

            wrapper.find('input[data-type="datetimesingle"]').not('[data-readonly="force"]').each(function (e) {
                removeDatepicker(jQuery(this));
                jQuery(this).attr('readonly', 'readonly');
                jQuery(this).siblings('[data-action="clear-date"]').addClass('hidden');
            });

            wrapper.find('input[data-type="timepicker"]').not('[data-readonly="force"]').each(function (e) {
                jQuery(this).attr('readonly', 'readonly');
            });

            wrapper.find('textarea[data-type="textarea"]').not('[data-readonly="force"]').attr('readonly', 'readonly');

            wrapper.find('textarea[data-type="ckeditor"]').not('[data-readonly="force"]').each(function (e) {
                jQuery(this).attr('readonly', 'readonly');
                CKEDITOR.instances[jQuery(this).attr('name')].setReadOnly(true);
            });

            wrapper.find('[data-type="bchackbox"]').not('[data-readonly="force"]').bootstrapSwitch('readonly', true);

            wrapper.find('[data-type="lookup"]').not('[data-readonly="force"]').each(function (e) {
                var elem = jQuery(this);
                elem.attr('disabled', 'disabled');
            });
            wrapper.find('[data-type="multiselect"]').not('[data-readonly="force"]').each(function (e) {
                var elem = jQuery(this);
                elem.attr('disabled', 'disabled');
            });

            /**
             * Image field and gallery block
             */
            if (wrapper.find('.dropzone-wrapper').length > 0) {
                wrapper.find('.dropzone-wrapper').each(function (e) {
                    if (jQuery(this).siblings('.image-holder').length > 0) {
                        jQuery(this).siblings('.image-holder').find('.image-holder-options').addClass('hidden');
                    } else {
                        if (wrapper.find('[data-action="toggle-library-dropzone"]').length > 0) {
                            wrapper.find('[data-action="toggle-library-dropzone"]').addClass('hidden');
                            wrapper.find('[data-action="download-files"]').addClass('hidden');
                            wrapper.find('[data-action="remove-all-items"]').addClass('hidden');

                            wrapper.find('.sp-gallery-item').each(function (e) {
                                jQuery(this).find('[data-action="standard_action"]').addClass('hidden');
                                jQuery(this).find('[data-action="rotate-image"]').addClass('hidden');

                                if (!jQuery(this).hasClass('sp-primary')) {
                                    jQuery(this).find('[data-action="set-gallery-item-primary"]').addClass('hidden');
                                }
                            });
                        } else {
                            jQuery(this).addClass('hidden');
                        }
                    }
                });
            }

            /**
             * Checkbox list block
             */
            if (wrapper.find('[data-checkbox-list="deselect_all"]').length > 0) {
                wrapper.find('[data-checkbox-list="deselect_all"]').addClass('disabled');
            }
            if (wrapper.find('[data-checkbox-list="select_all"]').length > 0) {
                wrapper.find('[data-checkbox-list="select_all"]').addClass('disabled');
            }
            if (wrapper.find('[data-action="checkbox-value"]').length > 0) {
                wrapper.find('[data-action="checkbox-value"]').each(function (e) {
                    jQuery(this).attr('disabled', true);
                });
            }

            if (jQuery('body').find('.sp-edit-mode').length == 0) {
                toggleSaveButtons(false);
            }
        }

        return false;
    }

    /**
     * Toggle edit mode
     */
    /*jQuery('body').on('click', '[data-action="toggle-edit"]', function (e) {
        var mode = "edit";
        var wrapper = jQuery(this).parents('.sp-block');
        if (wrapper.hasClass('sp-edit-mode')) {
            mode = "view";
        }

        toggleViewEditMode(wrapper, mode);
    });*/

    /**
     * Add block in front
     */
    jQuery('body').on('click', '[data-action="add-block-modal-front"]', function (e) {
        jQuery.post(jQuery(this).data("url"), {'is_front': true}, function (result) {
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
     * Edit block in front
     */
    jQuery('body').on('click', '[data-action="edit-block-modal-front"]', function (e) {
        jQuery.post(jQuery(this).data("url"), {'is_front': true}, function (result) {
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
     * Delete block in front
     */
    jQuery('body').on('click', '[data-action="remove-block-front"]', function (e) {

        var elem = jQuery(this);

        jQuery.confirm({
            title: translations.please_confirm,
            content: translations.yes_i_am_sure,
            buttons: {
                confirm: {
                    text: translations.yes_i_am_sure,
                    btnClass: 'sp-btn btn-primary btn-blue btn',
                    keys: ['enter'],
                    action: function () {
                        jQuery.post(elem.data("url"), {}, function (result) {
                            if (result.error == false) {
                                window.location.reload(true);
                            } else {
                                jQuery.growl.error({
                                    title: translations.error_message,
                                    message: result.message
                                });
                            }
                        }, "json");
                    }
                },
                cancel: {
                    text: translations.cancel,
                    btnClass: 'sp-btn btn-default btn-red btn',
                    action: function () {
                    }
                }
            }
        });
    });

    /**
     * Go back button
     */
    jQuery('body').on('click', '[data-action="back"]', function (e) {
        e.preventDefault();
        goBackWithRefresh(e);
    });

    /**
     * Create datatables
     */
    if (jQuery('.main-content').find('.datatables').length > 0) {
        jQuery('.main-content').find('.datatables').each(function (e) {
            jQuery('#' + jQuery(this).attr('id')).createDatatable();
        });
    }

    /**
     * Export to excel
     */
    jQuery('body').on('click', '[data-action="export_xls"]', function (e) {
        e.stopPropagation();
        var table = jQuery(this).parents('.panel').find('.dataTables_wrapper');

        jQuery.post(jQuery(this).data("url"), {
            data: table_state[table.find('.datatables').attr('id')],
            items: selectList[table.attr('id')]
        }, function (result) {
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
     * Import from excel
     */
    jQuery('body').on('click', '[data-action="import_xls"]', function (e) {
        e.stopPropagation();

        jQuery.post(jQuery(this).data("url"), {}, function (result) {
            if (result.error == false) {
                var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                clone.html(result.html);
                var modal = clone.find('.modal');
                modal.modal('show');

                var form = modal.find('[data-validate="true"]');
                form.initializeValidation();

                initializeDropzone(form.find('.dropzone'));
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    /**
     * Download import xls template
     */
    jQuery('body').on('click', '[data-action="import_xls_template"]', function (e) {
        e.stopPropagation();
        var table = jQuery(this).parents('.panel').find('.dataTables_wrapper').find('.datatables');

        jQuery.post(jQuery(this).data("url"), {data: table_state[table.attr('id')]}, function (result) {
            if (result.error == false) {
                window.open(result.filepath, '_blank');
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    /**
     * Toggle search
     */
    jQuery('[data-action="toggle_search"]').click(function () {
        jQuery('#top_search').toggleClass('hidden');
        jQuery('#top_search').siblings('.sp-top-search-wrapper').toggleClass('hidden');
    });

    jQuery('[data-action="clear_top_search"]').click(function () {
        jQuery('#quicksearch').find('[name="query"]').val("");
    });


    /**
     * Resize datatable
     */
    function onWindowResize() {
        return; //###

        if (jQuery('.sp-fixed-header').length > 0) {
            var main_table = jQuery('.sp-fixed-header').find('.dataTable');
            var dataTables_wrapper = jQuery('.sp-fixed-header').find('.dataTables_wrapper');
            var filter_holder = main_table.find('.sp-filter-holder');
            var table_header = main_table.find('.sp-table-header');

            //var width = jQuery(window).width();
            //jQuery(window).on('resize', function(){
            //if(jQuery(this).width() != width){
            //width = jQuery(this).width();
            if (main_table.find('tbody > tr').length > 0) {
                var w = main_table.find('td:not(.sp-actions-td)').width() + 18;
                main_table.find('th:not(.sp-actions-td)').css({
                    "width": w + "px",
                    "min-width": w + "px",
                    "max-width": w + "px"
                });
            }
            filter_holder.css("width", dataTables_wrapper.width());
            table_header.css("width", dataTables_wrapper.width());
            checkifneedscrolling(jQuery('body'));
            //}
            //});
        }
    }

    /**
     * Datatable fixed headers
     * TRENUTNO SE NE KORISTI ALI BUDE
     */
    if (jQuery('.sp-fixed-header').length > 0) {
        var main_table = jQuery('.sp-fixed-header').find('.dataTable');
        //     var dataTables_wrapper = jQuery('.sp-fixed-header').find('.dataTables_wrapper');
        var filter_holder = main_table.find('.sp-filter-holder');
        var table_header = main_table.find('.sp-table-header');
        //
        //     filter_holder.css("width", dataTables_wrapper.width());
        //     table_header.css("width", dataTables_wrapper.width());
        //
        //     jQuery(window).on('resize', function () {
        //         onWindowResize();
        //     });
        //
        //     dataTables_wrapper.scroll(function () {
        //         filter_holder.prop("scrollTop", this.scrollTop).prop("scrollLeft", this.scrollLeft);
        //         table_header.prop("scrollTop", this.scrollTop).prop("scrollLeft", this.scrollLeft);
        //     });
        //
        jQuery(window).scroll(function () {
            fixTableHeader(filter_holder, table_header);
        });
        fixTableHeader(filter_holder, table_header);
    }

    /**
     * Execute window resize
     */
    jQuery(window).on('resize', function () {
        checkifneedscrolling(jQuery('body'));
    });
    checkifneedscrolling(jQuery('body'));

    /**
     * Tooltip
     */
    if (jQuery('[data-tooltip="true"]') > 0) {
        jQuery('[data-tooltip="true"]').tooltip();
    }

    /**
     * Initialize form validation
     */
    if (jQuery('[data-validate="true"]')) {
        jQuery('[data-validate="true"]').initializeValidation();
    }

    /**
     * Double click on datatable row
     */
    /** DEPRECATED **/
    /*jQuery('body').on('dblclick', '.sp-row-clickable', function () {
        if (jQuery(this).data('doubleclick-url')) {
            window.location.href = jQuery(this).data('doubleclick-url');
        }
    });*/

    jQuery('body').on('dblclick', '[data-action="standard_row_action"]', function () {
        if (jQuery(this).data('url')) {
            window.location.href = jQuery(this).data('url');
        }
    });
    /**
     * Mass action submit
     */
    jQuery('[data-action="standard_mass_action"]').bind('click', function () {
        bindStandardMassActions(jQuery(this));
    });

    /**
     * Standard action
     */
    jQuery('body').on('click', '[data-action="standard_action"]', function (e) {
        e.stopPropagation();
        var elem = jQuery(this);
        var id = elem.data('id');
        if (!id) {
            id = jQuery('body').find('[data-validate="true"]').find('[name="id"]').val();
        }
        var sp_block = elem.parents('.sp-block');

        if (elem.data('confirm')) {
            jQuery.confirm({
                title: translations.please_confirm,
                content: translations.yes_i_am_sure,
                buttons: {
                    confirm: {
                        text: translations.yes_i_am_sure,
                        btnClass: 'sp-btn btn-primary btn-blue btn',
                        keys: ['enter'],
                        action: function () {
                            jQuery.post(elem.data("url"), {id: id}, function (result) {
                                if (result.error == false) {
                                    jQuery.growl.notice({
                                        title: result.title,
                                        message: result.message
                                    });

                                    if (elem.data('callback')) {
                                        var functions = elem.data('callback');
                                        if (typeof functions == "string") {
                                            functions = [functions];
                                        }
                                        jQuery.each(functions, function (key, f) {
                                            if (eval("typeof " + f + " === 'function'")) {
                                                window[f](elem, sp_block, result);
                                            }
                                        });
                                    }

                                } else {
                                    jQuery.growl.error({
                                        title: translations.error_message,
                                        message: result.message
                                    });
                                }
                            }, "json");
                        }
                    },
                    cancel: {
                        text: translations.cancel,
                        btnClass: 'sp-btn btn-default btn-red btn',
                        action: function () {
                        }
                    },
                }
            });
        } else {
            jQuery.post(elem.data("url"), {id: id}, function (result) {
                if (result.error == false) {
                    jQuery.growl.notice({
                        title: result.title,
                        message: result.message
                    });

                    if (elem.data('callback')) {
                        var functions = elem.data('callback');
                        if (typeof functions == "string") {
                            functions = [functions];
                        }
                        jQuery.each(functions, function (key, f) {
                            if (eval("typeof " + f + " === 'function'")) {
                                window[f](elem, sp_block, result);
                            }
                        });
                    }
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
     * List filter button with post filter
     */
    jQuery('body').on('click','[data-action="button_list_filtered"]',function (e) {
        e.preventDefault();
        e.stopPropagation();

        var button = jQuery(this);
        button.attr("disabled","disabled");

        var table = jQuery('body').find('.dataTable');
        var listViewId = table.data('view-id');
        if(!table){
            $.growl.error({
                title: result.title ? result.title : '',
                message: result.message ? result.message : translations.error_message,
            });
            return false;
        }

        var state = table_state[table.attr('id')];
        $("#ajax-loading").addClass('active');

        jQuery.post(button.data("url"), {
                data: state.send_data,
                custom_data: state.custom_data,
                list_view_id: listViewId
            }, function (result) {
            $("#ajax-loading").removeClass('active');
            button.removeAttr("disabled");
            if (result.error == false) {
                jQuery.growl.notice({
                    title: result.title,
                    message: result.message
                });

                if(result.file){
                   window.open(result.file, '_blank');
                }
                else if(result.html){
                    var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                    clone.html(result.html);
                    var modal = clone.find('.modal');
                    modal.modal('show');

                    var form = modal.find('[data-validate="true"]');
                    form.initializeValidation();
                }
                else{
                    window.location.reload(true);
                }
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    /**
     * Default button with post id
     */
    jQuery('body').on('click','[data-action="button_default"]',function (e) {
        e.preventDefault();
        e.stopPropagation();

        var button = jQuery(this);
        button.attr("disabled","disabled");

        var id = button.data('id');
        if (!id) {
            id = jQuery('body').find('[data-validate="true"]').find('[name="id"]').val();
        }

        jQuery.confirm({
            title: translations.please_confirm,
            content: translations.yes_i_am_sure,
            buttons: {
                confirm: {
                    text: translations.yes_i_am_sure,
                    btnClass: 'sp-btn btn-primary btn-blue btn',
                    keys: ['enter'],
                    action: function () {
                        $("#ajax-loading").addClass('active');
                        $.ajax({
                            url: button.data("url"),
                            method: 'POST',
                            data: {id: id},
                            cache: false
                        }).done(function (result) {
                            $("#ajax-loading").removeClass('active');
                            if (result.error == false) {
                                button.removeAttr("disabled");
                                jQuery.growl.notice({
                                    title: result.title,
                                    message: result.message
                                });

                                if(result.file){
                                   window.open(result.file, '_blank');
                                }
                                else{
                                    window.location.reload(true);
                                }
                            } else {
                                $.growl.error({
                                    title: result.title ? result.title : '',
                                    message: result.message ? result.message : translations.selection_error,
                                });
                            }
                        });
                    }
                },
                cancel: {
                    text: translations.cancel,
                    btnClass: 'sp-btn btn-default btn-red btn',
                    action: function () {
                        button.removeAttr("disabled");
                    }
                }
            }
        });
    });

    /**
     * Standard grid actions
     */
    jQuery('body').on('click', '[data-action="standard_grid_action"]', function (e) {
        e.stopPropagation();
        var elem = jQuery(this);
        var table = elem.parents('.dataTables_wrapper').find('.datatables');
        var id = elem.data('id');

        if (elem.data('confirm') == 1 || elem.data('confirm') == "true") {
            jQuery.confirm({
                title: translations.please_confirm,
                content: translations.yes_i_am_sure,
                buttons: {
                    confirm: {
                        text: translations.yes_i_am_sure,
                        btnClass: 'sp-btn btn-primary btn-blue btn',
                        keys: ['enter'],
                        action: function () {
                            jQuery.post(elem.data("url"), {
                                id: id,
                                parent_entity_id: (jQuery("[name='id']").length ? jQuery("[name='id']").val() : null)
                            }, function (result) {
                                if (result.error == false) {
                                    if(result.message){
                                        jQuery.growl.notice({
                                            title: result.title,
                                            message: result.message
                                        });
                                    }

                                    if(result.html){
                                        var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                                        clone.html(result.html);
                                        var modal = clone.find('.modal');
                                        modal.modal('show');

                                        var form = modal.find('[data-validate="true"]');
                                        form.initializeValidation();
                                    }
                                    else{
                                        refreshList(table, result);
                                        if (jQuery('body').find('.sp-preview-container').find('[name="id"]').length > 0 && jQuery('body').find('.sp-preview-container').find('[name="id"]').val() == id) {
                                            jQuery('body').find('.sp-preview-container').html('');
                                        }
                                    }

                                    if (elem.data('callback')) {
                                        var functions = elem.data('callback');
                                        if (typeof functions == "string") {
                                            functions = [functions];
                                        }
                                        jQuery.each(functions, function (key, f) {
                                            if (eval("typeof " + f + " === 'function'")) {
                                                window[f](table, result);
                                            }
                                        });
                                    }
                                } else {
                                    jQuery.growl.error({
                                        title: translations.error_message,
                                        message: result.message
                                    });
                                }
                            }, "json");
                        }
                    },
                    cancel: {
                        text: translations.cancel,
                        btnClass: 'sp-btn btn-default btn-red btn',
                        action: function () {
                        }
                    },
                }
            });
        } else {
            jQuery.post(elem.data("url"), {
                id: id,
                parent_entity_id: (jQuery("[name='id']").length ? jQuery("[name='id']").val() : null)
            }, function (result) {
                if (result.error == false) {
                    if (result.redirect_url != undefined) {
                        jQuery.redirectToUrl(result.redirect_url);
                    } else {
                        if(result.message){
                            jQuery.growl.notice({
                                title: result.title,
                                message: result.message
                            });
                        }

                        if(result.html){
                            var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                            clone.html(result.html);
                            var modal = clone.find('.modal');
                            modal.modal('show');

                            var form = modal.find('[data-validate="true"]');
                            form.initializeValidation();
                        }
                        else{
                            refreshList(table, result);
                        }

                        if (elem.data('callback')) {
                            var functions = elem.data('callback');
                            if (typeof functions == "string") {
                                functions = [functions];
                            }
                            jQuery.each(functions, function (key, f) {
                                if (eval("typeof " + f + " === 'function'")) {
                                    window[f](table.find('.datatables'), result);
                                }
                            });
                        }
                    }
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
     * ecommerce_health_check grid actions
     */
    jQuery('body').on('click', '[data-action="ecommerce_health_check_is_custom"]', function (e) {
        e.stopPropagation();
        var elem = jQuery(this);
        var table = elem.parents('.dataTables_wrapper').find('.datatables');
        var id = elem.parents("tr").find('[data-code="id"]').text().trim();
        var tableName = elem.parents("tr").find('[data-code="table_name"]').text().trim();

        if (id == undefined || table == undefined) {
            jQuery.growl.error({
                title: translations.error_message,
                message: translations.missing + " id, table",
            });
        } else {
            if (elem.data('confirm') == 1 || elem.data('confirm') == "true") {
                jQuery.confirm({
                    title: translations.please_confirm,
                    content: translations.yes_i_am_sure,
                    buttons: {
                        confirm: {
                            text: translations.yes_i_am_sure,
                            btnClass: 'sp-btn btn-primary btn-blue btn',
                            keys: ['enter'],
                            action: function () {
                                jQuery.post(elem.data("url"), {
                                    id: id,
                                    table: tableName,
                                }, function (result) {
                                    if (result.error == false) {
                                        jQuery.growl.notice({
                                            title: result.title,
                                            message: result.message
                                        });

                                        refreshList(table, result);
                                        if (jQuery('body').find('.sp-preview-container').find('[name="id"]').length > 0 && jQuery('body').find('.sp-preview-container').find('[name="id"]').val() == id) {
                                            jQuery('body').find('.sp-preview-container').html('');
                                        }

                                        if (elem.data('callback')) {
                                            var functions = elem.data('callback');
                                            if (typeof functions == "string") {
                                                functions = [functions];
                                            }
                                            jQuery.each(functions, function (key, f) {
                                                if (eval("typeof " + f + " === 'function'")) {
                                                    window[f](table.find('.datatables'), result);
                                                }
                                            });
                                        }

                                    } else {
                                        jQuery.growl.error({
                                            title: translations.error_message,
                                            message: result.message
                                        });
                                    }
                                }, "json");
                            }
                        },
                        cancel: {
                            text: translations.cancel,
                            btnClass: 'sp-btn btn-default btn-red btn',
                            action: function () {
                            }
                        },
                    }
                });
            }
        }
    });

    jQuery('body').on('click', '[data-action-type="inline_edit"]', function (e) {
        e.stopPropagation();
        var elem = jQuery(this);
        var table = elem.parents('.sp-list-view-block').find('.datatables');
        if (table.hasClass('sp-list-editable')) {
            table.removeClass('sp-list-editable')
            refreshList(table, null);
        } else {
            table.addClass('sp-list-editable');
            refreshList(table, null);
        }
    });

    var saveRow = function (elem, event) {
        if (jQuery(event.relatedTarget).closest('tr').index() != jQuery(event.currentTarget).closest('tr').index()) {
            var row = elem.parents('tr');

            if (jQuery(row).hasClass("sp-row-edited")) {

                var table = elem.parents('.dataTables_wrapper').find('.datatables');
                var id = elem.data('id');

                var data = {};
                var url = row.find('.sp-save-row').data('url');

                data.id = row.data("row-id");

                jQuery(jQuery(elem.parents('tr').find('.form-control'))).each(function () {
                    if (jQuery(this).data("json")) {
                        data[jQuery(this).attr("name")] = jQuery(this).val();
                    } else {
                        data[jQuery(this).attr("name")] = jQuery(this).val();
                    }
                });

                jQuery(row).find('.sp-save-row').removeClass('hidden');
                jQuery.post(url, data, function (result) {
                    if (result.error == false) {
                        var entity = result.entity;

                        jQuery(row).find('td').each(function () {
                            var td = jQuery(this);


                            if (td.data("attribute"))
                                td.html(entity[td.data("attribute")]);
                        });

                        jQuery(row).find('.sp-save-row').trigger("row_edited", entity);

                        if (result.error == false) {

                            jQuery(row).find('td').each(function () {
                                var td = jQuery(this);

                                if (td.data("attribute"))
                                    td.html(entity[td.data("attribute")]);
                            });
                        }

                        jQuery(row).removeClass("sp-row-edited");
                    } else {
                        jQuery.growl.error({
                            title: translations.error_message,
                            message: result.message
                        });
                    }
                    jQuery(row).find('.sp-save-row').addClass('hidden');
                }, "json");
            }
        }
    }

    /**
     * Tracking changes  inside editable list view row
     */
    jQuery('table.datatables').on('change', 'input, select, textarea', function (event) {
        var tr = jQuery(this).closest('tr')
        tr.addClass("sp-row-edited");
        jQuery(this).trigger("row_value_changed");
        jQuery(this).parents("table").data("editing-row", tr.index());
        saveRow(jQuery(this), event);
    });
    jQuery('table.datatables').on('switchChange.bootstrapSwitch', '[data-type="bchackbox"]', function (event, state) {
        var tr = jQuery(this).closest('tr')
        tr.addClass("sp-row-edited");
        jQuery(this).trigger("row_value_changed");
        jQuery(this).parents("table").data("editing-row", tr.index());
        if (state) {
            jQuery(this).closest('td').find('[data-action="checkbox-value"]').val(1);
        } else {
            jQuery(this).closest('td').find('[data-action="checkbox-value"]').val(0);
        }
        saveRow(jQuery(this).closest('td').find('[data-action="checkbox-value"]'), event);
    });

    /**
     * Method is used in editable list view to track when user leaves a form control
     * and check are changes made to entity inside a row, when changes are found post save entity
     */
    jQuery('table.datatables').on('blur', '.form-control,.sp-select2-cell', function (event) {
        // jQuery('table.datatables').on('focusout', '.form-control,.sp-select2-cell', function (event) {
        event.stopPropagation();
        var elem = jQuery(this);
        saveRow(elem, event);
    });

    /**
     * Standard edit modal form
     */
    /*jQuery('body').on('click', '[data-action="standard_edit_modal_form"]', function(e){
        e.stopPropagation();
        jQuery.post(jQuery(this).data("url"), { id: jQuery(this).data('id') }, function(result) {
            if(result.error == false){
                jQuery('#modal-container').html(result.html);
                var modal = jQuery('#modal-container').find('.modal');
                modal.modal('show');

                var form = modal.find('[data-action="form"]');
                form.initializeValidation();


            }
            else{
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });*/

    /**
     * Standard add modal form
     * TRENUTNO SE NE KORISTI ALI BUDE
     */
    jQuery('body').on('click', '[data-action="standard_add_modal_form"]', function (e) {
        e.stopPropagation();
        var button = jQuery(this).prop('disabled', true);
        standardAddModalForm(jQuery(this).data("url"), button);
    });

    /**
     * Upper save
     */
    jQuery('.sp-main-actions-wrapper').on('click', '[data-action="return"]', function (e) {
        jQuery('[data-validate="true"]').find('[data-action="return"]').trigger('click');
    });

    /**
     * Upper save and continue
     */
    jQuery('.sp-main-actions-wrapper').on('click', '[data-action="continue"]', function (e) {
        jQuery('[data-validate="true"]').find('[data-action="continue"]').trigger('click');
    });


    /**
     * Stop propagation on dropdown toggle
     */
    jQuery('#page-content').on('click', '.dropdown-toggle', function (e) {
        e.stopPropagation();
        jQuery(this).dropdown("toggle");
    });

    /**
     * Multi level menu
     */
    jQuery('ul.dropdown-menu [data-toggle=dropdown]').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        jQuery(this).parent().siblings().removeClass('open');
        jQuery(this).parent().toggleClass('open');
    });

    jQuery('body').on('click', '.sp-datatable-left-button', function (e) {
        e.stopPropagation();
    });

    /**
     * Mice panel kod dokumenata
     */
    if (jQuery('body').find('[data-form-group="file"]').length) {
        var panel = jQuery('body').find('[data-form-group="file"]').find('.panel');
        panel.find('.panel-heading').remove();
        panel.removeClass('panel-midnightblue').removeClass('panel');
        panel.find('.panel-body').removeClass('panel-body').removeClass('collapse').removeClass('in');
    }

    /**
     * Autosumbit on enter
     * OVO SE KORISTI SAMO AKO FILTERI NISU LIVE
     */
    jQuery('.datatables').on('keypress', '.yadcf-filter', function (e) {
        if (e.which == 13) {
            jQuery('[data-action="filter-submit"]').trigger('click');
            return false;
        }
    });

    /**
     * Privremeno ugaseno jer smeta kod otvaranja dropdowna u modalu
     */
    /*jQuery('body').on('click', '.sp-actions-td a', function(e){
        e.stopPropagation();
        window.location = jQuery(this).attr("href");
        return true;
    });*/

    jQuery('body').on('click', '[data-action="standard_preview"] .dropdown-menu a', function (e) {
        e.stopPropagation();
        window.location.href = jQuery(this).attr('href');
        return false;
    });

    /**
     * Advanced search toggle
     */
    jQuery('body').on('click', '[data-action="advanced_search"]', function (e) {
        e.preventDefault();

        var wrapper = jQuery(this).parents('.sp-block').find('.sp-search-wrapper');

        /**
         * Close
         */
        if (jQuery(this).hasClass('sp-active')) {
            jQuery(this).removeClass('sp-active');
            wrapper.removeClass('sp-search-wrapper-active');
        }
        /**
         * Open
         */
        else {
            jQuery(this).addClass('sp-active');
            wrapper.addClass('sp-search-wrapper-active');

            //initGridResize();
        }

        var table = jQuery(this).parents('.sp-block').find('.dataTables_wrapper');
        refreshList(table.find('.datatables'), null);

        return false;
    });

    /**
     * Advanced search use
     */
    jQuery('body').on('click', '[data-action="advanced_search_use"]', function (e) {
        e.preventDefault();

        var table = jQuery(this).parents('.sp-block').find('.dataTables_wrapper');
        refreshList(table.find('.datatables'), null);

        return false;
    });

    /**
     * Advanced search reset
     */
    jQuery('body').on('click', '[data-action="advanced_search_reset"]', function (e) {
        e.preventDefault();

        var form = jQuery('[data-action="advanced_search_reset"]').parents('.sp-advanced-search');
        form.find('[data-type="lookup"]').each(function (e) {
            jQuery(this).val(null).trigger("change");
        });
        form.find('input').val('').trigger("change");
        form.find('select').val('').trigger("change");

        jQuery(this).parents('.sp-block').find('[data-action="filter-reset"]').trigger('click');

        return false;
    });

    /**
     * Toggle multiselect item
     */
    jQuery('body').on('click', '.sp-list-checkbox', function (e) {
        e.stopPropagation();
        toggleSelectItem(jQuery(this), jQuery(this).parents('.dataTables_wrapper').attr('id'));
        return false;
    });

    /**
     * Toggle all visible select items
     */
    jQuery('body').on('click', '.sp-list-checkbox-all', function (e) {
        e.stopPropagation();
        if (jQuery(this).children('i').hasClass('fa-square')) {
            toggleAllItems(jQuery(this).parents('.dataTables_wrapper').attr('id'), 1);
        } else {
            toggleAllItems(jQuery(this).parents('.dataTables_wrapper').attr('id'), 0);
        }

        jQuery(this).children('i').toggleClass('fa-square').toggleClass('fa-check-square');

        return false;
    });

    /**
     * Standard preview
     */
    jQuery('body').on('click', '[data-action="standard_preview"]', function (e) {
        if (jQuery(this).hasClass('sp-row-active')) {
            return false;
        } else {
            jQuery('body').find('[data-action="standard_preview"]').removeClass('sp-row-active');
            jQuery(this).addClass('sp-row-active');
        }

        var preview_wrapper = jQuery('[data-wrapper="preview_sidebar"]');
        var main_wrapper = preview_wrapper.siblings('.main-content');

        var button = jQuery(this).prop('disabled', true);
        jQuery.post(jQuery(this).data("url"), {id: jQuery(this).data('id')}, function (result) {
            if (result.error == false) {
                main_wrapper.removeClass('col-md-12').addClass('col-md-8');
                preview_wrapper.html(result.html).removeClass('hidden');
                onWindowResize();

                preview_wrapper.initializeForm();
                preview_wrapper.find('.form-group-wrapper').find('.col-sm-12').removeClass('col-sm-12');
                if (preview_wrapper.find('[data-form-group="file"]').length) {
                    var panel = preview_wrapper.find('[data-form-group="file"]').find('.panel');
                    panel.find('.panel-heading').remove();
                    panel.removeClass('panel-midnightblue').removeClass('panel');
                    panel.find('.panel-body').removeClass('panel-body').removeClass('collapse').removeClass('in');
                }

                var form = preview_wrapper.find('form');
                var entity_type = form.data('type');
                var func = entity_type + '_standard_preview';

                /**
                 * Raise modal event
                 */
                if (eval("typeof " + func + " === 'function'")) {
                    window[func](form);
                }
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
            button.prop('disabled', false);
        }, "json");
    });

    /**
     * Close standard preview
     */
    jQuery('body').on('click', '[data-action="close_preview"]', function (e) {
        jQuery('body').find('[data-action="standard_preview"]').removeClass('sp-row-active');

        var preview_wrapper = jQuery('[data-wrapper="preview_sidebar"]');
        var main_wrapper = preview_wrapper.siblings('.main-content');

        main_wrapper.removeClass('col-md-8').addClass('col-md-12');
        preview_wrapper.html("").addClass('hidden');
        onWindowResize();

        return false;
    });


    /**
     * Modal preview
     */
    jQuery('body').on('click', '[data-action="modal_preview"]', function (e) {
        var button = jQuery(this).prop('disabled', true);
        jQuery.post(jQuery(this).data("url"), {}, function (result) {
            if (result.error == false) {
                var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('footer'));
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
            button.prop('disabled', false);
        }, "json");
    });

    /**
     * Modal add entity
     * Ovo je standard modal add
     */
    jQuery('body').on('click', '[data-action="modal_add"]', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var button = jQuery(this);

        jQuery.post(jQuery(this).data("url"), {}, function (result) {
            if (result.error == false) {
                var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                clone.html(result.html);
                var modal = clone.find('.modal');
                modal.modal('show');

                var form = modal.find('[data-validate="true"]');
                form.initializeValidation();
                form.forceBoostrapXs();

                if (form.find('[name="pid"]').length > 0 && button.data('pid')) {
                    form.find('[name="pid"]').val(button.data('pid'));
                }
                if (form.find('[name="ptype"]').length > 0 && button.data('ptype')) {
                    form.find('[name="ptype"]').val(button.data('ptype'));
                }

                //postavljamo focus na prvi element koji nije id
                //bootstrap ima delay zbog kojega se nemoze postaviti direktno fokus pa stavljamo delay s setTimeout
                setTimeout(function () {
                    form.find(".form-control:not([name='id']):first").focus().focus();
                }, 500);

                if (modal.find('.datatables').length > 0) {
                    modal.find('.datatables').each(function (e) {
                        jQuery('#' + jQuery(this).attr('id')).createDatatable();
                    });

                    if (modal.find('[data-action="standard_mass_action"]').length > 0) {
                        modal.find('[data-action="standard_mass_action"]').each(function (e) {
                            jQuery(this).bind('click', function () {
                                bindStandardMassActions(jQuery(this));
                            });
                        });
                    }
                }

                // Inicijaliziraj grid view ukoliko ga ima
                /*var gidviewWrapper = modal.find(".sp-grid-view-wrapper");
                if (gidviewWrapper.length) {
                    gidviewWrapper.each(function () {
                        initializeGridView(jQuery(this));
                    });
                }*/

                // Initialize notes if present
                $.initializeNotesCkeditor();
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    /**
     * Standard open row
     */
    jQuery('body').on('click', '[data-action="standard_view"]', function (e) {
        window.location.href = jQuery(this).data("url");
    });

    /**
     * Toggle dropzone
     */
    jQuery('body').on('click', '[data-action="toggle-dropzone"]', function (e) {

        jQuery(this).parents('.form-group').find('[data-action="toggle-hidden"]').toggleClass('hidden');

        //initGridResize();

        return false;
    });

    /**
     * Toggle library dropzone
     */
    jQuery('body').on('click', '[data-action="toggle-library-dropzone"]', function (e) {
        jQuery(this).closest('.sp-block').find('[data-action="toggle-hidden"]').toggleClass('hidden');
        return false;
    });

    /**
     * Toggle library dropzone
     */
    jQuery('body').on('click', '[data-action="remove-single-document"]', function (e) {
        if (jQuery(this).closest('.sp-block').find(".store-file-field").length > 0) {
            jQuery(this).closest('.store-file-field').find('[data-action="toggle-hidden"]').toggleClass('hidden');
            jQuery(this).closest('.store-file-field').find('[data-input="filepath"]').val("");
        } else {
            jQuery(this).closest('.sp-block').find('[data-action="toggle-hidden"]').toggleClass('hidden');
            jQuery(this).closest('.sp-block').find('[data-input="filepath"]').val("");
        }
        return false;
    });

    /**
     * Remove all items in gallery
     */
    jQuery('body').on('click', '[data-action="remove-all-items"]', function (e) {
        var elem = jQuery(this);

        var wrapper = elem.parents('.sp-block');
        var ids = [];
        wrapper.find('.sp-gallery-item').each(function (e) {
            ids.push(jQuery(this).data('gallery-id'));
        });

        jQuery.confirm({
            title: translations.please_confirm,
            content: translations.yes_i_am_sure,
            buttons: {
                confirm: {
                    text: translations.yes_i_am_sure,
                    btnClass: 'sp-btn btn-primary btn-blue btn',
                    keys: ['enter'],
                    action: function () {
                        jQuery.post(elem.data('url'), {
                            entity_type_code: elem.data('entity_type_code'),
                            ids: ids
                        }, function (result) {
                            if (result.error === false) {
                                wrapper.find('.sp-gallery-item').remove();
                                wrapper.find('[data-action="toggle-hidden"]').toggleClass('hidden');
                                wrapper.find('[data-action="toggle-library-dropzone"]').toggleClass('hidden');
                                wrapper.find('[data-action="remove-all-items"]').toggleClass('hidden');
                                wrapper.find('[data-action="download-files"]').toggleClass('hidden');
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
                        }, 'json');
                    }
                },
                cancel: {
                    text: translations.cancel,
                    btnClass: 'sp-btn btn-default btn-red btn',
                    action: function () {
                    }
                }
            }
        });

        return false;
    });

    /**
     * Rotate image
     */
    jQuery('body').on('click', '[data-action="rotate-image"]', function (e) {
        var wrapper = jQuery(this).parents('.sp-gallery-item');

        jQuery.post(jQuery(this).data('url'), {
            entity_type_code: jQuery(this).data('entity_type_code'),
            id: jQuery(this).data('id'),
            direction: jQuery(this).data('direction')
        }, function (result) {
            if (result.error == false) {
                wrapper.replaceWith(result.html);
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
        }, 'json');

        return false;
    });

    /**
     * Set image alt
     */
    // jQuery('.sp-gallery-item-metadata [name="image_alt"]').on('change keyup', function (e) {
    //     if ($(this).val() != $(this).data("value")) {
    //         $(this).parent().find(".save").removeClass("hidden");
    //     } else {
    //         $(this).parent().find(".save").addClass("hidden");
    //     }
    // });
    // jQuery('body').on('click', '[data-action="set-image-alt"]', function (e) {
    //     var saveBtn = $(this);
    //     var value = jQuery(this).parent().find("input").val();
    //
    //     jQuery.post(jQuery(this).data('url'), {
    //         entity_type_code: jQuery(this).data('entity_type_code'),
    //         id: jQuery(this).data('id'),
    //         value: value
    //     }, function (result) {
    //         if (result.error == false) {
    //             jQuery.growl.notice({
    //                 title: result.title,
    //                 message: result.message
    //             });
    //             saveBtn.addClass("hidden");
    //             saveBtn.parent().find("input").data("value", value);
    //         } else {
    //             jQuery.growl.error({
    //                 title: translations.error_message,
    //                 message: result.message
    //             });
    //         }
    //     }, 'json');
    //
    //     return false;
    // });
    $(document).on('keyup', '.sp-gallery-item-metadata [name="image_alt"]', $.debounce(function (e) {
        var value = jQuery(this).val();

        jQuery.post(jQuery(this).closest(".sp-gallery-item-metadata").find('[data-action="set-image-alt"]').data('url'), {
            entity_type_code: jQuery(this).closest(".sp-gallery-item-metadata").find('[data-action="set-image-alt"]').data('entity_type_code'),
            id: jQuery(this).closest(".sp-gallery-item-metadata").find('[data-action="set-image-alt"]').data('id'),
            value: value
        }, function (result) {
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
        }, 'json');
    }, 1000));

    /**
     * Set image title
     */
    // jQuery('.sp-gallery-item-metadata [name="image_title"]').on('change keyup', function (e) {
    //     if ($(this).val() != $(this).data("value")) {
    //         $(this).parent().find(".save").removeClass("hidden");
    //     } else {
    //         $(this).parent().find(".save").addClass("hidden");
    //     }
    // });
    // jQuery('body').on('click', '[data-action="set-image-title"]', function (e) {
    //     var saveBtn = $(this);
    //     var value = jQuery(this).parent().find("input").val();
    //
    //     jQuery.post(jQuery(this).data('url'), {
    //         entity_type_code: jQuery(this).data('entity_type_code'),
    //         id: jQuery(this).data('id'),
    //         value: value
    //     }, function (result) {
    //         if (result.error == false) {
    //             jQuery.growl.notice({
    //                 title: result.title,
    //                 message: result.message
    //             });
    //             saveBtn.addClass("hidden");
    //             saveBtn.parent().find("input").data("value", value);
    //         } else {
    //             jQuery.growl.error({
    //                 title: translations.error_message,
    //                 message: result.message
    //             });
    //         }
    //     }, 'json');
    //
    //     return false;
    // });

    $(document).on('keyup', '.sp-gallery-item-metadata [name="image_title"]', $.debounce(function (e) {
        var value = jQuery(this).val();

        jQuery.post(jQuery(this).closest(".sp-gallery-item-metadata").find('[data-action="set-image-title"]').data('url'), {
            entity_type_code: jQuery(this).closest(".sp-gallery-item-metadata").find('[data-action="set-image-title"]').data('entity_type_code'),
            id: jQuery(this).closest(".sp-gallery-item-metadata").find('[data-action="set-image-title"]').data('id'),
            value: value
        }, function (result) {
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
        }, 'json');
    }, 1000));

    /**
     * Remove image
     */
    jQuery('body').on('click', '[data-action="remove-image"]', function (e) {
        var wrapper = jQuery(this).parents('.form-group');

        wrapper.find('[data-input="filepath"]').val('');
        wrapper.find('.image-holder').remove();//addClass('hidden');
        wrapper.find('.dropzone-wrapper').removeClass('hidden');

        return false;
    });

    /**
     * Download all gallery files
     */
    jQuery('body').on('click', '[data-action="download-files"]', function (e) {
        var elem = jQuery(this);

        jQuery.post(elem.data('url'), {
            entity_type_code: elem.data('entity_type_code'),
            parent_attribute_id: elem.data('parent_attribute_id'),
            parent_id: elem.data('parent_id'),
            related_entity_type: elem.data('related_entity_type')
        }, function (result) {
            if (result.error === false) {
                window.open(result.filepath, '_blank');
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, 'json');

        return false;
    });

    /**
     * Remove modal on hide
     */
    jQuery('body').on('hidden.bs.modal', '.modal', function () {
        $(this).data('bs.modal', null);
        $(this).parent().remove();
    });

    /**
     * Linked attributes
     */

    /**
     * Get related list
     */
    jQuery('body').on('change', '[data-related-action="reload-list"]', function (e) {
        var related_code = jQuery(this).data('related-code');
        jQuery.post(jQuery(this).data("related-url"), {
            code: jQuery(this).data('code'),
            val: jQuery(this).val(),
            related_code: related_code,
            related_val: jQuery('[name="' + related_code + '"]').val()
        }, function (result) {
            if (result.error == false) {
                jQuery('[name="' + related_code + '"]').html(result.html);
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    /**
     * Info popover
     */
    var popover_options = {
        placement: function (context, source) {
            if (jQuery(source).data("placement")) {
                return jQuery(source).data("placement");
            }

            var position = jQuery(source).offset();

            if (position.left > 515) {
                return "left";
            }

            if (position.left < 515) {
                return "right";
            }

            if (position.top < 110) {
                return "bottom";
            }

            return "top";
        },
        trigger: "hover",
        container: 'body'
    };
    jQuery('[rel="popover"]').popover(popover_options);

    // -------------------------------
    // Back to Top button
    // -------------------------------

    $('#back-to-top').click(function () {
        $('body,html').animate({
            scrollTop: 0
        }, 500);
        return false;
    });

    // -------------------------------
    // Panel Collapses
    // -------------------------------
    $('a.panel-collapse').click(function () {
        $(this).children().toggleClass("fa-chevron-down fa-chevron-up");
        $(this).closest(".panel-heading").next().slideToggle({duration: 200});
        $(this).closest(".panel-heading").toggleClass('rounded-bottom');
        return false;
    });

    /*if($('.navbar-nav').length < 1){
        $('body').addClass('sp-no-padding');
    }*/

    /**
     * Login submit
     */
    if (jQuery('[data-action="login-form"]').length > 0) {
        jQuery('[data-action="login-submit"]').bind('click', function (e) {
            e.preventDefault();

            if (!jQuery('[data-action="login-form"]').find('[name="_username"]').val() || !jQuery('[data-action="login-form"]').find('[name="_password"]').val()) {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: translations.login_error
                });
                return false;
            }

            jQuery('.panel-primary').animate({height: 0}, 600, function () {
                jQuery(this).hide();
                jQuery('.sp-verticalcenter').animate({'marginTop': '30px'}, 500, function () {
                    jQuery('[data-action="login-form"]').submit();
                });
            });
        });
    }

    /**
     * Request new password submit
     */
    if (jQuery('[data-action="request-form"]').length > 0) {
        jQuery('[data-action="request-submit"]').bind('click', function (e) {
            e.preventDefault();

            if (!jQuery('[data-action="request-form"]').find('[name="username"]').val()) {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: translations.login_error
                });
                return false;
            } else {
                jQuery('[data-action="request-form"]').submit();
            }
        });
    }

    /**
     * Reset password
     */
    if (jQuery('[data-action="reset-form"]').length > 0) {
        jQuery('[data-action="reset-submit"]').bind('click', function (e) {
            e.preventDefault();

            if (!jQuery('[data-action="reset-form"]').find('#fos_user_resetting_form_plainPassword_first').val() || !jQuery('[data-action="reset-form"]').find('#fos_user_resetting_form_plainPassword_second').val()) {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: translations.login_error
                });
                return false;
            } else {
                jQuery('[data-action="reset-form"]').submit();
            }
        });
    }

    /**
     * Select menu item
     */
    var location = prepareUrl(window.location.pathname);
    if (jQuery('.navbar-nav').find('a[href^="' + location + '/"]').length > 0) {
        var selected_url = jQuery('.navbar-nav').find('a[href^="' + location + '/"]');
        selected_url.parents('li').addClass('active');
    }

    jQuery(document).on("click", 'ul.navbar-nav [data-toggle="dropdown"]', function () {
        jQuery(this).parent().siblings(".active").removeClass("active");
        jQuery(this).parent().toggleClass("active");
    })

    /**
     * Dismiss modal
     */
    jQuery('body').on('click', '[data-action="dismiss-modal"]', function (e) {

        e.preventDefault();
        e.stopPropagation();

        jQuery(this).parents('.modal').modal('hide');
    });

    /**
     * Export dropdown toggle
     */
    jQuery('body').on('click', '[data-toggle="dropdown-export"]', function (e) {
        jQuery(this).siblings('[data-menu="dropdown-export"]').toggle();
    });
    /**
     * End Export dropdown toggle
     */

    /**
     * Close dropdown on item click
     */
    jQuery('.sp-listview-dropdown span.menu-item').on('click', function (e) {
        jQuery(this).parents('.sp-listview-dropdown-wrapper').removeClass('open');
    });

    /**
     * Default form button
     */
    jQuery('body').on('click','[data-action="default_form_button"]',function (e) {
        e.preventDefault();
        e.stopPropagation();

        var button = jQuery(this);
        var id = jQuery('[data-validate="true"]').find('[name="id"]').val();

        jQuery.confirm({
            title: translations.please_confirm,
            content: translations.yes_i_am_sure,
            buttons: {
                confirm: {
                    text: translations.yes_i_am_sure,
                    btnClass: 'sp-btn btn-primary btn-blue btn',
                    keys: ['enter'],
                    action: function () {
                        button.attr("disabled","disabled");

                        jQuery.post(button.data("url"), {id: id}, function (result) {
                            if (result.error == false) {
                                button.removeAttr("disabled");

                                if(result.html){
                                    var clone = $('#modal-container').clone(true, true).appendTo($('body'));
                                    clone.html(result.html);
                                    clone.find('.modal').modal('show');

                                    var modal = $('.modal');

                                    var form = modal.find('[data-validate="true"]');
                                    form.initializeValidation();
                                }
                                else{
                                    window.location.reload(true);
                                }
                            }
                            else {
                                button.removeAttr("disabled");
                                jQuery.growl.error({
                                    title: translations.error_message,
                                    message: result.message
                                });
                            }
                        }, "json");
                    }
                },
                cancel: {
                    text: translations.cancel,
                    btnClass: 'sp-btn btn-default btn-red btn',
                    action: function () {
                        button.removeAttr("disabled");
                    }
                }
            }
        });
    });
});

// check if array contains value
Array.prototype.contains = function (needle) {
    for (i in this) {
        if (this[i] == needle) return true;
    }
    return false;
};


/**
 * Task reacurring toggle
 */
jQuery(document).ready(function () {

    jQuery('body').on('switchChange.bootstrapSwitch', '#repeat_event_checkbox', function (event, state) {

        if (state == true) {
            jQuery('body').find('#repeat_options_holder').show();
            jQuery('body').find('#repeat_interval_group :input').prop("disabled", false);
            jQuery('body').find('#ending_on_date :input').prop("disabled", false);

        } else {
            jQuery('body').find('#repeat_options_holder').hide();
            jQuery('body').find('#repeat_interval_group :input').prop("disabled", true);
            jQuery('body').find('#ending_on_date :input').prop("disabled", true);
            jQuery('body').find('#ending_after_number :input').prop("disabled", true);
        }
    });

    jQuery('body').on('change', '#ending_condition', function () {

        if (jQuery(this).val() == "on") {
            jQuery('body').find('#ending_after_number').hide();
            jQuery('body').find('#ending_after_number :input').prop("disabled", true);
            jQuery('body').find('#ending_on_date').show();
            jQuery('body').find('#ending_on_date :input').prop("disabled", false);
        } else {
            jQuery('body').find('#ending_after_number').show();
            jQuery('body').find('#ending_after_number :input').prop("disabled", false);
            jQuery('body').find('#ending_on_date').hide();
            jQuery('body').find('#ending_on_date :input').prop("disabled", true);
        }


    });

    jQuery('body').on('change', '#repeat_type', function () {

        jQuery('body').find('#repeat_interval_group').show();
        jQuery('body').find('#repeat_interval_group :input').prop("disabled", false);
        jQuery('body').find('#repeat_on_group').hide();
        jQuery('body').find('#repeat_by_group').hide();

        if (jQuery(this).val() == "daily") {
            jQuery('body').find('#repeat_interval_label').html("Days");
        } else if (jQuery(this).val() == "weekly") {
            jQuery('body').find('#repeat_interval_label').html("Weeks");
            jQuery('body').find('#repeat_on_group').show();
        } else if (jQuery(this).val() == "monthly") {
            jQuery('body').find('#repeat_interval_label').html("Months");
            jQuery('body').find('#repeat_by_group').show();
        } else if (jQuery(this).val() == "yearly") {
            jQuery('body').find('#repeat_interval_label').html("Years");
        } else {
            jQuery('body').find('#repeat_interval_group').hide();
            jQuery('body').find('#repeat_interval_group :input').prop("disabled", true);
        }
    });
});

/**
 * Callback function
 * @param table
 * @param result
 * @returns {boolean}
 */
function refreshPage(table, result) {

    window.location.reload();

    return false;
}

function bindStandardMassActions(button) {
    var table = button.parents('.sp-block').find('.dataTables_wrapper');

    if (!selectList[table.attr('id')] && !button.hasClass("allow-all")) {
        jQuery.growl.error({
            title: translations.error_message,
            message: translations.no_items_selected
        });
        return false;
    }

    if (button.data('confirm')) {
        jQuery.confirm({
            title: translations.please_confirm,
            content: translations.yes_i_am_sure,
            buttons: {
                confirm: {
                    text: translations.yes_i_am_sure,
                    btnClass: 'sp-btn btn-primary btn-blue btn',
                    keys: ['enter'],
                    action: function () {
                        jQuery.post(button.data("url"), {
                            items: selectList[table.attr('id')],
                            parent_entity_id: (jQuery("[name='id']").length ? jQuery("[name='id']").val() : null)
                        }, function (result) {
                            if (result.error == false) {
                                if(result.message){
                                    jQuery.growl.notice({
                                        title: result.title,
                                        message: result.message
                                    });
                                }

                                if (button.data('callback')) {
                                    var functions = button.data('callback');
                                    jQuery.each(functions, function (key, f) {
                                        if (eval("typeof " + f + " === 'function'")) {
                                            window[f](table.find('.datatables'), result);
                                        }
                                    });
                                }

                                if(result.html){
                                    var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                                    clone.html(result.html);
                                    var modal = clone.find('.modal');
                                    modal.modal('show');

                                    var form = modal.find('[data-validate="true"]');
                                    form.initializeValidation();
                                }

                                if(result.file){
                                    window.open(result.file, '_blank');
                                }
                            } else {
                                jQuery.growl.error({
                                    title: translations.error_message,
                                    message: result.message
                                });
                            }
                        }, "json");
                    }
                },
                cancel: {
                    text: translations.cancel,
                    btnClass: 'sp-btn btn-default btn-red btn',
                    action: function () {
                    }
                }
            }
        });
    } else {
        jQuery.post(button.data("url"), {
            items: selectList[table.attr('id')],
            parent_entity_id: (jQuery("[name='id']").length ? jQuery("[name='id']").val() : null)
        }, function (result) {
            if (result.error == false) {
                if(result.message){
                    jQuery.growl.notice({
                        title: result.title,
                        message: result.message
                    });
                }

                if (button.data('callback')) {
                    var functions = button.data('callback');
                    jQuery.each(functions, function (key, f) {
                        if (eval("typeof " + f + " === 'function'")) {
                            window[f](table.find('.datatables'), result);
                        }
                    });
                }

                if(result.html){
                    var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                    clone.html(result.html);
                    var modal = clone.find('.modal');
                    modal.modal('show');

                    var form = modal.find('[data-validate="true"]');
                    form.initializeValidation();
                }
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    }

    jQuery(this).parents('.options').removeClass('open');

    return false;
}
