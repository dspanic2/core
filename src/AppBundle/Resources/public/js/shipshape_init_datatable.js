/**
 * Dynamically resize grid to fit content
 */
/*var isResizeing = false;
var timesResized = 0;
function resizeGrid() {
    timesResized++;
    var checkAgain = false;
    jQuery('.grid-stack').each(function() {
        var grid = jQuery(this).data('gridstack');
        jQuery(this).children().each(function() {
            var gsi = jQuery(this);

            var height = 0;
            if(gsi.find(">.sp-block").length > 0){
                height = gsi.find(">.sp-block").outerHeight();
            }else if(gsi.find(">.sp-block-outer").length > 0){
                height = gsi.find(">.sp-block-outer").outerHeight();
            }

            if(height>0){
                if(gsi.height()<height){
                    grid.resize(gsi, gsi.attr('data-gs-width'), Math.ceil(height/65)+1);
                    checkAgain = true;
                }
            }
        });
    });

    if(checkAgain){
        setTimeout(resizeGrid,100);
    }else{
        console.log("Grid resized to fit content");
        jQuery(".loading-bar-wrapper").hide();
        jQuery("body").removeClass("loading");
        isResizeing = false;
    }
    return false;
}
function initGridResize() {
    if(!isResizeing){
        isResizeing = true;
        resizeGrid();
    }
}*/

var table_state = {};

function prepareAdvancedSearch(table, custom_data) {

    var block = table.parents('.sp-block');
    if (block.find('.sp-search-wrapper-active').length) {

        var form = table.parents('.sp-block').find('.sp-advanced-search');
        var post = [];

        form.find('.sp-search-item').each(function (e) {
            var filter_options = {};
            var val = null;
            var elem = null;
            var search_type = null;
            var wrapper = jQuery(this).parents('.sp-search-group');

            if (jQuery(this).find('[data-multiple="true"]').length > 0) {
                elem = jQuery(this).find('select');
                val = null;
                if (jQuery(this).find('select').val()) {
                    val = jQuery(this).find('select').val().join(',');
                }
                search_type = wrapper.find('[name="search_type"]').val();
            } else if (jQuery(this).find('input').length) {
                elem = jQuery(this).find('input');
                val = jQuery(this).find('input').val();
                if (wrapper.find('[name="search_type"]').length > 0) {
                    search_type = wrapper.find('[name="search_type"]').val();
                }
            } else {
                elem = jQuery(this).find('select');
                val = jQuery(this).find('select').val();
                search_type = wrapper.find('[name="search_type"]').val();
            }

            if (!search_type) {
                search_type = jQuery(this).data('search_type');
            }

            if (val) {
                filter_options['value'] = val;
                var filter = {};
                filter['attributeId'] = elem.data('id');
                filter['data'] = elem.attr('name');
                filter['search_type'] = search_type;
                filter['search'] = filter_options;
                post.push(filter);
            }
        });

        custom_data['custom_data'] = JSON.stringify(post);
    }

    return false;
}

function prepareQuickSearch(table, custom_data) {

    var block = table.parents('.sp-block');
    if (block.find('.sp-quick-search-wrapper').length) {

        var form = table.parents('.sp-block').find('.sp-quick-search');
        var post = [];

        form.find('.sp-search-item').each(function (e) {
            var filter_options = {};
            var val = null;
            var elem = null;

            if (jQuery(this).find('[data-multiple="true"]').length > 0) {
                elem = jQuery(this).find('select');
                val = null;
                if (jQuery(this).find('select').val()) {
                    val = jQuery(this).find('select').val().join(',');
                }
            } else if (jQuery(this).find('input').length) {
                elem = jQuery(this).find('input');
                val = jQuery(this).find('input').val();
            } else {
                elem = jQuery(this).find('select');
                val = jQuery(this).find('select').val();
            }

            if (val) {
                filter_options['value'] = val;
                var filter = {};
                filter['attributeId'] = elem.data('id');
                filter['data'] = elem.attr('name');
                filter['search_type'] = jQuery(this).data('search_type');
                filter['search'] = filter_options;
                post.push(filter);
            }
        });

        custom_data['quick_search_data'] = JSON.stringify(post);
    }
}

