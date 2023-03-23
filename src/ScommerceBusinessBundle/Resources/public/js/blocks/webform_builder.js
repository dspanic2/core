var menuEditor = null;

function exportWebformBlockToJson() {
    var str = menuEditor.getString();
    if (jQuery('[name="tmp_content"]').length < 1) {
        jQuery.growl.error({
            title: translations.error_message,
            message: "tmp_content field in menu is missing"
        });
        return false;
    }
    jQuery('[name="tmp_content"]').text(str);
    jQuery('[name="webform_structure_json"]').text(str);
    return true;
}

jQuery(document).ready(function ($) {

    // ADMIN

    // FRONTEND
    if ($('[data-type="webform_builder_block"]').length > 0) {

        $(".draggable-left, .draggable-right").sortable({
            connectWith: ".connected-sortable",
            stack: ".connected-sortable ul"
        }).disableSelection();

        $(".draggable-webform-groups").sortable({
            placeholder: "ui-state-highlight"
        }).disableSelection();

        if ($('[data-type="webform"]').find('[name="id"]').val() && $('[data-type="webform"]').find('[name="store_id"]').data('select2')) {
            $('[data-type="webform"]').find('[name="store_id"]').select2('destroy').attr('readonly', 'readonly');
        }

        //Add presave to form
        $('[data-type="webform"]').attr('data-presave', '["exportWebformBlockToJson"]');

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
        menuEditor = new MenuEditor('webform-builder', {
            listOptions: options,
            labelEdit: 'Edit',
            labelRemove: 'Remove',
            maxLevel: 1
        });
        menuEditor.setForm($('#frmEdit'));
        menuEditor.setUpdateButton($('#btnUpdate'));

        $('#btnOut').on('click', function () {
            var str = menuEditor.getString();
            $('[name="webform_structure_json"]').text(str);
        });
        $("#btnUpdate").click(function () {
            menuEditor.updateSmenu();
        });
        $('#btnAdd').click(function () {
            menuEditor.addSmenu();
        });

        menuEditor.setData($('[name="webform_structure_json"]').text());
    }
});
