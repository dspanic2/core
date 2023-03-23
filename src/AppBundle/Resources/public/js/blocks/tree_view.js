/**
 * Callback or on page load
 * @param table
 * @param result
 * @returns {boolean}
 */
function renderTreeView(table, result) {

    let treeview_div = jQuery('#treeview');
    if (treeview_div.length > 0) {
        let empty_content = treeview_div.siblings('.sp-empty-list-content');
        let json_data = treeview_div.data('content');
        if (json_data !== '') {
            empty_content.addClass('hidden');
            treeview_div.treeview({
                data: json_data,
                enableLinks: true
            });
        } else {
            empty_content.removeClass('hidden');
            treeview_div.addClass('hidden');
        }
    }

    return false;
}

jQuery(document).ready(function() {
    renderTreeView(null, null);
});