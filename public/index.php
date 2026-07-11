<?php

declare(strict_types=1);

/**
 * Herd front controller for an active `laravel web` session.
 *
 * The CLI owns the installer process and publishes its loopback port in the
 * ignored .web-installer.json file. This front controller lets a Herd-linked
 * host proxy the browser UI without exposing the installer directly to LAN.
 */
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (! in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Laravel Web Installer is available only from this computer.');
}
$configPath = __DIR__.'/.web-installer.json';
$config = is_file($configPath) ? json_decode((string) file_get_contents($configPath), true) : null;
$port = is_array($config) ? (int) ($config['port'] ?? 0) : 0;

if ($port < 1 || $port > 65535) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Laravel Web Installer is not running. Start it with `laravel web` and refresh this page.');
}

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$headers = [];

foreach (['CONTENT_TYPE' => 'Content-Type'] as $serverKey => $header) {
    if (isset($_SERVER[$serverKey])) {
        $headers[] = $header.': '.$_SERVER[$serverKey];
    }
}

$context = stream_context_create([
    'http' => [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'header' => implode("\r\n", $headers),
        'content' => file_get_contents('php://input'),
        'ignore_errors' => true,
        'timeout' => 5,
    ],
]);

$response = @file_get_contents('http://127.0.0.1:'.$port.$uri, false, $context);

if ($response === false) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Laravel Web Installer is not responding. Keep `laravel web` running and refresh this page.');
}

foreach ($http_response_header ?? [] as $header) {
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
        http_response_code((int) $matches[1]);
    }

    if (str_starts_with(strtolower($header), 'content-type:')) {
        header($header);
    }
}

echo $response;