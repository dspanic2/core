// ADMIN
var loadAttributes = function (form, url, entityTypeCode) {

    var backendTypes = new Array();
    backendTypes.push("text");
    backendTypes.push("decimal");
    backendTypes.push("int");
    backendTypes.push("varchar");
    backendTypes.push("lookup");

    jQuery.post(
        url,
        {
            type: form.find('[name="type"]').val(),
            entityType: entityTypeCode,
            backendTypes: backendTypes
        },
        function (result) {
            if (result.error == false) {
                form.find('[name="category"]').html(result.html);
                if (form.find('[name="category"]').data('value')) {
                    form.find('[name="category"]').val(form.find('[name="category"]').data('value'));
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
};

var loadAttributeSetDefinition = function (form, url, entityTypeCode) {

    jQuery.post(
        url,
        {
            entityType: entityTypeCode
        },
        function (result) {
            if (result.error == false) {
                form.find('.attribute_set_definition_wrapper').html(result.html);
                form.find('.attribute_set_definition_wrapper').find('[data-size="autosize"]').autosize({append: "\n"});
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        },
        "json"
    );
};

// FRONTEND
function gridviewGetData(grid) {
    var categoryVal = grid.find('[name="gridview_category"]').val();
    var keywordVal = grid.find('[name="keyword"]').val();
    var postUrl = grid.find('[name="keyword"]').data("url");
    var blockId = grid.data("block-id");

    jQuery.loader({
        className: "blue-with-image-2",
        content: translations.please_wait
    });
    jQuery.post(
        postUrl,
        {
            categoryVal: categoryVal,
            keywordVal: keywordVal,
            blockId: blockId
        },
        function (result) {
            if (result.error == false) {
                grid.find(".grid-view").html(result.html);
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
            jQuery("#jquery-loader-background").remove();
            jQuery("#jquery-loader").remove();
        },
        "json"
    );
}

var keywordChange = _.debounce(
    function (grid) {
        gridviewGetData(grid);
    }, 500);

function initializeGridView(grid) {
    grid.find('[name="gridview_category"]').on('change', function (e) {
        gridviewGetData(grid)
    });
    grid.find('[name="keyword"]').on('input', function (e) {
        keywordChange(grid);
    });

    gridviewGetData(grid);
}

jQuery(document).ready(function () {

    // ADMIN
    jQuery('body').on('change', 'fieldset.grid-view-settings [name="entity_type"]', function (e) {
        var form = jQuery(this).parents('form');
        var elem = jQuery(this);
        if (elem.val()) {
            loadAttributes(form, elem.data("url"), elem.val());
            loadAttributeSetDefinition(form, elem.data("attribute-set-definition-url"), elem.val());
        }
    });

    // FRONTEND
    if (jQuery('.sp-grid-view-wrapper').length) {

        var gidviewWrapper = jQuery(".sp-grid-view-wrapper");
        if (gidviewWrapper.length) {

            gidviewWrapper.each(function () {
                initializeGridView(jQuery(this));
            });
        }

        /**
         * Reset filter on grid view
         */
        jQuery('body').on('click', '[data-action="grid-view-filter-reset"]', function (e) {
            var wrapper = jQuery(this).parents('.sp-block');

            wrapper.find('[name="gridview_category"]').val('');
            wrapper.find('[name="keyword"]').val('').trigger('input');

            return false;
        });
    }

    /**
     *  add_simple_to_cart
     */
    jQuery('body').on('click', '[data-action="add_simple_to_cart"]', function (e) {
        console.log("a1");
        e.preventDefault();
        e.stopPropagation();

        var quote_id = jQuery('body').find('[name="id"]').val();
        var elem = jQuery(this);
        var wrapper = jQuery(this).parents('.item-list');

        if (!quote_id) {
            return false;
        }

        var submitQuoteItem = function (qty) {
            jQuery.post(elem.data('url'), {id: elem.data('id'), quote_id: quote_id, qty: qty}, function (result) {
                if (result.error == false) {
                    jQuery.growl.notice({
                        title: result.title,
                        message: result.message
                    });

                    if (elem.data('callback')) {
                        var functions = elem.data('callback');
                        console.log(functions);
                        jQuery.each(functions, function (key, f) {
                            if (eval("typeof " + f + " === 'function'")) {
                                window[f](elem, result);
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

        if (elem.hasClass("set-qty")) {
            $.confirm({
                title: translations.enter_qty,
                content: '<input type="number" name="quote-item-modal-qty" placeholder="Quantity..."/>',
                buttons: {
                    confirm: {
                        text: translations.button_ok,
                        btnClass: 'button',
                        keys: ['enter'],
                        action: function () {
                            var qty = jQuery('[name="quote-item-modal-qty"]').val();
                            submitQuoteItem(qty);
                        }
                    },
                    cancel: {
                        text: translations.button_cancel,
                        btnClass: 'button gray',
                        action: function () {
                        }
                    }
                }
            });
        } else {
            submitQuoteItem(wrapper.find('[name="qty"]').val());
        }
    });

    /**
     * open_configurable
     */
    jQuery('body').on('click', '[data-action="open_configurable"]', function () {

        jQuery.post(jQuery(this).data('url'), {
            id: jQuery(this).data('id'),
            blockId: jQuery(this).parents('.sp-block').data('block-id')
        }, function (result) {
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

                //TODO SET CALLBACK
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");

        return false;
    });


});
