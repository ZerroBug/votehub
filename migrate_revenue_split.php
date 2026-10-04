<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireSuperAdmin();

try {
    $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='events' AND column_name='admin_revenue_percentage'")->fetchColumn();
    if (!$exists) {
        $pdo->exec("ALTER TABLE events ADD COLUMN admin_revenue_percentage DECIMAL(5,2) NOT NULL DEFAULT 30.00 AFTER default_vote_price");
        $message = 'Revenue split has been added to events. Existing events default to Admin/VoteHub 30% and Client 70%.';
    } else {
        $message = 'Event revenue split is already installed. Existing event percentages were not changed.';
    }

    $txExists = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='transactions' AND column_name='admin_revenue_percentage'")->fetchColumn();
    if (!$txExists) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN admin_revenue_percentage DECIMAL(5,2) NOT NULL DEFAULT 30.00 AFTER amount");
        $pdo->exec("UPDATE transactions t JOIN events e ON e.id=t.event_id SET t.admin_revenue_percentage=e.admin_revenue_percentage");
        $message .= ' Transaction revenue percentages were added and initialized from each event.';
    }
    flash('success', $message);
    redirect(APP_URL . '/admin/events/');
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2>Revenue split migration failed</h2><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
}
