/**
 * Refresh quote item list
 */
function refreshQuoteItems(table, result) {
    refreshList(jQuery('body').find('[data-table="quote_item"]'), null);
}

function openQtyModal(table, result) {
    if (result.quote_item_id !== undefined) {
        $.ajax({
            url: "/quote/quote_item_change?id=" + result.quote_item_id + "&type=quote_item",
            method: "POST",
            data: {
                "quote_item_id": result.quote_item_id,
            },
            async: false,
            cache: false
        }).done(function (result) {
            if (result.error == false) {
                var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                clone.html(result.html);
                var modal = clone.find('.modal');
                modal.modal('show');

                var form = modal.find('[data-validate="true"]');
                form.initializeValidation();

            } else {
                $.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        });
    }
}

jQuery(document).ready(function ($) {

    var ajaxLoader = $("#ajax-loading");

    if (jQuery('[data-type="quote"]').length > 0) {

        jQuery('[data-type="quote"]').find('[name="account_id"]').attr('disabled','disabled');
        jQuery('[data-type="quote"]').formValidation('enableFieldValidators','account_id', false, null);
        /*jQuery('body').on('change', '[name="account_id"]', function (e) {
            jQuery('body').find('[name="contact_id"]').val('').trigger('change');
            jQuery('body').find('[name="account_billing_address_id"]').val('').trigger('change');
            jQuery('body').find('[name="account_shipping_address_id"]').val('').trigger('change');
        });*/

        /**
         * Modal calculation
         */
        jQuery('body').on('change', '[data-holder="qty"]', function (e) {
            var val = jQuery(this).val();
            var req = new RegExp('^(\-)?([0-9])([0-9])*(,([0-9])([0-9])([0-9])?)?$');
            if (val == '' || !req.test(val)) {
                jQuery(this).val(0);
            }
            var num_of = val;
            if (num_of < 0) {
                num_of = 0;
                jQuery(this).val(0);
            }

            //todo ovdje post
        });

        jQuery('body').on('change', '[data-holder="base_price_fixed_discount"]', function (e) {
            var val = jQuery(this).val();
            var req = new RegExp('^(\-)?([0-9])([0-9])*(,([0-9])([0-9])([0-9])?)?$');
            if (val == '' || !req.test(val)) {
                jQuery(this).val(0);
            }
            var num_of = val;
            if (num_of < 0) {
                num_of = 0;
                jQuery(this).val(0);
            }

            //todo ovdje post
        });

        jQuery('body').on('change', '[data-holder="percentage_discount_fixed"]', function (e) {
            var val = jQuery(this).val();
            var req = new RegExp('^(\-)?([0-9])([0-9])*(,([0-9])([0-9])([0-9])?)?$');
            if (val == '' || !req.test(val)) {
                jQuery(this).val(0);
            }
            var num_of = val;
            if (num_of < 0) {
                num_of = 0;
                jQuery(this).val(0);
            }

           //todo post
        });

        /**
         * Quote preview
         */
        jQuery('body').on('click', '[data-action="quote_preview"]', function (e) {
            e.preventDefault();
            e.stopPropagation();

            $("#ajax-loading").addClass('active');

            var button = $(this);
            $.ajax({
                url: button.data("url"),
                method: 'POST',
                data: {
                    quote_id: $(".main-content").find('[name="id"]').val(),
                },
                cache: false
            }).done(function (result) {
                $("#ajax-loading").removeClass('active');
                if (result.error == false) {
                    var win = window.open(result.redirect_url, '_blank');
                    win.focus();
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : translations.error_message,
                    });
                }
            });
        });

        /**
         * Download quote
         */
        jQuery('body').on('click', '[data-action="quote_download"]', function (e) {
            e.preventDefault();
            e.stopPropagation();

            jQuery.post(jQuery(this).data('url'), {
                id: (jQuery('[name="id"]').length ? jQuery('[name="id"]').val() : null)
            }, function (result) {
                if (result.error == false) {
                    var win = window.open(result.file, '_blank');
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
            });
        });

        /**
         * Quote item change
         */
        jQuery('body').on('click', '[data-action="quote_item_change"]', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var elem = jQuery(this);
            var table = elem.parents('.dataTables_wrapper').find('.datatables');
            var id = elem.data('id');
            ajaxLoader.addClass("active");

            $.post(elem.data("url"), {
                quote_item_id: id,
            }, function (result) {
                ajaxLoader.removeClass("active");
                if (result.error == false) {

                    var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                    clone.html(result.html);
                    var modal = clone.find('.modal');
                    modal.modal('show');

                    var form = modal.find('[data-validate="true"]');
                    form.initializeValidation();

                } else {
                    $.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, "json");
        });
    }

    /**
     * Send email modal
     */
    jQuery('body').on('click', '[data-action="send_to_client_form"]', function (e) {
        $("#ajax-loading").addClass('active');

        e.preventDefault();
        e.stopPropagation();

        var button = $(this);

        $.ajax({
            url: button.data("url"),
            method: 'POST',
            data: {
                quote_id: $(".main-content").find('[name="id"]').val(),
            },
            cache: false
        }).done(function (result) {
            $("#ajax-loading").removeClass('active');
            if (result.error == false) {
                var clone = $('#modal-container').clone(true, true).appendTo($('body'));
                clone.html(result.html);
                clone.find('.modal').modal('show');
                initializeCkeditor(clone.find("form"), ['Shape Quote URL token']);
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : translations.selection_error,
                });
            }
        });
    });

    /**
     * Send email to customer
     */
    jQuery('body').on('click', '[data-action="quote_send_client_email"]', function (e) {
        $("#ajax-loading").addClass('active');

        e.preventDefault();
        e.stopPropagation();

        var button = $(this);
        $.ajax({
            url: button.data("url"),
            method: 'POST',
            data: button.parents('form').serialize(),
            cache: false
        }).done(function (result) {
            $("#ajax-loading").removeClass('active');
            if (result.error == false) {
                $("#default_modal").modal('hide');
                $.growl.notice({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : translations.selection_error,
                });
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : translations.selection_error,
                });
            }
        });
    });

    /**
     * Fill in account id on order/quote customer add
     */
    $(document).on('shown.bs.modal', '#default_modal', function (e) {
        var wrapper = $(this);
        if (wrapper.find('[data-type="contact"]').length > 0) {
            var parent_type = false;
            var parent_id = false;
            var form = wrapper.find('[data-type="contact"]');
            if ($(document).find('form[data-type="quote"]').length > 0) {
                parent_id = $(document).find('[data-type="quote"]').find('[name="id"]').val();
                parent_type = "quote";
            } else if ($(document).find('form[data-type="order"]').length > 0) {
                parent_id = $(document).find('[data-type="order"]').find('[name="id"]').val();
                parent_type = "order";
            } else {
                return false;
            }
            ajaxLoader.addClass("active");
            $.post('/api/get/single_entity_data', {
                id: parent_id,
                entity_type: parent_type,
                token: "token"
            }, function (result) {
                ajaxLoader.removeClass("active");
                if (result.error == false) {

                    var accountField = form.find('[name="account_id"]');
                    setSingleValueForLookup(accountField, result.data.account_id[0].id);

                    accountField.attr('readonly', 'readonly');
                    accountField.select2('destroy');

                    var ownerField = form.find('[name="owner_id"]');
                    if (ownerField.length > 0 && result.data.owner_id) {
                        setSingleValueForLookup(ownerField, result.data.owner_id[0].id);

                        ownerField.attr('readonly', 'readonly');
                        ownerField.select2('destroy');
                    }
                } else {
                    $.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, "json");
        } else if (wrapper.find('[data-type="address"]').length > 0) {
            var parent_type = false;
            var parent_id = false;
            var form = wrapper.find('[data-type="address"]');
            if ($(document).find('form[data-type="quote"]').length > 0) {
                parent_id = $(document).find('[data-type="quote"]').find('[name="id"]').val();
                parent_type = "quote";
            } else if ($(document).find('form[data-type="order"]').length > 0) {
                parent_id = $(document).find('[data-type="order"]').find('[name="id"]').val();
                parent_type = "order";
            } else {
                return false;
            }

            ajaxLoader.addClass("active");
            $.post('/api/get/single_entity_data', {
                id: parent_id,
                entity_type: parent_type,
                token: "token"
            }, function (result) {
                ajaxLoader.removeClass("active");
                if (result.error == false) {

                    var accountField = form.find('[name="account_id"]');
                    setSingleValueForLookup(accountField, result.data.account_id[0].id);

                    accountField.attr('readonly', 'readonly');
                    accountField.select2('destroy');
                } else {
                    $.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, "json");
        }

        return true;
    });

});

