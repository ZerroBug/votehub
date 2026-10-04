<?php
declare(strict_types=1);

$config = is_file(__DIR__ . '/runtime.php') ? require __DIR__ . '/runtime.php' : [];

$appUrl = rtrim((string)($config['app_url'] ?? 'https://votehubgh.org'), '/');
date_default_timezone_set((string)($config['timezone'] ?? 'Africa/Accra'));

define('APP_NAME', 'VoteHub');
define('APP_URL', $appUrl);
define('APP_ENV', (string)($config['app_env'] ?? 'production'));

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with(APP_URL, 'https://');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';
