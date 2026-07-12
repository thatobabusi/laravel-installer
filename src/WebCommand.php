<?php

namespace Laravel\Installer\Console;

use Laravel\AgentDetector\AgentDetector;
use Laravel\Installer\Console\Concerns\InteractsWithHerdOrValet;
use Override;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function Illuminate\Filesystem\join_paths;

class WebCommand extends Command
{
    use InteractsWithHerdOrValet;

    /**
     * The state directory shared with the web server process.
     */
    protected string $stateDir;

    /**
     * The directory new applications will be installed into.
     */
    protected string $targetDirectory;

    /** The active asynchronous application installation. */
    protected ?Process $installProcess = null;

    /** @var array{name: string, directory: string}|null */
    protected ?array $activeInstall = null;

    /**
     * Configure the command options.
     */
    #[Override]
    protected function configure(): void
    {
        $this
            ->setName('web')
            ->setDescription('Launch the web-based Laravel installer in your browser')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'The port to serve the installer UI on')
            ->addOption('no-open', null, InputOption::VALUE_NONE, 'Do not automatically open the browser');
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->targetDirectory = (string) getcwd();
        $this->stateDir = $this->createStateDirectory();

        $port = (int) ($input->getOption('port') ?: $this->findAvailablePort());

        $this->writeStatus(['state' => 'idle']);

        $server = $this->startServer($port);

        if (! $this->waitForServer($port)) {
            $output->writeln('<error>Unable to start the installer web server.</error>');
            $output->writeln(trim($server->getErrorOutput()));

            return self::FAILURE;
        }

        $this->writePublicGateway($port);

        $url = "http://127.0.0.1:{$port}/";

        $output->writeln('');
        $output->writeln('  <fg=blue;options=bold>Laravel Web Installer</>');
        $output->writeln('');
        $output->writeln("  Serving the installer UI at: <options=bold>{$url}</>");
        $output->writeln("  New applications will be created in: <options=bold>{$this->targetDirectory}</>");
        $output->writeln('');
        $output->writeln('  Press <options=bold>Ctrl+C</> to stop the installer.');
        $output->writeln('');

        if (! $input->getOption('no-open')) {
            $this->openBrowser($url);
        }

