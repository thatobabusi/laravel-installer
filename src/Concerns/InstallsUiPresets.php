<?php

namespace Laravel\Installer\Console\Concerns;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

trait InstallsUiPresets
{
    /**
     * The available vanilla UI presets.
     *
     * Presets with "packages" swap the default Tailwind scaffolding for
     * another CSS framework by editing package.json, the Vite entry points,
     * and the Vite configuration. Presets with "commands" run installer
     * commands (Composer packages, SPA scaffolds) inside the new application.
     */
    protected static array $uiPresets = [
        'bootstrap' => [
            'label' => 'Bootstrap 5',
            'packages' => ['bootstrap' => '^5.3', '@popperjs/core' => '^2.11'],
            'css' => "@import 'bootstrap/dist/css/bootstrap.min.css';\n",
            'js' => "import 'bootstrap';\n",
        ],
        'coreui' => [
            'label' => 'CoreUI 5',
            'packages' => ['@coreui/coreui' => '^5.4', '@popperjs/core' => '^2.11'],
            'css' => "@import '@coreui/coreui/dist/css/coreui.min.css';\n",
            'js' => "import '@coreui/coreui';\n",
        ],
        'adminlte' => [
            'label' => 'AdminLTE 4',
            'packages' => ['admin-lte' => '^4.0', 'bootstrap' => '^5.3', '@popperjs/core' => '^2.11'],
            'css' => "@import 'admin-lte/dist/css/adminlte.min.css';\n",
            'js' => "import * as bootstrap from 'bootstrap';\nimport 'admin-lte/dist/js/adminlte.min.js';\n",
        ],
        'laravel-adminlte' => [
            'label' => 'Laravel AdminLTE',
            'commands' => [
                '@composer require jeroennoten/laravel-adminlte',
                '@php artisan adminlte:install',
            ],
        ],
        'bulma' => [
            'label' => 'Bulma',
            'packages' => ['bulma' => '^1.0'],
            'css' => "@import 'bulma/css/bulma.min.css';\n",
        ],
        'uikit' => [
            'label' => 'UIkit',
            'packages' => ['uikit' => '^3.21'],
            'css' => "@import 'uikit/dist/css/uikit.min.css';\n",
            'js' => "import UIkit from 'uikit';\nimport Icons from 'uikit/dist/js/uikit-icons';\nUIkit.use(Icons);\nwindow.UIkit = UIkit;\n",
        ],
        'pico' => [
            'label' => 'Pico CSS',
            'packages' => ['@picocss/pico' => '^2.0'],
            'css' => "@import '@picocss/pico/css/pico.min.css';\n",
        ],
    ];

    /**
     * The available JavaScript enhancements for Blade applications.
     *
     * Additive — npm packages plus an app.js bootstrap, leaving the CSS
     * setup (Tailwind or a UI preset) untouched.
     */
    protected static array $jsEnhancements = [
        'alpine' => [
            'label' => 'Alpine.js',
            'packages' => ['alpinejs' => '^3.14'],
            'js' => "import Alpine from 'alpinejs';\n\nwindow.Alpine = Alpine;\n\nAlpine.start();\n",
        ],
        'htmx' => [
            'label' => 'HTMX',
            'packages' => ['htmx.org' => '^2.0'],
            'js' => "import htmx from 'htmx.org';\n\nwindow.htmx = htmx;\n",
        ],
        'jquery' => [
            'label' => 'jQuery',
            'packages' => ['jquery' => '^3.7'],
            'js' => "import \$ from 'jquery';\n\nwindow.\$ = window.jQuery = \$;\n",
        ],
        'stimulus' => [
            'label' => 'Stimulus',
            'packages' => ['@hotwired/stimulus' => '^3.2'],
            'js' => "import { Application } from '@hotwired/stimulus';\n\nwindow.Stimulus = Application.start();\n",
        ],
    ];

