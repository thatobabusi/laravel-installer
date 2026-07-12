import { test, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';

/**
 * Generates the documentation screenshots in docs/images/.
 *
 * Skipped during normal test runs — regenerate with `npm run docs:screenshots`.
 * CI re-captures these on wizard-source changes (screenshots.yml) so the
 * committed images always match the shipped UI.
 */

const imagesDir = join(__dirname, '..', '..', 'docs', 'images');

test.describe('documentation screenshots', () => {
    test.skip(!process.env.DOCS_SCREENSHOTS, 'Set DOCS_SCREENSHOTS=1 (npm run docs:screenshots) to regenerate documentation images');

    test.use({ viewport: { width: 1100, height: 1000 } });

    const capture = async (page: Page, name: string) => {
        await page.screenshot({
            path: join(imagesDir, name),
            fullPage: true,
            animations: 'disabled',
        });
    };

    const next = (page: Page) => page.locator('.step:not(.hidden) [data-nav="next"]').click();

    test('captures every wizard screen', async ({ page }) => {
        mkdirSync(imagesDir, { recursive: true });

        await page.goto('/');

        // Step 1 — project name with live validation...
        await page.fill('#name', 'blog');
        await page.locator('#nameHint.ok').waitFor();
        await capture(page, '01-project.png');

        // Step 2 — project type...
        await page.click('#nameNext');
        await capture(page, '02-project-type.png');

        // Step 3 — the full frontend stack grid for blank setups...
        await next(page);
        await page.click('.opt[data-group="starterKit"][data-value="no"]');
        await page.click('.opt[data-group="stack"][data-value="blade"]');
        await capture(page, '03-stacks.png');

        // Step 4 — frontend extras: UI framework, JavaScript, theme helper...
        await next(page);
        await page.click('.opt[data-group="ui"][data-value="bootstrap"]');
        await page.click('.opt[data-group="js"][data-value="alpine"]');
        await capture(page, '04-frontend-extras.png');

        // Step 5 — application options...
        await next(page);
        await capture(page, '05-options.png');

        // Step 6 — version control...
        await next(page);
        await capture(page, '06-version-control.png');

        // Step 7 — review with the equivalent CLI command...
        await next(page);
        await capture(page, '07-review.png');

        // Success screen, rendered with representative content...
        await page.evaluate(() => {
            document.querySelectorAll('.step').forEach((el) => el.classList.add('hidden'));
            const progress = document.querySelector('[data-step="7"]')!;
            progress.classList.remove('hidden');

            document.querySelector('#progressTitle')!.innerHTML = '✅ Application ready';
            document.querySelector('#progressSub')!.textContent = 'Your new Laravel application is ready to go.';
            document.querySelector('#resultBanner')!.innerHTML = `
                <div class="result-banner success">
                    <b>blog</b> was created at <b>C:\\projects\\blog</b>
                    <div class="next-steps">
                        1. <code>cd blog</code><br>
                        2. Open <a href="#">http://blog.test</a> or run <code>composer run dev</code>
                    </div>
                </div>`;
            document.querySelector('#terminal')!.textContent = [
                '    Creating a "laravel/laravel" project at "./blog"',
                '    Installing laravel/laravel (v13.8.0)',
                '    Created project in C:\\projects\\blog',
                '    Bootstrap 5 UI preset applied.',
                '    Alpine.js added.',
                '    Light/dark theme helper added (window.toggleTheme).',
                '    Database migrated',
                '    Application ready',
            ].join('\n');
            document.querySelector('#anotherBtn')!.classList.remove('hidden');
            document.querySelector('#quitBtn')!.classList.remove('hidden');
        });
        await capture(page, '08-success.png');
    });
});
