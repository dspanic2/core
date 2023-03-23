function toggleCheckId(form) {

    var check_id = form.find('[name="check_id"]').val();
    if (check_id == 1) {
        if (form.find('[name="id_check_name"]').data('fv-notempty')) {
            form.formValidation('enableFieldValidators', 'id_check_name', true, null);
        }
        form.find('[data-form-group="id_check_name"]').show();

        if (form.find('[name="id_check_number"]').data('fv-notempty')) {
            form.formValidation('enableFieldValidators', 'id_check_number', true, null);
        }
        form.find('[data-form-group="id_check_number"]').show();
    } else {
        if (form.find('[name="id_check_name"]').data('fv-notempty')) {
            form.formValidation('enableFieldValidators', 'id_check_name', false, null);
        }
        form.find('[data-form-group="id_check_name"]').hide();

        if (form.find('[name="id_check_number"]').data('fv-notempty')) {
            form.formValidation('enableFieldValidators', 'id_check_number', false, null);
        }
        form.find('[data-form-group="id_check_number"]').hide();
    }

    return false;
}

function resetButtons() {

    var form = jQuery('[data-validate="true"]');

    var requested = form.find('[name="requested"]').val();
    var order_number = form.find('[name="order_number"]').val();

    if (order_number !== '') {
        jQuery('[data-action="request_dpd"]').removeClass('hidden');

        if (requested == 1) {
            jQuery('[data-action="print_dpd_labels"]').removeClass('hidden');
            jQuery('[data-action="refresh_dpd_status"]').removeClass('hidden');
            //jQuery('[data-action="delete_dpd"]').removeClass('hidden');
            jQuery('[data-action="cancel_dpd"]').removeClass('hidden');

            jQuery('[data-action="request_dpd"]').attr('disabled', 'disabled');
            jQuery('[data-action="request_dpd"]').attr('title', "Already requested.");
            form.find('[name="cod_amount"]').attr('readonly', 'readonly');
            form.find('[name="sender_remark"]').attr('readonly', 'readonly');
            form.find('[name="weight"]').attr('readonly', 'readonly');
            form.find('[name="parcel_type_id"]').attr('disabled','disabled');
            form.find('[name="number_of_parcels"]').attr('readonly', 'readonly');
        } else {
            jQuery('[data-action="print_dpd_labels"]').addClass('hidden');
            jQuery('[data-action="refresh_dpd_status"]').addClass('hidden');
            jQuery('[data-action="delete_dpd"]').addClass('hidden');
        }
    }
}

