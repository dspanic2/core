function refreshRelatedProductProductGroupList(table, result) {

    jQuery('body').find('[data-table="product_product_group_link"]').DataTable().ajax.reload(null, false);

    return false;
}