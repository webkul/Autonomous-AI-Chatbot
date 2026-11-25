/** @format */
/**
 * External dependencies
 */
const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');


const NODE_ENV = process.env.NODE_ENV || 'development';

const webpackConfig = {
    mode: NODE_ENV,
    watch: NODE_ENV !== 'production',
    entry: {
        'app': ['./src/js/front/app.js', './src/scss/app.scss'],
        'admin': ['./src/js/admin/admin.js', './src/scss/admin.scss'],
        'admin_post': ['./src/js/admin/adminPost.js', './src/scss/adminPost.scss'],
        'history': ['./src/js/admin/history/index.js', './src/scss/history.scss'],
        'dashboard': ['./src/js/admin/dashboard/index.js', './src/scss/dashboard.scss'],
        'content': ['./src/js/admin/content/index.js', './src/scss/content.scss'],
        'model': ['./src/js/admin/model/index.js', './src/scss/model.scss'],
        'download_modal': ['./src/js/admin/downloadModal/index.js'],
        'custom_notice': ['./src/scss/notice.scss'],
    },
    devtool: NODE_ENV != 'production' ? 'inline-source-map' : false,
    output: {
        filename: 'js/[name].js',
        path: path.resolve(__dirname, 'assets'),
        libraryTarget: 'this',
    },
    watchOptions: {
        ignored: [
            '**/node_modules/**',
            '**/vendor/**',
            '**/assets/**',
            '**/modules/**',
        ],
        poll: 1000,
    },
    externals: {
        "@wordpress/element": { this: ["wp", "element"] },
        react: "React",
        "react-dom": "ReactDOM",
    },
    experiments: {
        asyncWebAssembly: true,
    },
    module: {
        rules: [
            {
                parser: {
                    amd: false,
                },
            },
            {
                test: /\.js$/,
                include: /node_modules\/@ternlight\/base/,
                type: 'javascript/esm',
            },
            {
                test: /\.jsx?$/,
                loader: 'babel-loader',
                exclude: /node_modules/,
            },
            {
                test: /\.js?$/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: [
                            [
                                '@babel/preset-env',
                                { loose: true, modules: 'commonjs' },
                            ],
                        ],
                        plugins: ['transform-es2015-template-literals'],
                    },
                },
                include: new RegExp(
                    '/node_modules/(' +
                    '|acorn-jsx' +
                    '|d3-array' +
                    '|debug' +
                    '|regexpu-core' +
                    '|unicode-match-property-ecmascript' +
                    '|unicode-match-property-value-ecmascript)/'
                ),
            },
            {
                test: /\.s?css$/,
                exclude: [/storybook\/wordpress/, /build-style\/*\/*.css/],
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    {
                        loader: 'postcss-loader',
                    },
                    {
                        loader: 'sass-loader',
                    },
                ],
            },
        ],
    },
    plugins: [
        new webpack.DefinePlugin({
            'import.meta.url': JSON.stringify('file:///env.js'),
        }),
        new DependencyExtractionWebpackPlugin(),
        new MiniCssExtractPlugin({
            filename: 'css/[name].css',
        }),
        {
            apply: (compiler) => {
                if (webpackConfig.mode === 'production') {
                    compiler.hooks.done.tap('DonePlugin', (stats) => {
                        setTimeout(() => {
                            process.exit(0)
                        })
                    });
                }
            }
        }
    ],
    stats: 'errors-warnings',
};

if (webpackConfig.mode !== 'production') {
    webpackConfig.devtool = 'inline-source-map';
}
module.exports = webpackConfig;