    /**
     * The available project types.
     *
     * "web" is the plain skeleton; the others run additional setup after
     * the application is created.
     */
    protected static array $projectTypes = [
        'web' => [
            'label' => 'Web application',
            'commands' => [],
        ],
        'api' => [
            'label' => 'API only',
            'commands' => ['@php artisan install:api --no-interaction'],
        ],
        'dashboard' => [
            'label' => 'Filament dashboard',
            'commands' => [
                '@composer require filament/filament -W',
                '@php artisan filament:install --panels --no-interaction',
            ],
        ],
    ];

    /**
     * The available SPA frontend scaffolds.
     *
     * Each scaffold creates a standalone frontend workspace in frontend/
     * alongside the Laravel backend. Dependency installation is skipped so
     * the scaffold step stays fast — run the package manager inside
     * frontend/ afterwards.
     */
    protected static array $spaScaffolds = [
        'angular' => [
            'label' => 'Angular',
            'commands' => ['npx --yes @angular/cli@latest new frontend --defaults --skip-git --skip-install'],
        ],
        'next' => [
            'label' => 'Next.js',
            'commands' => ['npx --yes create-next-app@latest frontend --yes --ts --eslint --app --no-src-dir --tailwind --import-alias "@/*" --use-npm --skip-install'],
        ],
        'nuxt' => [
            'label' => 'Nuxt',
            'commands' => ['npx --yes nuxi@latest init frontend --template v4 --packageManager npm --no-gitInit --no-install'],
        ],
        'sveltekit' => [
            'label' => 'SvelteKit',
            'commands' => ['npx --yes sv@latest create frontend --template minimal --types ts --no-add-ons --no-install'],
        ],
        'astro' => [
            'label' => 'Astro',
            'commands' => ['npx --yes create-astro@latest frontend --template minimal --no-install --no-git --yes'],
        ],
    ];

    /**
     * Validate the UI preset input.
     *
     * @throws InvalidArgumentException
     */
    protected function validateUiOption(InputInterface $input): void
    {
        // --ui=angular predates the --spa option; keep it working as an alias...
        if ($input->getOption('ui') === 'angular') {
            $input->setOption('ui', null);

            if (! $input->getOption('spa')) {
                $input->setOption('spa', 'angular');
            }
        }

        $ui = $input->getOption('ui');

        if (! $ui) {
            return;
        }

        if (! array_key_exists($ui, static::$uiPresets)) {
            throw new InvalidArgumentException(
                "Invalid UI preset [{$ui}]. Possible values are: ".implode(', ', array_keys(static::$uiPresets)).'.'
            );
        }

        if ($this->usingStarterKit($input)) {
            throw new InvalidArgumentException(
                'The --ui option only applies to vanilla applications. Starter kits ship their own frontend stack.'
            );
        }
    }

    /**
     * Validate the JavaScript enhancement input.
     *
     * @throws InvalidArgumentException
     */
    protected function validateJsOption(InputInterface $input): void
    {
        $js = $input->getOption('js');

        if (! $js) {
            return;
        }

        if (! array_key_exists($js, static::$jsEnhancements)) {
            throw new InvalidArgumentException(
                "Invalid JavaScript enhancement [{$js}]. Possible values are: ".implode(', ', array_keys(static::$jsEnhancements)).'.'
            );
        }

        if ($this->usingStarterKit($input)) {
            throw new InvalidArgumentException(
                'The --js option only applies to vanilla applications. Starter kits ship their own frontend stack.'
            );
        }
    }

    /**
     * Validate the project type input.
     *
     * @throws InvalidArgumentException
     */
    protected function validateTypeOption(InputInterface $input): void
    {
        $type = $input->getOption('type');

        if (! $type) {
            return;
        }

        if (! array_key_exists($type, static::$projectTypes)) {
            throw new InvalidArgumentException(
                "Invalid project type [{$type}]. Possible values are: ".implode(', ', array_keys(static::$projectTypes)).'.'
            );
        }

        if ($type !== 'web' && $this->usingStarterKit($input)) {
            throw new InvalidArgumentException(
                'The --type option only applies to vanilla applications. Starter kits ship their own scaffolding.'
            );
        }
    }

