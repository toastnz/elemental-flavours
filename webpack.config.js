const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');

const SRC = path.resolve(__dirname, 'client/src');
const DIST = path.resolve(__dirname, 'client/dist');

/*
 * Built by hand rather than through @silverstripe/webpack-config.
 *
 * That package is the usual way to build a CMS module and it is where the
 * externals below come from, but its latest release pins sass below 1.100 and
 * sass-loader to 13, which drives dart-sass through the legacy JS API - removed
 * in dart-sass 2.0 and deprecated loudly on every build until then. Nothing can
 * lift that pin from here, and the project's own build already runs current
 * sass, so this config follows the project instead and keeps the handful of
 * things a CMS module needs on top.
 */

/*
 * silverstripe/admin puts its React runtime and its shared components on the
 * page as globals, so a module bundle has to reach for those rather than carry
 * its own. A second copy of React is not just weight: hooks break outright when
 * a component is rendered by one copy and dispatched by another.
 *
 * Only what this module imports is listed. Adding an import from admin means
 * adding the global it is exposed as here too - the full map lives in
 * @silverstripe/webpack-config/js/externals.js.
 */
const externals = {
    classnames: 'classnames',
    i18n: 'i18n',
    'prop-types': 'PropTypes',
    react: 'React',
    'react-dom': 'ReactDom',
    reactstrap: 'Reactstrap',
    'components/FieldHolder/FieldHolder': 'FieldHolder',
    'lib/Injector': 'Injector',
};

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';

    return {
        entry: {
            // Both halves under one entry name. Two entries both called
            // "bundle" would collide, and the names are not ours to change:
            // _config/flavours.yml requires client/dist/js/bundle.js and
            // client/dist/styles/bundle.css by path.
            bundle: [`${SRC}/js/bundle.js`, `${SRC}/styles/bundle.scss`],
            // The front-end half: the <elemental-flavour> custom element. It
            // imports nothing, so none of the CMS externals above reach it.
            'elemental-flavour': `${SRC}/js/elemental-flavour.js`,
        },
        output: {
            path: DIST,
            filename: 'js/[name].js',
            clean: true,
        },
        devtool: isProduction ? false : 'source-map',
        externals,
        module: {
            rules: [
                {
                    test: /\.jsx?$/,
                    exclude: /node_modules/,
                    use: [
                        {
                            loader: 'babel-loader',
                            options: {
                                // Stated here and nowhere else. Left to search,
                                // babel would find whichever config file sits
                                // above this module in whatever project it is
                                // installed into.
                                babelrc: false,
                                configFile: false,
                                presets: [
                                    '@babel/preset-env',
                                    // Classic, not babel 8's automatic default:
                                    // automatic compiles JSX to imports from
                                    // react/jsx-runtime, which isn't one of the
                                    // globals admin exposes, so webpack would
                                    // bundle a second React to satisfy it.
                                    ['@babel/preset-react', { runtime: 'classic' }],
                                ],
                                cacheDirectory: !isProduction,
                            },
                        },
                    ],
                },
                {
                    test: /\.scss$/,
                    use: [
                        MiniCssExtractPlugin.loader,
                        {
                            loader: 'css-loader',
                            options: { sourceMap: true },
                        },
                        {
                            loader: 'postcss-loader',
                            options: {
                                sourceMap: true,
                                postcssOptions: { plugins: ['autoprefixer'] },
                            },
                        },
                        {
                            // Sass resolves a url() against the file it was
                            // written in, css-loader against the file it ends
                            // up in. Those are the same folder today, and this
                            // keeps them the same if a partial ever moves.
                            loader: 'resolve-url-loader',
                            options: { sourceMap: true },
                        },
                        {
                            loader: 'sass-loader',
                            // Not optional: resolve-url-loader has nothing to
                            // trace a url back to without a map from sass.
                            options: { sourceMap: true },
                        },
                    ],
                },
                {
                    // The layout icons are a font, and the hash is a query
                    // rather than part of the name so the file stays findable.
                    test: /\.(woff2?|eot|ttf|otf)$/,
                    type: 'asset/resource',
                    generator: {
                        filename: 'fonts/[name][ext]?h=[contenthash]',
                    },
                },
                {
                    test: /\.(png|gif|jpe?g|svg)$/,
                    type: 'asset',
                    parser: {
                        dataUrlCondition: { maxSize: 10 * 1024 },
                    },
                    generator: {
                        filename: 'images/[name][ext]',
                    },
                },
            ],
        },
        optimization: {
            minimizer: [
                new TerserPlugin({ extractComments: false }),
                new CssMinimizerPlugin(),
            ],
        },
        plugins: [
            new MiniCssExtractPlugin({ filename: 'styles/[name].css' }),
        ],
    };
};
