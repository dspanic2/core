var calendars = {};

function initializeTimepicker(elem, initial) {
    if (typeof initial === 'undefined') {
        initial = true;
    }

    elem.timepicker({
        defaultTime: elem.val(),
        template: false,
        showInputs: false,
        showMeridian: false,
        minuteStep: 1,
        secondStep: 1,
        showSeconds: true
    });
    return true;
}


function initializeDatesingle(elem, initial) {
    if (typeof initial === 'undefined') {
        initial = true;
    }

    var val = elem.val();
    elem.daterangepicker({
        singleDatePicker: true,
        showDropdowns: true,
        locale: {
            firstDay: translations.firstDayNumber,
            cancelLabel: translations.Clear,
            format: 'DD/MM/YYYY',
            monthNames: [translations.January, translations.February, translations.March, translations.April, translations.May, translations.June, translations.July, translations.August, translations.September, translations.October, translations.November, translations.December],
            monthNamesShort: [translations.Jan, translations.Feb, translations.Mar, translations.Apr, translations.May, translations.Jun, translations.Jul, translations.Aug, translations.Sep, translations.Oct, translations.Nov, translations.Dec],
            dayNames: [translations.Sunday, translations.Monday, translations.Tuesday, translations.Wednesday, translations.Thursday, translations.Friday, translations.Saturday],
            daysOfWeek: [translations.Sun, translations.Mon, translations.Tue, translations.Wed, translations.Thu, translations.Fri, translations.Sat],
        }
    });
    if (!initial && !val) {
        elem.val('');
    }
    return true;
}

function initializeDateTimesingle(elem, initial) {
    if (typeof initial === 'undefined') {
        initial = true;
    }

    var val = elem.val();
    elem.daterangepicker({
        autoApply: false,
        singleDatePicker: true,
        showDropdowns: true,
        timePicker: true,
        timePickerIncrement: 1,
        timePicker24Hour: true,
        timePickerSeconds: true,
        locale: {
            firstDay: translations.firstDayNumber,
            cancelLabel: translations.Clear,
            format: 'DD/MM/YYYY HH:mm:ss',
            monthNames: [translations.January, translations.February, translations.March, translations.April, translations.May, translations.June, translations.July, translations.August, translations.September, translations.October, translations.November, translations.December],
            monthNamesShort: [translations.Jan, translations.Feb, translations.Mar, translations.Apr, translations.May, translations.Jun, translations.Jul, translations.Aug, translations.Sep, translations.Oct, translations.Nov, translations.Dec],
            dayNames: [translations.Sunday, translations.Monday, translations.Tuesday, translations.Wednesday, translations.Thursday, translations.Friday, translations.Saturday],
            daysOfWeek: [translations.Sun, translations.Mon, translations.Tue, translations.Wed, translations.Thu, translations.Fri, translations.Sat],
        }
    });
    if (!initial && !val) {
        elem.val('');
    }
    return true;
}

function initializeDaterange(elem, initial, localParent) {
    if (typeof initial === 'undefined') {
        initial = true;
    }
    if (typeof localParent === 'undefined') {
        localParent = false;
    }

    var val = elem.val();

    var options = {
        autoApply: false,
        locale: {
            firstDay: translations.firstDayNumber,
            cancelLabel: translations.Clear,
            format: 'DD/MM/YYYY',
            monthNames: [translations.January, translations.February, translations.March, translations.April, translations.May, translations.June, translations.July, translations.August, translations.September, translations.October, translations.November, translations.December],
            monthNamesShort: [translations.Jan, translations.Feb, translations.Mar, translations.Apr, translations.May, translations.Jun, translations.Jul, translations.Aug, translations.Sep, translations.Oct, translations.Nov, translations.Dec],
            dayNames: [translations.Sunday, translations.Monday, translations.Tuesday, translations.Wednesday, translations.Thursday, translations.Friday, translations.Saturday],
            daysOfWeek: [translations.Sun, translations.Mon, translations.Tue, translations.Wed, translations.Thu, translations.Fri, translations.Sat],
        },
    };

    if (localParent) {
        options.parentEl = elem.parent();
    }

    elem.daterangepicker(options);

    if (!initial && !val) {
        elem.val('');
    }
    return true;
}

function initializeDaterangeTime(elem, initial) {
    if (typeof initial === 'undefined') {
        initial = true;
    }

    var val = elem.val();
    elem.daterangepicker({
        autoApply: false,
        timePicker: true,
        timePickerIncrement: 30,
        timePicker24Hour: true,
        locale: {
            firstDay: translations.firstDayNumber,
            cancelLabel: translations.Clear,
            format: 'DD/MM/YYYY H:mm',
            monthNames: [translations.January, translations.February, translations.March, translations.April, translations.May, translations.June, translations.July, translations.August, translations.September, translations.October, translations.November, translations.December],
            monthNamesShort: [translations.Jan, translations.Feb, translations.Mar, translations.Apr, translations.May, translations.Jun, translations.Jul, translations.Aug, translations.Sep, translations.Oct, translations.Nov, translations.Dec],
            dayNames: [translations.Sunday, translations.Monday, translations.Tuesday, translations.Wednesday, translations.Thursday, translations.Friday, translations.Saturday],
            daysOfWeek: [translations.Sun, translations.Mon, translations.Tue, translations.Wed, translations.Thu, translations.Fri, translations.Sat],
        }
    });
    if (!initial && !val) {
        elem.val('');
    }
    return true;
}

