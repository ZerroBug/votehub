<?php
declare(strict_types=1);

$lock = __DIR__ . '/storage/install.lock';
if (is_file($lock)) {
    http_response_code(403);
    exit('VoteHub has already been installed. Remove storage/install.lock only if you intentionally need to reinstall.');
}

session_start();
$error=''; $success='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $dbHost=trim((string)($_POST['db_host']??'localhost'));
    $dbPort=(int)($_POST['db_port']??3306);
    $dbName=trim((string)($_POST['db_name']??'votehub_db'));
    $dbUser=trim((string)($_POST['db_user']??''));
    $dbPass=(string)($_POST['db_pass']??'');
    $appUrl=rtrim(trim((string)($_POST['app_url']??'https://votehubgh.org')),'/');
    $adminName=trim((string)($_POST['admin_name']??''));
    $adminEmail=trim((string)($_POST['admin_email']??''));
    $adminPass=(string)($_POST['admin_password']??'');
    $paystackKey=trim((string)($_POST['paystack_key']??''));
    $paystackMode=($_POST['paystack_mode']??'live')==='test'?'test':'live';
    $seedDemo=!empty($_POST['seed_demo']);
    try {
        if (!filter_var($appUrl,FILTER_VALIDATE_URL) || !str_starts_with($appUrl,'https://')) throw new RuntimeException('APP URL must be a valid HTTPS URL.');
        if (!filter_var($adminEmail,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid administrator email.');
        if (strlen($adminPass)<10) throw new RuntimeException('Administrator password must be at least 10 characters.');
        if (!preg_match('/^sk_(test|live)_/', $paystackKey)) throw new RuntimeException('Enter a valid Paystack secret key beginning with sk_test_ or sk_live_.');
        if ($dbName==='' || $dbUser==='') throw new RuntimeException('Database name and username are required.');
        $server=new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $server->exec("CREATE DATABASE IF NOT EXISTS `".str_replace('`','',$dbName)."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo=new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $sql=file_get_contents(__DIR__.'/database/schema.sql');
        $sql=preg_replace('/^CREATE DATABASE.*?;\s*/mi','',$sql); $sql=preg_replace('/^USE\s+[^;]+;\s*/mi','',$sql);
        foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement) { $statement=trim($statement); if($statement!=='') $pdo->exec($statement); }
        $hash=password_hash($adminPass,PASSWORD_DEFAULT);
        $stmt=$pdo->prepare("INSERT INTO users(full_name,email,password_hash,role,status) VALUES(?,?,?,'Super_Admin','Active')");
        $stmt->execute([$adminName,$adminEmail,$hash]);
        if($seedDemo){
            $event=$pdo->prepare("INSERT INTO events(name,event_code,description,start_date,end_date,status,default_vote_price,created_by) VALUES(?,?,?,?,?,'Active',1.00,?)");
            $event->execute(['Demo Voting Event','DEMO2026','Optional demonstration event.',date('Y-m-d H:i:s'),date('Y-m-d H:i:s',time()+86400*30),(int)$pdo->lastInsertId()]);
            $eid=(int)$pdo->lastInsertId();
            $cat=$pdo->prepare("INSERT INTO categories(event_id,name,category_code,description,vote_price,max_votes_per_transaction,display_order) VALUES(?,?,?,?,1.00,20,1)");
            $cat->execute([$eid,'Demo Category','DEMO1','Optional demonstration category.']); $cid=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO contestants(event_id,category_id,contestant_code,full_name,gender,status) VALUES(?,?,?,'Demo Contestant','Other','Active')")->execute([$eid,$cid,'1001']);
        }
        $runtime="<?php\nreturn ".var_export(['db_host'=>$dbHost,'db_port'=>$dbPort,'db_name'=>$dbName,'db_user'=>$dbUser,'db_pass'=>$dbPass,'app_url'=>$appUrl,'timezone'=>'Africa/Accra','app_env'=>'production'],true).";\n";
        if(!is_dir(__DIR__.'/config')) mkdir(__DIR__.'/config',0750,true);
        file_put_contents(__DIR__.'/config/runtime.php',$runtime,LOCK_EX);
        file_put_contents(__DIR__.'/config/secrets.php',"<?php\nreturn ".var_export(['paystack_mode'=>$paystackMode,'paystack_secret_key'=>$paystackKey],true).";\n",LOCK_EX);
        if(!is_dir(__DIR__.'/storage')) mkdir(__DIR__.'/storage',0750,true);
        file_put_contents($lock,'installed '.date('c'),LOCK_EX);
        $success='Installation completed successfully. The administrator account and production configuration are ready.';
    } catch(Throwable $e){ $error=$e->getMessage(); }
}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VoteHub Production Setup</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f5f7fb}.card{border:0;border-radius:18px;box-shadow:0 16px 55px rgba(20,25,45,.1)}.brand{font-weight:800;color:#172033}.section{border-top:1px solid #e7ebf2;margin-top:24px;padding-top:24px}</style></head><body><div class="container py-5"><div class="row justify-content-center"><div class="col-xl-8"><div class="card p-4 p-md-5"><h1 class="brand">VoteHub Production Setup</h1><p class="text-muted">Install VoteHub on <strong>votehubgh.org</strong>. Use your hosting MySQL credentials and Paystack secret key.</p><?php if($error):?><div class="alert alert-danger"><strong>Setup failed:</strong> <?=h($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=h($success)?></div><a class="btn btn-primary" href="auth/login.php">Open VoteHub</a><?php else:?><form method="post" autocomplete="off"><div class="section"><h5>Database</h5><div class="row g-3"><div class="col-md-6"><label class="form-label">Host</label><input name="db_host" class="form-control" value="<?=h($_POST['db_host']??'localhost')?>" required></div><div class="col-md-3"><label class="form-label">Port</label><input name="db_port" class="form-control" value="<?=h($_POST['db_port']??'3306')?>" required></div><div class="col-md-3"><label class="form-label">Database</label><input name="db_name" class="form-control" value="<?=h($_POST['db_name']??'votehub_db')?>" required></div><div class="col-md-6"><label class="form-label">Username</label><input name="db_user" class="form-control" required></div><div class="col-md-6"><label class="form-label">Password</label><input name="db_pass" type="password" class="form-control"></div></div></div><div class="section"><h5>Application</h5><label class="form-label">HTTPS Application URL</label><input name="app_url" class="form-control" value="<?=h($_POST['app_url']??'https://votehubgh.org')?>" required></div><div class="section"><h5>Super Admin</h5><div class="row g-3"><div class="col-md-6"><label class="form-label">Full name</label><input name="admin_name" class="form-control" required></div><div class="col-md-6"><label class="form-label">Email</label><input name="admin_email" type="email" class="form-control" required></div><div class="col-md-6"><label class="form-label">Password</label><input name="admin_password" type="password" class="form-control" minlength="10" required></div></div></div><div class="section"><h5>Paystack</h5><div class="row g-3"><div class="col-md-4"><label class="form-label">Environment</label><select name="paystack_mode" class="form-select"><option value="live">Live</option><option value="test">Test</option></select></div><div class="col-md-8"><label class="form-label">Secret key</label><input name="paystack_key" type="password" class="form-control" placeholder="sk_live_..." required></div></div><div class="form-text">Use your Live key only after Paystack has activated the account. Never paste a secret key into frontend JavaScript.</div></div><div class="section"><div class="form-check"><input class="form-check-input" type="checkbox" name="seed_demo" id="seed_demo"><label class="form-check-label" for="seed_demo">Create optional demo event and contestant</label></div></div><div class="section"><button class="btn btn-primary btn-lg">Install VoteHub</button></div></form><?php endif;?></div></div></div></div></body></html>
