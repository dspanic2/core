jQuery.extend({
    handleHideSameAttributesCompare: function (showAll) {
        if (showAll) {
            $("table.product-compare-table").find('tr.product-attribute').show();
        } else {
            $("table.product-compare-table").find('tr.product-attribute').each(function () {
                var row = $(this);
                var cells = row.find('td');
                var hideCell = true;
                var i;
                for (i = 1; i < cells.length - 1; i++) {
                    var firstCellText = $("<div/>").html(cells[i].innerHTML).text();
                    var j;
                    for (j = 1; j < cells.length - 1; j++) {
                        // Prevent comparison to itself.
                        if (i == j) {
                            continue;
                        }
                        var secondCellText = $("<div/>").html(cells[j].innerHTML).text();
                        if (firstCellText.toLowerCase() != secondCellText.toLowerCase()) {
                            hideCell = false;
                            break;
                        }
                    }
                    if (hideCell) {
                        row.hide();
                        break;
                    }
                }
            });
        }
    },
});
jQuery(document).ready(function ($) {
    if ($('.sp-block-outer.compare-products').length) {
        var productSearchInput = $('[name="product_search"]');

        $(document).on('click', '.compare-add-icon', function () {
            productSearchInput.focus();
        });

        $(document).on('click', '.remove-from-compare', function () {
            $.productRemoveFromCompare($(this));
            window.location.reload();
        });

        $(document).on('click', '.compare-hide-same-attributes', function () {
            $(this).toggleClass('active');
            $.handleHideSameAttributesCompare(!$(this).hasClass('active'));
        });

        $(document).on('click', '.compare-remove-all-products', function () {
            $.confirm({
                title: translations.remove_all_compare,
                content: '',
                buttons: {
                    confirm: {
                        text: 'Da',
                        btnClass: 'button btn-type-3',
                        keys: ['enter'],
                        action: function () {
                            $("table.product-compare-table").find('.remove-from-compare').each(function () {
                                $.productRemoveFromCompare($(this));
                            });
                            window.location.reload();
                        }
                    },
                    cancel: {
                        text: 'Ne',
                        btnClass: 'button btn-type-2',
                        action: function () {
                        }
                    }
                }
            });
        });

        $(document).on('click', '.results-autocomplete-wrapper.autocomplete-comparison .autocomplete-link', function (event) {
            event.preventDefault();
            if ($(this).data("pid")) {
                $.productAddToCompare($(this).data('pid'), false, 1);
            } else {
                $.productAddToCompare($(this).parent().data('pid'), false, 1);
            }
        });
    }
});
