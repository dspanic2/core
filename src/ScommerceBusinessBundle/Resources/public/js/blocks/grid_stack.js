/**
 *
 * @param form
 * @param result
 */
function addFrontBlock(form, result) {
    if (form.find('[name="parent_id"]').length > 0) {
        getFrontBlock(result.entity.id, form.find('[name="parent_id"]').val());
    } else {
        getFrontBlock(result.entity.id, form.find('[name="id"]').val());
    }

    return false;
}

/**
 *
 * @param id
 * @param parent_id
 * @returns {boolean}
 */
function getFrontBlock(id, parent_id) {

    var grid = null;

    var is_front = false;
    var url = null;

    if (parent_id) {
        if (jQuery('body').find('[data-block-id="' + parent_id + '"]').length > 0) {
            grid = jQuery('body').find('[data-block-id="' + parent_id + '"]').find('.grid-stack');
        }
    }

    if (!grid) {
        grid = jQuery('body').find('.sp-grid-wrapper-inner').find('.grid-stack:first');
    }

    url = grid.data("url");

    jQuery.post(url, {id: id, is_front: is_front}, function (result) {
        if (result.error == false) {

            var el = jQuery(result.html);
            //var new_id = el.data('block-id');

            if (is_front) {
                jQuery('body').find('.sp-block-group-wrapper > .row:last-child').first().append(result.html);
            } else {
                var grids = grid.data('gridstack');
                grids.addWidget(result.html, 10, 10, 12, 2, true);
            }

            /*var block = jQuery('body').find('[data-block-id="' + new_id + '"]');

            if (is_front) {
                block.find('[data-action="edit-block-modal-front"]').trigger("click");
            }
            else {
                block.find('[data-action="edit-block-modal"]').trigger("click");
            }*/
        } else {
            jQuery.growl.error({
                title: translations.error_message,
                message: result.message
            });
        }
    }, "json");

    return false;
}

jQuery(document).ready(function ($) {

    // ADMIN

    /** Remove block */
    jQuery('body').on('click', '[data-action="remove-front-block"]', function (e) {

        /** Elem to delete **/
        var elem = jQuery(this).closest('.grid-stack-item');
        var parent = jQuery(this).closest('.grid-stack');

        jQuery.post(jQuery(this).data("url"), {
            id: elem.data('block-id'),
            parent_id: parent.data('parent-id'),
            parent_type: parent.data('parent-type')
        }, function (result) {
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

    /** Add block */
    jQuery('body').on('click', '[data-action="add-edit-front-block"]', function (e) {
        jQuery.post(jQuery(this).data("url"), {
            id: jQuery(this).data("id"),
            parent_id: jQuery(this).data("parent-id"),
            parent_type: jQuery(this).data("parent-type"),
            form_type: jQuery(this).data("form-type"),
            is_front: jQuery(this).data("is-front")
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

    /** Inline save */
    $('body').on('click', '[data-action="inline-edit-front-block"]', function (e) {
        var values = {};

        var inlineData = $(this).parents(".inline-data");
        inlineData.find(".form-control").each(function () {
            values[$(this).data("name")] = $(this).val();
        });

        $.post($(this).data("url"), {
            values: values,
            block_id: $(this).data("block-id"),
            block_type: $(this).data("block-type")
        }, function (result) {
            if (result.error == false) {
                $.growl.notice({
                    title: result.title,
                    message: result.message
                });
            } else {
                $.growl.error({
                    title: result.title,
                    message: result.message
                });
            }
        }, "json");
    });

});