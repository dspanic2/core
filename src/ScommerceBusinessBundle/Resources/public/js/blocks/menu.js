jQuery.extend({
    changeMenuItemType: function () {
        var type = jQuery('#menu_item_type').val();

        if (type == 1) {
            jQuery('#page').val('').parents('.form-group').hide();
            jQuery('#product_group').val('').parents('.form-group').hide();
            jQuery('#url').val('').parents('.form-group').hide();
            jQuery('#blog_category').val('').parents('.form-group').hide();
            jQuery('#brand').val('').parents('.form-group').hide();
            jQuery('#warehouse').val('').parents('.form-group').hide();
        } else if (type == 2) {
            jQuery('#page').parents('.form-group').show();
            jQuery('#product_group').val('').parents('.form-group').hide();
            jQuery('#url').val('').parents('.form-group').hide();
            jQuery('#blog_category').val('').parents('.form-group').hide();
            jQuery('#brand').val('').parents('.form-group').hide();
            jQuery('#warehouse').val('').parents('.form-group').hide();
        } else if (type == 3) {
            jQuery('#page').val('').parents('.form-group').hide();
            jQuery('#product_group').parents('.form-group').show();
            jQuery('#url').val('').parents('.form-group').hide();
            jQuery('#blog_category').val('').parents('.form-group').hide();
            jQuery('#brand').val('').parents('.form-group').hide();
            jQuery('#warehouse').val('').parents('.form-group').hide();
        } else if (type == 5) {
            jQuery('#page').val('').parents('.form-group').hide();
            jQuery('#product_group').parents('.form-group').hide();
            jQuery('#url').val('').parents('.form-group').hide();
            jQuery('#blog_category').parents('.form-group').show();
            jQuery('#brand').val('').parents('.form-group').hide();
            jQuery('#warehouse').val('').parents('.form-group').hide();
        } else if (type == 6) {
            jQuery('#page').val('').parents('.form-group').hide();
            jQuery('#product_group').parents('.form-group').hide();
            jQuery('#url').val('').parents('.form-group').hide();
            jQuery('#blog_category').val('').parents('.form-group').hide();
            jQuery('#brand').parents('.form-group').show();
            jQuery('#warehouse').val('').parents('.form-group').hide();
        } else if (type == 7) {
            jQuery('#page').val('').parents('.form-group').hide();
            jQuery('#product_group').parents('.form-group').hide();
            jQuery('#url').val('').parents('.form-group').hide();
            jQuery('#blog_category').val('').parents('.form-group').hide();
            jQuery('#brand').val('').parents('.form-group').hide();
            jQuery('#warehouse').parents('.form-group').show();
        } else {
            jQuery('#page').val('').parents('.form-group').hide();
            jQuery('#product_group').val('').parents('.form-group').hide();
            jQuery('#url').parents('.form-group').show();
            jQuery('#blog_category').val('').parents('.form-group').hide();
            jQuery('#brand').val('').parents('.form-group').hide();
            jQuery('#warehouse').val('').parents('.form-group').hide();
        }

        return false;
    }
});

var menuEditor = null;

function exportMenuBlockToJson() {
    var str = menuEditor.getString();
    if (jQuery('[name="tmp_content"]').length < 1) {
        jQuery.growl.error({
            title: translations.error_message,
            message: "tmp_content field in menu is missing"
        });
        return false;
    }
    jQuery('[name="tmp_content"]').text(str);
    jQuery('[name="navigation_json"]').text(str);
    return true;
}

jQuery(document).ready(function () {

    // ADMIN

    // FRONTEND
    if (jQuery('[data-type="menu_block"]').length > 0) {

        if (jQuery('[data-type="s_menu"]').find('[name="id"]').val() && jQuery('[data-type="s_menu"]').find('[name="store_id"]').data('select2')) {
            jQuery('[data-type="s_menu"]').find('[name="store_id"]').select2('destroy').attr('readonly', 'readonly');
        }

        //Add presave to form
        jQuery('[data-type="s_menu"]').attr('data-presave', '["exportMenuBlockToJson"]');

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
        menuEditor = new MenuEditor('menuEditor', {
            listOptions: options,
            labelEdit: 'Edit',
            labelRemove: 'Remove'
        });
        menuEditor.setForm(jQuery('#frmEdit'));
        menuEditor.setUpdateButton(jQuery('#btnUpdate'));

        jQuery('#btnOut').on('click', function () {
            var str = menuEditor.getString();
            jQuery('[name="navigation_json"]').text(str);
        });
        jQuery("#btnUpdate").click(function () {
            menuEditor.updateSmenu();
        });
        jQuery('#btnAdd').click(function () {
            menuEditor.addSmenu();
        });

        menuEditor.setData(jQuery('[name="navigation_json"]').text());

        jQuery('body').on('change', '#menu_item_type', function (e) {
            $.changeMenuItemType();
        });
        $.changeMenuItemType();
    }
});