function fixedTableAfter(table, data) {

    var parent = table.parents('.dataTables_wrapper');

    /*var target = parent.find('.DTFC_LeftBodyLiner').find('tbody').empty();
    var col = 1;

    table.find('tbody').find('tr td:nth-child(' + col + ')').each(function (rowIndex) {
        target.append('<tr><td class="sp-width-200">'+jQuery(this).html()+'</td></tr>');
    });*/

    //var target = parent.find('.DTFC_RightBodyLiner').find('tbody').empty();

    /*table.find('tbody').find('tr td:last-child').each(function (rowIndex) {
        target.append('<tr><td class="sp-actions-td">'+jQuery(this).html()+'</td></tr>');
        jQuery(this).empty();
    });*/

    /**
     * Preslozi thead tablice radi pivota
     */
    /*var wrapper = jQuery('#products_table_wrapper').find('.dataTables_scrollHead').find('thead > tr');

    var currentArray = [];
    wrapper.find('.sp-rotate').each(function(e){
        currentArray.push(jQuery(this).data('id'));
    });

    if(!(currentArray.toString()===data.partners.toString())){
        jQuery.each(data.partners, function(key, value){
            var elem = wrapper.find('[data-id="'+value+'"]');
            elem.appendTo(wrapper);
        });
        wrapper.find('[data-col-name="avg"]').appendTo(wrapper);
        wrapper.find('[data-col-name="actions"]').appendTo(wrapper);
    }*/

    //jQuery('#table_wrapper').find('[data-tooltip="true"]').tooltip();

    var dt = table.DataTable();
    dt.columns.adjust();

    if (table.parents('.sp-fixed-header').length > 0) {
        if (table.find('tbody tr').length > 0) {
            var w = table.find('td:not(.sp-actions-td)').width() + 18;
            table.find('th:not(.sp-actions-td)').css({"width": w + "px", "min-width": w + "px", "max-width": w + "px"});
        }
        table.find('thead').show();
    }

    //positionFixed(table);

    //compareList = [];

    /*jQuery('#products_table_wrapper').scroll(function(){
     jQuery('#products_table_wrapper').find('.sp-fixed').scrollLeft(jQuery(this).scrollLeft());
     });*/

    /*if(jQuery('#products_table_wrapper > .row:first-child > .col-xs-6').length > 0){
        var active_actions_wrapper = jQuery('#products_table_wrapper > .row:first-child > .col-xs-6');
        active_actions_wrapper.removeClass('col-xs-6').addClass('col-xs-9')
        active_actions_wrapper.append(jQuery('.sp-live-actions-wrapper').html());
        active_actions_wrapper.find('[data-type="bchackbox-after"]').bootstrapSwitch();

        active_actions_wrapper.find('[rel="popover"]').popover({ trigger: "hover" });
    }*/


    /*if(active_actions_wrapper.children().length < 1){
     active_actions_wrapper.append(jQuery('.sp-live-actions-wrapper').html());
     active_actions_wrapper.find('[data-type="bchackbox-after"]').bootstrapSwitch();
     }*/

    /*if(table.data('jump')){
        jQuery('html, body').animate({
            scrollTop: table.offset().top - 190
        }, 200);
    }
    else{
        table.data('jump', true);
    }*/


    /*table.find('.sp-lazy').lazyload({
        event : "showImg",
        threshold : 20,
        skip_invisible : true,
        effect : "fadeIn"
    });*/

    //checkVisibleImages();
    //checkVisiblePrices();

    //initGridResize();

    return false;
}

/**
 * Create datatable
 * @returns {boolean}
 */
