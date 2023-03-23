jQuery(document).ready(function ($) {
    var discountCouponForm = $('form[data-type="discount_coupon"]');

    if (discountCouponForm.length > 0) {

        var id = discountCouponForm.find('[name="id"]').val();

        /**
         * @deprecated
         */
        /*var handleDiscountStates = function () {
            var discountValue = parseInt(discountCouponForm.find('[name="discount_coupon_type_id"]').val());
            // 1	Po proizvodima
            // 2	Po grupama proizvoda
            // 3	Po proizvodima i kupcu
            // 4	Po grupama proizvoda i kupcu
            // 5	Po proizvodima i grupi kupaca
            // 6	Po grupi proizvoda i grupama kupaca
            // 7	Sve
            switch (discountValue) {
                case 1:
                    discountCouponForm.formValidation('enableFieldValidators', 'products[]', true, null);
                    discountCouponForm.find('[data-form-group="products"]').show();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_groups[]', false, null);
                    discountCouponForm.find('[data-form-group="account_groups"]').hide();
                    discountCouponForm.find('[name="account_groups[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_id', false, null);
                    discountCouponForm.find('[data-form-group="account_id"]').hide();
                    discountCouponForm.find('[name="account_id"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'product_groups[]', false, null);
                    discountCouponForm.find('[data-form-group="product_groups"]').hide();
                    discountCouponForm.find('[name="product_groups[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'brands[]', false, null);
                    discountCouponForm.find('[data-form-group="brands"]').hide();
                    discountCouponForm.find('[name="brands[]"]').val('').change();

                    discountCouponForm.find('[data-form-group="number_of_usage_per_customer"]').show();
                    if(!id){
                        discountCouponForm.find('[name="number_of_usage_per_customer"]').val(-1);
                    }
                    break;
                case 2:
                    discountCouponForm.formValidation('enableFieldValidators', 'products[]', false, null);
                    discountCouponForm.find('[data-form-group="products"]').hide();
                    discountCouponForm.find('[name="products[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_groups[]', false, null);
                    discountCouponForm.find('[data-form-group="account_groups"]').hide();
                    discountCouponForm.find('[name="account_groups[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_id', false, null);
                    discountCouponForm.find('[data-form-group="account_id"]').hide();
                    discountCouponForm.find('[name="account_id"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'product_groups[]', true, null);
                    discountCouponForm.find('[data-form-group="product_groups"]').show();

                    discountCouponForm.formValidation('enableFieldValidators', 'brands[]', false, null);
                    discountCouponForm.find('[data-form-group="brands"]').hide();
                    discountCouponForm.find('[name="brands[]"]').val('').change();

                    discountCouponForm.find('[data-form-group="number_of_usage_per_customer"]').show();
                    if(!id){
                        discountCouponForm.find('[name="number_of_usage_per_customer"]').val(-1);
                    }
                    break;
                case 3:
                    discountCouponForm.formValidation('enableFieldValidators', 'products[]', true, null);
                    discountCouponForm.find('[data-form-group="products"]').show();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_groups[]', false, null);
                    discountCouponForm.find('[data-form-group="account_groups"]').hide();
                    discountCouponForm.find('[name="account_groups[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_id', true, null);
                    discountCouponForm.find('[data-form-group="account_id"]').show();

                    discountCouponForm.formValidation('enableFieldValidators', 'product_groups[]', false, null);
                    discountCouponForm.find('[data-form-group="product_groups"]').hide();
                    discountCouponForm.find('[name="product_groups[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'brands[]', false, null);
                    discountCouponForm.find('[data-form-group="brands"]').hide();
                    discountCouponForm.find('[name="brands[]"]').val('').change();

                    discountCouponForm.find('[data-form-group="number_of_usage_per_customer"]').show();
                    if(!id) {
                        discountCouponForm.find('[name="number_of_usage_per_customer"]').val(1);
                    }
                    break;
                case 4:
                    discountCouponForm.formValidation('enableFieldValidators', 'products[]', false, null);
                    discountCouponForm.find('[data-form-group="products"]').hide();
                    discountCouponForm.find('[name="products[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_groups[]', false, null);
                    discountCouponForm.find('[data-form-group="account_groups"]').hide();
                    discountCouponForm.find('[name="account_groups[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_id', true, null);
                    discountCouponForm.find('[data-form-group="account_id"]').show();

                    discountCouponForm.formValidation('enableFieldValidators', 'product_groups[]', true, null);
                    discountCouponForm.find('[data-form-group="product_groups"]').show();

                    discountCouponForm.formValidation('enableFieldValidators', 'brands[]', false, null);
                    discountCouponForm.find('[data-form-group="brands"]').hide();
                    discountCouponForm.find('[name="brands[]"]').val('').change();

                    discountCouponForm.find('[data-form-group="number_of_usage_per_customer"]').show();
                    if(!id) {
                        discountCouponForm.find('[name="number_of_usage_per_customer"]').val(1);
                    }
                    break;
                case 5:
                    discountCouponForm.formValidation('enableFieldValidators', 'products[]', true, null);
                    discountCouponForm.find('[data-form-group="products"]').show();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_groups[]', true, null);
                    discountCouponForm.find('[data-form-group="account_groups"]').show();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_id', false, null);
                    discountCouponForm.find('[data-form-group="account_id"]').hide();
                    discountCouponForm.find('[name="account_id"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'product_groups[]', false, null);
                    discountCouponForm.find('[data-form-group="product_groups"]').hide();
                    discountCouponForm.find('[name="product_groups[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'brands[]', false, null);
                    discountCouponForm.find('[data-form-group="brands"]').hide();
                    discountCouponForm.find('[name="brands[]"]').val('').change();

                    discountCouponForm.find('[data-form-group="number_of_usage_per_customer"]').show();
                    if(!id) {
                        discountCouponForm.find('[name="number_of_usage_per_customer"]').val(1);
                    }
                    break;
                case 6:
                    discountCouponForm.formValidation('enableFieldValidators', 'products[]', false, null);
                    discountCouponForm.find('[data-form-group="products"]').hide();
                    discountCouponForm.find('[name="products[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_groups[]', true, null);
                    discountCouponForm.find('[data-form-group="account_groups"]').show();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_id', false, null);
                    discountCouponForm.find('[data-form-group="account_id"]').hide();
                    discountCouponForm.find('[name="account_id"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'product_groups[]', true, null);
                    discountCouponForm.find('[data-form-group="product_groups"]').show();

                    discountCouponForm.formValidation('enableFieldValidators', 'brands[]', false, null);
                    discountCouponForm.find('[data-form-group="brands"]').hide();
                    discountCouponForm.find('[name="brands[]"]').val('').change();

                    discountCouponForm.find('[data-form-group="number_of_usage_per_customer"]').show();
                    if(!id) {
                        discountCouponForm.find('[name="number_of_usage_per_customer"]').val(1);
                    }
                    break;
                case 8:
                    discountCouponForm.formValidation('enableFieldValidators', 'products[]', false, null);
                    discountCouponForm.find('[data-form-group="products"]').hide();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_groups[]', true, null);
                    discountCouponForm.find('[data-form-group="account_groups"]').show();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_id', false, null);
                    discountCouponForm.find('[data-form-group="account_id"]').hide();
                    discountCouponForm.find('[name="account_id"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'product_groups[]', false, null);
                    discountCouponForm.find('[data-form-group="product_groups"]').hide();
                    discountCouponForm.find('[name="product_groups[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'brands[]', false, null);
                    discountCouponForm.find('[data-form-group="brands"]').hide();
                    discountCouponForm.find('[name="brands[]"]').val('').change();

                    discountCouponForm.find('[data-form-group="number_of_usage_per_customer"]').show();
                    if(!id) {
                        discountCouponForm.find('[name="number_of_usage_per_customer"]').val(1);
                    }
                    break;
                case 9:
                    discountCouponForm.formValidation('enableFieldValidators', 'brands[]', true, null);
                    discountCouponForm.find('[data-form-group="brands"]').show();

                    discountCouponForm.formValidation('enableFieldValidators', 'products[]', false, null);
                    discountCouponForm.find('[data-form-group="products"]').hide();
                    discountCouponForm.find('[name="products[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_groups[]', false, null);
                    discountCouponForm.find('[data-form-group="account_groups"]').hide();
                    discountCouponForm.find('[name="account_groups[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_id', false, null);
                    discountCouponForm.find('[data-form-group="account_id"]').hide();
                    discountCouponForm.find('[name="account_id"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'product_groups[]', false, null);
                    discountCouponForm.find('[data-form-group="product_groups"]').hide();
                    discountCouponForm.find('[name="product_groups[]"]').val('').change();

                    discountCouponForm.find('[data-form-group="number_of_usage_per_customer"]').show();
                    if(!id){
                        discountCouponForm.find('[name="number_of_usage_per_customer"]').val(1);
                    }
                    break;

                default:
                    discountCouponForm.formValidation('enableFieldValidators', 'brands[]', false, null);
                    discountCouponForm.find('[data-form-group="brands"]').hide();
                    discountCouponForm.find('[name="brands[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'products[]', false, null);
                    discountCouponForm.find('[data-form-group="products"]').hide();
                    discountCouponForm.find('[name="products[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_groups[]', false, null);
                    discountCouponForm.find('[data-form-group="account_groups"]').hide();
                    discountCouponForm.find('[name="account_groups[]"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'account_id', false, null);
                    discountCouponForm.find('[data-form-group="account_id"]').hide();
                    discountCouponForm.find('[name="account_id"]').val('').change();

                    discountCouponForm.formValidation('enableFieldValidators', 'product_groups[]', false, null);
                    discountCouponForm.find('[data-form-group="product_groups"]').hide();
                    discountCouponForm.find('[name="product_groups[]"]').val('').change();

                    discountCouponForm.find('[data-form-group="number_of_usage_per_customer"]').show();
                    if(!id) {
                        discountCouponForm.find('[name="number_of_usage_per_customer"]').val(1);
                    }
                    break;
            }
        };*/

        /**
         * @deprecated
         */
        /*handleDiscountStates();

        $('[name="discount_coupon_type_id"]').on('change', function () {
            handleDiscountStates();
        });*/

        jQuery('body').on('switchChange.bootstrapSwitch', '[name="is_template_checkbox"]', function (event, state) {
            changeDiscountCouponIsTemplate(state);
        });
        changeDiscountCouponIsTemplate(discountCouponForm.find('[name="is_template_checkbox"]').bootstrapSwitch('state'));

        jQuery('body').on('switchChange.bootstrapSwitch', '[name="is_fixed_checkbox"]', function (event, state) {
            changeDiscountIsFixed(state);
        });
        changeDiscountIsFixed(discountCouponForm.find('[name="is_fixed_checkbox"]').bootstrapSwitch('state'));

        jQuery('body').on('switchChange.bootstrapSwitch', '[name="allow_on_discount_products_checkbox"]', function (event, state) {
            changeAllowOnDiscountProducts(state);
        });
        changeAllowOnDiscountProducts(discountCouponForm.find('[name="allow_on_discount_products_checkbox"]').bootstrapSwitch('state'));
    }
});

