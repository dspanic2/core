function disableEditApprovedAbsence() {
    var form = jQuery('[data-type="absence"]');

    if (form.find('[name="approved"]').val() == 1) {
        jQuery('.sp-main-actions-wrapper').find('[type="submit"]').remove();
        form.find('input:not(readonly)').attr('readonly', 'readonly');
        form.find('select:not(readonly)').attr('readonly', 'readonly');
        form.find('textarea:not(readonly)').attr('readonly', 'readonly');
        form.find('[name="absence_type_id"]').select2('destroy');
        form.find('[data-type="datetimesingle"]').data('daterangepicker').remove();
    }
}

jQuery(document).ready(function() {

    /**
     * Disable edit approved absence
     */
    if (jQuery('[data-type="absence"]').length > 0) {
        disableEditApprovedAbsence();
    }

    /**
     * Add new absence
     */
    jQuery('[data-action="open_new_absence"]').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        jQuery.post(jQuery(this).data('url'), {}, function (result) {
            if (result.error == false) {
                var clone = jQuery('#modal-container').clone(true, true).appendTo(jQuery('#page-content > div'));
                clone.html(result.html);
                var modal = clone.find('.modal');
                modal.modal('show');

                var form = modal.find('[data-validate="true"]');
                form.initializeValidation();
                form.forceBoostrapXs();
            }
            else {
                jQuery.growl.error({
                    title: translations.error_message,
                    message: result.message
                });
            }
        }, "json");
    });
    /**
     * End absence
     */

});