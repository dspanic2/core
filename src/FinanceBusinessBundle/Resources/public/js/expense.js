function toggleManualCurrency(state){

    if (state == true) {
        jQuery('body').find('[name="currency_rate"]').removeAttr('readonly','readonly');
    } else {
        jQuery('body').find('[name="currency_rate"]').attr('readonly','readonly');
    }

    return true;
}

jQuery(document).ready(function() {

    /** Expense page */
    if (jQuery('[data-type="expense"]').length > 0) {
        var form = jQuery('[data-type="expense"]');

        jQuery('body').on('change','[name="currency_rate"]',function (e) {
            jQuery('body').find('[name="currency_changed_checkbox"]').prop('checked', true).change();
        });

        jQuery('body').on('change','[name="currency_id"]',function (e) {
            jQuery('body').find('[name="currency_changed_checkbox"]').prop('checked', true).change();
        });

        jQuery('body').on('change','[name="date_invoice"]',function (e) {
            jQuery('body').find('[name="currency_changed_checkbox"]').prop('checked', true).change();
        });

        jQuery('body').on('change','[name="payment_due_date"]',function (e) {
            jQuery('body').find('[name="currency_changed_checkbox"]').prop('checked', true).change();
        });

        jQuery('body').on('switchChange.bootstrapSwitch', '[name="manual_currency_rate_checkbox"]', function (event, state) {
            toggleManualCurrency(state);
            jQuery('body').find('[name="currency_changed_checkbox"]').prop('checked', true).change();
        });

        jQuery('body').on('change','[name="expense_type_id"]',function (e) {
            jQuery('body').find('[name="cron_recalculate_checkbox"]').prop('checked', true).change();
        });

        jQuery('body').on('change','[name="expense_category_id"]',function (e) {
            jQuery('body').find('[name="cron_recalculate_checkbox"]').prop('checked', true).change();
        });

        toggleManualCurrency(jQuery('body').find('[name="manual_currency_rate_checkbox"]').bootstrapSwitch('state'));
    }

    if(jQuery('[data-type="outbound_payment"]').length > 0) {

        jQuery('body').on('change','[name="currency_rate"]',function (e) {
            jQuery('body').find('[name="currency_changed_checkbox"]').prop('checked', true).change();
        });

        jQuery('body').on('change','[name="currency_id"]',function (e) {
            jQuery('body').find('[name="currency_changed_checkbox"]').prop('checked', true).change();
        });

        jQuery('body').on('change','[name="payment_date"]',function (e) {
            jQuery('body').find('[name="currency_changed_checkbox"]').prop('checked', true).change();
        });

        jQuery('body').on('switchChange.bootstrapSwitch', '[name="manual_currency_rate_checkbox"]', function (event, state) {
            toggleManualCurrency(state);
            jQuery('body').find('[name="currency_changed_checkbox"]').prop('checked', true).change();
        });

        toggleManualCurrency(jQuery('body').find('[name="manual_currency_rate_checkbox"]').bootstrapSwitch('state'));
    }

});
