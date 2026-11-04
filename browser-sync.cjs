module.exports = {
    proxy: {
        target: "http://localhost/ImproovWeb/",
        ws: true
    },

    files: [
        "MapaPavimento/**/*.php",
        "MapaPavimento/**/*.js",
        "MapaPavimento/**/*.mjs",
        "MapaPavimento/**/*.css"
    ],

    port: 3000,

    open: false,
    notify: false,
    ghostMode: false,

    logLevel: "debug",
    logFileChanges: true,

    reloadDelay: 100
};