function changeDiscountCouponIsTemplate(state) {

    var discountCouponForm = $('form[data-type="discount_coupon"]');

    if(state){
        discountCouponForm.formValidation('enableFieldValidators', 'coupon_code', false, null);
        discountCouponForm.find('[data-form-group="coupon_code"]').hide();
        discountCouponForm.find('[name="coupon_code"]').val('');
        discountCouponForm.find('[data-form-group="template_code"]').show();
        discountCouponForm.find('[data-form-group="email"]').hide();
    }
    else{
        discountCouponForm.formValidation('enableFieldValidators', 'coupon_code', true, null);
        discountCouponForm.find('[data-form-group="coupon_code"]').show();
        discountCouponForm.find('[data-form-group="template_code"]').hide();
        discountCouponForm.find('[data-form-group="email"]').show();
    }

    return true;
}

function changeAllowOnDiscountProducts(state) {

    var discountCouponForm = $('form[data-type="discount_coupon"]');

    if(state){
        discountCouponForm.formValidation('enableFieldValidators', 'application_type_id', true, null);
        discountCouponForm.find('[data-form-group="application_type_id"]').show();
    }
    else{
        discountCouponForm.formValidation('enableFieldValidators', 'application_type_id', false, null);
        discountCouponForm.find('[data-form-group="application_type_id"]').hide();
        discountCouponForm.find('[name="application_type_id"]').val('').change();
    }

    return true;
}

