jQuery(document).ready(function () {

    if (jQuery('.sidebar').length > 0) {
        jQuery(".main-content").addClass("sidebar-closed");
    }

    jQuery('.side-bar-link').on('click', function (e) {
        e.preventDefault();
        var offset = 0;
        var target = this.hash;
        if (jQuery(this).data('offset') != undefined) offset = jQuery(this).data('offset');
        jQuery('html, body').stop().animate({
            'scrollTop': jQuery(target).offset().top - offset
        }, 500, 'swing', function () {
            // window.location.hash = target;
        });
    });

});


/* Set the width of the sidebar to 250px and the left margin of the page content to 250px */
function toggleNav() {
    var $sidebar = jQuery(".sidebar");
    var $mainContent = jQuery(".main-content");
    var $sidebarToggleBtn = jQuery(".sidebar-toggle-btn");

    if ($sidebar.hasClass("sidebar-closed")) {
        $sidebarToggleBtn.html("<i class='fa fa-chevron-left' aria-hidden='true'></i>");
        $sidebar.removeClass("sidebar-closed");
        $sidebar.addClass("sidebar-opened");
        $mainContent.removeClass("sidebar-closed");
        $mainContent.addClass("sidebar-opened");
    } else {
        $sidebarToggleBtn.html("<i class='fa fa-chevron-right' aria-hidden='true'></i>");
        $sidebar.addClass("sidebar-closed");
        $sidebar.removeClass("sidebar-opened");
        $mainContent.addClass("sidebar-closed");
        $mainContent.removeClass("sidebar-opened");
    }
}
