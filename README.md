# Laravel Installer — CLI + Web UI

<p>
<a href="https://github.com/thatobabusi/laravel-installer/actions/workflows/tests.yml"><img src="https://github.com/thatobabusi/laravel-installer/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
<a href="https://github.com/thatobabusi/laravel-installer/actions/workflows/static-analysis.yml"><img src="https://github.com/thatobabusi/laravel-installer/actions/workflows/static-analysis.yml/badge.svg" alt="Static Analysis"></a>
<a href="https://github.com/thatobabusi/laravel-installer/actions/workflows/docs.yml"><img src="https://github.com/thatobabusi/laravel-installer/actions/workflows/docs.yml/badge.svg" alt="Documentation"></a>
<a href="https://github.com/thatobabusi/laravel-installer/releases"><img src="https://img.shields.io/github/v/release/thatobabusi/laravel-installer" alt="Latest Release"></a>
<a href="LICENSE.md"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License"></a>
</p>

Create new Laravel applications from your terminal **or your browser**. This is a fork of
[laravel/installer](https://github.com/laravel/installer) that adds a local web-based installer
(`laravel web`), a full documentation set, and self-maintaining repository automation — while
staying continuously synced with upstream.

```sh
laravel new my-app        # the classic CLI wizard
laravel web               # the same wizard, in your browser
```

## Features

- **Everything upstream** — starter kits (React, Svelte, Vue, Livewire), WorkOS auth, teams,
  database setup, Pest/PHPUnit, npm/pnpm/bun/yarn, Laravel Boost, Git and GitHub publishing.
- **Web installer** — `laravel web` serves a local wizard UI that walks every `laravel new`
  option, live-validates the project name, disables databases whose PDO extension is missing,
  shows the equivalent CLI command before installing, and streams the install log to the browser.
- **Loopback-only by design** — the web UI binds to `127.0.0.1`; an optional
  [Herd front controller](public/index.php) proxies it at a friendly `.test` domain.
- **Agent-aware** — when run by an AI coding agent, the installer suppresses prompts and emits a
  machine-readable JSON result ([details](docs/agent-integration.md)).
- **Documented and self-maintaining** — narrative docs in [docs/](docs/README.md), a generated
  command reference that CI keeps honest, weekly upstream syncs, and one-click releases.

## Requirements

| Purpose | Requirement |
| --- | --- |
| Run the installer | PHP 8.2+, Composer |
| Create Laravel 13 apps | PHP 8.4+ |
| Optional features | Git, a Node package manager, the [`gh` CLI](https://cli.github.com) (GitHub publishing) |

## Installation

**From this fork (includes `laravel web`):**

```sh
git clone https://github.com/thatobabusi/laravel-installer.git
cd laravel-installer
composer install
php bin/laravel --version
```

Add `bin/laravel` to your `PATH`, or run it through your PHP of choice. [Laravel Herd](https://herd.laravel.com)
users can also link this repository with its document root set to `public/` to reach the web
installer at a Herd host such as `https://laravel-installer.test`.

**The upstream package (CLI only, no web UI):**

```sh
composer global require laravel/installer
```

## Usage

Create an application interactively, or script it with explicit flags:

```sh
laravel new blog
laravel new blog --react --database=pgsql --pest --pnpm --git --no-interaction
```

Launch the browser wizard from the directory that should contain new projects:

```sh
laravel web              # picks a free port (8123-8199) and opens your browser
laravel web --port=8123  # choose the port
laravel web --no-open    # don't open the browser automatically
```

See [Getting started](docs/getting-started.md) for recipes and the
[generated command reference](docs/reference/command-help.md) for every option and default.

## Documentation

| Guide | Covers |
| --- | --- |
| [Getting started](docs/getting-started.md) | Installing, creating apps, CLI recipes |
| [CLI guide](docs/cli.md) | `laravel new` and `laravel package` in depth |
| [Web installer](docs/web-installer.md) | `laravel web`, Herd setup, behavior and requirements |
| [Agent integration](docs/agent-integration.md) | JSON output contract for AI agents |
| [Command reference](docs/reference/command-help.md) | Generated `--help` for every command |
| [Maintaining docs](docs/maintaining-docs.md) | `composer docs:update` / `docs:check` |
| [Upstream sync](docs/upstream-sync.md) | How this fork tracks laravel/installer |

## Repository automation

| Workflow | What it does |
| --- | --- |
| `tests.yml` / `static-analysis.yml` | PHP/Laravel compatibility matrix and PHPStan on every push and PR |
| `docs.yml` | Fails CI when generated documentation is stale |
| `docs-sync.yml` | Regenerates the command reference on schedule and commits the drift |
| `upstream-sync.yml` | Merges `laravel/installer` weekly — PR on a clean merge, issue on conflict |
| `release.yml` | Manual dispatch: bumps the version, tags, and publishes a GitHub Release |

## Contributing

Issues and pull requests are welcome on this fork for web-installer and documentation changes.
Improvements to the core installer belong upstream in
[laravel/installer](https://github.com/laravel/installer) — they will arrive here through the
weekly sync.

## License

Open-sourced software licensed under the [MIT license](LICENSE.md). Built on the
[Laravel Installer](https://github.com/laravel/installer) by Taylor Otwell and the Laravel team.
