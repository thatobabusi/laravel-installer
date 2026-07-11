<?php

namespace Laravel\Installer\Console\Concerns;

use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait InstallsUiPresets
{
    /**
     * The available vanilla UI presets.
     *
     * Each preset swaps the default Tailwind scaffolding for another CSS
     * framework by editing package.json, the Vite entry points, and the
     * Vite configuration of the freshly created application.
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
    ];

    /**
     * Validate the UI preset input.
     *
     * @throws InvalidArgumentException
     */
    protected function validateUiOption(InputInterface $input): void
    {
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
     * Apply the selected UI preset to the freshly created application.
     */
    protected function installUiPreset(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $preset = static::$uiPresets[$input->getOption('ui')];

        $this->swapNodeDependenciesForPreset($directory, $preset);
        $this->writePresetEntryPoints($directory, $preset);
        $this->removeTailwindVitePlugin($directory);

        $output->writeln("  <fg=green>{$preset['label']} UI preset applied.</>");

        $this->commitChanges("Install {$preset['label']} UI preset", $directory, $input, $output);
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

        $jsPath = $directory.'/resources/js/app.js';

        if (file_exists($jsPath)) {
            file_put_contents($jsPath, rtrim((string) file_get_contents($jsPath))."\n\n".$preset['js']);
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
