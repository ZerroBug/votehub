<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) redirect(APP_URL.'/admin/dashboard.php');

$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $email=trim($_POST['email'] ?? '');
    $password=$_POST['password'] ?? '';
    $stmt=$pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $user=$stmt->fetch();

    if($user && $user['status']==='Active' && password_verify($password,$user['password_hash'])){
        session_regenerate_id(true);
        $_SESSION['user_id']=$user['id'];
        $_SESSION['full_name']=$user['full_name'];
        $_SESSION['role']=$user['role'];
        $pdo->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([$user['id']]);
        redirect(APP_URL.'/admin/dashboard.php');
    }
    $error='Invalid email or password.';
}
?>
<!doctype html><html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login | VoteHub</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= APP_URL ?>/assets/css/app.css" rel="stylesheet">
<style>body{display:grid;place-items:center;min-height:100vh}.login{width:min(420px,92%);padding:32px;background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:0 20px 60px #17203312}.logo{width:48px;height:48px;border-radius:13px;background:linear-gradient(135deg,#5b4ce1,#8b7ff7);color:#fff;display:grid;place-items:center;margin:auto;font-size:22px}</style>
</head><body><div class="login">
<div class="logo"><i class="bi bi-broadcast-pin"></i></div>
<h3 class="text-center fw-bold mt-3 mb-1">VoteHub</h3><p class="text-center text-secondary small">Super Admin Portal</p>
<?php if($error): ?><div class="alert alert-danger small"><?= e($error) ?></div><?php endif; ?>
<form method="post"><label class="form-label">Email</label><input type="email" name="email" class="form-control mb-3" required>
<label class="form-label">Password</label><input type="password" name="password" class="form-control mb-4" required>
<button class="btn btn-primary w-100">Sign In</button></form>
</div></body></html>