function removeDatepicker(elem) {
    elem.data('daterangepicker').remove();
}


function goBackWithRefresh(event) {

    var ref = sessionStorage.getItem('history');
    if (ref) {
        ref = jQuery.parseJSON(ref);
    }

    var currentUrl = window.location.href;
    if (currentUrl) {
        currentUrl = currentUrl.split('?');
        currentUrl = currentUrl[0];

        currentUrl = currentUrl.split('#');
        currentUrl = currentUrl[0];
    }

    var i = ref.length - 1;
    for (i; i >= 0; i--) {
        if (ref[i] == currentUrl) {
            ref.splice(i, 1);
        } else {
            var url = ref[i];
            var tabId = sessionStorage.getItem("tab-" + $.hashCode(url));
            if ((typeof tabId === 'undefined' || !tabId) && window.sessionStorage) {
                tabId = "";
            }
            // sessionStorage.setItem(tabId + 'history', JSON.stringify(ref));
            window.location = url + tabId;
            return;
        }
    }
    return false;
}

function advanced_search(a, result) {
    jQuery("#advanced-result-wrapper").html(result.html);
    jQuery('#' + jQuery("#advanced-result-wrapper").find('.datatables').attr('id')).createDatatable();
}

/**
 * Trigers admin navigation export to json so we dont have to do that mannualy
 */
function exportMenuToJson() {
    jQuery('#btnOut').trigger('click');
}

function downloadFile(form, result) {

    var file = "";
    if(result.file){
        file = result.file;
    }
    else if(result.filepath){
        file = result.filepath;
    }

    var win = window.open(file, '_blank');
    if (result.error === false) {
        if (win) {
            // Browser has allowed it to be opened
            win.focus();
        } else {
            // Browser has blocked it
            alert('Please allow popups for this website');
        }
    } else {
        jQuery.growl.error({
            title: translations.error_message,
            message: result.message
        });
    }
    return false;
}

function showImportResults(form, result) {
    form.find('.sp-matching-container').hide();
    form.find('.sp-results-container').html(result.html).show();
    form.find('.btn-primary').hide();
    jQuery('body').find('.datatables').each(function (e) {
        jQuery(this).DataTable().ajax.reload(null, false);
    });
    return false;
}

function gridstackSort(a, b) {
    if (a['y'] === b['y']) {
        if (a['x'] === b['x']) {
            return 0;
        } else {
            return (a['x'] < b['x']) ? -1 : 1;
        }
    } else {
        return (a['y'] < b['y']) ? -1 : 1;
    }
}

/**
 * Standard add modal form
 */
function standardAddModalForm(url, button) {
    jQuery.post(url, {}, function (result) {
        if (result.error == false) {
            var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('footer'));
            clone.html(result.html);
            var modal = clone.find('.modal');
            modal.modal('show');

            modal.data('source_url', url);

            var form = modal.find('[data-validate="true"]');
            form.initializeValidation();

            var entity_type = form.data('type');
            var func = entity_type + '_modal_open';

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
        if (button) {
            button.prop('disabled', false);
        }
    }, "json");
}

/**
 * URL parameters to string
 */
function getUrlAsAstring() {
    if (window.location.href.indexOf('?') > 0) {
        var params = "?", hash;
        var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
        for (var i = 0; i < hashes.length; i++) {
            hash = hashes[i].split('=');
            params += hash[0] + "=" + hash[1];
        }
        return params;
    } else {
        return "";
    }
}

function custom(form, result) {
    var custom_function = form.data('custom-callback');
    jQuery.each(custom_function, function (key, f) {
        if (eval("typeof " + f + " === 'function'")) {
            window[f](form, result);
        }
    });
    return false;
}

function postSave(form, result) {
    if (form.parents(".redirect-to-edit").length > 0) {
        if (result.error == false) {
            var redirect_url = "/page/" + result.entity.entity_type_code + "/form/" + result.entity.id;
            window.location.replace(redirect_url);
        }
    }
}

function postCloneRedirect(form, result) {
    console.log(typeof result.entity !== "undefined");
    if (typeof result.entity !== "undefined") {
        window.location.replace("/page/" + result.entity.attribute_set_code + "/form/" + result.entity.id);
    }
}

