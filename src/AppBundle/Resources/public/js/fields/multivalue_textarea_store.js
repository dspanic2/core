jQuery(document).ready(function ($) {
    $(document).on("click", '[data-action="multivalue_new_item"]', function (e) {
        e.preventDefault();

        var newItemBtn = $(e.target);
        var lastItem = newItemBtn.parent().find(".multivalue-item:last-of-type");
        var index = lastItem.index() + 2;
        var groupIndex = $(this).parents(".multivalue-group").index() + 1;
        var newName = lastItem.find("input").data("name") + "[" + groupIndex + "][items]["+index+"]";

        var clonedLast = lastItem.clone();
        clonedLast.find("input").val("");
        clonedLast.find("input").data("index", index);
        clonedLast.find("input").attr("name", newName);

        newItemBtn.before(clonedLast);
    });

    $(document).on("click", '[data-action="multivalue_new_group"]', function (e) {
        e.preventDefault();

        var newGroupBtn = $(e.target);
        var lastItem = newGroupBtn.parent().find(".multivalue-group:last-of-type");
        var index = lastItem.find("input.group-title").data("index") + 1;

        var clonedLast = lastItem.clone();
        clonedLast.find("input").val("");
        clonedLast.find(".multivalue-item:not(:first-of-type)").remove();

        clonedLast.find("input.group-title").data("index", index);
        clonedLast.find("input.group-title").attr("name", clonedLast.find("input.group-title").data("name") + "[" + index + "]" + "[title]");
        clonedLast.find("input:not(.group-title)").attr("name", clonedLast.find("input.group-title").data("name") + "[" + index + "]" + "[items][1]");

        newGroupBtn.before(clonedLast);
    });
});
