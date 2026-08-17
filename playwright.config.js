import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    reporter: [['line']],
    use: {
        baseURL: 'http://127.0.0.1:8000',
        viewport: { width: 899, height: 679 },
        colorScheme: 'light',
        locale: 'pt-BR',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
});
