<?php

/**
 * Router script for the Laravel web installer UI.
 *
 * Served by PHP's built-in web server (see WebCommand). Communicates with the
 * installer CLI process exclusively through files in the shared state directory:
 * the browser POSTs a job to /api/install, the CLI process picks it up, runs the
 * installation, and streams progress back through status.json + install.log.
 */
$stateDir = getenv('LARAVEL_WEB_INSTALLER_STATE');
$targetCwd = getenv('LARAVEL_WEB_INSTALLER_CWD') ?: getcwd();

if ($stateDir === false) {
    http_response_code(500);
    exit('The installer web server was started without its state environment.');
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    exit(json_encode($payload, JSON_UNESCAPED_SLASHES));
}

function read_status(string $stateDir): array
{
    $status = json_decode((string) @file_get_contents($stateDir.'/status.json'), true);

    return is_array($status) ? $status : ['state' => 'idle'];
}

if ($uri === '/' && $method === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__.'/index.html');
    exit;
}

if ($uri === '/api/env' && $method === 'GET') {
    json_response([
        'phpVersion' => PHP_VERSION,
        'os' => PHP_OS_FAMILY,
        'targetDirectory' => $targetCwd,
        'separator' => DIRECTORY_SEPARATOR,
        'databases' => [
            'sqlite' => extension_loaded('pdo_sqlite'),
            'mysql' => extension_loaded('pdo_mysql'),
            'mariadb' => extension_loaded('pdo_mysql'),
            'pgsql' => extension_loaded('pdo_pgsql'),
            'sqlsrv' => extension_loaded('pdo_sqlsrv'),
        ],
    ]);
}

if ($uri === '/api/check-name' && $method === 'GET') {
    $name = trim((string) ($_GET['name'] ?? ''));

    if ($name === '') {
        json_response(['valid' => false, 'error' => 'The project name is required.']);
    }

    if (preg_match('/[^\pL\pN\-_.]/u', $name) !== 0) {
        json_response(['valid' => false, 'error' => 'The name may only contain letters, numbers, dashes, underscores, and periods.']);
    }

    $location = trim((string) ($_GET['location'] ?? ''));

    if ($location !== '' && ! is_dir($location)) {
        json_response(['valid' => false, 'error' => 'The target location does not exist.']);
    }

    $directory = ($location !== '' ? rtrim($location, '/\\') : $targetCwd).DIRECTORY_SEPARATOR.$name;

    if (is_dir($directory) || is_file($directory)) {
        json_response(['valid' => false, 'error' => 'Application already exists.']);
    }

    json_response(['valid' => true, 'directory' => $directory]);
}

if ($uri === '/api/status' && $method === 'GET') {
    $status = read_status($stateDir);
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    $logPath = $stateDir.'/install.log';

    $chunk = '';
    $size = is_file($logPath) ? (int) filesize($logPath) : 0;

    if ($size > $offset) {
        $handle = @fopen($logPath, 'r');

        if (is_resource($handle)) {
            fseek($handle, $offset);
            $chunk = (string) stream_get_contents($handle);
            fclose($handle);

            // Strip ANSI styling and OSC-8 hyperlink sequences for the browser terminal...
            $chunk = (string) preg_replace(
                ['/\x1b\]8;[^\x07\x1b]*(?:\x07|\x1b\\\\)/', '/\x1b\[[0-9;?]*[a-zA-Z]/'],
                '',
                $chunk
            );
        }
    }

    json_response([
        'status' => $status,
        'log' => $chunk,
        'offset' => max($offset, $size),
    ]);
}

if ($uri === '/api/install' && $method === 'POST') {
    $status = read_status($stateDir);

    if (($status['state'] ?? 'idle') === 'running') {
        json_response(['error' => 'An installation is already running.'], 409);
    }

    $job = json_decode((string) file_get_contents('php://input'), true);

    if (! is_array($job)) {
        json_response(['error' => 'Invalid job payload.'], 422);
    }

    file_put_contents($stateDir.'/job.json', json_encode($job, JSON_UNESCAPED_SLASHES));
    file_put_contents($stateDir.'/status.json', json_encode(['state' => 'queued', 'name' => $job['name'] ?? null], JSON_UNESCAPED_SLASHES));

    json_response(['queued' => true]);
}

if ($uri === '/api/quit' && $method === 'POST') {
    touch($stateDir.'/quit');

    json_response(['bye' => true]);
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Not found.']);
