<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../includes/voting_functions.php';

try {
    $reference = trim((string)($_POST['reference'] ?? ''));
    if ($reference === '' || !preg_match('/^[A-Za-z0-9.=_-]{3,150}$/', $reference)) {
        jsonResponse(['success'=>false,'message'=>'A valid transaction reference is required.'],422);
    }

    $q = $pdo->prepare("SELECT * FROM transactions WHERE transaction_reference=? LIMIT 1");
    $q->execute([$reference]);
    $tx = $q->fetch(PDO::FETCH_ASSOC);
    if (!$tx) jsonResponse(['success'=>false,'message'=>'VoteHub transaction was not found.'],404);

    if ($tx['status'] === 'Successful') {
        $v = $pdo->prepare("SELECT COALESCE(SUM(vote_count),0) FROM votes WHERE transaction_id=?");
        $v->execute([$tx['id']]);
        jsonResponse(['success'=>true,'payment_status'=>'success','vote_recorded'=>true,'votes_recorded'=>(int)$v->fetchColumn(),'message'=>'Transaction is already successful; no duplicate votes were created.']);
    }

    $response = paystackRequest('GET', '/transaction/verify/' . rawurlencode($reference));
    $data = $response['data'] ?? [];
    $status = strtolower((string)($data['status'] ?? ''));

    $expectedAmount = (int)round((float)$tx['amount'] * 100);
    $actualAmount = isset($data['amount']) ? (int)$data['amount'] : -1;
    $currency = strtoupper((string)($data['currency'] ?? ''));
    $verifiedReference = (string)($data['reference'] ?? '');

    if ($status === 'success') {
        if ($verifiedReference !== $reference) {
            jsonResponse(['success'=>false,'message'=>'Paystack returned a different transaction reference. No vote was recorded.'],409);
        }
        if ($currency !== 'GHS' || $actualAmount !== $expectedAmount) {
            jsonResponse(['success'=>false,'message'=>'Payment was successful but amount/currency did not match VoteHub. No vote was recorded.'],409);
        }
        $fulfilled = fulfillSuccessfulTransaction($pdo, $reference, $data);
        if (!$fulfilled) jsonResponse(['success'=>false,'message'=>'Payment verification succeeded, but VoteHub could not fulfill the transaction.'],409);
        $v = $pdo->prepare("SELECT COALESCE(SUM(vote_count),0) FROM votes WHERE transaction_id=(SELECT id FROM transactions WHERE transaction_reference=?)");
        $v->execute([$reference]);
        jsonResponse(['success'=>true,'payment_status'=>'success','vote_recorded'=>true,'votes_recorded'=>(int)$v->fetchColumn(),'message'=>'Payment verified and votes recorded exactly once.']);
    }

    if (in_array($status, ['failed','abandoned','reversed'], true)) {
        $pdo->prepare("UPDATE transactions SET status='Failed', metadata=? WHERE transaction_reference=? AND status='Pending'")
            ->execute([json_encode($data, JSON_UNESCAPED_SLASHES), $reference]);
        jsonResponse(['success'=>true,'payment_status'=>$status,'vote_recorded'=>false,'message'=>'Paystack reports this payment as '.$status.'. No votes were recorded.']);
    }

    jsonResponse(['success'=>true,'payment_status'=>$status ?: 'unknown','vote_recorded'=>false,'message'=>'Payment is not successful yet. VoteHub remains Pending.']);
} catch (Throwable $e) {
    error_log('VoteHub reconciliation error: '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>'Verification failed. Check Paystack Diagnostics for details.'],500);
}
