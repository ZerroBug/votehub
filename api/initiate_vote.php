<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../includes/voting_functions.php';

try {
    $eventId = (int)($_POST['event_id'] ?? 0);
    $contestantCode = preg_replace('/\D/', '', (string)($_POST['contestant_code'] ?? ''));
    $voteCount = (int)($_POST['vote_count'] ?? 0);
    $phone = normalizePhone((string)($_POST['phone_number'] ?? ''));
    $provider = providerCode((string)($_POST['provider'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));

    if (!$eventId || strlen($contestantCode) !== 4 || $voteCount < 1 || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success'=>false,'message'=>'Event, 4-digit contestant code, vote quantity, valid phone number and email are required.'],422);
    }

    $selection = getVotingSelection($pdo, $eventId, $contestantCode);
    $max = (int)$selection['max_votes_per_transaction'];
    if ($max > 0 && $voteCount > $max) {
        jsonResponse(['success'=>false,'message'=>'Maximum votes per transaction for this category is ' . $max . '.'],422);
    }

    $amount = round((float)$selection['vote_price'] * $voteCount, 2);
    $reference = generateTransactionReference();

    $meta = json_encode([
        'source'=>'VoteHub',
        'event_code'=>$selection['event_code'],
        'contestant_code'=>$selection['contestant_code'],
        'category_code'=>$selection['category_code']
    ], JSON_UNESCAPED_SLASHES);

    $stmt = $pdo->prepare("INSERT INTO transactions
        (transaction_reference,event_id,category_id,contestant_id,phone_number,vote_count,amount,payment_provider,status,metadata)
        VALUES (?,?,?,?,?,?,?,?, 'Pending', ?)");
    $stmt->execute([
        $reference, $eventId, $selection['category_id'], $selection['contestant_id'],
        $phone, $voteCount, $amount, 'Paystack Mobile Money', $meta
    ]);

    try {
        $paystack = paystackRequest('POST', '/charge', [
            'amount' => (string)round($amount * 100),
            'email' => $email,
            'currency' => 'GHS',
            'reference' => $reference,
            'mobile_money' => [
                'phone' => $phone,
                'provider' => $provider,
            ],
            'metadata' => json_encode([
                'votehub_transaction_id' => (int)$pdo->lastInsertId(),
                'event_id'=>$eventId,
                'contestant_id'=>(int)$selection['contestant_id'],
                'vote_count'=>$voteCount
            ], JSON_UNESCAPED_SLASHES)
        ]);
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE transactions SET status='Failed', metadata=? WHERE transaction_reference=?")
            ->execute([json_encode(['error'=>$e->getMessage()]), $reference]);
        throw $e;
    }

    $data = $paystack['data'] ?? [];
    $pdo->prepare("UPDATE transactions SET payment_reference=?, metadata=? WHERE transaction_reference=?")
        ->execute([
            $data['reference'] ?? $reference,
            json_encode(['paystack'=>$data], JSON_UNESCAPED_SLASHES),
            $reference
        ]);

    jsonResponse([
        'success'=>true,
        'message'=>$data['display_text'] ?? 'Payment initiated. Please authorize the payment on the customer mobile phone.',
        'transaction_reference'=>$reference,
        'status'=>$data['status'] ?? 'pending',
        'display_text'=>$data['display_text'] ?? null,
        'event'=>$selection['event_name'],
        'contestant'=>$selection['full_name'],
        'category'=>$selection['category_name'],
        'vote_count'=>$voteCount,
        'amount'=>$amount
    ]);
} catch (Throwable $e) {
    jsonResponse(['success'=>false,'message'=>$e->getMessage()],500);
}
