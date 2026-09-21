import { defineConfig } from 'vitest/config';
import { fileURLToPath } from 'node:url';

const wpsrRepoRoot = fileURLToPath(new URL('../..', import.meta.url));

export default defineConfig({
    root: wpsrRepoRoot,
    resolve: {
        extensions: ['.js', '.vue', '.json'],
        alias: {
            '@': fileURLToPath(new URL('../../resources/assets', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        environmentOptions: {
            happyDOM: {
                url: 'https://site.test/wp-admin/admin.php?page=wpsocialninja.php',
            },
        },
        include: ['tests/js/specs/**/*.test.js'],
        clearMocks: true,
        restoreMocks: true,
        maxWorkers: 1,
        minWorkers: 1,
        testTimeout: 3000,
    },
    cacheDir: fileURLToPath(new URL('./node_modules/.vite', import.meta.url)),
});
