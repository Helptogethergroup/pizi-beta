// Public website (layouts/app.blade.php) -> public/css/pizi-site.css
module.exports = {
    content: require('./content.js'),
    theme: {
        extend: {
            colors: {
                ink: { 950: '#0a1a30', 900: '#0f2748', 800: '#15355f', 700: '#1d4577' },
                coral: { 50: '#fff3f1', 100: '#ffe3df', 400: '#ff8c7e', 500: '#ff6b5b', 600: '#ed4e3d', 700: '#c93b2c' },
                cream: '#fefcf6',
            },
            fontFamily: {
                display: ['"Fraunces"', 'serif'],
                sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
            },
        },
    },
};
