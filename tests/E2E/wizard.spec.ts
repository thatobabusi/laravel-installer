import { test, expect, type Page } from '@playwright/test';
import { rmSync } from 'node:fs';
import { join } from 'node:path';

/**
 * E2E coverage for the `laravel web` wizard.
 *
 * Playwright boots the real installer via playwright.config.ts (PHP built-in
 * server + CLI worker), so these tests exercise the same router, state files,
 * and job pipeline that production uses. The full installation test is opt-in
 * (E2E_INSTALL=1) because it downloads a Laravel skeleton with Composer.
 */

const uniqueName = (prefix: string) => `${prefix}-${Date.now().toString(36)}`;

async function fillValidName(page: Page, name: string): Promise<void> {
    await page.fill('#name', name);
    await expect(page.locator('#nameHint')).toHaveClass(/ok/);
    await expect(page.locator('#nameNext')).toBeEnabled();
}

const visibleStep = (page: Page) => page.locator('.step:not(.hidden)');

test.describe('wizard shell', () => {
    test('loads the project step', async ({ page }) => {
        await page.goto('/');

        await expect(page).toHaveTitle('Laravel Installer');
        await expect(page.getByRole('heading', { name: 'Name your project' })).toBeVisible();
        await expect(page.locator('#targetDir')).not.toHaveText('…');
    });

    test('rejects invalid project names', async ({ page }) => {
        await page.goto('/');
        await page.fill('#name', 'bad name!');

        await expect(page.locator('#nameHint')).toHaveClass(/error/);
        await expect(page.locator('#nameNext')).toBeDisabled();
    });

    test('rejects names of existing directories', async ({ page }) => {
        await page.goto('/');
        // "src" exists in the repository root the test server runs from...
        await page.fill('#name', 'src');

        await expect(page.locator('#nameHint')).toHaveClass(/error/);
        await expect(page.locator('#nameHint')).toContainText('already exists');
    });
});

test.describe('stack step conditionals', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/');
        await fillValidName(page, uniqueName('pw-wizard'));
        await page.click('#nameNext');
    });

    test('starter kits show auth options and hide UI presets', async ({ page }) => {
        await page.click('.opt[data-group="starterKit"][data-value="yes"]');

        await expect(page.locator('#authField')).toBeVisible();
        await expect(page.locator('#uiField')).toBeHidden();
        await expect(page.locator('.opt[data-group="stack"][data-value="blade"]')).toHaveCount(0);
    });

    test('blank blade setups offer the UI framework presets', async ({ page }) => {
        await page.click('.opt[data-group="starterKit"][data-value="no"]');
        await page.click('.opt[data-group="stack"][data-value="blade"]');

        await expect(page.locator('#uiField')).toBeVisible();
        await expect(page.locator('#authField')).toBeHidden();

        const presets = page.locator('#uiField .opt .name');
        await expect(presets).toHaveText(['Tailwind', 'Bootstrap 5', 'CoreUI 5', 'AdminLTE 4', 'Laravel AdminLTE']);
    });

    test('blank setups offer the SPA stacks and hide UI presets for them', async ({ page }) => {
        await page.click('.opt[data-group="starterKit"][data-value="no"]');

        const stacks = page.locator('#stackOptions .opt .name');
        await expect(stacks).toHaveText(['Blade', 'React', 'Svelte', 'Vue', 'Livewire', 'Angular', 'Next.js', 'Nuxt', 'SvelteKit', 'Astro']);

        await page.click('.opt[data-group="stack"][data-value="next"]');
        await expect(page.locator('#uiField')).toBeHidden();
    });

    test('livewire starter kit exposes the single-file component toggle', async ({ page }) => {
        await page.click('.opt[data-group="starterKit"][data-value="yes"]');
        await page.click('.opt[data-group="stack"][data-value="livewire"]');

        await expect(page.locator('#livewireSingleFile')).toBeVisible();

        await page.click('.opt[data-group="auth"][data-value="workos"]');
        await expect(page.locator('#livewireSingleFile')).toBeHidden();
    });
});

test.describe('review step', () => {
    test('builds the equivalent CLI command for a preset install', async ({ page }) => {
        const name = uniqueName('pw-review');

        await page.goto('/');
        await fillValidName(page, name);
        await page.click('#nameNext');

        await page.click('.opt[data-group="starterKit"][data-value="no"]');
        await page.click('.opt[data-group="stack"][data-value="blade"]');
        await page.click('.opt[data-group="ui"][data-value="bootstrap"]');
        await visibleStep(page).locator('[data-nav="next"]').click();

        await page.click('.opt[data-group="node"][data-value="skip"]');
        await visibleStep(page).locator('[data-nav="next"]').click();
        await visibleStep(page).locator('[data-nav="next"]').click();

        const cli = page.locator('#cliPreview');
        await expect(cli).toContainText(`laravel new ${name}`);
        await expect(cli).toContainText('--ui=bootstrap');
        await expect(cli).toContainText('--no-authentication');
        await expect(cli).toContainText('--no-node');
        await expect(cli).toContainText('--no-interaction');

        await expect(page.locator('#summaryTable')).toContainText('Bootstrap 5');
    });
});

test.describe('http api', () => {
    test('reports the environment', async ({ request }) => {
        const response = await request.get('/api/env');

        expect(response.ok()).toBeTruthy();

        const env = await response.json();
        expect(env.databases).toHaveProperty('sqlite');
        expect(env.targetDirectory.length).toBeGreaterThan(0);
    });

    test('validates names', async ({ request }) => {
        const invalid = await (await request.get('/api/check-name?name=bad name')).json();
        expect(invalid.valid).toBe(false);

        const valid = await (await request.get(`/api/check-name?name=${uniqueName('pw-api')}`)).json();
        expect(valid.valid).toBe(true);
    });

    test('rejects malformed install payloads', async ({ request }) => {
        const response = await request.post('/api/install', { data: 'not-json', headers: { 'Content-Type': 'application/json' } });

        expect(response.status()).toBe(422);
    });
});

test.describe('full installation', () => {
    test('creates a working application through the wizard', async ({ page }) => {
        test.skip(!process.env.E2E_INSTALL, 'Set E2E_INSTALL=1 to run the full Composer installation');
        test.setTimeout(600_000);

        const name = uniqueName('e2e-install');

        await page.goto('/');
        await fillValidName(page, name);
        await page.click('#nameNext');

        await page.click('.opt[data-group="starterKit"][data-value="no"]');
        await page.click('.opt[data-group="stack"][data-value="blade"]');
        await visibleStep(page).locator('[data-nav="next"]').click();

        await page.click('.opt[data-group="node"][data-value="skip"]');
        await page.click('.toggle-row[data-toggle="boost"]');
        await visibleStep(page).locator('[data-nav="next"]').click();
        await visibleStep(page).locator('[data-nav="next"]').click();

        await page.click('#installBtn');

        try {
            await expect(page.locator('#progressTitle')).toContainText('Application ready', { timeout: 540_000 });
            await expect(page.locator('.result-banner.success')).toContainText(name);
        } finally {
            rmSync(join(__dirname, '..', '..', name), { recursive: true, force: true });
        }
    });
});
