<?php
declare(strict_types=1);

$lock = __DIR__ . '/storage/install.lock';
$installed = is_file($lock);

if ($installed && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Show the installer so an authorized operator can explicitly choose a destructive reset.
    // The reset option below requires an additional confirmation phrase.
}

session_start();
$error = '';
$success = '';

$voteHubTables = [
    'votes', 'transactions', 'ussd_sessions', 'audit_logs',
    'contestants', 'categories', 'events', 'users', 'webhook_events', 'client_cashouts'
];

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function existingVoteHubTables(PDO $pdo, array $tables): array {
    $found = [];
    foreach ($tables as $table) if (tableExists($pdo, $table)) $found[] = $table;
    return $found;
}

function dropVoteHubTables(PDO $pdo, array $tables): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        $safe = str_replace('`', '``', $table);
        $pdo->exec("DROP TABLE IF EXISTS `{$safe}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function importSchema(PDO $pdo): void {
    $path = __DIR__ . '/database/schema.sql';
    if (!is_file($path)) throw new RuntimeException('database/schema.sql was not found.');
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') throw new RuntimeException('database/schema.sql is empty or unreadable.');
    $sql = preg_replace('/^\s*CREATE\s+DATABASE\b.*?;\s*$/im', '', $sql);
    $sql = preg_replace('/^\s*USE\s+[`\'\"]?[^;]+[`\'\"]?\s*;\s*$/im', '', $sql);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $pdo->exec($statement);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim((string)($_POST['db_host'] ?? 'localhost'));
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = trim((string)($_POST['db_name'] ?? ''));
    $dbUser = trim((string)($_POST['db_user'] ?? ''));
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $appUrl = rtrim(trim((string)($_POST['app_url'] ?? 'https://votehubgh.org')), '/');
    $adminName = trim((string)($_POST['admin_name'] ?? ''));
    $adminEmail = trim((string)($_POST['admin_email'] ?? ''));
    $adminPass = (string)($_POST['admin_password'] ?? '');
    $paystackKey = trim((string)($_POST['paystack_key'] ?? ''));
    $paystackMode = ($_POST['paystack_mode'] ?? 'test') === 'live' ? 'live' : 'test';
    $seedDemo = !empty($_POST['seed_demo']);
    $freshInstall = !empty($_POST['fresh_install']);
    $resetExisting = !empty($_POST['reset_existing']);
    $resetConfirm = trim((string)($_POST['reset_confirmation'] ?? ''));

    try {
        if (!filter_var($appUrl, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($appUrl), 'https://')) throw new RuntimeException('APP URL must be a valid HTTPS URL.');
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid administrator email.');
        if ($adminName === '') throw new RuntimeException('Administrator full name is required.');
        if (strlen($adminPass) < 10) throw new RuntimeException('Administrator password must be at least 10 characters.');
        if (!preg_match('/^sk_(test|live)_[A-Za-z0-9]+$/', $paystackKey)) throw new RuntimeException('Enter a valid Paystack secret key beginning with sk_test_ or sk_live_.');
        if ($paystackMode === 'test' && !str_starts_with($paystackKey, 'sk_test_')) throw new RuntimeException('Test environment requires an sk_test_ secret key.');
        if ($paystackMode === 'live' && !str_starts_with($paystackKey, 'sk_live_')) throw new RuntimeException('Live environment requires an sk_live_ secret key.');
        if ($dbName === '' || $dbUser === '') throw new RuntimeException('Database name and username are required.');
        if ($dbPort < 1 || $dbPort > 65535) throw new RuntimeException('Database port is invalid.');

        $server = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $safeDb = str_replace('`', '``', $dbName);
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $existing = existingVoteHubTables($pdo, $voteHubTables);

        if (($freshInstall || $resetExisting) && $existing) {
            if ($resetExisting && $resetConfirm !== 'RESET VOTEHUB') {
                throw new RuntimeException('Destructive reset requires the exact confirmation phrase: RESET VOTEHUB');
            }
            dropVoteHubTables($pdo, $voteHubTables);
            // A reset is a complete reinstall, so remove the previous installation lock.
            if (is_file($lock)) @unlink($lock);
        } elseif ($existing) {
            throw new RuntimeException('An existing or partial VoteHub database was detected: ' . implode(', ', $existing) . '. Select Fresh installation for a new/partial deployment, or use Reset existing VoteHub installation to delete and recreate all VoteHub tables.');
        } elseif ($resetExisting && $resetConfirm !== 'RESET VOTEHUB') {
            throw new RuntimeException('Destructive reset requires the exact confirmation phrase: RESET VOTEHUB');
        }

        importSchema($pdo);

        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users(full_name,email,password_hash,role,status) VALUES(?,?,?,'Super_Admin','Active')");
        $stmt->execute([$adminName, $adminEmail, $hash]);
        $adminId = (int)$pdo->lastInsertId();

        if ($seedDemo) {
            $event = $pdo->prepare("INSERT INTO events(name,event_code,description,start_date,end_date,status,default_vote_price,created_by) VALUES(?,?,?,?,?,'Active',1.00,?)");
            $event->execute(['Demo Voting Event', 'DEMO2026', 'Optional demonstration event.', date('Y-m-d H:i:s'), date('Y-m-d H:i:s', time() + 86400 * 30), $adminId]);
            $eventId = (int)$pdo->lastInsertId();
            $cat = $pdo->prepare("INSERT INTO categories(event_id,name,category_code,description,vote_price,max_votes_per_transaction,display_order) VALUES(?,?,?,?,1.00,20,1)");
            $cat->execute([$eventId, 'Demo Category', 'DEMO1', 'Optional demonstration category.']);
            $categoryId = (int)$pdo->lastInsertId();
            $contestant = $pdo->prepare("INSERT INTO contestants(event_id,category_id,contestant_code,full_name,gender,status) VALUES(?,?,?,?,'Other','Active')");
            $contestant->execute([$eventId, $categoryId, '1001', 'Demo Contestant']);
        }

        $runtime = "<?php\nreturn " . var_export([
            'db_host'=>$dbHost, 'db_port'=>$dbPort, 'db_name'=>$dbName,
            'db_user'=>$dbUser, 'db_pass'=>$dbPass, 'app_url'=>$appUrl,
            'timezone'=>'Africa/Accra', 'app_env'=>'production'
        ], true) . ";\n";
        $secrets = "<?php\nreturn " . var_export([
            'paystack_mode'=>$paystackMode, 'paystack_secret_key'=>$paystackKey
        ], true) . ";\n";

        $configDir = __DIR__ . '/config';
        if (!is_dir($configDir)) mkdir($configDir, 0750, true);
        file_put_contents($configDir . '/runtime.php', $runtime, LOCK_EX);
        file_put_contents($configDir . '/secrets.php', $secrets, LOCK_EX);

        $storage = __DIR__ . '/storage';
        if (!is_dir($storage)) mkdir($storage, 0750, true);
        file_put_contents($lock, 'installed ' . date('c'), LOCK_EX);
        $success = 'Installation completed successfully. Your VoteHub database, administrator account, and production configuration are ready.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VoteHub Production Setup</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f7fb}.setup-card{border:0;border-radius:18px;box-shadow:0 16px 55px rgba(20,25,45,.1)}.brand{font-weight:800;color:#172033}.section{border-top:1px solid #e7ebf2;margin-top:24px;padding-top:24px}.danger-box{border:1px solid #f1b8b8;background:#fff7f7;border-radius:12px;padding:15px}.form-label{font-weight:600}
</style>
</head>
<body>
<div class="container py-5"><div class="row justify-content-center"><div class="col-xl-9"><div class="card setup-card p-4 p-md-5">
<h1 class="brand">VoteHub Production Setup</h1>
<p class="text-muted">Install VoteHub on <strong>votehubgh.org</strong>. Use your Hostinger MySQL credentials and Paystack secret key.</p>
<?php if ($error): ?><div class="alert alert-danger"><strong>Setup failed:</strong> <?=h($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><strong>Installation successful.</strong><div class="mt-2"><?=h($success)?></div></div><a class="btn btn-primary" href="auth/login.php">Open VoteHub</a>
<?php else: ?>
<form method="post" autocomplete="off">
<div class="section"><h5>Database</h5><p class="small text-muted">Use the exact database name, username and password created in Hostinger.</p><div class="row g-3">
<div class="col-md-6"><label class="form-label">Host</label><input name="db_host" class="form-control" value="<?=h($_POST['db_host']??'localhost')?>" required></div>
<div class="col-md-3"><label class="form-label">Port</label><input name="db_port" type="number" class="form-control" value="<?=h($_POST['db_port']??'3306')?>" required></div>
<div class="col-md-3"><label class="form-label">Database</label><input name="db_name" class="form-control" value="<?=h($_POST['db_name']??'')?>" required></div>
<div class="col-md-6"><label class="form-label">Username</label><input name="db_user" class="form-control" value="<?=h($_POST['db_user']??'')?>" required></div>
<div class="col-md-6"><label class="form-label">Password</label><input name="db_pass" type="password" class="form-control" required></div>
</div></div>
<div class="section"><h5>Application</h5><label class="form-label">HTTPS Application URL</label><input name="app_url" class="form-control" value="<?=h($_POST['app_url']??'https://votehubgh.org')?>" required></div>
<div class="section"><h5>Super Admin</h5><div class="row g-3">
<div class="col-md-6"><label class="form-label">Full name</label><input name="admin_name" class="form-control" value="<?=h($_POST['admin_name']??'')?>" required></div>
<div class="col-md-6"><label class="form-label">Email</label><input name="admin_email" type="email" class="form-control" value="<?=h($_POST['admin_email']??'')?>" required></div>
<div class="col-md-6"><label class="form-label">Password</label><input name="admin_password" type="password" class="form-control" minlength="10" required><div class="form-text">Minimum 10 characters.</div></div>
</div></div>
<div class="section"><h5>Paystack</h5><div class="row g-3">
<div class="col-md-4"><label class="form-label">Environment</label><select name="paystack_mode" class="form-select"><option value="test" <?=($_POST['paystack_mode']??'test')==='test'?'selected':''?>>Test</option><option value="live" <?=($_POST['paystack_mode']??'')==='live'?'selected':''?>>Live</option></select></div>
<div class="col-md-8"><label class="form-label">Secret key</label><input name="paystack_key" type="password" class="form-control" placeholder="sk_test_..." required></div>
</div><div class="form-text">The environment and key prefix must match.</div></div>
<div class="section"><div class="danger-box">
<div class="form-check"><input class="form-check-input" type="checkbox" name="fresh_install" value="1" id="fresh_install"><label class="form-check-label fw-semibold" for="fresh_install">Fresh installation</label></div>
<div class="small text-danger mt-2">For a new/partial deployment. Existing VoteHub tables will be dropped and recreated.</div>
<hr>
<div class="form-check"><input class="form-check-input" type="checkbox" name="reset_existing" value="1" id="reset_existing"><label class="form-check-label fw-bold text-danger" for="reset_existing">Reset existing VoteHub installation</label></div>
<div class="small text-danger mt-2">This permanently deletes all existing VoteHub data in the selected database, including users, events, categories, contestants, transactions, votes, USSD sessions, audit logs, webhook records and client cash-outs. Unrelated database tables are not touched.</div>
<div class="mt-3"><label class="form-label">Reset confirmation</label><input name="reset_confirmation" class="form-control" placeholder="Type RESET VOTEHUB" autocomplete="off"><div class="form-text text-danger">Required only when resetting an existing installation.</div></div>
</div></div>
<div class="section"><div class="form-check"><input class="form-check-input" type="checkbox" name="seed_demo" value="1" id="seed_demo"><label class="form-check-label" for="seed_demo">Create optional demo event, category and contestant</label></div></div>
<div class="section"><button class="btn btn-primary btn-lg" type="submit">Install VoteHub</button></div>
</form>
<?php endif; ?>
</div></div></div></div>
</body></html>