        return $this->watchForJobs($server, $output);
    }

    /**
     * Watch the state directory for install jobs submitted by the UI.
     */
    protected function watchForJobs(Process $server, OutputInterface $output): int
    {
        $jobFile = join_paths($this->stateDir, 'job.json');
        $quitFile = join_paths($this->stateDir, 'quit');

        while (true) {
            if (! $server->isRunning()) {
                $output->writeln('<error>The installer web server stopped unexpectedly.</error>');

                $this->removePublicGateway();

                return self::FAILURE;
            }

            $this->completeInstallIfFinished($output);

            if (file_exists($quitFile)) {
                $output->writeln('  Installer closed from the browser. Goodbye!');
                $server->stop();

                $this->removePublicGateway();

                return self::SUCCESS;
            }

            if (file_exists($jobFile)) {
                $job = json_decode((string) file_get_contents($jobFile), true);

                @unlink($jobFile);

                if (is_array($job)) {
                    $this->runInstallJob($job, $output);
                }
            }

            usleep(250_000);
        }
    }

    /**
     * Run a single install job by invoking the "new" command as a subprocess.
     */
    protected function runInstallJob(array $job, OutputInterface $output): void
    {
        $logPath = join_paths($this->stateDir, 'install.log');

        file_put_contents($logPath, '');

        $name = $job['name'] ?? null;

        if (! is_string($name) || $name === '' || preg_match('/[^\pL\pN\-_.]/', $name) !== 0) {
            $this->writeStatus(['state' => 'failed', 'error' => 'Invalid project name.']);

            return;
        }

        $workingDirectory = $this->targetDirectory;
        $location = $job['location'] ?? null;

        if (is_string($location) && $location !== '') {
            if (! is_dir($location)) {
                $this->writeStatus(['state' => 'failed', 'name' => $name, 'error' => 'The target location does not exist.']);

                return;
            }

            $workingDirectory = $location;
        }

        $directory = join_paths($workingDirectory, $name);

        if (is_dir($directory) || is_file($directory)) {
            $this->writeStatus(['state' => 'failed', 'name' => $name, 'error' => 'Application already exists.']);

            return;
        }

        try {
            $flags = $this->flagsForJob($job);
        } catch (RuntimeException $e) {
            $this->writeStatus(['state' => 'failed', 'name' => $name, 'error' => $e->getMessage()]);

            return;
        }

        $console = ($job['type'] ?? null) === 'package' ? 'package' : 'new';

        $command = [
            $this->phpBinary(),
            $this->installerBinary(),
            $console,
            $name,
            ...($console === 'package' ? [] : $flags),
            '--no-interaction',
            '--no-ansi',
        ];

        $this->writeStatus([
            'state' => 'running',
            'name' => $name,
            'directory' => $directory,
            'startedAt' => time(),
        ]);

        $output->writeln("  Creating application <options=bold>{$name}</>...");

        $this->installProcess = new Process($command, $workingDirectory, $this->cleanEnvironment(), null, null);
        $this->installProcess->setTimeout(null);
        $this->activeInstall = compact('name', 'directory');
        $this->installProcess->start(function ($type, $line) use ($logPath) {
            file_put_contents($logPath, $line, FILE_APPEND);
        });
    }

    /** Finalize an asynchronous install without blocking API status polling. */
    protected function completeInstallIfFinished(OutputInterface $output): void
    {
        if ($this->installProcess === null || $this->installProcess->isRunning() || $this->activeInstall === null) {
            return;
        }

        $process = $this->installProcess;
        $name = $this->activeInstall['name'];
        $directory = $this->activeInstall['directory'];

        if ($process->isSuccessful()) {
            $this->writeStatus(['state' => 'success', 'name' => $name, 'directory' => $directory, 'url' => $this->appUrl($name, $directory), 'finishedAt' => time()]);
            $output->writeln("  <fg=green>Application [{$name}] created successfully.</>");
        } else {
            $this->writeStatus(['state' => 'failed', 'name' => $name, 'directory' => $directory, 'exitCode' => $process->getExitCode(), 'error' => 'The installation failed. Check the log output for details.', 'finishedAt' => time()]);
            $output->writeln("  <fg=red>Application [{$name}] failed to install (exit code {$process->getExitCode()}).</>");
        }

        $this->installProcess = null;
        $this->activeInstall = null;
    }

    /**
     * Translate a validated job payload into "laravel new" flags.
     *
     * @throws RuntimeException
     */
    protected function flagsForJob(array $job): array
    {
        $flags = [];

        $stack = $job['stack'] ?? 'blade';
        $using = $job['using'] ?? null;

        if (is_string($using) && $using !== '') {
            if (preg_match('#^[A-Za-z0-9._/:@\-]+$#', $using) !== 1) {
                throw new RuntimeException('Invalid custom starter kit package name.');
            }

            $flags[] = '--using='.$using;
        } elseif (! empty($job['starterKit'])) {
            if (! in_array($stack, ['react', 'svelte', 'vue', 'livewire'], true)) {
                throw new RuntimeException('Invalid frontend stack for a starter kit.');
            }

            $flags[] = '--'.$stack;

            if (($job['auth'] ?? 'laravel') === 'workos') {
                $flags[] = '--workos';
            }

            if ($stack === 'livewire' && ! empty($job['livewireClassComponents'])) {
                $flags[] = '--livewire-class-components';
            }

            if (! empty($job['teams'])) {
                $flags[] = '--teams';
            }
        } else {
            if (in_array($stack, ['react', 'svelte', 'vue', 'livewire'], true)) {
                $flags[] = '--'.$stack;
            } elseif (in_array($stack, ['angular', 'next', 'nuxt', 'sveltekit', 'astro'], true)) {
                $flags[] = '--spa='.$stack;
            } elseif (in_array($job['ui'] ?? null, ['bootstrap', 'coreui', 'adminlte', 'laravel-adminlte', 'bulma', 'uikit', 'pico'], true)) {
                $flags[] = '--ui='.$job['ui'];
            }

            if ($stack === 'blade' && in_array($job['js'] ?? null, ['alpine', 'htmx', 'jquery', 'stimulus'], true)) {
                $flags[] = '--js='.$job['js'];
            }

            if ($stack === 'blade' && ! empty($job['theme'])) {
                $flags[] = '--theme';
            }

            if (in_array($job['type'] ?? null, ['api', 'dashboard'], true)) {
                $flags[] = '--type='.$job['type'];
            }

            $flags[] = '--no-authentication';
        }

        $database = $job['database'] ?? 'sqlite';

        if (! in_array($database, NewCommand::DATABASE_DRIVERS, true)) {
            throw new RuntimeException('Invalid database driver.');
        }

        $flags[] = '--database='.$database;

        $flags[] = ($job['testing'] ?? 'pest') === 'phpunit' ? '--phpunit' : '--pest';

        $flags[] = match ($job['node'] ?? 'npm') {
            'pnpm' => '--pnpm',
            'bun' => '--bun',
            'yarn' => '--yarn',
            'skip' => '--no-node',
            default => '--npm',
        };

        $flags[] = empty($job['boost']) ? '--no-boost' : '--boost';

        $github = $job['github'] ?? 'none';

        if (in_array($github, ['private', 'public'], true)) {
            $flags[] = $github === 'public' ? '--github=--public' : '--github';

            $organization = $job['organization'] ?? null;

            if (is_string($organization) && $organization !== '') {
                if (preg_match('/^[A-Za-z0-9._\-]+$/', $organization) !== 1) {
                    throw new RuntimeException('Invalid GitHub organization name.');
                }

                $flags[] = '--organization='.$organization;
            }
        } elseif (! empty($job['git'])) {
            $flags[] = '--git';
        }

        return $flags;
    }

    /**
     * Build an environment that prevents the child process from entering agent mode.
     */
    protected function cleanEnvironment(): array
    {
        $env = ['AI_AGENT' => false];

        foreach (array_keys(AgentDetector::AGENT_ENV_VARS) as $envVar) {
            $env[$envVar] = false;
        }

        return $env;
    }

    /**
     * Generate the local URL the finished application will be reachable at.
     */
    protected function appUrl(string $name, string $directory): string
    {
        if (! $this->isParkedOnHerdOrValet($directory)) {
            return 'http://localhost:8000';
        }

        $hostname = mb_strtolower($name).'.'.($this->runOnValetOrHerd('tld') ?: 'test');

        return gethostbyname($hostname.'.') !== $hostname.'.' ? 'http://'.$hostname : 'http://localhost';
    }

    /**
     * Create the temporary state directory shared with the web server.
     */
    protected function createStateDirectory(): string
    {
        $stateDir = join_paths(sys_get_temp_dir(), 'laravel-web-installer-'.bin2hex(random_bytes(4)));

        if (! mkdir($stateDir, 0700, true)) {
            throw new RuntimeException('Unable to create the installer state directory.');
        }

        return $stateDir;
    }

    /**
     * Atomically write the shared status file.
     */
    protected function writeStatus(array $status): void
    {
        $path = join_paths($this->stateDir, 'status.json');

        file_put_contents($path.'.tmp', json_encode($status, JSON_UNESCAPED_SLASHES));

        rename($path.'.tmp', $path);
    }

    /**
     * Publish the active local server port for a Herd public front controller.
     */
    protected function writePublicGateway(int $port): void
    {
        $publicDirectory = join_paths(dirname(__DIR__), 'public');

        if (! is_dir($publicDirectory) && ! mkdir($publicDirectory, 0755, true)) {
            throw new RuntimeException('Unable to create the public installer directory.');
        }

        $gateway = join_paths($publicDirectory, '.web-installer.json');
        $temporaryGateway = $gateway.'.tmp';

        file_put_contents($temporaryGateway, json_encode(['port' => $port], JSON_UNESCAPED_SLASHES), LOCK_EX);
        rename($temporaryGateway, $gateway);
    }

    /**
     * Remove the public gateway file so the Herd front controller reports
     * the installer as offline instead of proxying to a dead port.
     */
    protected function removePublicGateway(): void
    {
        @unlink(join_paths(dirname(__DIR__), 'public', '.web-installer.json'));
    }

    /**
     * Start the PHP built-in web server that serves the installer UI.
     */
    protected function startServer(int $port): Process
    {
        $server = new Process(
            [$this->phpBinary(), '-S', '127.0.0.1:'.$port, 'router.php'],
            __DIR__.'/Web',
            [
                'LARAVEL_WEB_INSTALLER_STATE' => $this->stateDir,
                'LARAVEL_WEB_INSTALLER_CWD' => $this->targetDirectory,
            ],
        );

        $server->setTimeout(null);
        $server->start();

        return $server;
    }

    /**
     * Wait until the web server is accepting connections.
     */
    protected function waitForServer(int $port): bool
    {
        for ($i = 0; $i < 50; $i++) {
            $connection = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.1);

            if (is_resource($connection)) {
                fclose($connection);

                return true;
            }

            usleep(100_000);
        }

        return false;
    }

    /**
     * Find an available local port for the installer UI.
     */
    protected function findAvailablePort(): int
    {
        foreach (range(8123, 8199) as $port) {
            $socket = @stream_socket_server('tcp://127.0.0.1:'.$port, $errorCode, $errorMessage);

            if (is_resource($socket)) {
                fclose($socket);

                return $port;
            }
        }

        throw new RuntimeException('Unable to find an available port for the installer UI.');
    }

    /**
     * Open the given URL in the default browser.
     */
    protected function openBrowser(string $url): void
    {
        $command = match (PHP_OS_FAMILY) {
            'Windows' => ['cmd', '/c', 'start', '', $url],
            'Darwin' => ['open', $url],
            default => ['xdg-open', $url],
        };

        (new Process($command))->run();
    }

    /**
     * Get the path to the installer's own console binary.
     */
    protected function installerBinary(): string
    {
        return join_paths(dirname(__DIR__), 'bin', 'laravel');
    }

    /**
     * Get the path to the appropriate PHP binary.
     */
    protected function phpBinary(): string
    {
        $phpBinary = (new PhpExecutableFinder)->find(false);

        return $phpBinary !== false ? $phpBinary : 'php';
    }
}