function initializeLookup(elem, form) {
    var searched_for = "";
    var show_create_new = false;
    var show_create_new_type = "simple";
    var show_create_new_url = "";
    var is_in_search_form = elem.parents('.sp-search-wrapper').length > 0 ? true : false;
    var singleclick = true;
    var parentElem = jQuery('body');
    if (form && form.parents('.modal').length > 0) {
        parentElem = form.parents('.modal');
    }

    var attr = elem.attr('readonly');
    if (typeof attr !== typeof undefined && attr !== false) {
        return false;
    }

    elem.select2({
        minimumInputLength: 0,//jQuery(this).data('min-len'),
        width: '100%',
        closeOnSelect: true,
        dropdownParent: parentElem,
        ajax: {
            url: elem.data('search-url'),
            dataType: 'json',
            quietMillis: 100,
            type: "POST",
            data: function (term) {
                searched_for = term.term;
                var ret = {};
                ret['q'] = term;
                ret['id'] = elem.data('id');
                ret['template'] = elem.data('template');
                ret['form'] = jQuery('[data-type="' + elem.parents('form').data('type') + '"] [name]').not('[data-type="ckeditor"],textarea,[data-avoid="autocomplete"],[data-multivalue="1"]').serialize();

                /**
                 * FIX for disabled lookups
                 * @type {string}
                 */
                var disabled_lookups = "";
                var disabled_lookup_array = {};
                var disabled_lookup_exists = false;
                if (jQuery('body').find('[data-type="lookup"][data-single="true"]:disabled').length > 0) {
                    jQuery('body').find('[data-type="lookup"][data-single="true"]:disabled').each(function (e) {
                        if (jQuery(this).val()) {
                            disabled_lookup_array[jQuery(this).attr('name')] = jQuery(this).val();
                            disabled_lookup_exists = true;
                        }
                    });
                    if (disabled_lookup_exists) {
                        disabled_lookups = '&' + jQuery.param(disabled_lookup_array);
                    }
                }
                ret['form'] = ret['form'] + disabled_lookups;

                if (elem.data('related')) {
                    var related = elem.data('related');
                    jQuery.each(related, function (key, f) {
                        var el = f.split(':');
                        if (form.find('[name="' + el[0] + '"]').length > 0) {
                            ret[f] = form.find('[name="' + el[0] + '"]').val();
                        }
                    });
                }

                return ret;
                /*return {
                 q: term,
                 id: jQuery(this).data('id'),
                 template: jQuery(this).data('template'),

                 };*/
            },
            processResults: function (data, params) {
                singleclick = true;
                if (data.error == false) {
                    show_create_new = data.create_new;
                    show_create_new_type = data.create_new_type;
                    show_create_new_url = data.create_new_url;
                    var res = {
                        results: jQuery.map(data.ret, function (item) {
                            return {
                                id: item.id,
                                text: item.html,
                                title: jQuery(jQuery.parseHTML(item.html)).text()
                            }
                        })
                    };
                    return res;
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: data.message
                    });
                }
            }
            //cache: true
        },
        templateResult: function select2FormatResult(item) {
            return "<li title='" + jQuery(jQuery.parseHTML(item.html)).text() + "' data-id='" + item.id + "' class='sp-select-2-result'>" + item.text + "<li>";
        },
        escapeMarkup: function (m) {
            return m;
        }, // we do not want to escape markup since we are displaying html in results
        language: {
            searching: function () {
                return translations.searching
            },
            errorLoading: function () {
                return translations.searching_in_progress
            },
            noResults: function (params) {
                /**
                 * Autocomplete create not found result
                 */
                jQuery(".autocomplete-noresult-create").remove(); //remove previously returned buttons
                if (show_create_new && !is_in_search_form) {
                    jQuery(document).on("click", ".autocomplete-noresult-create", function (e) {
                        elem.off('click');
                        if (singleclick) {
                            singleclick = false;
                            if (show_create_new_type == "simple") {
                                jQuery.post(
                                    elem.data("search-create-url"),
                                    {
                                        "attribute_id": elem.data('id'),
                                        "name": searched_for
                                    },
                                    function (result) {
                                        if (result.error == false) {

                                            var option = new Option(result.ret.data.name, result.ret.data.id, true, true);
                                            elem.append(option).trigger('change');

                                            elem.trigger({
                                                type: 'select2:select',
                                                params: {
                                                    data: result.ret.data.id
                                                }
                                            });
                                            elem.select2('close');
                                        } else {
                                            jQuery.growl.error({
                                                title: translations.error_message,
                                                message: translations.there_has_been_an_error_please_try_again
                                            });
                                        }
                                        e.stopImmediatePropagation();
                                        e.preventDefault();
                                    },
                                    'json');
                            } else {
                                jQuery.post(show_create_new_url, {}, function (result) {
                                    if (result.error == false) {
                                        var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                                        clone.html(result.html);
                                        var modal = clone.find('.modal');
                                        modal.modal('show');

                                        var form = modal.find('[data-validate="true"]');
                                        form.initializeValidation();
                                        jQuery('#default_modal').on('shown.bs.modal', function (e) {
                                            form.forceBoostrapXs();
                                        });

                                        form.data('callback', ["modalFormAfterSave"]);
                                        form.data('parent-form-group', elem.parents('.form-group').data('form-group'));
                                    } else {
                                        jQuery.growl.error({
                                            title: translations.error_message,
                                            message: result.message
                                        });
                                    }
                                }, "json");
                            }
                        }
                    });
                    return translations.no_results_fund + ".<span class='autocomplete-noresult-create pull-right' data-name='" + elem.data('id') + "'>" + translations.create + "</span>";
                } else {
                    return translations.no_results_fund + ".";
                }
            }
        }
    });

    /*var attr = elem.attr('readonly');
    if (typeof attr !== typeof undefined && attr !== false) {
        elem.select2({'readonly': true});
    }*/

    return true;
}

