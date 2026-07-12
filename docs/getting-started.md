# Getting started

## Install Laravel Installer

Install the global executable with Composer:

```sh
composer global require laravel/installer
laravel --version
```

Ensure Composer's global `vendor/bin` folder is on your `PATH`. The installer requires PHP 8.2+. Composer is required to create applications. Git, a Node package manager, and the GitHub CLI are optional and used only when their related features are selected.

> **Running this fork as your `laravel` command (Laravel Herd on Windows):** Herd ships its own
> `laravel` shim (`%USERPROFILE%\.config\herd\bin\laravel.bat`) that runs a bundled phar and sits
> first on `PATH`, shadowing other installs. To use this fork everywhere, back that file up and
> repoint it at this repository:
>
> ```bat
> @ECHO OFF
> php "C:\path\to\laravel-installer\bin\laravel" %*
> ```
>
> The bare `php` resolves through Herd's own shim to a current PHP (8.4+), which Laravel 13
> application skeletons require. Note that a Herd update may restore the original shim — if
> `laravel web` ever reports an unknown command again, re-apply this edit.

## Create an application

Run the command from the folder that should contain the project:

```sh
laravel new blog
cd blog
composer run dev
```

In an interactive terminal, the installer prompts for the application name, frontend starter kit, database, test framework, Node package manager, Laravel Boost, and Git/GitHub setup. Names may contain letters, numbers, dashes, underscores, and periods.

For scripts and CI, provide explicit choices and disable prompts:

```sh
laravel new blog --react --database=pgsql --pest --pnpm --git --no-interaction
```

## Recipes

```sh
# Blank Laravel application, no authentication scaffolding or Node setup
laravel new api --no-authentication --database=sqlite --no-node

# Blank Laravel application scaffolded with Bootstrap 5
# (also: --ui=coreui, --ui=adminlte, --ui=laravel-adminlte)
laravel new admin --ui=bootstrap --no-authentication --database=sqlite --npm

# Laravel API backend + a Next.js SPA scaffolded in frontend/
# (also: --spa=angular, --spa=nuxt, --spa=sveltekit, --spa=astro)
laravel new platform --spa=next --no-authentication --database=sqlite --no-node

# Vue starter kit and a public GitHub repository
laravel new portal --vue --github=--public --organization=acme

# Latest Laravel development release
laravel new preview --dev

# Replace an existing target directory (destructive)
laravel new demo --force
```

`--github` needs the `gh` CLI to be installed and authenticated. If it is not available, the installer warns and skips publishing.