function changeDiscountIsFixed(state) {

    var discountCouponForm = $('form[data-type="discount_coupon"]');

    if(state){
        discountCouponForm.formValidation('enableFieldValidators', 'discount_percent', false, null);
        discountCouponForm.find('[data-form-group="discount_percent"]').hide();
        discountCouponForm.find('[name="discount_percent"]').val('0');

        discountCouponForm.formValidation('enableFieldValidators', 'fixed_discount', true, null);
        discountCouponForm.find('[data-form-group="fixed_discount"]').show();

        /*discountCouponForm.formValidation('enableFieldValidators', 'min_cart_price', true, null);*/
        discountCouponForm.find('[data-form-group="min_cart_price"]').show();

        discountCouponForm.find('[name="allow_on_discount_products_checkbox"]').prop('checked', false).change();
        changeAllowOnDiscountProducts(false);
        discountCouponForm.find('[data-form-group="allow_on_discount_products"]').hide();
    }
    else{
        discountCouponForm.formValidation('enableFieldValidators', 'discount_percent', true, null);
        discountCouponForm.find('[data-form-group="discount_percent"]').show();

        discountCouponForm.formValidation('enableFieldValidators', 'fixed_discount', false, null);
        discountCouponForm.find('[data-form-group="fixed_discount"]').hide();
        discountCouponForm.find('[name="fixed_discount"]').val('0');

        /*discountCouponForm.formValidation('enableFieldValidators', 'min_cart_price', false, null);*/
        discountCouponForm.find('[data-form-group="min_cart_price"]').hide();
        discountCouponForm.find('[name="min_cart_price"]').val('0');

        discountCouponForm.find('[data-form-group="allow_on_discount_products"]').show();
    }

    return true;

}