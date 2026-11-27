// Logged-in panels (layouts/dashboard, tenant, fieldexec) -> public/css/pizi-panel.css
module.exports = {
    content: require('./content.js'),
    theme: {
        extend: {
            colors: {
                ink: { 950: '#0a1a30', 900: '#0f2748', 800: '#15355f' },
                coral: { 500: '#ff6b5b', 600: '#ed4e3d' },
                cream: '#fefcf6',
            },
            fontFamily: { display: ['Fraunces', 'serif'], sans: ['Plus Jakarta Sans', 'sans-serif'] },
        },
    },
};
