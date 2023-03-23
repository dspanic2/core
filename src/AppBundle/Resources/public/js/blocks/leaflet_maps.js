jQuery(document).ready(function () {

  // ADMIN
  jQuery('body').on('change', '[data-action="leaflet_attribute_set"]', function (e) {
    var form = jQuery(this).parents('form');
    var elem = jQuery(this);

    var backendTypes = new Array();
    backendTypes.push("text");
    backendTypes.push("decimal");

    jQuery.post(elem.data("url"), {
      type: form.find('[name="type"]').val(),
      attributeSet: elem.val(),
      backendTypes: backendTypes
    }, function (result) {
      if (result.error == false) {
        form.find('[name="lat"]').html(result.html);
        form.find('[name="lng"]').html(result.html);
        form.find('[name="gmaps_title"]').html(result.html);
      } else {
        jQuery.growl.error({
          title: translations.error_message,
          message: result.message
        });
      }
    }, "json");
  });

  // FRONTEND
  jQuery(window).on("load", function () {
    if (jQuery('body').find('.leaflet-wrapper').length) {
      var initMap = function (mapWrapper) {
        var data = mapWrapper.data("locations");
        var map = L.map(mapWrapper.attr("id"), {
          // minZoom: 10,
          maxZoom: 18,
          fullscreenControl: true,
          scrollWheelZoom: false,
          dragging: !L.Browser.mobile,
          tap: false,
        });

        L.tileLayer('https://{s}.tile.osm.org/{z}/{x}/{y}.png', {
          attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        if (Array.isArray(data) && data.length > 0) {
          $.each(data, function (index, value) {
            var marker = L.marker([value["lat"], value["lon"]]).addTo(map);
            map.setView([value["lat"], value["lon"]], 14);
          });
        }
      };
      jQuery('body').find('.leaflet-wrapper').each(function (e) {
        var mapWapper = jQuery(this);
        if (mapWapper.parents(".tab-pane:not(.active)").length > 0) {
          var tabId = mapWapper.parents(".tab-pane:not(.active)").attr("id");
          jQuery("ul.nav-tabs").find("a[href='#" + tabId + "']").on("click", function () {
            if (!jQuery(this).hasClass("map-initialized")) {
              // Initialize with delay so tab animation completes
              setTimeout(function () {
                initMap(mapWapper);
                jQuery(this).addClass("map-initialized");
              }, 200);
            }
          });
        } else {
          initMap(mapWapper);
        }
      });
    }
  });
});
