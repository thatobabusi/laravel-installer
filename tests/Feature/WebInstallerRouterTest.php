<?php

namespace Laravel\Installer\Console\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Feature tests for the web installer's HTTP layer.
 *
 * Boots src/Web/router.php on PHP's built-in server exactly as WebCommand
 * does, with the state directory and target directory pointed at temp
 * locations, then exercises every endpoint over real HTTP.
 */
class WebInstallerRouterTest extends TestCase
{
    protected static Process $server;

    protected static int $port;

    protected static string $stateDir;

    protected static string $targetDir;

    public static function setUpBeforeClass(): void
    {
        self::$stateDir = sys_get_temp_dir().'/router-test-state-'.bin2hex(random_bytes(4));
        self::$targetDir = sys_get_temp_dir().'/router-test-cwd-'.bin2hex(random_bytes(4));

        mkdir(self::$stateDir, 0777, true);
        mkdir(self::$targetDir.'/taken', 0777, true);

        self::$port = self::findAvailablePort();

        self::$server = new Process(
            [PHP_BINARY, '-S', '127.0.0.1:'.self::$port, 'router.php'],
            realpath(__DIR__.'/../../src/Web'),
            [
                'LARAVEL_WEB_INSTALLER_STATE' => self::$stateDir,
                'LARAVEL_WEB_INSTALLER_CWD' => self::$targetDir,
            ],
        );

        self::$server->setTimeout(null);
        self::$server->start();

        self::waitForServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop(3);

        foreach ([self::$stateDir, self::$targetDir] as $directory) {
            if (PHP_OS_FAMILY === 'Windows') {
                exec('rd /s /q "'.str_replace('/', '\\', $directory).'" 2>nul');
            } else {
                exec('rm -rf '.escapeshellarg($directory));
            }
        }
    }

    protected function setUp(): void
    {
        // Each test starts from a clean state directory...
        foreach (['status.json', 'job.json', 'install.log', 'quit'] as $file) {
            @unlink(self::$stateDir.'/'.$file);
        }
    }

