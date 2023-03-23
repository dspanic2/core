const mix = require('laravel-mix');

var scripts = [];
var fs = require('fs');
var files = fs.readdirSync('js');
files.forEach(compileJs);

function compileJs(value, index, array) {
    if (value.includes(".js") && !value.includes("min.")) {
        var script = 'js/' + value;
        var minScript = ('js/' + value).replace(".js", ".min.js");
        mix.js(script, minScript);
        mix.minify(script);
    }
}
