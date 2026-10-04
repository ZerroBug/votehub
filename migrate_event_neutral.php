<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

try {
    $columns = $pdo->query("SHOW COLUMNS FROM contestants LIKE 'class_department'")->fetchAll();

    if ($columns) {
        $pdo->exec("ALTER TABLE contestants DROP COLUMN class_department");
        echo "<h2>Migration completed successfully.</h2>";
        echo "<p>The Class / Department field has been removed from contestants.</p>";
    } else {
        echo "<h2>No migration required.</h2>";
        echo "<p>The Class / Department field is already absent.</p>";
    }

    echo '<p><a href="auth/login.php">Go to VoteHub</a></p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h2>Migration failed</h2><pre>" .
         htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
         "</pre>";
}