jQuery.fn.extend({
    insertDatatableData: function (data) {
        var table = jQuery(this);

        table.find('tbody').html(data.html);

        if (table.data('callback-after')) {
            var functions = table.data('callback-after');
            jQuery.each(functions, function (key, f) {
                window[f](table, data);
            });
        }

        /**
         * Check if has selected items
         */
        if (selectList && selectList[table.attr('id') + "_wrapper"]) {
            jQuery.each(selectList[table.attr('id') + "_wrapper"], function (index, obj) {
                jQuery.each(obj, function (key, value) {
                    if (table.find('[data-select-id="' + value + '"]').length > 0) {
                        table.find('[data-select-id="' + value + '"]').children('i').toggleClass('fa-square').toggleClass('fa-check-square');
                        table.find('[data-select-id="' + value + '"]').closest('tr').toggleClass('sp-row-active');
                    }
                });
            });

            table.parents('.sp-block').find('.sp-listview-dropdown-control').removeClass('hidden');
        }

        /**
         * Other methods
         */

        /**
         * Bind tooltip
         */
        /*if(table.find('[data-tooltip="true"]').length > 0){
            table.find('[data-tooltip="true"]').tooltip();
        }*/

        /**
         * Bind popover
         */
        if (table.find('[data-action="popover-lite"]').length > 0) {
            table.on('mouseenter', '[data-action="popover-lite"]', function () {
                jQuery(this).popover({
                    html: true,
                    trigger: 'hover',
                    content: function () {
                        return jQuery(this).data('html');
                    },
                    title: function () {
                        return "";
                    },
                    placement: "top"
                });
                jQuery(this).popover('show');
            });
        }

        return false;
    },
    setFilterHtml: function () {
        var table_id = jQuery(this).attr('id');
        var html = '<tr class="sp-filter-holder">';
        if (jQuery(this).find('.sp-table-header').length > 0) {
            jQuery(this).find('.sp-table-header').children('th').each(function (index) {
                html = html + '<th';
                if (jQuery(this).data("col-filter")) {
                    html = html + ' id="sp-filter-holder-' + index + '_' + table_id + '" class="sp-filter"';
                }
                html = html + '></th>';
            });
        }
        html = html + '</tr>';

        jQuery(this).find('.sp-table-header').before(html);
        jQuery(this).find('.sp-filter-holder').find('th:last-child').addClass('sp-actions-td sp-actions-td-filer').html('<span class="sp-actions-td-inner"></span>');
        if (jQuery(this).find('.sp-list-checkbox-td').length) {
            jQuery(this).find('.sp-filter-holder').find('th:first-child').addClass('sp-list-checkbox-td');
        }

        return false;
    },
    setYFilterHtmlButtons: function () {
        jQuery(this).parents('.dataTables_wrapper').find('.sp-buttons-holder').html(jQuery('#sp-filter-buttons-template').clone().children());
        return false;
    },
    /*setYFilterHtmlButtonReset: function(){
        jQuery(this).parents('.dataTables_wrapper').find('.sp-buttons-holder').html(jQuery('#sp-filter-button-reset-template').clone().children());
        return false;
    },*/
    createDatatable: function () {

        var table = jQuery(this);
        var table_wrapper = jQuery(this).parent();
        var datatable_configuration = {};


        var datatable_header = "<'row'<'col-xs-3'><'col-xs-3'><'col-xs-6 sp-buttons-holder'>r>t<'row'<'col-xs-3'i><'col-xs-9'p>>";

        var aoColumns = [];
        if (table.find('.sp-table-header').length > 0) {
            table.find('.sp-table-header').children('th').each(function (e) {
                var prop = {};
                prop['mDataProp'] = jQuery(this).data("col-name");
                prop['bSearchable'] = jQuery(this).data("col-search");
                prop['bSortable'] = jQuery(this).data("col-sort");
                aoColumns.push(prop);
            });
        }

        if (table.data('empty')) {
            datatable_configuration = {
                "sDom": 't'
            };
        } else {
            datatable_configuration = {
                "stateSave": true,
                "sDom": datatable_header,
                "sPaginationType": "bootstrap",
                "pageLength": table.data('limit'),
                "oLanguage": {
                    "sInfo": translations.showing + " _START_ " + translations.smallto + " _END_ " + translations.smallfrom + " _TOTAL_ " + translations.results,
                    "sInfoFiltered": "(" + translations.filtered_from + " _MAX_ " + translations.totals + " " + translations.results + ")",
                    "sInfoEmpty": translations.showing + " 0 "/* + translations.smallto + " 0 "*/ + translations.smallfrom + " 0 " + translations.results,
                    "sLengthMenu": "_MENU_ " + translations.records_per_page,
                    "sSearch": "",
                    "oPaginate": {
                        "sFirst": translations.sfrist,
                        "sPrevious": translations.sprevious,
                        "sNext": translations.snext,
                        "sLast": translations.slast
                    },
                },
                "lengthMenu": [[5, 10, 25, 50, 100, 150], [5, 10, 25, 50, 100, 150]]
            };
            if (aoColumns.length > 0) {
                datatable_configuration["aoColumns"] = aoColumns;
            }

            if (table.data('f-columns')) {
                datatable_configuration["scrollY"] = "80vh";
                datatable_configuration["scrollX"] = true;
                datatable_configuration["scrollCollapse"] = true;
                datatable_configuration["fixedColumns"] = {};
                var lcolumn = table.data('fl-column');
                var rcolumn = table.data('fr-column');
                if (jQuery.isNumeric(lcolumn)) {
                    datatable_configuration["fixedColumns"]["leftColumns"] = 0;
                }
                if (jQuery.isNumeric(rcolumn)) {
                    datatable_configuration["fixedColumns"]["rightColumns"] = rcolumn;
                }
                //datatable_configuration["columnDefs"] = { width: '20%', targets: 0 };
            }

            /*table.on('click','[data-col-sort="true"]',function(){
                jQuery(this).toggleClass('ascending').toggleClass('descending');
            });*/
        }

        if (table.data('order-col') !== false) {
            datatable_configuration["order"] = [[table.data('order-col'), table.data('order-dir')]];
        }

        if (table.data('export')) {

            datatable_configuration["buttons"] = [
                {
                    extend: 'excelHtml5',
                    text: 'Save current page',
                    exportOptions: {
                        modifier: {
                            page: 'current'
                        }
                    }
                }
            ];
            /*datatable_configuration["buttons"] = [
                jQuery.extend( true, {}, buttonCommon, {
                    extend:    'copyHtml5',
                    text:      '<i class="fa fa-files-o"></i> Copy',
                    titleAttr: 'Copy',
                    exportOptions: {
                        columns: ':not(.no-export)'
                    }
                }),
                jQuery.extend( true, {}, buttonCommon, {
                    extend:    'excelHtml5',
                    text:      '<i class="fa fa-file-excel-o"></i> Excel',
                    titleAttr: 'Excel',
                    title:  translations.data_export,
                    exportOptions: {
                        columns: ':not(.no-export)'
                    }
                }),
                jQuery.extend( true, {}, buttonCommon, {
                    extend:    'csvHtml5',
                    text:      '<i class="fa fa-file-text-o"></i> CSV',
                    titleAttr: 'CSV',
                    title:  translations.data_export,
                    exportOptions: {
                        columns: ':not(.no-export)'
                    }
                })
            ]*/
        }

        if (table.data('filter') && !table.data('server')) {
            table.setFilterHtml();
            datatable_configuration["initComplete"] = function (e) {
                this.api().columns().every(function () {
                    var column = this;
                    var cellIndex = jQuery(column.header()).index();
                    var filterWrapper = jQuery(column.header()).closest('tr').prev().children().eq(cellIndex);
                    if (!filterWrapper.hasClass('sp-filter')) {
                        return true;
                    }
                    var select = jQuery('<select><option value=""></option></select>')
                        .appendTo(filterWrapper)
                        .on('change', function () {
                            var val = jQuery.fn.dataTable.util.escapeRegex(
                                jQuery(this).val()
                            );
                            column
                                .search(val ? '^' + val + '$' : '', true, false)
                                .draw();
                        });

                    column.data().unique().sort().each(function (d, j) {
                        select.append('<option value="' + d + '">' + d + '</option>')
                    });
                });
            }
        }


        if (table.data('filter') && table.data('server') && table.find('.sp-table-header').length > 0) { //&& table.attr('id') != "table_60_26_wrapper"
            var filterData = [];

            table.setFilterHtml();

            table.find('.sp-table-header').children('th').each(function (index) {
                var prop = {};
                if (jQuery(this).data("col-filter")) {
                    prop['column_number'] = index;
                    prop['filter_type'] = jQuery(this).data('filter-type');
                    prop['filter_container_id'] = 'sp-filter-holder-' + index + '_' + table.attr('id');
                    prop['style_class'] = 'form-control';
                    prop['filter_delay'] = '300';
                    if (jQuery(this).data("filter-type") == 'multi_select') {
                        prop['select_type'] = 'select2';
                    } else if (jQuery(this).data("filter-type") == 'range_date') {
                        prop['date_format'] = jQuery(this).data('date-format');
                    } else if (jQuery(this).data("filter-type") == 'range_number') {
                        if (jQuery(this).data('ignore-char')) {
                            prop['ignore_char'] = jQuery(this).data('ignore-char');
                        }
                        prop['column_data_type'] = 'rendered_html';
                        prop['html_data_type'] = 'text';
                        if (jQuery(this).data('filter-plugin-options')) {
                            prop['filter_plugin_options'] = {step: jQuery(this).data('filter-plugin-options')};
                        }
                    }

                    if (jQuery(this).data("filter-type") == 'multi_select' || jQuery(this).data("filter-type") == 'select') {
                        prop['data'] = jQuery(this).data('list');
                        prop['filter_default_label'] = translations.select_value;
                    }
                    filterData.push(prop);
                }
            });
        }

        if (table.data('server')) {
            datatable_configuration["processing"] = true;
            datatable_configuration["serverSide"] = true;
            datatable_configuration["serverData"] = function (source, request_data, fnCallback, settings) {
                var send_data = {};
                jQuery.each(request_data, function (d, val) {
                    send_data[val.name] = val;
                    if (val.name == 'columns') {
                        jQuery.each(val.value, function (k, val) {
                            send_data['columns']['value'][k]['search_type'] = table.find('[data-col-name="' + val.data + '"]').data('search-type');
                        });
                    } else if (val.name == 'order') {
                        //selectedCol = val.value[0].column;
                        sortType = table.find('[data-col-name="' + send_data['columns']['value'][val.value[0].column]['data'] + '"]').data('is_json');
                        if (sortType !== "undefined") {
                            send_data['order']['value'][0]['sort_type'] = sortType;
                        }
                    }
                });

                var inlineEditing = 0
                if (table.hasClass("sp-list-editable")) {
                    inlineEditing = 1;
                }
                send_data["edit"] = inlineEditing;
                send_data = JSON.stringify(send_data);

                var custom_data = {};
                if (table.data('before')) {
                    var functions = table.data('before');
                    jQuery.each(functions, function (key, f) {
                        if (eval("typeof " + f + " === 'function'")) {
                            window[f](table, custom_data);
                        }
                    });
                }

                /**
                 * SAVING STATE FOR EXPORT
                 */
                table_state[table.attr('id')] = {"send_data": send_data, "custom_data": custom_data['custom_data']};

                settings.jqXHR = jQuery.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": table.data('url'),
                    "data": {
                        data: send_data,
                        custom_data: custom_data['custom_data'],
                        quick_search_data: custom_data['quick_search_data']
                    },
                    "success": function (data) {
                        fnCallback(data);
                        table.insertDatatableData(data);

                        jQuery.setCookie("inline_editing_" + table.attr('id'), inlineEditing);

                        if (table.find('[data-type="bchackbox"]').length) {
                            table.find('[data-type="bchackbox"]').bootstrapSwitch();
                        }

                        // Initialize listview image lightbox
                        if (jQuery("td .image-holder").length) {
                            jQuery("td .image-holder").each(function () {
                                jQuery(this).magnificPopup({
                                    delegate: 'a',
                                    type: 'image',
                                    tLoading: 'Loading image #%curr%...',
                                    mainClass: 'mfp-img-mobile',
                                    gallery: {
                                        enabled: true,
                                        navigateByImgClick: true,
                                        preload: [0, 1] // Will preload 0 - before current, and 1 after the current image
                                    },
                                    image: {
                                        tError: '<a href="%url%">The image #%curr%</a> could not be loaded.',
                                    }
                                });

                                // Datable upload images
                                if (jQuery(this).find('.dropzone').length > 0) {
                                    jQuery(this).find('.dropzone').each(function (e) {
                                        initializeDropzone(jQuery(this));
                                    });
                                }

                                if (jQuery(this).find('.sortable-items').length > 0) {
                                    var sortableItems = jQuery(this).find('.sortable-items');
                                    sortableItems.sortable({
                                        update: function (event, ui) {
                                            var data = [];
                                            jQuery.each(sortableItems.find('[name="image_sort_id[]"]'), function (e) {
                                                data.push(jQuery(this).val());
                                            });

                                            jQuery.showAjaxLoader();
                                            jQuery.post(sortableItems.data("sortable_url"), {
                                                'data': data,
                                                'parent_id': sortableItems.data('parent_id'),
                                                'entity_type_code': sortableItems.data('entity_type_code'),
                                                'parent_attribute_id': sortableItems.data('parent_attribute_id')
                                            }, function (result) {
                                                jQuery.hideAjaxLoader();
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
                            });
                        }

                        if (table.find('[data-type="lookup"]').length > 0) {
                            table.find('[data-type="lookup"]').each(function (e) {
                                initializeLookup(jQuery(this), null);
                            });
                        }
                    }
                });
            }
        }

        var filter = table.DataTable(datatable_configuration);

        if (filterData) {

            if (table.data('filter-buttons')) {

                yadcf.init(filter, filterData, {
                    externally_triggered: true,
                });

                table.setYFilterHtmlButtons();
            } else {
                yadcf.init(filter, filterData);
                //table.setYFilterHtmlButtonReset();
            }

            table_wrapper.on('click', '[data-action="filter-submit"]', function () {
                yadcf.exFilterExternallyTriggered(filter);
            });

            /*table_wrapper.on('click','[data-action="filter-reset"]',function() {
                yadcf.exResetAllFilters(filter);
            });*/

            table_wrapper.parents('.panel').on('click', '[data-action="filter-reset"]', function () {
                yadcf.exResetAllFilters(filter);
            });
            /*if(table_wrapper.parents('.panel').hasClass('main-panel-heading')){
                jQuery('.main-panel-heading').on('click','[data-action="filter-reset"]',function() {
                    yadcf.exResetAllFilters(filter);
                });
            }
            else{
                table_wrapper.parents('.panel').on('click','[data-action="filter-reset"]',function() {
                    yadcf.exResetAllFilters(filter);
                });
            }*/

        }

        table.parent().addClass('table-responsive');

        if (jQuery('.dataTables_filter input').length) {
            jQuery('.dataTables_filter input').addClass('form-control').attr('placeholder', translations.quick_search);
        }
        if (jQuery('.dataTables_length select').length) {
            jQuery('.dataTables_length select').addClass('form-control');
        }
        if (table.data('export')) {
            jQuery('.dt-button').addClass('btn').addClass('btn-default').addClass('btn-sm');
        }

        return true;
    }
});
