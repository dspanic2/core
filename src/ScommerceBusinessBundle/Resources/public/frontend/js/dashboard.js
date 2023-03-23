$.extend({
    handleDeleteRequest: function () {
        $.confirm({
            title: translations.delete_account,
            content: '',
            buttons: {
                confirm: {
                    text: translations.yes,
                    btnClass: 'button btn-type-1',
                    keys: ['enter'],
                    action: function () {
                        $.showAjaxLoader();
                        $.ajax({
                            url: "/dashboard/anonymize",
                            method: 'POST',
                            data: {},
                            cache: false
                        }).done(function (result) {
                            if (result.error == false) {
                                $('[data-action="logout-customer"]').trigger("click");
                            } else {
                                $.hideAjaxLoader();
                                $.growl.error({
                                    title: result.title ? result.title : '',
                                    message: result.message ? result.message : translations.selection_error,
                                });
                            }
                        });
                    }
                },
                cancel: {
                    text: translations.no,
                    btnClass: 'button btn-type-2',
                    action: function () {
                    }
                }
            }
        });
    }
});
jQuery(document).ready(function ($) {
    // Order repeat
    function repeatOrder(orderID, url) {
        $.showAjaxLoader();
        $.ajax({
            url: url,
            method: 'POST',
            data: {'order_id': orderID},
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                $.growl.notice({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
                $.reloadPage();
                if (result.redirect_url) {
                    window.location.href = result.redirect_url;
                }
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    }

    $(document).on('click', '.order-repeat', function () {
        if ($(this).data("order-id")) {
            repeatOrder($(this).data("order-id"), $(this).data("url"));
        }
    });

    // Order return
    // Generate order return modal
    function returnOrderGenerateModal(orderID, url) {
        var options = {};
        options.order = orderID;
        options.items = [];

        var cartItems = $(".cart-items");
        if (cartItems.length && cartItems.find('[name="return"]').length) {
            cartItems.find('[name="return"]').each(function () {
                if ($(this).is(":checked")) {
                    options.items.push({
                        "item": $(this).data("quote-item"),
                        "return_qty": $(this).closest(".actions").find('[name="qty_return"]').val()
                    });
                }
            });
        }

        $.showAjaxLoader();
        $.ajax({
            url: url,
            method: 'POST',
            data: options,
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                $(".overlay").removeClass("active");
                var orderReturnModal = $("#order-return-modal");
                if (orderReturnModal.length == 0) {
                    $("body").append(result.html);
                    orderReturnModal = $("#order-return-modal");
                } else {
                    orderReturnModal.replaceWith(result.html);
                    orderReturnModal = $("#order-return-modal");
                }
                orderReturnModal.addClass("active");
                $("body").addClass("disable-scroll");
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    }

    // Submit order return to backend
    function returnOrderItems(url) {
        $.showAjaxLoader();
        $.ajax({
            url: url,
            method: 'POST',
            data: $("#order-return").serialize(),
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                $("#order-return-modal .inner").html(result.message);
                if (result.redirect) {
                    window.location.href = result.redirect;
                }
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    }

    // Show/hide return button
    $(document).on('change', '.cart-items [name="return"]', function () {
        if ($('.cart-items [name="return"]:checked').length > 0) {
            if ($('.order-return-items').length > 0) {
                $('.order-return-items').show();
            }
            if ($('.order-complaint-items').length > 0) {
                $('.order-complaint-items').show();
            }
            $(this).closest(".actions").find('.return-amount').show();
        } else {
            if ($('.order-return-items').length > 0) {
                $('.order-return-items').hide();
            }
            if ($('.order-complaint-items').length > 0) {
                $('.order-complaint-items').hide();
            }
            $(this).closest(".actions").find('.return-amount').hide();
        }
    });
    // Handle return order button
    $(document).on('click', '.order-return-items', function () {
        if ($(this).data("order-id")) {
            returnOrderGenerateModal($(this).data("order-id"), $(this).data("url"));
        }
    });
    // Handle return order modal submit
    $(document).on('click', '.submit-return-request', function () {
        returnOrderItems($(this).data("url"));
    });
    // Handle return order modal
    $(document).on('click', '.return-add-new-bank-account', function (e) {
        e.preventDefault();
        $(".return-new-bank-account").addClass("active");
    });
    $(document).on('click', '.return-add-new-address', function (e) {
        e.preventDefault();
        $(".return-new-address").addClass("active");
    });
    $(document).on('click', '.cancel-new-address', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $.disableOverlays();
    });

    // Generate order complaint modal
    function complaintOrderGenerateModal(orderID, url) {
        var options = {};
        options.order = orderID;
        options.items = [];

        var cartItems = $(".cart-items");
        if (cartItems.length && cartItems.find('[name="return"]').length) {
            cartItems.find('[name="return"]').each(function () {
                if ($(this).is(":checked")) {
                    options.items.push($(this).data("order-item"));
                }
            });
        }

        $.showAjaxLoader();
        $.ajax({
            url: url,
            method: 'POST',
            data: options,
            cache: false
        }).done(function (result) {
            $.hideAjaxLoader();
            if (result.error == false) {
                $(".overlay").removeClass("active");
                var orderComplaintModal = $("#order-complaint-modal");
                if (orderComplaintModal.length == 0) {
                    $("body").append(result.html);
                    orderComplaintModal = $("#order-complaint-modal");
                } else {
                    orderComplaintModal.replaceWith(result.html);
                    orderComplaintModal = $("#order-complaint-modal");
                }
                orderComplaintModal.addClass("active");
                $("body").addClass("disable-scroll");
            } else {
                $.growl.error({
                    title: result.title ? result.title : '',
                    message: result.message ? result.message : '',
                });
            }
        });
    }

    // Handle return order button
    $(document).on('click', '.order-complaint-items', function () {
        if ($(this).data("order-id")) {
            complaintOrderGenerateModal($(this).data("order-id"), $(this).data("url"));
        }
    });

    // Handle responsive dashboard menu toggle.
    $(document).on('click', '#dashboard-menu-toggle', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(this).parent().find('.dashboard-account-menu-row').slideToggle();
    });

    // Delete account
    $("#delete-account").on("click", function (e) {
        e.preventDefault();
        $.handleDeleteRequest();
    });

    // Orders filter
    var ordersTable = $(".orders-table");
    if (ordersTable.length) {
        var filterOrders = function () {
            var values = {};

            $(".date-filters").find("input, select").each(function () {
                values[$(this).attr("name")] = $(this).val();
            });
            $(".orders-table th").find("input,select").each(function () {
                values[$(this).attr("name")] = $(this).val();
            });

            $.showAjaxLoader();
            $.ajax({
                url: '/dashboard/get_filtered_orders',
                method: 'POST',
                data: values,
                cache: false
            }).done(function (result) {
                $.hideAjaxLoader();
                if (result.error == false) {
                    if (result.orders) {
                        ordersTable.find("tbody").replaceWith(result.orders);
                    }
                } else {
                    $.growl.error({
                        title: result.title ? result.title : '',
                        message: result.message ? result.message : translations.search_error,
                    });
                }
            });
        }
        ordersTable.parents(".sp-block-outer").find("input,select").each(function () {
            $(this).on('input keyup change', $.debounce(function (e) {
                filterOrders();
            }, 500));
        });

        $(document).on("click", ".reset-order-filters", function () {
            ordersTable.parents(".sp-block-outer").find("input,select").each(function () {
                $(this).val("");
            });
            filterOrders();
        });
    }
});
