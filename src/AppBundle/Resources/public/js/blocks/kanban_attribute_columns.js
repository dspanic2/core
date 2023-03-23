jQuery(document).ready(function() {

    // ADMIN
    jQuery('body').on('change','fieldset.kanban-attribute-columns-settings [name="listView"]',function (e) {
        var fieldset = jQuery("fieldset.kanban-attribute-columns-settings");
        var listView = fieldset.find('[name="listView"]');
        getListViewAttributes(listView.val(), 'lookup', "[name='kanbanColumnAttribute']", "Select column attribute");
        getListViewAttributes(listView.val(), 'lookup', "[name='kanbanTitleAttribute']", "Select title attribute");
        getListViewAttributes(listView.val(), 'lookup', "[name='kanbanDescriptionAttribute']", "Select description attribute");
        if (listView.find(":selected")) {
            listView.find('select[name="kanbanColumnAttribute"]').removeAttr('disabled').show();
            listView.find('select[name="kanbanTitleAttribute"]').removeAttr('disabled').show();
            listView.find('select[name="kanbanDescriptionAttribute"]').removeAttr('disabled').show();
        } else {
            listView.find('select[name="kanbanColumnAttribute"]').attr('disabled', 'disabled').hide();
            listView.find('select[name="kanbanTitleAttribute"]').attr('disabled', 'disabled').hide();
            listView.find('select[name="kanbanDescriptionAttribute"]').attr('disabled', 'disabled').hide();
        }
    });

    // FRONTEND
    var kanban_attribute_columns = jQuery('.sp-kanban-wrapper.kanban-attribute-columns');
    if(kanban_attribute_columns.length > 0){
        kanban_attribute_columns.each(function (e) {
            var wrapper = jQuery(this);
            var type = wrapper.find('.kanban-init').data("type");

            var settings = wrapper.find('.kanban-init').data('settings');

            var titles = [];
            var colors = [];
            var items = [];
            var ids = [];
            jQuery.each(settings, function(key,val){
                titles.push(val.title);
                ids.push(val.id);
                colors.push(val.color);
                jQuery.each(val.items, function(key,val){
                    items.push(val);
                });
            });

            wrapper.find('.kanban-init').kanbanAttributeColumns({
                titles: titles,
                colours: colors,
                items: items,
                ids: ids
            });

            wrapper.find('.kanban-init').on( "sortstop", function( event, ui ) {
                if(type == "generated"){
                    var task_id = ui.item[0].dataset.task;
                    var column_id = ui.item[0].parentElement.dataset.block;

                    var tasks = [];
                    wrapper.find("[data-block="+column_id+"]").find(".cd_kanban_board_block_item").each(function(){
                        tasks.push(jQuery(this).data("task"));
                    });

                    jQuery.post(wrapper.data("url"), { type: type, entity_type: wrapper.data("entity-type"), column_changed: wrapper.data("column-changed"), task_id: task_id, block_id: column_id , order: tasks }, function(result) {
                        if(result.error == false) {
                            jQuery.growl.notice({
                                title: result.title,
                                message: result.message
                            });
                        }else{
                            jQuery.growl.error({
                                title: translations.error_message,
                                message: result.message
                            });
                        }
                    }, "json");
                }else if(type == "custom"){
                    var data = {};

                    jQuery(this).find(".cd_kanban_board_block.ui-sortable").each(function(){
                        var block = jQuery(this);

                        var block_items = [];
                        block.find(".cd_kanban_board_block_item").each(function(){
                            var item = jQuery(this);
                            block_items.push(item.data("task"));
                        });

                        data[block.data("block")] = block_items;
                    });

                    jQuery.post(wrapper.data("url"), { type: type, data: data, block_id: jQuery(this).closest(".new-column-form").data("block-id") }, function(result) {
                        if(result.error == false) {
                            jQuery.growl.notice({
                                title: result.title,
                                message: result.message
                            });
                        }else{
                            jQuery.growl.error({
                                title: translations.error_message,
                                message: result.message
                            });
                        }
                    }, "json");
                }

            });
        });
    }

});