jQuery(document).ready(function () {

    var form = jQuery('form[data-type="dpd_parcel"]');
    if (form.length > 0) {

        if (form.find('[name="id"]').length > 0) {
            var orderField = form.find('[name="order_id"]');
            if (form.find('[name="id"]').val()) {

                // Is edit.
                orderField.attr('disabled', 'disabled');
                orderField.attr('readonly', 'readonly');

                /**
                 * Request dpd
                 */
                jQuery('body').on('click','[data-action="request_dpd"]', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var form = jQuery('[data-validate="true"]');
                    var button = jQuery(this);
                    var id = form.find('[name="id"]').val();
                    var order_number = form.find('[name="order_number"]').val();
                    var parcel_type_id = form.find('[name="parcel_type_id"]').val();

                    jQuery.confirm({
                        title: translations.please_confirm,
                        content: translations.yes_i_am_sure,
                        buttons: {
                            confirm: {
                                text: translations.yes_i_am_sure,
                                btnClass: 'sp-btn btn-primary btn-blue btn',
                                keys: ['enter'],
                                action: function () {
                                    button.attr("disabled", "disabled");

                                    jQuery.post(button.data("url"), {
                                        id: id,
                                        order_number: order_number,
                                        parcel_type_id: parcel_type_id
                                    }, function (result) {
                                        if (result.error == false) {
                                            button.removeAttr("disabled");
                                            location.reload(true);
                                        } else {
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
                                }
                            }
                        }
                    });
                });

                /**
                 * Refresh dpd status
                 */
                jQuery('body').on('click','[data-action="refresh_dpd_status"]', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var form = jQuery('[data-validate="true"]');
                    var button = jQuery(this);
                    var id = form.find('[name="id"]').val();

                    jQuery.confirm({
                        title: translations.please_confirm,
                        content: translations.yes_i_am_sure,
                        buttons: {
                            confirm: {
                                text: translations.yes_i_am_sure,
                                btnClass: 'sp-btn btn-primary btn-blue btn',
                                keys: ['enter'],
                                action: function () {
                                    button.attr("disabled", "disabled");

                                    jQuery.post(button.data("url"), {id: id}, function (result) {
                                        if (result.error == false) {
                                            jQuery.growl.notice({
                                                title: result.title,
                                                message: result.message
                                            });
                                            button.removeAttr("disabled");
                                            location.reload(true);
                                        } else {
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
                                }
                            }
                        }
                    });
                });

                /**
                 * Delete
                 */
                jQuery('body').on('click','[data-action="delete_dpd"]', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var form = jQuery('[data-validate="true"]');
                    var button = jQuery(this);
                    var id = form.find('[name="id"]').val();

                    jQuery.confirm({
                        title: translations.please_confirm,
                        content: translations.yes_i_am_sure,
                        buttons: {
                            confirm: {
                                text: translations.yes_i_am_sure,
                                btnClass: 'sp-btn btn-primary btn-blue btn',
                                keys: ['enter'],
                                action: function () {
                                    button.attr("disabled", "disabled");

                                    jQuery.post(button.data("url"), {id: id, operation: "delete"}, function (result) {
                                        if (result.error == false) {
                                            jQuery.growl.notice({
                                                title: result.title,
                                                message: result.message
                                            });
                                            button.removeAttr("disabled");
                                            location.reload(true);
                                        } else {
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
                                }
                            }
                        }
                    });
                });

                /**
                 * Cancel dpd
                 */
                jQuery('body').on('click','[data-action="cancel_dpd"]',function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var form = jQuery('[data-validate="true"]');
                    var button = jQuery(this);
                    var id = form.find('[name="id"]').val();

                    jQuery.confirm({
                        title: translations.please_confirm,
                        content: translations.yes_i_am_sure,
                        buttons: {
                            confirm: {
                                text: translations.yes_i_am_sure,
                                btnClass: 'sp-btn btn-primary btn-blue btn',
                                keys: ['enter'],
                                action: function () {
                                    button.attr("disabled", "disabled");

                                    jQuery.post(button.data("url"), {id: id, operation: "cancel"}, function (result) {
                                        if (result.error == false) {
                                            jQuery.growl.notice({
                                                title: result.title,
                                                message: result.message
                                            });
                                            button.removeAttr("disabled");
                                            location.reload(true);
                                        } else {
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
                                }
                            }
                        }
                    });
                });

                /**
                 * Print dpd label
                 */
                jQuery('body').on('click','[data-action="print_dpd_labels"]',function (e) {

                    e.preventDefault();
                    e.stopPropagation();

                    var form = jQuery('[data-validate="true"]');
                    var button = jQuery(this);
                    var id = form.find('[name="id"]').val();

                    jQuery.confirm({
                        title: translations.please_confirm,
                        content: translations.yes_i_am_sure,
                        buttons: {
                            confirm: {
                                text: translations.yes_i_am_sure,
                                btnClass: 'sp-btn btn-primary btn-blue btn',
                                keys: ['enter'],
                                action: function () {
                                    button.attr("disabled", "disabled");

                                    jQuery.post(button.data("url"), {id: id}, function (result) {
                                        if (result.error == false) {
                                            button.removeAttr("disabled");
                                            location.reload(true);
                                        } else {
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
                                }
                            }
                        }
                    });
                });

                resetButtons();
            } else {
                // Is new.
                orderField.on('change', function () {

                    if ($(this).val()) {
                        form.find('[name="order_number"]').attr('disabled', 'disabled');
                        form.find('[name="order_number"]').attr('readonly', 'readonly');
                        form.find('[name="order_number"]').parents('.form-group').hide();

                        form.find('[name="recipient_name"]').attr('disabled', 'disabled');
                        form.find('[name="recipient_name"]').attr('readonly', 'readonly');
                        form.find('[name="recipient_name"]').parents('.form-group').hide();

                        form.find('[name="recipient_name_2"]').attr('disabled', 'disabled');
                        form.find('[name="recipient_name_2"]').attr('readonly', 'readonly');
                        form.find('[name="recipient_name_2"]').parents('.form-group').hide();

                        form.find('[name="contact_information"]').attr('disabled', 'disabled');
                        form.find('[name="contact_information"]').attr('readonly', 'readonly');
                        form.find('[name="contact_information"]').parents('.form-group').hide();

                        form.find('[name="recipient_street"]').attr('disabled', 'disabled');
                        form.find('[name="recipient_street"]').attr('readonly', 'readonly');
                        form.find('[name="recipient_street"]').parents('.form-group').hide();

                        form.find('[name="recipient_city"]').attr('disabled', 'disabled');
                        form.find('[name="recipient_city"]').attr('readonly', 'readonly');
                        form.find('[name="recipient_city"]').parents('.form-group').hide();

                        form.find('[name="country"]').attr('disabled', 'disabled');
                        form.find('[name="country"]').attr('readonly', 'readonly');
                        form.find('[name="country"]').parents('.form-group').hide();

                        form.find('[name="postal_code"]').attr('disabled', 'disabled');
                        form.find('[name="postal_code"]').attr('readonly', 'readonly');
                        form.find('[name="postal_code"]').parents('.form-group').hide();

                        form.find('[name="recipient_email"]').attr('disabled', 'disabled');
                        form.find('[name="recipient_email"]').attr('readonly', 'readonly');
                        form.find('[name="recipient_email"]').parents('.form-group').hide();

                        form.find('[name="recipient_house_number"]').attr('disabled', 'disabled');
                        form.find('[name="recipient_house_number"]').attr('readonly', 'readonly');
                        form.find('[name="recipient_house_number"]').parents('.form-group').hide();

                        form.find('[name="recipient_phone"]').attr('disabled', 'disabled');
                        form.find('[name="recipient_phone"]').attr('readonly', 'readonly');
                        form.find('[name="recipient_phone"]').parents('.form-group').hide();

                        form.find('[name="parcel_type_id"]').attr('disabled', 'disabled');
                        form.find('[name="parcel_type_id"]').attr('readonly', 'readonly');
                        form.find('[name="parcel_type_id"]').parents('.form-group').hide();

                        form.find('[name="cod_amount"]').attr('disabled', 'disabled');
                        form.find('[name="cod_amount"]').attr('readonly', 'readonly');
                        form.find('[name="cod_amount"]').parents('.form-group').hide();

                        form.find('[name="cod_purpose"]').attr('disabled', 'disabled');
                        form.find('[name="cod_purpose"]').attr('readonly', 'readonly');
                        form.find('[name="cod_purpose"]').parents('.form-group').hide();
                    } else {
                        form.find('[name="order_number"]').removeAttr('disabled');
                        form.find('[name="order_number"]').removeAttr('readonly');
                        form.find('[name="order_number"]').parents('.form-group').show();

                        form.find('[name="recipient_name"]').removeAttr('disabled');
                        form.find('[name="recipient_name"]').removeAttr('readonly');
                        form.find('[name="recipient_name"]').parents('.form-group').show();

                        form.find('[name="recipient_name_2"]').removeAttr('disabled');
                        form.find('[name="recipient_name_2"]').removeAttr('readonly');
                        form.find('[name="recipient_name_2"]').parents('.form-group').show();

                        form.find('[name="contact_information"]').removeAttr('disabled');
                        form.find('[name="contact_information"]').removeAttr('readonly');
                        form.find('[name="contact_information"]').parents('.form-group').show();

                        form.find('[name="recipient_street"]').removeAttr('disabled');
                        form.find('[name="recipient_street"]').removeAttr('readonly');
                        form.find('[name="recipient_street"]').parents('.form-group').show();

                        form.find('[name="recipient_city"]').removeAttr('disabled');
                        form.find('[name="recipient_city"]').removeAttr('readonly');
                        form.find('[name="recipient_city"]').parents('.form-group').show();

                        form.find('[name="country"]').removeAttr('disabled');
                        form.find('[name="country"]').removeAttr('readonly');
                        form.find('[name="country"]').parents('.form-group').show();

                        form.find('[name="postal_code"]').removeAttr('disabled');
                        form.find('[name="postal_code"]').removeAttr('readonly');
                        form.find('[name="postal_code"]').parents('.form-group').show();

                        form.find('[name="recipient_email"]').removeAttr('disabled');
                        form.find('[name="recipient_email"]').removeAttr('readonly');
                        form.find('[name="recipient_email"]').parents('.form-group').show();

                        form.find('[name="recipient_house_number"]').attr('disabled', 'disabled');
                        form.find('[name="recipient_house_number"]').attr('readonly', 'readonly');
                        form.find('[name="recipient_house_number"]').parents('.form-group').show();

                        form.find('[name="recipient_phone"]').removeAttr('disabled');
                        form.find('[name="recipient_phone"]').removeAttr('readonly');
                        form.find('[name="recipient_phone"]').parents('.form-group').show();

                        form.find('[name="parcel_type_id"]').removeAttr('disabled');
                        form.find('[name="parcel_type_id"]').removeAttr('readonly');
                        form.find('[name="parcel_type_id"]').parents('.form-group').show();

                        form.find('[name="cod_amount"]').removeAttr('disabled');
                        form.find('[name="cod_amount"]').removeAttr('readonly');
                        form.find('[name="cod_amount"]').parents('.form-group').show();

                        form.find('[name="cod_purpose"]').removeAttr('disabled');
                        form.find('[name="cod_purpose"]').removeAttr('readonly');
                        form.find('[name="cod_purpose"]').parents('.form-group').show();
                    }
                });
            }

            if (form.find('[name="check_id_checkbox"]').length > 0) {
                toggleCheckId(form);

                form.find('[name="check_id_checkbox"]').on('switchChange.bootstrapSwitch', function (e) {
                    toggleCheckId(form);
                });
            }

            orderField.trigger('change');
        }
    }

    /**
     * Mass print DPD labels
     */
    jQuery('body').on('click', '[data-action="mass_print_dpd_label"]', function (e) {
        e.stopPropagation();
        var table = jQuery(this).parents('.panel').find('.dataTables_wrapper');

        jQuery.post(jQuery(this).data("url"), {
            data: table_state[table.find('.datatables').attr('id')],
            items: selectList[table.attr('id')]
        }, function (result) {
            if (result.error == false) {
                jQuery.growl.notice({
                    title: result.title,
                    message: result.message
                });
                window.open(result.filepath, '_blank');
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    jQuery('body').on('click', '[data-action="redirect_dpd_tracking_status"]', function (e) {
        jQuery.post(jQuery(this).data("url"), {
            id: jQuery(this).data("id")
        }, function (result) {
            if (result.error == false) {
                window.open(result.url, '_blank')
            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });

    jQuery(document).on('click', '[data-action="print_dpd_manifest"]', function (e) {

        e.preventDefault();
        e.stopPropagation();

        var form = jQuery('[data-validate="true"]');
        var type = form.find('[name="manifest_code"]').val();
        var date = form.find('[name="date_start"]').val();

        jQuery.post($(this).data("url"), {type: type, date: date}, function (result) {

            if (result.error == false) {
                let pdfWindow = window.open("about:blank", "_blank")
                pdfWindow.document.write(
                    "<embed type='application/pdf' style='position:fixed; top:0; left:0; bottom:0; right:0; width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden; z-index:999999;' " +
                    "src='data:application/pdf;base64, " +
                    encodeURI(result.pdf) + "' full-frame>"
                )

                pdfWindow.document.title = result.type;

            } else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });


});
