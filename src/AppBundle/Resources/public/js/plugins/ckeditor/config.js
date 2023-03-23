/**
 * @license Copyright (c) 2003-2017, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
    // Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';
    // config.extraPlugins = 'lineutils';
    // config.extraPlugins = 'widget';
    // config.extraPlugins = 'basewidget';
    // config.extraPlugins = 'layoutmanager';
    // config.extraPlugins = 'shape_entity_values';
    config.extraAllowedContent = '*{*}';
    config.extraPlugins = 'blockquote,shape_page_break,shape_uploader';

    // Shape uploader conf
    config.uploaderUploadURL = '/ckeditor/uploader/save';
    config.uploaderAllowedExtensions = ['jpeg','jpg','png','svg','gif','tif', 'doc','docx','pdf','xls','xlsx','zip', 'ppt', 'pptx', 'webp'];
    config.uploaderMaxSize = 50; //In MB
};
