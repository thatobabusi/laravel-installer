<?php

namespace Laravel\Installer\Console\Tests\Unit;

use Laravel\AgentDetector\AgentDetector;
use Laravel\Installer\Console\WebCommand;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class WebCommandFlagsTest extends TestCase
{
    protected function command()
    {
        return new class extends WebCommand
        {
            public function flagsPublic(array $job): array
            {
                return $this->flagsForJob($job);
            }

            public function cleanEnvironmentPublic(): array
            {
                return $this->cleanEnvironment();
            }
        };
    }

    public function test_blank_blade_defaults()
    {
        $flags = $this->command()->flagsPublic(['stack' => 'blade']);

        $this->assertSame(
            ['--no-authentication', '--database=sqlite', '--pest', '--npm', '--no-boost'],
            $flags
        );
    }

    public function test_blank_blade_maps_ui_presets()
    {
        foreach (['bootstrap', 'coreui', 'adminlte', 'laravel-adminlte', 'bulma', 'uikit', 'pico'] as $preset) {
            $flags = $this->command()->flagsPublic(['stack' => 'blade', 'ui' => $preset]);

            $this->assertContains('--ui='.$preset, $flags);
        }
    }

    public function test_blank_blade_maps_js_enhancements_and_theme()
    {
        foreach (['alpine', 'htmx', 'jquery', 'stimulus'] as $js) {
            $flags = $this->command()->flagsPublic(['stack' => 'blade', 'js' => $js, 'theme' => true]);

            $this->assertContains('--js='.$js, $flags);
            $this->assertContains('--theme', $flags);
        }

        $reactFlags = $this->command()->flagsPublic(['stack' => 'react', 'js' => 'alpine', 'theme' => true]);
        $this->assertSame([], preg_grep('/^--js=/', $reactFlags));
        $this->assertNotContains('--theme', $reactFlags);
    }

    public function test_project_types_map_to_the_type_flag()
    {
        foreach (['api', 'dashboard'] as $type) {
            $flags = $this->command()->flagsPublic(['stack' => 'blade', 'type' => $type]);

            $this->assertContains('--type='.$type, $flags);
        }

        $webFlags = $this->command()->flagsPublic(['stack' => 'blade', 'type' => 'web']);
        $this->assertSame([], preg_grep('/^--type=/', $webFlags));
    }

    public function test_spa_stacks_map_to_the_spa_flag()
    {
        foreach (['angular', 'next', 'nuxt', 'sveltekit', 'astro'] as $spa) {
            $flags = $this->command()->flagsPublic(['stack' => $spa]);

            $this->assertContains('--spa='.$spa, $flags);
            $this->assertContains('--no-authentication', $flags);
            $this->assertSame([], preg_grep('/^--ui=/', $flags));
        }
    }

    public function test_ui_preset_is_ignored_for_non_blade_blank_stacks()
    {
        $flags = $this->command()->flagsPublic(['stack' => 'react', 'ui' => 'bootstrap']);

        $this->assertContains('--react', $flags);
        $this->assertNotContains('--ui=bootstrap', $flags);
    }

    public function test_unknown_ui_preset_is_dropped()
    {
        $flags = $this->command()->flagsPublic(['stack' => 'blade', 'ui' => 'jquery-ui']);

        $this->assertSame([], preg_grep('/^--ui=/', $flags));
    }

    public function test_starter_kit_stack_options()
    {
        $flags = $this->command()->flagsPublic([
            'starterKit' => true,
            'stack' => 'react',
            'auth' => 'workos',
            'teams' => true,
        ]);

        $this->assertContains('--react', $flags);
        $this->assertContains('--workos', $flags);
        $this->assertContains('--teams', $flags);
        $this->assertNotContains('--no-authentication', $flags);
    }

    public function test_livewire_class_components_only_apply_to_livewire()
    {
        $livewire = $this->command()->flagsPublic([
            'starterKit' => true,
            'stack' => 'livewire',
            'livewireClassComponents' => true,
        ]);

        $react = $this->command()->flagsPublic([
            'starterKit' => true,
            'stack' => 'react',
            'livewireClassComponents' => true,
        ]);

        $this->assertContains('--livewire-class-components', $livewire);
        $this->assertNotContains('--livewire-class-components', $react);
    }

    public function test_starter_kit_rejects_invalid_stack()
    {
        $this->expectException(RuntimeException::class);

        $this->command()->flagsPublic(['starterKit' => true, 'stack' => 'blade']);
    }

    public function test_custom_starter_kit_overrides_other_selections()
    {
        $flags = $this->command()->flagsPublic(['using' => 'acme/starter-kit', 'stack' => 'react', 'starterKit' => true]);

        $this->assertContains('--using=acme/starter-kit', $flags);
        $this->assertNotContains('--react', $flags);
    }

    public function test_custom_starter_kit_rejects_shell_metacharacters()
    {
        $this->expectException(RuntimeException::class);

        $this->command()->flagsPublic(['using' => 'acme/kit; rm -rf /']);
    }

    public function test_invalid_database_is_rejected()
    {
        $this->expectException(RuntimeException::class);

        $this->command()->flagsPublic(['stack' => 'blade', 'database' => 'mongodb']);
    }

    public function test_testing_node_and_boost_mappings()
    {
        $flags = $this->command()->flagsPublic([
            'stack' => 'blade',
            'database' => 'pgsql',
            'testing' => 'phpunit',
            'node' => 'bun',
            'boost' => true,
        ]);

        $this->assertContains('--database=pgsql', $flags);
        $this->assertContains('--phpunit', $flags);
        $this->assertContains('--bun', $flags);
        $this->assertContains('--boost', $flags);

        $skip = $this->command()->flagsPublic(['stack' => 'blade', 'node' => 'skip']);

        $this->assertContains('--no-node', $skip);
    }

    public function test_github_flags_and_organization_validation()
    {
        $private = $this->command()->flagsPublic(['stack' => 'blade', 'github' => 'private', 'organization' => 'babusi-group']);
        $this->assertContains('--github', $private);
        $this->assertContains('--organization=babusi-group', $private);

        $public = $this->command()->flagsPublic(['stack' => 'blade', 'github' => 'public']);
        $this->assertContains('--github=--public', $public);

        $gitOnly = $this->command()->flagsPublic(['stack' => 'blade', 'git' => true]);
        $this->assertContains('--git', $gitOnly);
        $this->assertNotContains('--github', $gitOnly);

        $this->expectException(RuntimeException::class);
        $this->command()->flagsPublic(['stack' => 'blade', 'github' => 'private', 'organization' => 'bad org!']);
    }

    public function test_clean_environment_disables_every_agent_variable()
    {
        $env = $this->command()->cleanEnvironmentPublic();

        $this->assertFalse($env['AI_AGENT']);

        foreach (array_keys(AgentDetector::AGENT_ENV_VARS) as $variable) {
            $this->assertArrayHasKey($variable, $env);
            $this->assertFalse($env[$variable]);
        }
    }
}
