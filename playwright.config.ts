import { defineConfig, devices } from '@playwright/test';

/**
 * E2E tests for the `laravel web` installer UI.
 *
 * Playwright boots the real installer (PHP built-in server + CLI worker)
 * before the suite and tears it down afterwards. Applications created by the
 * opt-in full-install test land in tests/E2E/workspace (gitignored).
 */
export default defineConfig({
    testDir: './tests/E2E',
    timeout: 60_000,
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['github']] : [['list']],
    use: {
        baseURL: 'http://127.0.0.1:8143',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'php bin/laravel web --port=8143 --no-open',
        cwd: __dirname,
        url: 'http://127.0.0.1:8143/api/env',
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
    },
});
