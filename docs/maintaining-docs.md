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