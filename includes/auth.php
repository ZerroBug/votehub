<?php
require_once __DIR__ . '/../config/app.php';

function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

function requireSuperAdmin(): void
{
    requireLogin();

    if (($_SESSION['role'] ?? '') !== 'Super_Admin') {
        http_response_code(403);
        exit('Access denied.');
    }
}
