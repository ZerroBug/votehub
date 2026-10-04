<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/voting_functions.php';

$rawBody = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
if (!verifyPaystackSignature($rawBody, $signature)) { http_response_code(401); echo 'Invalid signature'; exit; }
$payload = json_decode($rawBody, true);
if (!is_array($payload)) { http_response_code(400); echo 'Invalid JSON'; exit; }
$eventName=(string)($payload['event']??''); $data=$payload['data']??[];
$eventId=(string)($data['id']??''); $reference=(string)($data['reference']??'');
if($eventName==='' || ($eventId==='' && $reference==='')) { http_response_code(400); echo 'Missing event identifier'; exit; }
$key=$eventName.':'.($eventId!==''?$eventId:$reference);
try {
    $stmt=$pdo->prepare("SELECT id,processed_at FROM webhook_events WHERE event_key=? LIMIT 1"); $stmt->execute([$key]); $existing=$stmt->fetch(PDO::FETCH_ASSOC);
    if($existing && $existing['processed_at']!==null){ http_response_code(200); echo 'OK'; exit; }
    if(!$existing){ $stmt=$pdo->prepare("INSERT INTO webhook_events(event_key,event_name,payload) VALUES(?,?,?)"); $stmt->execute([$key,$eventName,$rawBody]); }
    else { $pdo->prepare("UPDATE webhook_events SET payload=? WHERE event_key=?")->execute([$rawBody,$key]); }
    if($eventName==='charge.success' && $reference!==''){
        // Verify independently before granting votes. This prevents trusting an incomplete webhook payload.
        $verified=paystackRequest('GET','/transaction/verify/'.rawurlencode($reference));
        $verifiedData=$verified['data']??[];
        if(strtolower((string)($verifiedData['status']??''))!=='success') throw new RuntimeException('Webhook received but Paystack verification is not successful yet.');
        if((string)($verifiedData['reference']??'')!==$reference) throw new RuntimeException('Paystack reference mismatch.');
        fulfillSuccessfulTransaction($pdo,$reference,$verifiedData);
    }
    $pdo->prepare("UPDATE webhook_events SET processed_at=NOW() WHERE event_key=?")->execute([$key]);
    http_response_code(200); echo 'OK';
} catch(Throwable $e){ error_log('VoteHub webhook processing failed: '.$e->getMessage()); http_response_code(500); echo 'Webhook processing failed'; }