function getUrlParameter(sParam, url) {
    if (typeof url === 'undefined') {
        return false;
    }
    var sPageURL = url.split('?');
    if (typeof sPageURL === 'undefined' || !sPageURL[1]) {
        return false;
    }
    sPageURL = sPageURL[1];
    var sURLVariables = sPageURL.split('&'),
        sParameterName,
        i;

    for (i = 0; i < sURLVariables.length; i++) {
        sParameterName = sURLVariables[i].split('=');

        if (sParameterName[0] === sParam) {
            return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
        }
    }
}

function addQueryToUrl(url, param) {

    if (url.indexOf("?") >= 0) {
        if (url.indexOf(param) < 1) {
            url = url + '&' + param;
        }
    } else {
        url = url + '?' + param;
    }

    return url;
}

function setCookieHistory() {

    var ref = [];

    if (jQuery('body').find('[data-page-type="list"]').length > 0) {
        ref.push(document.referrer);
        sessionStorage.setItem('history', JSON.stringify(ref));
        //Cookies.set(tabId, JSON.stringify(ref));
        return false;
    }

    var refUrl = document.referrer;
    if (refUrl) {
        refUrl = refUrl.split('?');
        refUrl = refUrl[0];
    }

    ref = sessionStorage.getItem('history');
    ref = jQuery.parseJSON(ref);

    if (typeof ref === 'undefined' || ref === null) {
        ref = [];
        ref.push(document.referrer);
    } else if (ref[ref.length - 1] != refUrl) {
        if (getUrlParameter('noref', window.location.href) != 1 && getUrlParameter('noref', document.referrer) != 1) {
            ref.push(document.referrer);
        }
    }
    sessionStorage.setItem('history', JSON.stringify(ref));
}

function serializeGridstack(grid) {

    var block_list = [];

    grid.children('.grid-stack-item').each(function (e) {
        var el = jQuery(this);

        var block = {};
        block['id'] = el.attr('data-gs-id');
        block['type'] = el.attr('data-gs-type');
        block['title'] = el.attr('data-gs-title');
        block['x'] = el.data("gs-x");
        block['y'] = el.data("gs-y");
        block['width'] = el.data("gs-width");
        block['height'] = el.data("gs-height");
        block['children'] = null;

        if (el.children('.grid-stack-item-content').children('.grid-stack-inner-wrapper').length > 0) {
            block['children'] = serializeGridstack(el.children('.grid-stack-item-content').children('.grid-stack-inner-wrapper').children('[data-action="grid_stack_inner"]'));
        }

        block_list.push(block);
    });

    block_list.sort(gridstackSort);
    return block_list;
}

setCookieHistory();

