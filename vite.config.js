import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import copy from 'rollup-plugin-copy';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/backend/scss/main.scss',
                'resources/frontend/scss/main.scss',
            ],
            refresh: true,
        }),
        copy({
            targets: [
                { src: 'resources/backend/img/**/*', dest: 'public/backend/img' },
                { src: 'resources/backend/fonts/**/*', dest: 'public/backend/fonts' },
                { src: 'resources/backend/js/**/**/*', dest: 'public/backend/js' },
                { src: 'resources/frontend/img/**/*', dest: 'public/frontend/img' },
                { src: 'resources/frontend/fonts/**/*', dest: 'public/frontend/fonts' },
                { src: 'resources/frontend/js/**/**/*', dest: 'public/frontend/js' },
            ]
        })
    ],
    build: {
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    let extType = assetInfo.name.split('.').pop();
                    if (/\.(gif|jpe?g|png|svg)$/.test(extType)) {
                        extType = 'img';
                    } else if (/\.(woff2?|eot|ttf|otf)$/.test(extType)) {
                        extType = 'fonts';
                    }
                    return `${extType}/[name]-[hash][extname]`;
                },
            },
        },
    },
});
