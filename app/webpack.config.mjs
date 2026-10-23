import Encore from '@symfony/webpack-encore';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or subdirectory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    .addEntry('app', './assets/app.js')
    .addEntry('login', './assets/js/login.js')
    .addEntry('tablet', './assets/js/tablet.js')
    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    //.enableSingleRuntimeChunk()
    .disableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()

    // Displays build status system notifications to the user
    // .enableBuildNotifications()

    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // Configure JS and CSS minimizers
    // .configureJsMinimizerPlugin((options, MinimizerPlugin) => {
    //     options.minify = MinimizerPlugin.esbuildMinify
    // })
    // .configureCssMinimizerPlugin((options, MinimizerPlugin) => {
    //     options.minify = MinimizerPlugin.lightningCssMinify;
    // })

    // configure Babel
    // Babel 8 removed useBuiltIns/corejs from @babel/preset-env; add the
    // polyfills via babel-plugin-polyfill-corejs3 instead.
    .configureBabel((config) => {
        config.plugins.push(['polyfill-corejs3', { method: 'usage-global', version: '3.49' }]);
    })

    // enables Sass/SCSS support
    .enableSassLoader(options => {
        options.sassOptions = {
            silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function']
        };
    })

    // uncomment if you use TypeScript
    //.enableTypeScriptLoader()

    // uncomment if you use React
    //.enableReactPreset()

    // uncomment to get integrity="..." attributes on your script & link tags
    // requires WebpackEncoreBundle 1.4 or higher
    //.enableIntegrityHashes(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()

    .copyFiles({
        from: './assets/images',
        //optional target path, relative to the output dir
        //to: 'images/[path][name].[ext]',
    
        //if versioning is enabled, add the file hash too
        to: 'images/[path][name].[hash:8].[ext]',
        //only copy files matching this pattern
        //pattern: /\.(png|jpg|jpeg)$/
    })
;

const config = await Encore.getWebpackConfig();
config.module.rules.unshift({
    parser: {
        amd: false,
    },
});

// jQuery 4 espone di default (condizione `import`) il build ES module
// dist-module/jquery.module.js. Con autoProvidejQuery(), Webpack passa al codice
// vendor (Bootstrap, datepicker, bootstrap-select) l'*oggetto namespace* del
// modulo — privo di `.fn` — facendo esplodere `$.fn[name]` in defineJQueryPlugin
// e abortendo l'esecuzione di app.js (dropdown/datepicker non inizializzati).
// Puntiamo ogni richiesta di `jquery` al build UMD, il cui module.exports È la
// funzione jQuery (con `.fn`), coerente sia per import che per il provide.
// Path assoluto: bypassa la mappa `exports` che vieterebbe ./dist/jquery.js.
config.resolve = config.resolve || {};
config.resolve.alias = config.resolve.alias || {};
config.resolve.alias.jquery = path.resolve(__dirname, 'node_modules/jquery/dist/jquery.js');

export default config;
