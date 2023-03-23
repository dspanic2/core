CKEDITOR.dialog.add( 'insertEntityValues', function ( editor ) {

    var types = getEntityAttributes();

    return {
        title: 'Abbreviation Properties',
        minWidth: 400,
        minHeight: 200,
        contents: [
            {
                id: 'tab-basic',
                label: 'Basic Settings',
                elements: [
                    {
                        type: 'html',
                        html: '<div id="entity-attribute" data-value=""><label style="display: block">Select entity attribute</label><select data-type="lead" style="display: block;" onchange="getEntityAttributeValue(this)" class="cke_dialog_ui_input_select"><option value="">Please select</option>'+types+'</select></div>'
                    }
                ]
            }
        ],
        onOk: function() {
            var entity_value = editor.document.createElement( 'span' );
            entity_value.setText(jQuery("#entity-attribute").data("value"));
            editor.insertElement( entity_value );
        }
    };
});

function getEntityAttributes() {
    var tmpid = 1; //get real id
    var tmpentity_type = "lead"; //get real entity_type
    jQuery.post("/ckeditor/get-entity-attributes", { }, function(result) {
        if(result.error == false){
            var items = '<option value="">Please select</option>';
            jQuery.each(result.attributes, function(key,val){
                items += '<option value="'+key+'">'+val+'</option>';
            });
            jQuery("#entity-attribute").find("select").html(items);
        }
        else{
            jQuery.growl.error({
                title: translations.error_message,
                message: result.message
            });
        }
    }, "json");
}

function getEntityAttributeValue(select) {
    var tmpid = 1; //get real id
    var tmpentity_type = jQuery(select).data("type"); //get real entity_type
    var attr_code = select.value;
    console.log(attr_code);
    jQuery.post("/ckeditor/get-entity-attribute-value", { attr_code: attr_code, entity_type: tmpentity_type, entity_id: tmpid }, function(result) {
        console.log(result);
        if(result.error == false){
            if(typeof result.value !== 'undefined'){
                jQuery(select).data("value", result.value);
            }else if(typeof result.attributes !== 'undefined'){
                var select_new = $("<select></select>").attr("class", "cke_dialog_ui_input_select").attr("onchange", "getEntityAttributeValue(this)").data("type", result.type).css("display", "block");
                jQuery.each(result.attributes, function(key,val){
                    select_new.append($("<option></option>").attr("value", key).text(val));
                });
                jQuery(select).after(select_new);
                console.log(select_new);
            }
        }
        else{
            jQuery.growl.error({
                title: translations.error_message,
                message: result.message
            });
        }
    }, "json");
}
