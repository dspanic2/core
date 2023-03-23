# ckeditor-image-uploader-plugin
An open source plugin for CKEDITOR to upload images saved on your local machine.

# How to install?

-  Click on download button, rename the folder to simage and add the entire simage folder into CKEditor plugins folder.

- Add simage in toolbar configuration with your config.js file to make the simage button visible on your CKEditor toolbar.

- You can use this plugin with CKEDITOR. You need to configure the end point where you want to store the images uploaded using this plugin. Add it in your config.js as follows:
	```
	CKEDITOR.config.extraPlugins: 'shape_uploader'  //to enable to plugin
	CKEDITOR.config.uploaderUploadURL: <INSERT URL>
	CKEDITOR.config.uploaderAllowedExtensions = ['jpeg','jpg','png','svg','gif','tif', 'doc','docx','pdf','xls','xlsx','zip', 'ppt', 'pptx'];
	CKEDITOR.config.dataParser: func(data)
	```

- The `dataParser` attribute expects a `function` with a parameter in which you should pass the `data` returned by the endpoint that you have configured (`imageUploadURL`) . This function is expected to return a url. This url will be set to the `src` attribute of `image` html element.

- Example response by `imageUploadURL` endpoint:
	```
	{
		url: 'imageUrl'
	}
	```

- Example `dataParser` code:
	```
	function(data){
		if (data){
			var keys = Object.keys(data)
			return data[keys[0]].url
		}
	}
	```