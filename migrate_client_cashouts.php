<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireSuperAdmin();
header('Content-Type: text/html; charset=utf-8');
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_cashouts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_id BIGINT UNSIGNED NOT NULL,
        requested_amount DECIMAL(12,2) NOT NULL,
        status ENUM('Requested','Approved','Paid','Rejected','Cancelled') NOT NULL DEFAULT 'Requested',
        payment_method VARCHAR(80) NULL,
        account_name VARCHAR(150) NULL,
        account_number VARCHAR(100) NULL,
        mobile_number VARCHAR(30) NULL,
        reference VARCHAR(150) NULL,
        notes TEXT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        approved_at DATETIME NULL,
        paid_at DATETIME NULL,
        processed_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
        FOREIGN KEY(processed_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX(event_id), INDEX(status), INDEX(requested_at)
    ) ENGINE=InnoDB");
    echo '<h2>Client cash-out module installed successfully.</h2><p>You can now use Admin → Cash-outs.</p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2>Cash-out migration failed</h2><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
}