    /**
     * Validate the SPA scaffold input.
     *
     * @throws InvalidArgumentException
     */
    protected function validateSpaOption(InputInterface $input): void
    {
        $spa = $input->getOption('spa');

        if (! $spa) {
            return;
        }

        if (! array_key_exists($spa, static::$spaScaffolds)) {
            throw new InvalidArgumentException(
                "Invalid SPA scaffold [{$spa}]. Possible values are: ".implode(', ', array_keys(static::$spaScaffolds)).'.'
            );
        }

        if ($this->usingStarterKit($input)) {
            throw new InvalidArgumentException(
                'The --spa option only applies to vanilla applications. Starter kits ship their own frontend stack.'
            );
        }
    }

    /**
     * Apply the selected UI preset to the freshly created application.
     */
    protected function installUiPreset(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $preset = static::$uiPresets[$input->getOption('ui')];

        if (isset($preset['packages'])) {
            $this->swapNodeDependenciesForPreset($directory, $preset);
            $this->writePresetEntryPoints($directory, $preset);
            $this->removeTailwindVitePlugin($directory);
        }

        if (! empty($preset['commands'])) {
            $output->writeln("  Applying the {$preset['label']} UI preset...");

            $this->runPresetCommands($preset, $directory, $output);
        }

        $output->writeln("  <fg=green>{$preset['label']} UI preset applied.</>");

        $this->commitChanges("Install {$preset['label']} UI preset", $directory, $input, $output);
    }

    /**
     * Add the selected JavaScript enhancement to the application.
     */
    protected function installJsEnhancement(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $enhancement = static::$jsEnhancements[$input->getOption('js')];

        $this->addNodeDependencies($directory, $enhancement['packages']);
        $this->appendToAppJs($directory, $enhancement['js']);

        $output->writeln("  <fg=green>{$enhancement['label']} added.</>");

        $this->commitChanges("Add {$enhancement['label']}", $directory, $input, $output);
    }

    /**
     * Run the selected project type's additional setup.
     */
    protected function installProjectType(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $type = static::$projectTypes[$input->getOption('type')];

        if (empty($type['commands'])) {
            return;
        }

        $output->writeln("  Setting up the {$type['label']} project type...");

        $this->runPresetCommands(['label' => $type['label']] + $type, $directory, $output);

        $output->writeln("  <fg=green>{$type['label']} setup complete.</>");

        $this->commitChanges("Set up {$type['label']}", $directory, $input, $output);
    }

    /**
     * Write the light/dark theme helper and import it from app.js.
     *
     * Sets data-theme + data-bs-theme on <html> and toggles the "dark"
     * class, covering Tailwind, Bootstrap 5.3+, and custom CSS hooks.
     */
    protected function installThemeToggle(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $themePath = $directory.'/resources/js/theme.js';

        if (! is_dir(dirname($themePath))) {
            return;
        }

        file_put_contents($themePath, implode("\n", [
            "const apply = (theme) => {",
            "    document.documentElement.dataset.theme = theme;",
            "    document.documentElement.setAttribute('data-bs-theme', theme);",
            "    document.documentElement.classList.toggle('dark', theme === 'dark');",
            "};",
            '',
            "const stored = localStorage.getItem('theme');",
            "const preferred = stored ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');",
            '',
            'apply(preferred);',
            '',
            "window.setTheme = (theme) => {",
            "    localStorage.setItem('theme', theme);",
            '    apply(theme);',
            '};',
            '',
            "window.toggleTheme = () => {",
            "    window.setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');",
            '};',
            '',
        ]));

        $this->appendToAppJs($directory, "import './theme';\n");

        $output->writeln('  <fg=green>Light/dark theme helper added (window.toggleTheme).</>');

        $this->commitChanges('Add theme toggle helper', $directory, $input, $output);
    }

