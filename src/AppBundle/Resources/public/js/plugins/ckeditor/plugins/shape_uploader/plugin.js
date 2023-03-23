CKEDITOR.plugins.add('shape_uploader', {
    icons: 'shapeuploader',
    allowedContent: 'img[alt,!src,width,height,data-width,data-height]{border-style,border-width,float,height,margin‌​,margin-bottom,margi‌​n-left,margin-right,‌​margin-top,width}',
    init: function (editor) {
        editor.addCommand('shape_upload', {
            exec: function (editor) {
                a = document.createElement('input');
                a.setAttribute('type', 'file');
                a.click();
                a.onchange = function () {
                    file = a.files[0];
                    editor_name = editor.name;

                    if (editor.config.uploaderAllowedExtensions.indexOf(file.name.split('.').slice(-1)[0]) !== -1) {
                        $(CKEDITOR.instances[editor_name]).trigger('enableFormSubmit');
                        curr = CKEDITOR.instances[editor_name];
                        if (file.size > editor.config.uploaderMaxSize * 1000000) {
                            loaderElem.remove();
                            setError(editor_name, "Size exceeded! Upload size must not be greater than " + editor.config.uploaderMaxSize + "MB.");
                            $(CKEDITOR.instances[editor_name]).trigger('enableFormSubmit');
                        } else if (['jpeg', 'jpg', 'png', 'svg', 'gif', 'tif', 'svg+xml', 'webp'].indexOf(file.type.split('/')[1]) !== -1) {
                            // is image
                            CKEDITOR.instances[editor_name].setReadOnly(true);

                            img = new Image();
                            img.onload = function () {
                                inputWidth = this.width;
                                inputHeight = this.height;
                            };
                            img.src = window.URL.createObjectURL(file);

                            formData = new FormData;
                            formData.append('file', file);

                            loaderElem = createLoader("Please wait while image is uploading...");
                            editor.insertElement(loaderElem);

                            $.ajax({
                                url: editor.config.uploaderUploadURL,
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false
                            }).done(function (result) {
                                if (result.error === false) {
                                    CKEDITOR.instances[editor_name].setReadOnly(false);
                                    maxWidth = Math.min(inputWidth, 600);
                                    maxHeight = Math.min(inputHeight, 600);
                                    if ((maxWidth / maxHeight) > (inputWidth / inputHeight)) {
                                        width = (maxWidth * inputWidth) / inputHeight;
                                        height = maxHeight;
                                    } else if ((maxWidth / maxHeight) < (inputWidth / inputHeight)) {
                                        width = maxWidth;
                                        height = (maxHeight * inputHeight) / inputWidth;
                                    } else {
                                        width = maxWidth;
                                        height = maxHeight;
                                    }
                                    // newLine = CKEDITOR.dom.element.createFromHtml('<p><br></p>');
                                    imgElem = '<img src="' + result.src + '" class="image-editor" data-width="' + inputWidth + '" data-height="' + inputHeight + '">';
                                    imgDomElem = CKEDITOR.dom.element.createFromHtml(imgElem);
                                    editor.insertElement(imgDomElem);
                                    loaderElem.remove();
                                    $(CKEDITOR.instances[editor_name]).trigger('enableFormSubmit');
                                } else {
                                    loaderElem.remove();
                                    setError(editor_name, "Image upload failed! Please try again!");
                                }
                            });
                        } else {
                            // Other document
                            // Show as link with both link and text as path
                            CKEDITOR.instances[editor_name].setReadOnly(true);

                            formData = new FormData;
                            formData.append('file', file);

                            $.ajax({
                                url: editor.config.uploaderUploadURL,
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false
                            }).done(function (result) {
                                if (result.error === false) {
                                    CKEDITOR.instances[editor_name].setReadOnly(false);
                                    var selected_text = editor.getSelection().getSelectedText();
                                    if (selected_text.length > 0) {
                                        var newElement = new CKEDITOR.dom.element('a');
                                        newElement.setAttributes({href: result.src, class: "file-editor"});
                                        newElement.setText(selected_text);
                                        editor.insertElement(newElement);
                                    } else {
                                        imgElem = '<a href="' + result.src + '" class="file-editor">' + result.src + '</a>';
                                        imgDomElem = CKEDITOR.dom.element.createFromHtml(imgElem);
                                        editor.insertElement(imgDomElem);
                                    }

                                    $(CKEDITOR.instances[editor_name]).trigger('enableFormSubmit');
                                } else {
                                    loaderElem.remove();
                                    setError(editor, "File upload failed! Please try again!");
                                }
                            });
                        }
                    } else {
                        setError(editor_name, "Invalid file! Allowed extensions are: " + editor.config.uploaderAllowedExtensions.join());
                    }
                };
            }
        });

        editor.ui.addButton('Shape Upload', {
            label: 'Shipshape Uploader',
            command: 'shape_upload',
            toolbar: 'insert',
            icon: this.path + 'icons/shapeuploader.png'
        });
    }
});

function dataParser(data) {
    if (!data.error) {
        return data.src;
    }
}

function createLoader(text) {
    loaderElem = new CKEDITOR.dom.element('loader-elem');
    loaderHtmlStr = '<div style="position: relative; z-index: 100;width: 100%;height: 100%;text-align: center;background: white;opacity: 0.75;pointer-events:none"><div style="width: 100%;height: 30px;margin-top: 100px;">' + text + '</div></div>';
    loaderDomEle = CKEDITOR.dom.element.createFromHtml(loaderHtmlStr);
    loaderElem.append(loaderDomEle);
    return loaderElem;
}

function setError(editor, text) {
    alert(text);
}