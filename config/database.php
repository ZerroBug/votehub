<?php
declare(strict_types=1);

/*
 * VoteHub production database connection.
 * The installer writes config/runtime.php. Do not place credentials in public files.
 */
$runtime = __DIR__ . '/runtime.php';
if (!is_file($runtime)) {
    http_response_code(503);
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VoteHub Setup Required</title><style>body{font-family:Arial,sans-serif;background:#f5f7fb;padding:40px;color:#172033}.box{max-width:700px;margin:auto;background:#fff;border-radius:16px;padding:32px;box-shadow:0 12px 40px rgba(0,0,0,.08)}a{display:inline-block;margin-top:12px;padding:11px 16px;background:#5b4ce1;color:#fff;text-decoration:none;border-radius:8px}</style></head><body><div class="box"><h1>VoteHub setup required</h1><p>The application has not been configured yet.</p><a href="../setup.php">Run secure setup</a></div></body></html>';
    exit;
}

$config = require $runtime;
$host = (string)($config['db_host'] ?? 'localhost');
$port = (int)($config['db_port'] ?? 3306);
$db   = (string)($config['db_name'] ?? 'votehub_db');
$user = (string)($config['db_user'] ?? '');
$pass = (string)($config['db_pass'] ?? '');
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
} catch (PDOException $e) {
    http_response_code(500);
    $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VoteHub Database Error</title><style>body{font-family:Arial,sans-serif;background:#f5f7fb;padding:40px}.box{max-width:850px;margin:auto;background:#fff;border-radius:16px;padding:30px;box-shadow:0 12px 40px rgba(0,0,0,.08)}code{background:#f1f5f9;padding:3px 6px;border-radius:5px}.err{color:#b42318}</style></head><body><div class="box"><h1 class="err">Database connection failed</h1><p><code>'.$msg.'</code></p><p>Check the database credentials in <code>config/runtime.php</code>.</p></div></body></html>';
    exit;
}
