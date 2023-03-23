/**
 * Used in src/AppBundle/Resources/views/Admin/Sync/import.html.twig
 * Once user selects a file, we read it and paste its content into a read-only text-area. The content will be
 * included in the form submit data, and imported on the backend.
 */
jQuery(function() {
  document.getElementById("json_file").addEventListener(
    "change",
    function() {
      var file = this.files[0];

      if (file) {
        var reader = new FileReader();

        reader.onload = function(evt) {
          document.getElementById("json_content").value = evt.target.result;
        };

        reader.onerror = function(evt) {
          console.error("An error ocurred reading the file", evt);
        };

        reader.readAsText(file, "UTF-8");
      }
    },
    false
  );
});
