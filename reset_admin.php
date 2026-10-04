<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

$email = 'admin@votehub.test';
$password = 'ChangeMe@123';
$hash = "$2y$12$FCihZ5cXJuS.9XI4hXZzsuOQv8rAu3Owi1S0nT8/1TPzd1/1c.J/y";

try {
    $stmt = $pdo->prepare("UPDATE users SET password_hash=?, role='Super_Admin', status='Active' WHERE email=?");
    $stmt->execute([$hash, $email]);

    if ($stmt->rowCount() === 0) {
        $ins = $pdo->prepare("INSERT INTO users (full_name,email,password_hash,role,status) VALUES (?,?,?,?,?)");
        $ins->execute(['Super Administrator',$email,$hash,'Super_Admin','Active']);
    }

    echo '<h2>Admin account fixed successfully.</h2>';
    echo '<p>Email: <strong>admin@votehub.test</strong></p>';
    echo '<p>Password: <strong>ChangeMe@123</strong></p>';
    echo '<p><a href="auth/login.php">Go to login</a></p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2>Could not fix admin account</h2><pre>' .
         htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
         '</pre>';
}
