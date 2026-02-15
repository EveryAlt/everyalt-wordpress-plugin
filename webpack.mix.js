// webpack.mix.js
let mix = require('laravel-mix');

mix.js('src/js/everyalt-admin', 'admin/js')
.js('src/js/everyalt-bulk', 'admin/js')
.js('src/js/everyalt-history', 'admin/js')
.js('src/js/everyalt-media-button', 'admin/js')
.js('src/js/everyalt-gutenberg-button', 'admin/js')


.react()
.sass('src/sass/everyalt-admin.scss', 'admin/css')