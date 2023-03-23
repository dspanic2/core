jQuery(document).ready(function() {

    // ADMIN

    // FRONTEND
    var accountCreationWrapper = jQuery(".sp-account-creation-wrapper");
    if(accountCreationWrapper.length) {
        var searchElem = null;
        var search_url = accountCreationWrapper.find('form.account-creation-form').data("url");
        accountCreationWrapper.find('#acc_search').select2({
            ajax: {
                allowClear: true,
                url: search_url,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    searchElem = jQuery(this);
                    return {
                        q: params.term, // search term
                        code: jQuery(this).attr('name')
                    };
                },
                processResults: function (data, params) {
                    data.ret.unshift({
                        'id': 'new',
                        'text': '<i class="fa fa-plus"></i>&nbsp; Create new',
                        'entity': null
                    });
                    return {
                        results: jQuery.map(data.ret, function (item) {
                            return {
                                id: item.id,
                                text: item.text,
                                entity: item.entity
                            }
                        })
                    };
                },
                cache: true
            },
            templateResult: function select2FormatResult(item) {
                return "<li data-id='" + item.id + "' data-entity='" + item.entity + "' class='sp-select-2-result'>" + item.text + "<li>";
            },
            placeholder: 'Search for account',
            escapeMarkup: function (markup) {
                return markup;
            },
            minimumInputLength: 1
        }).on('select2:select', function(){
            var creation_form = jQuery('.sp-account-creation-wrapper form.account-creation-form');
            if(!jQuery.isEmptyObject(searchElem.select2('data')[0].entity)){
                var entity = searchElem.select2('data')[0].entity;

                creation_form.find("input").each(function(){
                    jQuery(this).val("").prop("disabled", true);
                });

                jQuery.each(entity, function(code, value){
                    var input = creation_form.find("input[name='"+code+"']");
                    if(input.length) {
                        input.val(value);
                    }
                });
            }else{
                creation_form.find("input").each(function(){
                    jQuery(this).val("").prop("disabled", false);
                });
            }
        });
    }
});
