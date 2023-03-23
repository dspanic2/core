$.extend({
    initializeNotesCkeditor: function () {
        if (jQuery('[data-type="ckeditor-note"]').length > 0) {
            jQuery('[data-type="ckeditor-note"]').each(function (e) {
                initializeElementCkeditor(jQuery(this), [
                    'Paste',
                    'Bold',
                    'Italic',
                    'Underline',
                    'NumberedList',
                    'BulletedList',
                    'JustifyLeft',
                    'JustifyCenter',
                    'JustifyRight',
                    'JustifyBlock',
                    'Shape Upload',
                    'Link',
                ], true);
            });
        }
    },
});

jQuery(document).ready(function () {
    jQuery(document).on('click', '[data-action="save-note"]', function (e) {
        e.stopPropagation();
        var elem = jQuery(this);
        var wrapper = elem.parents('.new-note');

        // Convert pasted images
        if (wrapper.find('[name="note"]').val().indexOf('src="data:image') != -1) {
            wrapper.find('[name="note"]').val(jQuery.ckeditorConvertPastedImages(wrapper.find('[name="note"]').val()));
        }

        var notes_content = wrapper.find('[name="note"]').val();
        var note_id = elem.data('note-id');
        if (notes_content !== '') {
            jQuery.post(elem.data('url'), {
                comment: notes_content,
                related_entity_type: elem.data('related-type'),
                related_entity_id: elem.data('related-id'),
                user_id: elem.data('uid'),
                id: note_id
            }, function (result) {
                if (result.error == false) {
                    // display new note inside container
                    var html = jQuery.parseHTML(result.html);
                    jQuery(html).addClass('slide-left').on('animationend', function () {
                        jQuery(html).removeClass('slide-left');
                    });
                    if (note_id != '') {
                        var recent_note = wrapper.parents(".sp-block-wrapper").find('.recent-note');
                        if (recent_note !== '') {
                            var note = recent_note.find('#note_' + note_id);
                            note.replaceWith(html);
                        }
                    } else {
                        var empty_list = wrapper.parents(".sp-block-wrapper").find('.sp-empty-list-content');
                        //jQuery(empty_list).fadeOut('slow', function() {
                        empty_list.addClass('hidden');
                        //});
                        wrapper.parents(".sp-block-wrapper").find('.recent-note').append(html);
                    }
                    CKEDITOR.instances['form-note'].setData('');
                    elem.siblings('.note-info').addClass('hidden');
                    elem.data('note-id', '');
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, 'json');
        } else {
            jQuery.growl.error({
                title: translations.error_message,
                message: translations.error_message
            });
        }
    });

    jQuery(document).on('click', '[data-action="delete-note"]', function (e) {
        e.stopPropagation();
        var wrapper = jQuery(this).parents('.note');
        if (wrapper !== '') {
            var note_id = wrapper.find('[name="note_id"]').val();
            jQuery.post(jQuery(this).data('url'), {
                id: note_id
            }, function (result) {
                if (result.error === false) {
                    var recent_notes = wrapper.parent();
                    wrapper.addClass('slide-right').on('animationend', function () {
                        wrapper.removeClass('slide-right').remove();
                        if (jQuery.trim(recent_notes.text()).length === 0) {
                            var empty_list = recent_notes.siblings('.sp-empty-list-content');
                            //jQuery(empty_list).fadeIn('slow', function() {
                            empty_list.removeClass('hidden');
                            //});
                        }
                    });
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, 'json');
        } else {
            jQuery.growl.error({
                title: translations.error_message,
                message: translations.error_message
            });
        }
    });

    jQuery(document).on('click', '[data-action="toggle-like"]', function (e) {
        e.stopPropagation();
        var elem = jQuery(this);
        var wrapper = elem.parents('.note-footer');
        if (wrapper !== '') {
            var note_id = wrapper.find('[name="note_id"]').val();
            var count_elem = wrapper.find('.note-likes');
            var count = parseInt(count_elem.text());
            if (count_elem.text() === '') {
                count = 0;
            }
            jQuery.post(elem.data('url'), {
                id: note_id
            }, function (result) {
                if (result.error === false) {
                    if (elem.hasClass('note-like-active') && count_elem.hasClass('note-like-active')) {
                        // make inactive
                        elem.removeClass('note-like-active');
                        count_elem.removeClass('note-like-active');
                        // decrease like count
                        count_elem.text(count > 1 ? count - 1 : '');
                    } else {
                        // make active
                        elem.addClass('note-like-active');
                        count_elem.addClass('note-like-active');
                        // increase like count
                        count_elem.text(count + 1);
                    }
                    if (result.content !== '') {
                        elem.attr('data-content', result.content);
                    }
                    // ovdje bi trebalo refreshat tooltip okidac
                } else {
                    jQuery.growl.error({
                        title: translations.error_message,
                        message: result.message
                    });
                }
            }, 'json');
        } else {
            jQuery.growl.error({
                title: translations.error_message,
                message: translations.error_message
            });
        }
    });

    jQuery(document).on('click', '[data-action="edit-note"]', function (e) {
        e.stopPropagation();
        var wrapper = jQuery(this).parents('.note');
        var block_wrapper = jQuery(this).parents('.sp-block');
        var comment = wrapper.find('.note-body').html();
        CKEDITOR.instances['form-note'].setData(comment);
        var user = wrapper.find('.note-user').text();
        var date = wrapper.find('.note-date').text();
        var note_id = wrapper.find('[name="note_id"]').val();
        var form_wrapper = block_wrapper.find('.new-note .form-group');
        form_wrapper.find('.note-user').text(user);
        form_wrapper.find('.note-date').text(date);
        form_wrapper.find('.note-info').removeClass('hidden');
        form_wrapper.find('[data-action="save-note"]').data('note-id', note_id);
    });

    jQuery(document).on('click', '[data-action="cancel-note"]', function (e) {
        e.stopPropagation();
        var elem = jQuery(this);
        elem.siblings('.note-info').addClass('hidden');
        var button = elem.siblings('[data-action="save-note"]');
        button.data('note-id', '');
        CKEDITOR.instances['form-note'].setData('');
    });

    $.initializeNotesCkeditor();
});
