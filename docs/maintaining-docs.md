# Maintaining documentation

Narrative documentation is maintained in the Markdown files in this folder. The command reference and source manifest are generated to prevent stale option lists.

After changing `bin/laravel`, a command class, or agent output:

1. Update the relevant narrative document if behavior changed.
2. Regenerate the machine-derived documentation:

   ```sh
   composer docs:update
   ```

3. Verify it and run the test suite:

   ```sh
   composer docs:check
   vendor/bin/phpunit
   ```

`docs:check` compares checked-in command help and SHA-256 hashes for installer command sources against the current checkout. CI fails when a command contract changes without regenerating documentation. Add a source path to `scripts/sync-docs.php` whenever it can alter a documented public contract.

## Backend tests

`tests/Unit/` covers the web installer's PHP internals (wizard job-to-flag mapping, UI preset
mechanics) and `tests/Feature/` boots the installer's HTTP router on PHP's built-in server to
test every API endpoint. Run them with the rest of the PHPUnit suite:

```sh
vendor/bin/phpunit --filter "WebCommandFlagsTest|UiPresetsTest|WebInstallerRouterTest"
```

## End-to-end tests

`tests/E2E/` holds Playwright tests that boot the real `laravel web` server and drive the wizard in Chromium — step navigation, conditional fields, the CLI preview, and the HTTP API. They run in CI on every push (`e2e.yml`). Locally:

```sh
npm install && npx playwright install chromium   # once
npm run test:e2e                                 # the suite
npm run test:e2e:install                         # + the full Composer installation test
```

The full-install test is skipped unless `E2E_INSTALL=1`; it creates (and removes) a real application in the repository root, and needs a PHP new enough for the current Laravel skeleton on your `PATH`.