    /**
     * @return array{0: int, 1: string}
     */
    protected function request(string $method, string $uri, ?string $body = null): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => 'Content-Type: application/json',
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);

        $response = file_get_contents('http://127.0.0.1:'.self::$port.$uri, false, $context);

        $status = 0;

        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return [$status, (string) $response];
    }

    protected function json(string $method, string $uri, ?array $body = null): array
    {
        [$status, $response] = $this->request($method, $uri, $body === null ? null : json_encode($body));

        $decoded = json_decode($response, true);

        $this->assertIsArray($decoded, "Response was not JSON: {$response}");

        return [$status, $decoded];
    }

    public function test_it_serves_the_wizard()
    {
        [$status, $body] = $this->request('GET', '/');

        $this->assertSame(200, $status);
        $this->assertStringContainsString('<title>Laravel Installer</title>', $body);
    }

    public function test_env_reports_php_and_databases()
    {
        [$status, $env] = $this->json('GET', '/api/env');

        $this->assertSame(200, $status);
        $this->assertSame(PHP_VERSION, $env['phpVersion']);
        $this->assertSame(self::$targetDir, $env['targetDirectory']);
        $this->assertArrayHasKey('sqlite', $env['databases']);
        $this->assertArrayHasKey('sqlsrv', $env['databases']);
    }

    public function test_check_name_validates_input()
    {
        [, $empty] = $this->json('GET', '/api/check-name?name=');
        $this->assertFalse($empty['valid']);

        [, $invalid] = $this->json('GET', '/api/check-name?name='.rawurlencode('bad name!'));
        $this->assertFalse($invalid['valid']);
        $this->assertStringContainsString('letters, numbers', $invalid['error']);

        [, $taken] = $this->json('GET', '/api/check-name?name=taken');
        $this->assertFalse($taken['valid']);
        $this->assertStringContainsString('already exists', $taken['error']);

        [, $valid] = $this->json('GET', '/api/check-name?name=fresh-app');
        $this->assertTrue($valid['valid']);
        $this->assertSame(self::$targetDir.DIRECTORY_SEPARATOR.'fresh-app', $valid['directory']);
    }

    public function test_status_defaults_to_idle()
    {
        [$status, $data] = $this->json('GET', '/api/status');

        $this->assertSame(200, $status);
        $this->assertSame('idle', $data['status']['state']);
        $this->assertSame('', $data['log']);
    }

    public function test_status_streams_the_log_with_ansi_stripped()
    {
        file_put_contents(self::$stateDir.'/status.json', json_encode(['state' => 'running', 'name' => 'demo']));
        file_put_contents(
            self::$stateDir.'/install.log',
            "plain line\n\e[32mgreen line\e[0m\n\e]8;;https://laravel.com\e\\link\e]8;;\e\\ done\n"
        );

        [, $first] = $this->json('GET', '/api/status?offset=0');

        $this->assertSame('running', $first['status']['state']);
        $this->assertStringContainsString('plain line', $first['log']);
        $this->assertStringContainsString('green line', $first['log']);
        $this->assertStringContainsString('link done', $first['log']);
        $this->assertStringNotContainsString("\e[", $first['log']);
        $this->assertGreaterThan(0, $first['offset']);

        // Polling from the reported offset returns nothing new...
        [, $second] = $this->json('GET', '/api/status?offset='.$first['offset']);
        $this->assertSame('', $second['log']);
    }

    public function test_install_queues_a_job()
    {
        $job = ['name' => 'queued-app', 'stack' => 'blade', 'database' => 'sqlite'];

        [$status, $response] = $this->json('POST', '/api/install', $job);

        $this->assertSame(200, $status);
        $this->assertTrue($response['queued']);
        $this->assertFileExists(self::$stateDir.'/job.json');

        $written = json_decode(file_get_contents(self::$stateDir.'/job.json'), true);
        $this->assertSame('queued-app', $written['name']);

        $queued = json_decode(file_get_contents(self::$stateDir.'/status.json'), true);
        $this->assertSame('queued', $queued['state']);
    }

    public function test_install_rejects_malformed_payloads()
    {
        [$status, $response] = $this->request('POST', '/api/install', 'not-json');

        $this->assertSame(422, $status);
        $this->assertStringContainsString('Invalid job payload', $response);
    }

    public function test_install_refuses_concurrent_runs()
    {
        file_put_contents(self::$stateDir.'/status.json', json_encode(['state' => 'running']));

        [$status, $response] = $this->json('POST', '/api/install', ['name' => 'second-app']);

        $this->assertSame(409, $status);
        $this->assertStringContainsString('already running', $response['error']);
        $this->assertFileDoesNotExist(self::$stateDir.'/job.json');
    }

    public function test_quit_touches_the_quit_file()
    {
        [$status, $response] = $this->json('POST', '/api/quit');

        $this->assertSame(200, $status);
        $this->assertTrue($response['bye']);
        $this->assertFileExists(self::$stateDir.'/quit');
    }

    public function test_unknown_routes_return_404()
    {
        [$status] = $this->request('GET', '/api/nope');

        $this->assertSame(404, $status);
    }

    protected static function findAvailablePort(): int
    {
        foreach (range(8300, 8399) as $port) {
            $socket = @stream_socket_server('tcp://127.0.0.1:'.$port);

            if (is_resource($socket)) {
                fclose($socket);

                return $port;
            }
        }

        self::fail('Unable to find an available port for the router test server.');
    }

    protected static function waitForServer(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $connection = @fsockopen('127.0.0.1', self::$port, $errorCode, $errorMessage, 0.1);

            if (is_resource($connection)) {
                fclose($connection);

                return;
            }

            usleep(100_000);
        }

        self::fail('The router test server did not start: '.self::$server->getErrorOutput());
    }
}
