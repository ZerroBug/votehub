<?php
declare(strict_types=1);

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function generateTransactionReference(): string
{
    return 'VHB-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(5)));
}

function normalizePhone(string $phone): string
{
    $phone = preg_replace('/[^0-9+]/', '', trim($phone));
    if (str_starts_with($phone, '+233')) {
        return '0' . substr($phone, 4);
    }
    if (str_starts_with($phone, '233')) {
        return '0' . substr($phone, 3);
    }
    return $phone;
}

function providerCode(string $provider): string
{
    return match (strtolower(trim($provider))) {
        'mtn' => 'mtn',
        'atl', 'airteltigo', 'airtel', 'atmoney' => 'atl',
        'vod', 'telecel', 'vodafone' => 'vod',
        default => throw new InvalidArgumentException('Unsupported mobile money provider.'),
    };
}

function getVotingSelection(PDO $pdo, int $eventId, string $contestantCode): array
{
    $stmt = $pdo->prepare("SELECT
        x.id contestant_id,
        x.event_id,
        x.category_id,
        x.contestant_code,
        x.full_name,
        x.status contestant_status,
        c.name category_name,
        c.category_code,
        c.vote_price,
        c.max_votes_per_transaction,
        c.status category_status,
        e.name event_name,
        e.event_code,
        e.status event_status,
        e.admin_revenue_percentage
      FROM contestants x
      JOIN categories c ON c.id=x.category_id AND c.event_id=x.event_id
      JOIN events e ON e.id=x.event_id
      WHERE x.event_id=? AND x.contestant_code=?
      LIMIT 1");
    $stmt->execute([$eventId, $contestantCode]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Contestant code was not found for the selected event.');
    }
    if ($row['event_status'] !== 'Active') {
        throw new RuntimeException('This event is not currently accepting votes.');
    }
    if ($row['category_status'] !== 'Active') {
        throw new RuntimeException('This category is not currently accepting votes.');
    }
    if ($row['contestant_status'] !== 'Active') {
        throw new RuntimeException('This contestant is not currently available for voting.');
    }
    return $row;
}

function fulfillSuccessfulTransaction(PDO $pdo, string $transactionReference, array $paymentData = []): bool
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE transaction_reference=? FOR UPDATE");
        $stmt->execute([$transactionReference]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tx) {
            $pdo->rollBack();
            return false;
        }

        if ($tx['status'] === 'Successful') {
            $pdo->commit();
            return true;
        }

        if ($tx['status'] !== 'Pending') {
            $pdo->commit();
            return false;
        }

        $amountSubunit = isset($paymentData['amount']) ? (int)$paymentData['amount'] : (int)round((float)$tx['amount'] * 100);
        $expectedSubunit = (int)round((float)$tx['amount'] * 100);
        if ($amountSubunit !== $expectedSubunit) {
            $pdo->prepare("UPDATE transactions SET status='Failed', metadata=? WHERE id=?")
                ->execute([json_encode(['reason'=>'Payment amount mismatch','payment'=>$paymentData]), $tx['id']]);
            $pdo->commit();
            return false;
        }

        $metadata = json_encode($paymentData, JSON_UNESCAPED_SLASHES);
        $pdo->prepare("UPDATE transactions SET status='Successful', payment_reference=?, metadata=?, paid_at=NOW() WHERE id=?")
            ->execute([$paymentData['reference'] ?? $transactionReference, $metadata, $tx['id']]);

        $insert = $pdo->prepare("INSERT INTO votes
            (transaction_id,event_id,category_id,contestant_id,phone_number,vote_count)
            VALUES (?,?,?,?,?,?)");
        $insert->execute([
            $tx['id'], $tx['event_id'], $tx['category_id'], $tx['contestant_id'],
            $tx['phone_number'], $tx['vote_count']
        ]);

        $meta = json_decode((string)$tx['metadata'], true);
        if (is_array($meta) && !empty($meta['session_id'])) {
            $pdo->prepare("UPDATE ussd_sessions SET status='Completed',ended_at=NOW(),last_activity_at=NOW() WHERE session_id=?")->execute([(string)$meta['session_id']]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
