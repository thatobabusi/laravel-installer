# CLI guide

The executable provides `new`, `package`, and `web`. All commands accept standard console options including `--help`, `--no-interaction`, `--quiet`, and `--no-ansi`. The [generated command reference](reference/command-help.md) is authoritative for option spelling and defaults.

## `laravel new <name>`

Creates a Laravel application. `--react`, `--svelte`, `--vue`, and `--livewire` select a first-party starter kit. Use `--no-authentication` for the corresponding blank kit. `--using=vendor/package` installs a community kit and overrides first-party starter-kit selection.

| Area | Options |
| --- | --- |
| Database | `--database=mysql|mariadb|pgsql|sqlite|sqlsrv` |
| Tests | `--pest`, `--phpunit` |
| Frontend | `--npm`, `--pnpm`, `--bun`, `--yarn`, `--no-node` |
| UI preset (vanilla only) | `--ui=bootstrap|coreui` |
| Authentication | `--workos`, `--teams`, `--livewire-class-components` |
| AI tooling | `--boost`, `--no-boost` |
| Git and GitHub | `--git`, `--branch`, `--github`, `--organization` |

`--ui` applies a UI preset to a vanilla (no starter kit) application: it swaps the skeleton's
Tailwind scaffolding for Bootstrap 5 or CoreUI 5 by updating `package.json`, the Vite entry
points (`resources/css/app.css`, `resources/js/app.js`), and the Vite configuration. It is
rejected when combined with a starter kit, since kits ship their own frontend stack.

Do not mix mutually exclusive choices such as two package-manager flags or both test-framework flags. In automation, specify all choices and pass `--no-interaction`.

## `laravel package [name]`

Creates a Laravel package using Laravel's package skeleton. Feature flags add components: `--config`, `--routes`, `--views`, `--translations`, `--migrations`, `--assets`, `--commands`, `--facade`, and `--boost-skill`. Metadata flags include author, package, namespace, and class details.

```sh
laravel package activity-log --config --migrations --author-name="Acme" --no-interaction
```

`--force` replaces an existing target directory and must be used deliberately. It is refused for the current directory.