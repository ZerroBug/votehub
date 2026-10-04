<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../includes/voting_functions.php';

$rawBody = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
if (!verifyPaystackSignature($rawBody, $signature)) { http_response_code(401); echo 'Invalid signature'; exit; }
$payload = json_decode($rawBody, true);
if (!is_array($payload)) { http_response_code(400); echo 'Invalid JSON'; exit; }
$eventName=(string)($payload['event']??'');
$data=$payload['data']??[];
$eventId=(string)($data['id']??'');
$reference=(string)($data['reference']??'');
if($eventName==='' || ($eventId==='' && $reference==='')) { http_response_code(400); echo 'Missing event identifier'; exit; }
$key=$eventName.':'.($eventId!==''?$eventId:$reference);
try {
    $stmt=$pdo->prepare("INSERT IGNORE INTO webhook_events(event_key,event_name,payload) VALUES(?,?,?)");
    $stmt->execute([$key,$eventName,$rawBody]);
    $isNew=$stmt->rowCount()===1;
} catch(Throwable $e){ error_log('VoteHub webhook log error: '.$e->getMessage()); $isNew=true; }
http_response_code(200); echo 'OK';
if(!$isNew) exit;
if($eventName!=='charge.success' || $reference==='') exit;
try {
    fulfillSuccessfulTransaction($pdo,$reference,$data);
    $pdo->prepare("UPDATE webhook_events SET processed_at=NOW() WHERE event_key=?")->execute([$key]);
} catch(Throwable $e){ error_log('VoteHub webhook fulfillment failed: '.$e->getMessage()); }
