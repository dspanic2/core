CKEDITOR.plugins.add( 'shape_page_break', {
    icons: 'shape_page_break',
    init: function( editor ) {
        editor.addCommand( 'insertPageBreak', {
            exec: function( editor ) {
                editor.insertHtml( '<div class="page-break" style="page-break-after: always"><hr></div>' );
            }
        });
        editor.ui.addButton( 'Print - add page break', {
            label: 'Print - add page break',
            command: 'insertPageBreak',
            icon: this.path + 'icons/shape_page_break.png'
        });
    }
});