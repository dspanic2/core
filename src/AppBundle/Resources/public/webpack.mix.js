const mix = require('laravel-mix');

mix.sass('scss/style.scss', '../../../../web/backend/style.css');
mix.options({
    processCssUrls: false
});

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
