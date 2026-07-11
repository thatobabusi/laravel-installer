# Upstream sync

This repository is a fork of [laravel/installer](https://github.com/laravel/installer) with local additions (the web installer, this documentation, and the automation in `scripts/` and `.github/workflows/`). Upstream keeps moving — new Laravel releases, new `laravel new` options — so the fork pulls those changes in on a schedule.

## How updates flow

There are two update loops, both automated:

| Loop | What it updates | Automation |
| --- | --- | --- |
| **Upstream sync** | Code from `laravel/installer` master | `upstream-sync.yml` — weekly (Mondays 05:00 UTC) or manual via *Actions → Sync upstream → Run workflow* |
| **Docs sync** | Generated command reference in `docs/reference/` | `docs-sync.yml` — on command-source pushes, weekly, or manual |

The upstream workflow fetches `laravel/installer` master and counts how far the fork is behind. When there are new commits it attempts a merge on an `upstream-sync` branch:

- **Clean merge** → the branch is pushed and a pull request is opened against `master`. CI (tests, static analysis, `composer docs:check`) runs against the merge; review and merge the PR.
- **Conflicts** → the merge is aborted and an issue is opened describing the manual resolve procedure. Conflicts are expected occasionally in `bin/laravel` (both sides register commands) and are trivial to resolve.

## Syncing manually

From a clean working tree:

```sh
bash scripts/sync-upstream.sh
```

The script adds the `upstream` remote if missing, merges `upstream/master`, regenerates the command reference, runs `docs:check` and PHPStan, and commits any documentation drift. Push afterwards. On conflict it stops and prints which areas are ours.

## What is "ours" vs "theirs"

When resolving conflicts, these paths are fork-owned — prefer our side and re-apply upstream intent by hand if needed:

- `src/WebCommand.php`, `src/Web/`, `public/` — the web installer
- `docs/`, `scripts/` — documentation and automation
- `.github/workflows/docs.yml`, `docs-sync.yml`, `release.yml`, `upstream-sync.yml` — fork workflows

Everything else (notably `src/NewCommand.php`, `src/PackageCommand.php`, `src/Agent.php`, `src/Concerns/`) is upstream-owned — take their side unless a fork change deliberately touched it. `bin/laravel` is shared: keep upstream's changes *and* the `WebCommand` registration lines.

## After any sync

```sh
composer docs:update && composer docs:check
vendor/bin/phpstan analyse
```

If upstream added or changed `laravel new` options, the web installer may need matching UI work: `src/Web/index.html` (wizard controls), `src/WebCommand.php` `flagsForJob()` (flag mapping), and [web-installer.md](web-installer.md). The `docs.yml` CI check fails PRs whose command sources changed without regenerated documentation, so drift cannot land silently.
