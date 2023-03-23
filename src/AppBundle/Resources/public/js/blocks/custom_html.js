jQuery(document).ready(function() {

    // ADMIN
    jQuery(window).on('shown.bs.modal', function() {
        var code_editor = jQuery("#code-editor");
        if(code_editor.length){
            console.log("CONVERT TO CODE EDITOR");
        }
    });

    // FRONTEND
    var custom_html = jQuery('.sp-custom-html-wrapper');
    if(custom_html.length > 0){

    }
});
