<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

$message=[];
try {
    $idx = $pdo->query("SHOW INDEX FROM votes WHERE Key_name='uq_votes_transaction'")->fetchAll();
    if (!$idx) {
        $dupes = $pdo->query("SELECT transaction_id, COUNT(*) c FROM votes GROUP BY transaction_id HAVING c>1 LIMIT 1")->fetch();
        if ($dupes) {
            $message[] = 'Migration stopped: duplicate votes already exist for transaction_id ' . $dupes['transaction_id'] . '. Remove/reconcile duplicates before adding the unique protection.';
        } else {
            $pdo->exec("ALTER TABLE votes ADD UNIQUE KEY uq_votes_transaction(transaction_id)");
            $message[] = 'Added unique protection on votes.transaction_id.';
        }
    } else {
        $message[] = 'Unique protection already exists.';
    }
    $message[] = 'Voting engine migration completed.';
} catch (Throwable $e) {
    $message[] = 'Migration error: ' . $e->getMessage();
}
header('Content-Type: text/plain; charset=utf-8');
echo implode(PHP_EOL, $message);
