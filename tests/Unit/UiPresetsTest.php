<?php

namespace Laravel\Installer\Console\Tests\Unit;

use InvalidArgumentException;
use Laravel\Installer\Console\NewCommand;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class UiPresetsTest extends TestCase
{
    protected string $appDirectory;

    protected function setUp(): void
    {
        $this->appDirectory = sys_get_temp_dir().'/ui-preset-test-'.bin2hex(random_bytes(4));

        mkdir($this->appDirectory.'/resources/css', 0777, true);
        mkdir($this->appDirectory.'/resources/js', 0777, true);

        file_put_contents($this->appDirectory.'/package.json', json_encode([
            'private' => true,
            'devDependencies' => [
                '@tailwindcss/vite' => '^4.0.0',
                'laravel-vite-plugin' => '^3.1',
                'tailwindcss' => '^4.0.0',
                'vite' => '^8.0.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        file_put_contents($this->appDirectory.'/resources/css/app.css', "@import 'tailwindcss';\n");
        file_put_contents($this->appDirectory.'/resources/js/app.js', "// bootstrap the app\n");

        file_put_contents($this->appDirectory.'/vite.config.js', implode("\n", [
            "import { defineConfig } from 'vite';",
            "import laravel from 'laravel-vite-plugin';",
            "import tailwindcss from '@tailwindcss/vite';",
            '',
            'export default defineConfig({',
            '    plugins: [',
            '        laravel({',
            "            input: ['resources/css/app.css', 'resources/js/app.js'],",
            '            refresh: true,',
            '        }),',
            '        tailwindcss(),',
            '    ],',
            '});',
            '',
        ]));
    }

    protected function tearDown(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            exec('rd /s /q "'.str_replace('/', '\\', $this->appDirectory).'" 2>nul');
        } else {
            exec('rm -rf '.escapeshellarg($this->appDirectory));
        }
    }

    protected function command()
    {
        return new class extends NewCommand
        {
            public function validateUiPublic(array $options): void
            {
                $this->validateUiOption(new ArrayInput($options, $this->getDefinition()));
            }

            public function presetPublic(string $name): array
            {
                return static::$uiPresets[$name];
            }

            public function swapPublic(string $directory, array $preset): void
            {
                $this->swapNodeDependenciesForPreset($directory, $preset);
            }

            public function entryPointsPublic(string $directory, array $preset): void
            {
                $this->writePresetEntryPoints($directory, $preset);
            }

            public function removeTailwindPublic(string $directory): void
            {
                $this->removeTailwindVitePlugin($directory);
            }
        };
    }

    public function test_every_documented_preset_exists()
    {
        $command = $this->command();

        foreach (['bootstrap', 'coreui', 'adminlte', 'laravel-adminlte', 'angular'] as $preset) {
            $this->assertNotEmpty($command->presetPublic($preset)['label']);
        }
    }

    public function test_it_rejects_unknown_presets()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UI preset');

        $this->command()->validateUiPublic(['name' => 'demo', '--ui' => 'jquery-ui']);
    }

    public function test_it_rejects_presets_combined_with_starter_kits()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('vanilla applications');

        $this->command()->validateUiPublic(['name' => 'demo', '--ui' => 'bootstrap', '--react' => true]);
    }

    public function test_valid_presets_pass_validation()
    {
        $this->command()->validateUiPublic(['name' => 'demo', '--ui' => 'coreui']);
        $this->command()->validateUiPublic(['name' => 'demo']);

        $this->assertTrue(true);
    }

    public function test_it_swaps_tailwind_for_the_preset_packages()
    {
        $command = $this->command();

        $command->swapPublic($this->appDirectory, $command->presetPublic('bootstrap'));

        $packageJson = json_decode(file_get_contents($this->appDirectory.'/package.json'), true);

        $this->assertArrayNotHasKey('tailwindcss', $packageJson['devDependencies']);
        $this->assertArrayNotHasKey('@tailwindcss/vite', $packageJson['devDependencies']);
        $this->assertArrayHasKey('bootstrap', $packageJson['devDependencies']);
        $this->assertArrayHasKey('@popperjs/core', $packageJson['devDependencies']);
        $this->assertArrayHasKey('vite', $packageJson['devDependencies']);
    }

    public function test_it_rewrites_the_vite_entry_points()
    {
        $command = $this->command();

        $command->entryPointsPublic($this->appDirectory, $command->presetPublic('coreui'));

        $this->assertStringContainsString(
            '@coreui/coreui/dist/css/coreui.min.css',
            file_get_contents($this->appDirectory.'/resources/css/app.css')
        );

        $js = file_get_contents($this->appDirectory.'/resources/js/app.js');
        $this->assertStringContainsString('// bootstrap the app', $js);
        $this->assertStringContainsString("import '@coreui/coreui';", $js);
    }

    public function test_it_removes_the_tailwind_vite_plugin()
    {
        $this->command()->removeTailwindPublic($this->appDirectory);

        $config = file_get_contents($this->appDirectory.'/vite.config.js');

        $this->assertStringNotContainsString('tailwindcss', $config);
        $this->assertStringContainsString('laravel({', $config);
        $this->assertStringContainsString('refresh: true', $config);
    }

    public function test_command_presets_run_and_fail_loudly()
    {
        $command = new class extends NewCommand
        {
            protected static array $uiPresets = [
                'marker' => [
                    'label' => 'Marker',
                    'commands' => ['php -r "fclose(fopen(\'preset-marker.txt\', \'w\'));"'],
                ],
                'broken' => [
                    'label' => 'Broken',
                    'commands' => ['php -r "exit(3);"'],
                ],
            ];

            public function installPublic(string $ui, string $directory): void
            {
                $input = new ArrayInput(['name' => 'demo', '--ui' => $ui], $this->getDefinition());

                $this->installUiPreset($directory, $input, new BufferedOutput);
            }
        };

        $command->installPublic('marker', $this->appDirectory);
        $this->assertFileExists($this->appDirectory.'/preset-marker.txt');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Broken UI preset failed');

        $command->installPublic('broken', $this->appDirectory);
    }
}
