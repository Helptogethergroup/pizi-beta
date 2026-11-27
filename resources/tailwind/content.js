// Every file that can contain a Tailwind class name. Paths are from the project root.
module.exports = [
    './resources/views/**/*.blade.php',
    './app/**/*.php',
    './config/**/*.php',
    './routes/**/*.php',
    './public/*.js',
    // the page-number buttons under long lists come from Laravel's own views
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
];