jQuery.fn.extend({
    initializeForm: function () {
        var form = jQuery(this);

        if (form.find('.dropzone').length) {
            Dropzone.autoDiscover = false;
        }

        /**
         * Textarea autosize
         */
        if (form.find('[data-size="autosize"]').length > 0) {
            form.find('[data-size="autosize"]').autosize({append: "\n"});
        }

        /**
         * Multiselect
         */
        if (form.find('[data-type="multiselect"]').length > 0) {
            form.find('[data-type="multiselect"]').multiSelect();
        }

        /**
         * Ckeditor
         */
        if (form.find('[data-type="ckeditor"]').length > 0) {
            initializeCkeditor(form);
        }

        /**
         * Multiselect tag
         */
        if (form.find('[data-type="multiselect_tag"]').length > 0) {
            form.find('[data-type="multiselect_tag"]').select2();
        }

        /**
         * Bootstrap checkbox
         */
        if (form.find('[data-type="bchackbox"]').length > 0) {
            form.find('[data-type="bchackbox"]').each(function (e) {
                jQuery(this).bootstrapSwitch();
                if (jQuery(this).data('form-type') == 'view') {
                    jQuery(this).bootstrapSwitch('readonly', true);
                }
            });
            jQuery('[data-type="bchackbox"]').on('switchChange.bootstrapSwitch', function (event, state) {
                if (state) {
                    jQuery(this).parents('.form-group').find('[data-action="checkbox-value"]').val(1);
                } else {
                    jQuery(this).parents('.form-group').find('[data-action="checkbox-value"]').val(0);
                }
            });

        }

        /**
         * Multiselect block
         */
        if (form.find('.sp-multiselect-wrapper').length > 0) {
            form.find('.sp-multiselect-wrapper').each(function (e) {
                var wrapper = jQuery(this);

                if (wrapper.find('.sp-sortable')) {
                    wrapper.find('.sp-sortable').sortable();
                }

                wrapper.find('.sp-sortable > li').each(function (e) {
                    wrapper.find('.sp-available').find('[data-id="' + jQuery(this).data('id') + '"]').hide();
                });

                wrapper.on('click', '[data-multiselect="select_all"]', function (e) {
                    wrapper.find('.sp-available > li:visible').each(function (e) {
                        jQuery(this).trigger('click');
                    })
                });

                wrapper.on('click', '[data-multiselect="deselect_all"]', function (e) {
                    wrapper.find('.sp-sortable > li').each(function (e) {
                        jQuery(this).trigger('click');
                    })
                });

                wrapper.on('click', '[data-action="remove_multiselect_item"]', function (e) {
                    //jQuery(this).parents('.sp-productgrouppartner-wrapper')
                    wrapper.find('.sp-available').find('[data-id="' + jQuery(this).data('id') + '"]').show();
                    jQuery(this).remove();
                    if (wrapper.find('.sp-sortable').find('li').length < 1) {
                        var validation_field = wrapper.find('[data-action="multiselect-validator"]');
                        validation_field.val('');
                        wrapper.parents('form').data('formValidation').updateStatus(validation_field, 'NOT_VALIDATED').validateField(validation_field);
                    }
                });

                wrapper.on('click', '[data-action="add_multiselect_item"]', function (e) {
                    //jQuery(this).parents('.sp-productgrouppartner-wrapper')
                    wrapper.find('.sp-sortable').append('<li data-action="remove_multiselect_item" data-id="' + jQuery(this).data('id') + '"><i class="fa fa-arrows-alt"></i> ' + jQuery(this).text() + '</li>');
                    var validation_field = wrapper.find('[data-action="multiselect-validator"]');
                    validation_field.val(1);
                    wrapper.parents('form').data('formValidation').updateStatus(validation_field, 'NOT_VALIDATED').validateField(validation_field);
                    jQuery(this).hide();
                });

            });
        }

        /**
         * Checkbox list block
         */
        if (form.find('.sp-checkboxlist-wrapper').length > 0) {
            form.find('.sp-checkboxlist-wrapper').each(function (e) {
                var wrapper = jQuery(this);

                wrapper.on('click', '[data-checkbox-list="select_all"]', function (e) {
                    var validation_field = wrapper.find('[data-action="checkbox-validator"]');
                    validation_field.val(1);
                    wrapper.find('.checkbox-list input').each(function (e) {
                        jQuery(this).prop('checked', true);
                    });
                    wrapper.parents('form').data('formValidation').updateStatus(validation_field, 'NOT_VALIDATED').validateField(validation_field);
                });

                wrapper.on('click', '[data-checkbox-list="deselect_all"]', function (e) {
                    var validation_field = wrapper.find('[data-action="checkbox-validator"]');
                    validation_field.val('');
                    wrapper.find('.checkbox-list input').each(function (e) {
                        jQuery(this).prop('checked', false);
                    });
                    wrapper.parents('form').data('formValidation').updateStatus(validation_field, 'NOT_VALIDATED').validateField(validation_field);
                });

                wrapper.on('click', '[data-action="checkbox-value"]', function (e) {
                    var validation_field = wrapper.find('[data-action="checkbox-validator"]');
                    validation_field.val('');
                    wrapper.find('.checkbox-list input').each(function (e) {
                        if (jQuery(this).is(":checked")) {
                            validation_field.val(1);
                        }
                    });
                    wrapper.parents('form').data('formValidation').updateStatus(validation_field, 'NOT_VALIDATED').validateField(validation_field);
                });
            });
        }

        /**
         * Knob
         */
        if (form.find(".dial").length > 0) {
            form.find(".dial").knob();
        }

        /**
         * Timepicker
         */
        if (form.find('[data-type="timepicker"]:not([readonly])').length > 0) {
            jQuery.each(form.find('[data-type="timepicker"]:not([readonly])'), function (e) {
                initializeTimepicker(jQuery(this));
            });
        }

        /**
         * Date single
         */
        if (form.find('[data-type="datesingle"]:not([readonly])').length > 0) {
            initializeDatesingle(form.find('[data-type="datesingle"]:not([readonly])'));
        }

        /**
         * Datetime single
         */
        if (form.find('[data-type="datetimesingle"]:not([readonly])').length > 0) {
            initializeDateTimesingle(form.find('[data-type="datetimesingle"]:not([readonly])'));
        }

        /**
         * Date range
         */
        if (form.find('[data-type="daterange"]:not([readonly])').length > 0) {
            initializeDaterange(form.find('[data-type="daterange"]:not([readonly])'));
        }

        /**
         * Date range time
         */
        if (form.find('[data-type="daterangetime"]:not([readonly])').length > 0) {
            initializeDaterangeTime(form.find('[data-type="daterangetime"]:not([readonly])'));
        }

        if (form.find('[data-action="date"][data-clear="true"]').length) {
            form.find('[data-action="date"][data-clear="true"]').val('');
        }
        form.find('[data-action="date"]:not([readonly])').on('cancel.daterangepicker', function (ev, picker) {
            jQuery(this).val('');
        });

        form.find('[data-action="clear-date"]').on('click', function (e) {
            jQuery(this).siblings('input').val('').attr('value', '').trigger('change');
        });

        /**
         * Datatable in form
         */
        /*if(form.find('.datatables').length > 0){
         form.find('.datatables').each(function(e){
         jQuery(this).createDatatable();
         });
         }*/

        /**
         * Basic autocomplete with preloaded options
         */
        if (form.find('[data-type="base_lookup"]').length > 0) {
            form.find('[data-type="base_lookup"]').each(function (e) {
                var elem = jQuery(this);
                elem.select2({
                    minimumInputLength: 0,
                    width: '100%',
                    closeOnSelect: true,
                    dropdownParent: elem.closest("form")
                });

                var attr = elem.attr('readonly');
                if (typeof attr !== typeof undefined && attr !== false) {
                    elem.select2({'disabled': true});
                }
            });
        }

        /**
         * Autocomplete
         */
        if (form.find('[data-type="lookup"]').length > 0) {
            form.find('[data-type="lookup"]').each(function (e) {
                var elem = jQuery(this);
                initializeLookup(elem, form);
            });
        }

        if (eval("typeof checkifneedscrolling === 'function'")) {
            checkifneedscrolling(form);
        }
    },
    standardReload: function () {
        var form = jQuery(this);
        var table = jQuery('[data-table="' + form.data('type') + '"]').parents('.dataTables_wrapper').find('.datatables');
        table.DataTable().ajax.reload(null, false);

        return false;
    },
    redirect: function () {
        var redirect_url = jQuery(this).data('redirect-url');
        window.location.replace(redirect_url);
        return false;
    },
    custom: function (result) {
        var custom_function = jQuery(this).data('custom-callback');
        var form = jQuery(this);
        jQuery.each(custom_function, function (key, f) {
            if (eval("typeof " + f + " === 'function'")) {
                window[f](form, result);
            }
        });
        return false;
    },
    forceBoostrapXs: function () {
        var form = jQuery(this);

        form.find('.sp-block-wrapper.col-sm-2').removeClass('col-sm-2').addClass('col-sm-6');
        form.find('.sp-block-wrapper.col-sm-3').removeClass('col-sm-3').addClass('col-sm-6');
        form.find('.sp-block-wrapper.col-sm-4').removeClass('col-sm-4').addClass('col-sm-6');
        form.find('.sp-block-wrapper.col-sm-5').removeClass('col-sm-5').addClass('col-sm-6');
        form.find('.sp-block-wrapper.col-sm-7').removeClass('col-sm-7').addClass('col-sm-12');
        form.find('.sp-block-wrapper.col-sm-8').removeClass('col-sm-8').addClass('col-sm-12');
        form.find('.sp-block-wrapper.col-sm-9').removeClass('col-sm-9').addClass('col-sm-12');
        form.find('.sp-block-wrapper.col-sm-10').removeClass('col-sm-10').addClass('col-sm-12');
        form.find('.sp-block-wrapper.col-sm-11').removeClass('col-sm-11').addClass('col-sm-12');

        form.find('.sp-block-wrapper.col-md-2').removeClass('col-md-2').addClass('col-md-6');
        form.find('.sp-block-wrapper.col-md-3').removeClass('col-md-3').addClass('col-md-6');
        form.find('.sp-block-wrapper.col-md-4').removeClass('col-md-4').addClass('col-md-6');
        form.find('.sp-block-wrapper.col-md-5').removeClass('col-md-5').addClass('col-md-6');
        form.find('.sp-block-wrapper.col-md-7').removeClass('col-md-7').addClass('col-md-12');
        form.find('.sp-block-wrapper.col-md-8').removeClass('col-md-8').addClass('col-md-12');
        form.find('.sp-block-wrapper.col-md-9').removeClass('col-md-9').addClass('col-md-12');
        form.find('.sp-block-wrapper.col-md-10').removeClass('col-md-10').addClass('col-md-12');
        form.find('.sp-block-wrapper.col-md-11').removeClass('col-md-11').addClass('col-md-12');

        form.find('.sp-block-group-wrapper > .row').each(function (e) {
            var container = jQuery(this);
            if (container.find('.sp-block-wrapper').length > 1) {
                var highestBox = 0;
                container.find('.sp-block-wrapper').each(function () {
                    if (jQuery(this).height() > highestBox) {
                        highestBox = jQuery(this).height();
                    }
                });
                container.find('.sp-block-wrapper').css('min-height', highestBox + 'px');
            }
        });

        return false;
    },
    initializeValidation: function () {

        var form = jQuery(this);

        form.initializeForm();

        form.on('keyup keypress', function (e) {
            var keyCode = e.keyCode || e.which;
            if (keyCode === 13 && !jQuery(e.target).is("textarea")) {
                e.preventDefault();
                return false;
            }
        });

        /**
         * Validate form
         */
        form.on('init.field.fv', function (e, data) {
            // data.fv      --> The BootstrapValidator instance
            // data.field   --> The field name
            // data.element --> The field element

            var $parent = data.element.parents('.form-group'),
                $icon = $parent.find('.form-control-feedback[data-fv-icon-for="' + data.field + '"]'),
                options = data.fv.getOptions(),                      // Entire options
                validators = data.fv.getOptions(data.field).validators; // The field validators

            if (validators.notEmpty && options.icon && options.icon.required) {
                // The field uses notEmpty validator
                // Add required icon
                $icon.addClass(options.icon.required).show();
            }
        });

        form.formValidation({
            framework: 'bootstrap',
            excluded: [':disabled', '.no-icon'], /*':disabled' , ':hidden' , ':not(:visible)'*/
            icon: {
                required: 'glyphicon glyphicon-asterisk',
                valid: 'glyphicon glyphicon-ok',
                invalid: 'glyphicon glyphicon-remove',
                validating: 'glyphicon glyphicon-refresh'
            },
            live: 'enabled',
            message: translations.please_fill_in_the_field_correctly,
            button: {
                selector: 'button[type="submit"]'
            },
            trigger: null
        }).on('status.field.fv', function (e, data) {
            // Remove the required icon when the field updates its status
            var $parent = data.element.parents('.form-group'),
                $icon = $parent.find('.form-control-feedback[data-fv-icon-for="' + data.field + '"]'),
                options = data.fv.getOptions(),                      // Entire options
                validators = data.fv.getOptions(data.field).validators; // The field validators

            if (validators.notEmpty && options.icon && options.icon.required) {
                $icon.removeClass(options.icon.required).addClass('glyphicon');
            }
        }).on('success.field.fv', function (e, data) {
            // e, data parameters are the same as in err.field.fv event handler
            // Despite that the field is valid, by default, the submit button will be disabled if all the following conditions meet
            // - The submit button is clicked
            // - The form is invalid
            data.fv.disableSubmitButtons(false);
        }).on('err.form.fv', function (e, data) {
            //initGridResize();

            var fv = jQuery(e.target).data('formValidation');
            var res = fv.getInvalidFields();

            jQuery(e.target).formValidation('disableSubmitButtons', false);

            jQuery.growl.error({
                title: translations.form_error_message,
                message: res.length + ' ' + translations.invalid_fields
            });

        }).on('success.form.fv', function (e, data) {
            // Prevent form submission
            e.preventDefault();

            jQuery(".loading-bar-wrapper").show();

            if (typeof (CKEDITOR) !== "undefined") {
                for (instance in CKEDITOR.instances) {
                    CKEDITOR.instances[instance].updateElement();
                }
            }

            // Get the form instance
            var $form = jQuery(e.target);
            var $button = $form.data('formValidation').getSubmitButton();

            // Get the BootstrapValidator instance
            $form.formValidation('disableSubmitButtons', true);
            var fv = $form.data('formValidation');

            /**
             * admin serijalizacija gridstacka
             */
            if ($form.find('.sp-grid-wrapper-inner').find('.grid-stack:first').length > 0) {
                var grid = $form.find('.sp-grid-wrapper-inner').find('.grid-stack:first');
                var block_list = serializeGridstack(grid);

                var res = JSON.stringify(block_list);
                console.log("saving grid...");
                if ($form.find('[name="layout"]').length) {
                    $form.find('[name="layout"]').val(res);
                } else {
                    $form.find('[name="content"]').val(res);
                }
            }

            /**
             * Save email template if exists
             */
            if (form.find('[data-holder="email_template_configuration"]').length > 0) {
                saveEmailTemplateHtmlContent();
            }

            /**
             * Run presave functions
             */
            if (form.data('presave')) {
                var functions = form.data('presave');
                jQuery.each(functions, function (key, f) {
                    if (eval("typeof " + f + " === 'function'")) {
                        window[f](form);
                    }
                });
            }

            /**
             * Save multiselect
             * @type {Array}
             */
            var multiselect = [];
            if (form.find('.sp-multiselect-wrapper').length > 0) {
                form.find('.sp-multiselect-wrapper').each(function (e) {
                    var wrapper = jQuery(this);

                    var relation = {};
                    relation['parent_entity'] = jQuery(this).data('parent_entity');
                    relation['child_entity'] = jQuery(this).data('child_entity');
                    relation['link_entity'] = jQuery(this).data('link_entity');
                    relation['attribute_id'] = jQuery(this).data('id');

                    var related_ids = [];
                    if (wrapper.find('.sp-sortable li').length) {
                        wrapper.find('.sp-sortable li').each(function (e) {
                            related_ids.push(jQuery(this).data('id'));
                        });
                    } else if (wrapper.find('.checkbox-list input').length) {
                        wrapper.find('.checkbox-list input:checked').each(function (e) {
                            related_ids.push(jQuery(this).data('id'));
                        });
                    }

                    relation['related_ids'] = related_ids;

                    multiselect.push(relation);
                });
            }
            if (form.find('[data-type="lookup"][data-multiple="true"]').length > 0) {
                form.find('[data-type="lookup"][data-multiple="true"]').each(function (e) {

                    var relation = {};
                    relation['parent_entity'] = jQuery(this).data('parent_entity');
                    relation['child_entity'] = jQuery(this).data('child_entity');
                    relation['link_entity'] = jQuery(this).data('link_entity');
                    relation['attribute_id'] = jQuery(this).data('id');

                    var related_ids = jQuery(this).val();

                    relation['related_ids'] = related_ids;

                    multiselect.push(relation);
                });
            }

            /**
             * FIX for empty lookups
             * @type {string}
             */
            var empty_lookups = "";
            var empty_lookup_array = {};
            var empty_lookup_exists = false;
            if (form.find('[data-type="lookup"][data-single="true"]').length > 0) {
                form.find('[data-type="lookup"][data-single="true"]').each(function (e) {
                    if (!jQuery(this).val()) {
                        empty_lookup_array[jQuery(this).attr('name')] = '';
                        empty_lookup_exists = true;
                    }
                });
                if (empty_lookup_exists) {
                    empty_lookups = '&' + jQuery.param(empty_lookup_array);
                }
            }

            /**
             * FIX for disabled lookups
             * @type {string}
             */
            var disabled_lookups = "";
            var disabled_lookup_array = {};
            var disabled_lookup_exists = false;
            if (form.find('[data-type="lookup"][data-single="true"]:disabled').length > 0) {
                form.find('[data-type="lookup"][data-single="true"]:disabled').each(function (e) {
                    if (jQuery(this).val()) {
                        disabled_lookup_array[jQuery(this).attr('name')] = jQuery(this).val();
                        disabled_lookup_exists = true;
                    }
                });
                if (disabled_lookup_exists) {
                    disabled_lookups = '&' + jQuery.param(disabled_lookup_array);
                }
            }

            // Convert pasted images
            if ($form.find('[data-type="ckeditor"]').length > 0) {
                $form.find('[data-type="ckeditor"]').each(function () {
                    var ckeditor = jQuery(this);

                    // Check if there are pasted images
                    if (ckeditor.val().indexOf('src="data:image') != -1) {
                        ckeditor.val(jQuery.ckeditorConvertPastedImages(ckeditor.val()));
                    }
                });
            }

            jQuery.post($form.attr('action'), $form.find(":not(.inline-edit-field)").serialize() + '&' + jQuery.param({'multiselect': multiselect}) + empty_lookups + disabled_lookups, function (result) {
                $form.formValidation('disableSubmitButtons', false);
                if (result.error == true) {
                    jQuery(".loading-bar-wrapper").hide();
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });

                    if (form.data('callback')) {
                        var functions = form.data('callback');
                        jQuery.each(functions, function (key, f) {
                            if (eval("typeof " + f + " === 'function'")) {
                                window[f](form, result);
                            }
                        });
                    }
                } else if (result.error == false) {
                    jQuery(".loading-bar-wrapper").hide();
                    jQuery.growl.notice({
                        title: result.title,
                        message: result.message
                    });


                    if (form.data('callback')) {
                        var functions = form.data('callback');
                        jQuery.each(functions, function (key, f) {
                            if (eval("typeof " + f + " === 'function'")) {
                                window[f](form, result);
                            }
                        });
                    }

                    /**
                     * If no button is clicked - only when form submitted trough javascript
                     */
                    if (!$button) {
                        return false;
                    }

                    if ($button.data('action')) {
                        var action = $button.data('action');

                        if (action === "close-modal") {
                            $button.parents('.modal').modal('hide');
                        } else if (action === "reload") {
                            window.location = addQueryToUrl(window.location.pathname, 'noref=1');
                        } else if (action === "refresh") {

                            jQuery(document).trigger('modal-close', $form);

                            var resets = jQuery.find('[data-action="filter-reset"]');
                            jQuery(resets).each(function () {
                                jQuery(this).trigger("click");
                            });

                            /**
                             * Refresh calendars
                             */
                            if (calendars) {
                                jQuery.each(calendars, function (key, calendar) {
                                    calendar.refetchEvents();
                                });
                            }

                            $button.parents('.modal').modal('hide');

                        } else if (action === "continue") {
                            if (result.entity && result.entity.id) {
                                if ($form.find('[name="id"]').val() > 0) {
                                    window.location = addQueryToUrl(window.location.pathname, 'noref=1');
                                } else {
                                    var url2 = addQueryToUrl(window.location.pathname + '/' + String(result.entity.id) + getUrlAsAstring(), 'noref=1');
                                    location.replace(url2);
                                }

                            }
                            return false;
                        } else if (action == "stay") {
                            // TODO ovo se ne koristi
                            if (result.entity.id && form.find('[name="id"]').val() == '') {
                                form.find('[name="id"]').val(result.entity.id);

                                var source_url = form.parents('.modal').data('source_url');
                                form.parents('.modal').modal('hide');
                                source_url = source_url + '&id=' + result.entity.id;

                                standardAddModalForm(source_url);
                            }
                            return false;
                        } else if (action == "goto") {
                            if (result.url) {
                                var url3 = addQueryToUrl(result.url, 'noref=1');
                                window.location = url3;
                            }
                        } else {
                            goBackWithRefresh(e);
                            return false;
                        }
                    }
                } else {
                    jQuery(".loading-bar-wrapper").hide();
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: translations.there_has_been_an_error_please_try_again
                    });
                }
            }, 'json');
        });

        /**
         * Revalidate date field
         */
        form.find('[data-action="date"]').on('change', function (e) {
            if (jQuery(this).siblings('i').length > 0 && jQuery(this).siblings('i').hasClass('form-control-feedback')) {
                form.formValidation('revalidateField', (jQuery(this).attr("name")));
            }
        });

        return false;
    }
});


jQuery(document).on("click", ".sp-chart-filter", function (e) {

    var path = "/block/modal?type=filter&&block_id=" + jQuery(this).data('filter-chart')
    if (jQuery(this).data('page-id') !== "") {
        path = path + "&&id=" + jQuery(this).data('page-id');
    }

    jQuery.post(path, {}, function (result) {
        if (result.error == false) {
            var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
            clone.html(result.html);
            var modal = clone.find('.modal');
            modal.modal('show');

            var form = modal.find('[data-validate="true"]');
            form.initializeValidation();
            jQuery('#default_modal').on('shown.bs.modal', function (e) {
                form.forceBoostrapXs();
            });

            //TODO set value
        } else {
            jQuery.growl.error({
                title: translations.error_message,
                message: result.message
            });
        }
    }, "json");
});

