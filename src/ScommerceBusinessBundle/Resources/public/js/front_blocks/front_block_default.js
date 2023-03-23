jQuery(document).ready(function() {

    // ADMIN

    /** Show blocks */
    if(jQuery('[data-type="s_front_block"]').length > 0 && jQuery('[data-type="s_front_block"]').find('[name="type"]').val()){
        var block_type = jQuery('[data-type="s_front_block"]').find('[name="type"]').val();

        if(jQuery('[data-show-type="true"]').length > 0){
            jQuery('[data-show-type="true"]').each(function(e){
                var show_block_types = jQuery(this).data('for-type');
                if(show_block_types.length > 0){
                    if(jQuery.inArray(block_type, show_block_types) > -1){
                        jQuery(this).removeClass('hidden');
                    }
                }
            });
        }
    }

});