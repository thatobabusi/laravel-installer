# CLI guide

The executable provides `new`, `package`, and `web`. All commands accept standard console options including `--help`, `--no-interaction`, `--quiet`, and `--no-ansi`. The [generated command reference](reference/command-help.md) is authoritative for option spelling and defaults.

## `laravel new <name>`

Creates a Laravel application. `--react`, `--svelte`, `--vue`, and `--livewire` select a first-party starter kit. Use `--no-authentication` for the corresponding blank kit. `--using=vendor/package` installs a community kit and overrides first-party starter-kit selection.

| Area | Options |
| --- | --- |
| Database | `--database=mysql|mariadb|pgsql|sqlite|sqlsrv` |
| Tests | `--pest`, `--phpunit` |
| Frontend | `--npm`, `--pnpm`, `--bun`, `--yarn`, `--no-node` |
| Project type (vanilla only) | `--type=web|api|dashboard` |
| UI preset (vanilla only) | `--ui=bootstrap|bulma|uikit|pico|coreui|adminlte|laravel-adminlte` |
| JS enhancement (vanilla only) | `--js=alpine|htmx|jquery|stimulus` |
| SPA frontend (vanilla only) | `--spa=angular|next|nuxt|sveltekit|astro` |
| Theme helper (vanilla only) | `--theme` |
| Authentication | `--workos`, `--teams`, `--livewire-class-components` |
| AI tooling | `--boost`, `--no-boost` |
| Git and GitHub | `--git`, `--branch`, `--github`, `--organization` |

`--type` tailors the application after creation: `api` runs `php artisan install:api`
(Sanctum + API routes), and `dashboard` installs a [Filament](https://filamentphp.com)
admin panel. Package skeletons have their own command (`laravel package`).

`--js` adds a JavaScript enhancement (Alpine.js, HTMX, jQuery, or Stimulus) to a vanilla
Blade application without touching its CSS setup. `--theme` writes a
`resources/js/theme.js` helper giving every project light and dark mode out of the box —
`window.toggleTheme()` persists the choice and follows the system preference, wiring
`data-theme`, Bootstrap's `data-bs-theme`, and Tailwind's `dark` class.

`--ui` applies a UI preset to a vanilla (no starter kit) application and is rejected when
combined with a starter kit, since kits ship their own frontend stack:

- **Vite swaps** (`bootstrap`, `bulma`, `uikit`, `pico`, `coreui`, `adminlte`) replace the
  skeleton's Tailwind scaffolding with the chosen framework by updating `package.json`, the
  Vite entry points (`resources/css/app.css`, `resources/js/app.js`), and the Vite
  configuration.
- **Composer package** (`laravel-adminlte`) requires
  [jeroennoten/laravel-adminlte](https://github.com/jeroennoten/Laravel-AdminLTE) and runs
  `php artisan adminlte:install`, leaving the Vite/Tailwind setup untouched.

`--spa` scaffolds a standalone SPA workspace in `frontend/` alongside the Laravel backend
using the framework's own CLI — Angular CLI, `create-next-app`, `nuxi`, `sv create`, or
`create-astro`. Dependency installation is skipped so the scaffold step stays fast: run your
package manager inside `frontend/` afterwards. Requires Node and network access; the Laravel
application itself is untouched, making it a natural API backend. `--ui=angular` from
earlier releases keeps working as an alias for `--spa=angular`. Both options are rejected
when combined with a starter kit.

Do not mix mutually exclusive choices such as two package-manager flags or both test-framework flags. In automation, specify all choices and pass `--no-interaction`.

## `laravel package [name]`

Creates a Laravel package using Laravel's package skeleton. Feature flags add components: `--config`, `--routes`, `--views`, `--translations`, `--migrations`, `--assets`, `--commands`, `--facade`, and `--boost-skill`. Metadata flags include author, package, namespace, and class details.

```sh
laravel package activity-log --config --migrations --author-name="Acme" --no-interaction
```

`--force` replaces an existing target directory and must be used deliberately. It is refused for the current directory.