    /**
     * Add npm packages to the application without touching existing ones.
     */
    protected function addNodeDependencies(string $directory, array $packages): void
    {
        $packageJsonPath = $directory.'/package.json';

        if (! file_exists($packageJsonPath)) {
            return;
        }

        $packageJson = json_decode((string) file_get_contents($packageJsonPath), true);

        if (! is_array($packageJson)) {
            return;
        }

        foreach ($packages as $package => $version) {
            $packageJson['devDependencies'][$package] = $version;
        }

        ksort($packageJson['devDependencies']);

        file_put_contents(
            $packageJsonPath,
            json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }

    /**
     * Append the given JavaScript to the application's app.js entry point.
     */
    protected function appendToAppJs(string $directory, string $javascript): void
    {
        $jsPath = $directory.'/resources/js/app.js';

        if (file_exists($jsPath)) {
            file_put_contents($jsPath, rtrim((string) file_get_contents($jsPath))."\n\n".$javascript);
        }
    }

    /**
     * Scaffold the selected SPA frontend workspace alongside the application.
     */
    protected function installSpaScaffold(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $scaffold = static::$spaScaffolds[$input->getOption('spa')];

        $output->writeln("  Scaffolding the {$scaffold['label']} frontend in frontend/...");

        $this->runPresetCommands($scaffold, $directory, $output);

        $output->writeln("  <fg=green>{$scaffold['label']} frontend scaffolded. Run your package manager inside frontend/ to install its dependencies.</>");

        $this->commitChanges("Scaffold {$scaffold['label']} frontend", $directory, $input, $output);
    }

    /**
     * Run the given preset's commands verbatim inside the application.
     *
     * Tools like the Angular CLI reject the --no-ansi / --quiet flags that
     * runCommands() appends, so preset commands bypass that decoration.
     *
     * @throws RuntimeException
     */
    protected function runPresetCommands(array $preset, string $directory, OutputInterface $output): void
    {
        foreach ($preset['commands'] as $command) {
            $process = Process::fromShellCommandline(
                $this->normalizeInstallerHookCommand($command),
                $directory,
                null,
                null,
                null,
            );

            $process->run(function ($type, $line) use ($output) {
                $output->write('    '.$line);
            });

            if (! $process->isSuccessful()) {
                throw new RuntimeException("The {$preset['label']} preset failed to install. Command failed: {$command}");
            }
        }
    }

    /**
     * Replace the Tailwind npm dependencies with the preset's packages.
     */
    protected function swapNodeDependenciesForPreset(string $directory, array $preset): void
    {
        $packageJsonPath = $directory.'/package.json';

        if (! file_exists($packageJsonPath)) {
            return;
        }

        $packageJson = json_decode((string) file_get_contents($packageJsonPath), true);

        if (! is_array($packageJson)) {
            return;
        }

        unset(
            $packageJson['devDependencies']['tailwindcss'],
            $packageJson['devDependencies']['@tailwindcss/vite'],
        );

        foreach ($preset['packages'] as $package => $version) {
            $packageJson['devDependencies'][$package] = $version;
        }

        ksort($packageJson['devDependencies']);

        file_put_contents(
            $packageJsonPath,
            json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }

    /**
     * Point the Vite CSS and JavaScript entry points at the preset framework.
     */
    protected function writePresetEntryPoints(string $directory, array $preset): void
    {
        $cssPath = $directory.'/resources/css/app.css';

        if (file_exists($cssPath)) {
            file_put_contents($cssPath, $preset['css']);
        }

        if (isset($preset['js'])) {
            $this->appendToAppJs($directory, $preset['js']);
        }
    }

    /**
     * Remove the Tailwind plugin from the application's Vite configuration.
     */
    protected function removeTailwindVitePlugin(string $directory): void
    {
        foreach (['vite.config.js', 'vite.config.ts', 'vite.config.mjs'] as $config) {
            $configPath = $directory.'/'.$config;

            if (! file_exists($configPath)) {
                continue;
            }

            $contents = (string) file_get_contents($configPath);

            $contents = (string) preg_replace('/^import tailwindcss from .@tailwindcss\/vite.;\r?\n/m', '', $contents);
            $contents = (string) preg_replace('/^\s*tailwindcss\(\),?\r?\n/m', '', $contents);

            file_put_contents($configPath, $contents);
        }
    }
}
