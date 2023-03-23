CKEDITOR.plugins.add( 'shape_entity_values', {
    icons: 'shape_entity_values',
    init: function( editor ) {
        editor.addCommand( 'insertEntityValues', new CKEDITOR.dialogCommand( 'insertEntityValues' ));
        editor.ui.addButton( 'Entity values', {
            label: 'Insert',
            command: 'insertEntityValues',
            toolbar: 'insert'
        });

        CKEDITOR.dialog.add( 'insertEntityValues', this.path + 'dialogs/shape_entity_values.js' );
    }
});