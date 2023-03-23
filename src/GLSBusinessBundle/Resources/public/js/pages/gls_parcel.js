jQuery(document).ready(function ($) {

    var form = $('form[data-type="gls_parcel"]');
    if (form.length) {
        if (form.find('[name="id"]').length) {
            var orderField = form.find('[name="order_id"]');
            if (form.find('[name="id"]').val()) {
                // Is edit.
                orderField.attr('disabled', 'disabled');
                orderField.attr('readonly', 'readonly');
            } else {

                // Is new.
                orderField.on("change", function () {

                    if ($(this).val()) {
                        form.find('[name="cod_reference"]').attr('disabled', 'disabled');
                        form.find('[name="cod_reference"]').attr('readonly', 'readonly');
                        form.find('[name="cod_reference"]').parents('.form-group').hide();

                        form.find('[name="content"]').attr('disabled', 'disabled');
                        form.find('[name="content"]').attr('readonly', 'readonly');
                        form.find('[name="content"]').parents('.form-group').hide();

                        form.find('[name="delivery_name"]').attr('disabled', 'disabled');
                        form.find('[name="delivery_name"]').attr('readonly', 'readonly');
                        form.find('[name="delivery_name"]').parents('.form-group').hide();

                        form.find('[name="delivery_contact_name"]').attr('disabled', 'disabled');
                        form.find('[name="delivery_contact_name"]').attr('readonly', 'readonly');
                        form.find('[name="delivery_contact_name"]').parents('.form-group').hide();

                        form.find('[name="delivery_contact_phone"]').attr('disabled', 'disabled');
                        form.find('[name="delivery_contact_phone"]').attr('readonly', 'readonly');
                        form.find('[name="delivery_contact_phone"]').parents('.form-group').hide();

                        form.find('[name="delivery_contact_email"]').attr('disabled', 'disabled');
                        form.find('[name="delivery_contact_email"]').attr('readonly', 'readonly');
                        form.find('[name="delivery_contact_email"]').parents('.form-group').hide();

                        form.find('[name="delivery_city"]').attr('disabled', 'disabled');
                        form.find('[name="delivery_city"]').attr('readonly', 'readonly');
                        form.find('[name="delivery_city"]').parents('.form-group').hide();

                        form.find('[name="delivery_zip_code"]').attr('disabled', 'disabled');
                        form.find('[name="delivery_zip_code"]').attr('readonly', 'readonly');
                        form.find('[name="delivery_zip_code"]').parents('.form-group').hide();

                        form.find('[name="delivery_street"]').attr('disabled', 'disabled');
                        form.find('[name="delivery_street"]').attr('readonly', 'readonly');
                        form.find('[name="delivery_street"]').parents('.form-group').hide();

                        form.find('[name="delivery_house_number"]').attr('disabled', 'disabled');
                        form.find('[name="delivery_house_number"]').attr('readonly', 'readonly');
                        form.find('[name="delivery_house_number"]').parents('.form-group').hide();

                        form.find('[name="delivery_house_number_info"]').attr('disabled', 'disabled');
                        form.find('[name="delivery_house_number_info"]').attr('readonly', 'readonly');
                        form.find('[name="delivery_house_number_info"]').parents('.form-group').hide();

                        form.find('[name="delivery_country_iso_code"]').attr('disabled', 'disabled');
                        form.find('[name="delivery_country_iso_code"]').attr('readonly', 'readonly');
                        form.find('[name="delivery_country_iso_code"]').parents('.form-group').hide();
                    } else {
                        form.find('[name="cod_reference"]').removeAttr('disabled');
                        form.find('[name="cod_reference"]').removeAttr('readonly');
                        form.find('[name="cod_reference"]').parents('.form-group').show();

                        form.find('[name="content"]').removeAttr('disabled');
                        form.find('[name="content"]').removeAttr('readonly');
                        form.find('[name="content"]').parents('.form-group').show();

                        form.find('[name="delivery_name"]').removeAttr('disabled');
                        form.find('[name="delivery_name"]').removeAttr('readonly');
                        form.find('[name="delivery_name"]').parents('.form-group').show();

                        form.find('[name="delivery_contact_name"]').removeAttr('disabled');
                        form.find('[name="delivery_contact_name"]').removeAttr('readonly');
                        form.find('[name="delivery_contact_name"]').parents('.form-group').show();

                        form.find('[name="delivery_contact_phone"]').removeAttr('disabled');
                        form.find('[name="delivery_contact_phone"]').removeAttr('readonly');
                        form.find('[name="delivery_contact_phone"]').parents('.form-group').show();

                        form.find('[name="delivery_contact_email"]').removeAttr('disabled');
                        form.find('[name="delivery_contact_email"]').removeAttr('readonly');
                        form.find('[name="delivery_contact_email"]').parents('.form-group').show();

                        form.find('[name="delivery_city"]').removeAttr('disabled');
                        form.find('[name="delivery_city"]').removeAttr('readonly');
                        form.find('[name="delivery_city"]').parents('.form-group').show();

                        form.find('[name="delivery_zip_code"]').removeAttr('disabled');
                        form.find('[name="delivery_zip_code"]').removeAttr('readonly');
                        form.find('[name="delivery_zip_code"]').parents('.form-group').show();

                        form.find('[name="delivery_street"]').removeAttr('disabled');
                        form.find('[name="delivery_street"]').removeAttr('readonly');
                        form.find('[name="delivery_street"]').parents('.form-group').show();

                        form.find('[name="delivery_house_number"]').removeAttr('disabled');
                        form.find('[name="delivery_house_number"]').removeAttr('readonly');
                        form.find('[name="delivery_house_number"]').parents('.form-group').show();

                        form.find('[name="delivery_house_number_info"]').removeAttr('disabled');
                        form.find('[name="delivery_house_number_info"]').removeAttr('readonly');
                        form.find('[name="delivery_house_number_info"]').parents('.form-group').show();

                        form.find('[name="delivery_country_iso_code"]').removeAttr('disabled');
                        form.find('[name="delivery_country_iso_code"]').removeAttr('readonly');
                        form.find('[name="delivery_country_iso_code"]').parents('.form-group').show();
                    }
                });
            }

            orderField.trigger('change');
        }
    }

    /**
     * Mass request GLS for selected parcels
     */
    jQuery('body').on('click', '[data-action="mass_request_gls"]', function (e) {
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
});