import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'happy-dom',
        include: ['resources/js/**/*.{test,spec}.js'],
        setupFiles: ['resources/js/tests/setup.js'],
        globals: true,
        coverage: {
            provider: 'v8',
            include: ['resources/js/**/*.{js,vue}'],
            exclude: ['resources/js/**/*.{test,spec}.js', 'resources/js/tests/**', 'resources/js/app.js', 'resources/js/bootstrap.js'],
            reporter: ['text', 'text-summary', 'html'],
            reportsDirectory: 'coverage/js',
            thresholds: {
                lines: 80,
                branches: 60,
                statements: 80,
            },
        },
    },
    resolve: {
        alias: {
            vue: path.resolve(__dirname, 'node_modules/vue/dist/vue.esm-bundler.js'),
        },
    },
});
