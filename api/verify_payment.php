<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../includes/voting_functions.php';

try {
    $reference = trim((string)($_POST['reference'] ?? $_GET['reference'] ?? ''));
    if ($reference === '') {
        jsonResponse(['success'=>false,'message'=>'Transaction reference is required.'],422);
    }

    $response = paystackRequest('GET', '/transaction/verify/' . rawurlencode($reference));
    $data = $response['data'] ?? [];
    $status = (string)($data['status'] ?? '');

    if ($status === 'success') {
        $fulfilled = fulfillSuccessfulTransaction($pdo, $reference, $data);
        jsonResponse([
            'success'=>$fulfilled,
            'payment_status'=>'success',
            'vote_recorded'=>$fulfilled,
            'message'=>$fulfilled ? 'Payment confirmed and vote recorded.' : 'Payment was successful but could not be fulfilled.'
        ], $fulfilled ? 200 : 409);
    }

    $txStatus = match ($status) {
        'failed','abandoned','reversed' => 'Failed',
        default => 'Pending'
    };
    if ($txStatus === 'Failed') {
        $pdo->prepare("UPDATE transactions SET status='Failed', metadata=? WHERE transaction_reference=? AND status='Pending'")
            ->execute([json_encode($data, JSON_UNESCAPED_SLASHES), $reference]);
    }

    jsonResponse([
        'success'=>true,
        'payment_status'=>$status ?: 'unknown',
        'vote_recorded'=>false,
        'message'=>$data['gateway_response'] ?? $data['message'] ?? 'Payment is not successful yet.'
    ]);
} catch (Throwable $e) {
    jsonResponse(['success'=>false,'message'=>$e->getMessage()],500);
}
