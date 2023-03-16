// webpack.mix.js
let mix = require('laravel-mix');

mix.js('src/js/every-alt-admin', 'admin/js')
.js('src/js/every-alt-bulk', 'admin/js')
.js('src/js/every-alt-history', 'admin/js')
.js('src/js/every-alt-media-button', 'admin/js')
.js('src/js/every-alt-gutenberg-button', 'admin/js')


.react()
.sass('src/sass/every-alt-admin.scss', 'admin/css')