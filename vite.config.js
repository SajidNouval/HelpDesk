import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import viteCompression from 'vite-plugin-compression';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5176,
        cors: true,
        hmr: {
            host: 'helpdeskta.test',
            port: 5176,
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        // Pre-compress assets to Gzip format
        viteCompression({
            algorithm: 'gzip',
            ext: '.gz',
            threshold: 1024,
            filter: /\.(js|css|html|svg|json)$/i,
        }),
        // Pre-compress assets to Brotli format
        viteCompression({
            algorithm: 'brotliCompress',
            ext: '.br',
            threshold: 1024,
            filter: /\.(js|css|html|svg|json)$/i,
        }),
    ],
    build: {
        minify: 'esbuild',
        cssMinify: true,
        rollupOptions: {
            // Enable default Rollup automatic chunking for cleaner dynamic imports & tree shaking
            output: {
                chunkFileNames: 'assets/[name]-[hash].js',
                entryFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash].[ext]',
            },
        },
    },
});