(function($) {
    $.fn.kanbanAttributeColumns = function(options) {

        // defaults
        var $this = $(this);

        var settings = $.extend({
            titles: [],
            colours: [],
            items: [],
            ids: []
        }, options);

        var classes = {
            kanban_board_class: "cd_kanban_board",
            kanban_board_titles_class: "cd_kanban_board_titles",
            kanban_board_title_class: "cd_kanban_board_title",
            kanban_board_blocks_class: "cd_kanban_board_blocks",
            kanban_board_block_class: "cd_kanban_board_block",
            kanban_board_item_class: "cd_kanban_board_block_item",
            kanban_board_item_placeholder_class: "cd_kanban_board_block_item_placeholder",
            kanban_board_item_title_class: "cd_kanban_board_block_item_title",
            kanban_board_item_description_class: "cd_kanban_board_block_item_description",
            kanban_board_item_footer_class: "cd_kanban_board_block_item_footer"
        };

        // var build_kanban = $.extend({
        function build_kanban(){
            $this.addClass(classes.kanban_board_class);
            $this.append('<div class="'+classes.kanban_board_titles_class+'"></div>');
            $this.append('<div class="'+classes.kanban_board_blocks_class+'"></div>');

            build_titles();
            build_blocks();
            build_items();

            return $this;
        }

        function build_titles() {
            settings.titles.forEach(function (item, index, array) {
                var item = '<div data-column-id="'+settings.ids[index]+'" style="background: '+settings.colours[index]+'" class="' + classes.kanban_board_title_class + '">' + '<p>'+item+'</p>' + '<div class="column-actions pull-right"><a data-title="'+item+'" data-color="'+settings.colours[index]+'" data-action="edit-column" class="btn btn-default" data-id="'+settings.ids[index]+'"><i class="fa fa-pencil-alt"></i></a><a data-action="delete-column" class="btn btn-default" data-id="'+settings.ids[index]+'"><i class="fa fa-times"></i></a></div></div>';
                $this.find('.'+classes.kanban_board_titles_class).append(item);
            });
        }

        function build_blocks() {
            settings.titles.forEach(function (item, index, array) {
                var item = '<div class="' + classes.kanban_board_block_class + '" data-block="' + settings.ids[index] + '"></div>';
                $this.find('.'+classes.kanban_board_blocks_class).append(item);
            });

            $( "."+classes.kanban_board_block_class ).sortable({
                connectWith: "."+classes.kanban_board_block_class,
                containment: "."+classes.kanban_board_blocks_class,
                placeholder: classes.kanban_board_item_placeholder_class,
                scroll: true,
                cursor: "move"
            }).disableSelection();
        }

        function build_items(){
            settings.items.forEach(function (item , index , array) {
                var block = $this.find('.'+classes.kanban_board_block_class+'[data-block="'+item.block+'"]');
                var append =  '<div class="'+classes.kanban_board_item_class+'" data-task="'+item.id+'">';
                append += '<div class="'+classes.kanban_board_item_title_class+'">'+item.title+'</div>';
                append += '<div class="'+classes.kanban_board_item_description_class+'">'+item.description+'</div>';
                // append += '<div class="'+classes.kanban_board_item_footer_class+'"><span class="priority priority-' + item.footer.priority_id + '">' + item.footer.priority_name + '</span><span class="kanban-actions"><a class="btn btn-default btn-xs pull-left sp-datatable-left-button" href="/page/task/form/' + item.id + '" data-container="body" data-tooltip="true" data-placement="top" title="Prikaži"><i class="fa fa-pencil-alt"></i></a></span></div>';
                append += '<div class="'+classes.kanban_board_item_footer_class+'"><span class="kanban-actions"><a class="btn btn-default btn-xs pull-left sp-datatable-left-button" href="/page/'+item.type+'/form/' + item.id + '" data-container="body" data-tooltip="true" data-placement="top" title="Prikaži"><i class="fa fa-pencil-alt"></i></a></span><div class="clearfix"></div></div>';
                append += '</div>';
                block.append(append);
            });
        }
        build_kanban();
    };

    $.fn.kanbanAddColumn = function(title, color, id){
        var kanban = jQuery(this);

        var new_title = '<div data-column-id="'+id+'" style="background: '+color+'" class="cd_kanban_board_title">' + '<p>'+title+'</p>' + '<div class="column-actions pull-right"><a data-title="'+title+'" data-color="'+color+'" data-action="edit-column" class="btn btn-default" data-id="'+id+'"><i class="fa fa-pencil-alt"></i></a><a data-action="delete-column" class="btn btn-default" data-id="'+id+'"><i class="fa fa-times"></i></a></div></div>';
        // var new_title = '<div style="background: '+color+'" class="cd_kanban_board_title">' + '<p>'+title+'</p>' + '</div>';

        kanban.find('.cd_kanban_board_titles').append(new_title);

        var new_block = '<div class="cd_kanban_board_block" data-block="'+id+'"></div>';
        kanban.find('.cd_kanban_board_blocks').append(new_block);

        $( "[data-block='"+id+"']").sortable({
            connectWith: ".cd_kanban_board_block",
            containment: ".cd_kanban_board_blocks",
            placeholder: "cd_kanban_board_block_item_placeholder",
            scroll: true,
            cursor: "move"
        }).disableSelection();
    };
}(jQuery));
