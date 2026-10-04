<?php
declare(strict_types=1);

require_once __DIR__ . '/voting_functions.php';
require_once __DIR__ . '/../config/paystack.php';

final class UssdResponse extends RuntimeException
{
    public function __construct(public string $prefix, public string $responseMessage)
    {
        parent::__construct($prefix . ' ' . $responseMessage);
    }
}

function ussdResponse(string $prefix, string $message): string
{
    return $prefix . ' ' . $message;
}

function runVoteHubUssd(PDO $pdo, string $sessionId, string $phone, string $text): string
{
    if ($sessionId === '' || $phone === '') {
        return 'END Invalid USSD request.';
    }

    $parts = $text === '' ? [] : array_map('trim', explode('*', $text));

    try {
    // Start / reset session.
    if (count($parts) === 0) {
        $events = $pdo->query("SELECT id,name,event_code FROM events WHERE status='Active' ORDER BY name LIMIT 9")->fetchAll(PDO::FETCH_ASSOC);
        if (!$events) {
            throw new UssdResponse('END', 'Voting is currently unavailable. No active event is open.');
        }
        $pdo->prepare("INSERT INTO ussd_sessions(session_id,phone_number,current_step,state_data,status,last_activity_at) VALUES(?,?, 'event', ?, 'Active',NOW()) ON DUPLICATE KEY UPDATE phone_number=VALUES(phone_number),current_step='event',state_data=VALUES(state_data),status='Active',last_activity_at=NOW()")
            ->execute([$sessionId, $phone, json_encode(['events'=>$events], JSON_UNESCAPED_SLASHES)]);
        $menu = "Welcome to VoteHub\nSelect Event:\n";
        foreach ($events as $i => $event) {
            $menu .= ($i + 1) . '. ' . $event['name'] . "\n";
        }
        throw new UssdResponse('CON', rtrim($menu));
    }

    $sessionStmt = $pdo->prepare("SELECT * FROM ussd_sessions WHERE session_id=? LIMIT 1");
    $sessionStmt->execute([$sessionId]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    $state = $session ? json_decode((string)$session['state_data'], true) : [];
    if (!is_array($state)) $state = [];

    // Event selection.
    if (count($parts) === 1) {
        $choice = (int)$parts[0];
        $events = $state['events'] ?? [];
        if ($choice < 1 || $choice > count($events)) throw new UssdResponse('END', 'Invalid event selection. Please try again.');
        $event = $events[$choice - 1];
        $state = ['event_id'=>(int)$event['id'], 'event_name'=>$event['name'], 'event_code'=>$event['event_code']];
        $pdo->prepare("UPDATE ussd_sessions SET event_id=?,current_step='contestant',state_data=?,last_activity_at=NOW() WHERE session_id=?")
            ->execute([$event['id'], json_encode($state, JSON_UNESCAPED_SLASHES), $sessionId]);
        throw new UssdResponse('CON', "{$event['name']}\nEnter 4-digit contestant code:");
    }

    $eventId = (int)($state['event_id'] ?? 0);
    if (!$eventId) throw new UssdResponse('END', 'Session expired. Please dial the USSD code again.');

    // Contestant code.
    if (count($parts) === 2) {
        $code = preg_replace('/\D/', '', $parts[1]);
        if (strlen($code) !== 4) throw new UssdResponse('CON', 'Invalid code. Enter exactly 4 digits:');
        $selection = getVotingSelection($pdo, $eventId, $code);
        $state['contestant_code'] = $code;
        $state['contestant_id'] = (int)$selection['contestant_id'];
        $state['category_id'] = (int)$selection['category_id'];
        $state['vote_price'] = (float)$selection['vote_price'];
        $state['max_votes'] = (int)$selection['max_votes_per_transaction'];
        $pdo->prepare("UPDATE ussd_sessions SET selected_category_id=?,selected_contestant_id=?,current_step='confirm',state_data=?,last_activity_at=NOW() WHERE session_id=?")
            ->execute([$selection['category_id'],$selection['contestant_id'],json_encode($state,JSON_UNESCAPED_SLASHES),$sessionId]);
        throw new UssdResponse('CON', "{$selection['full_name']}\n{$selection['category_name']}\nGHS " . number_format((float)$selection['vote_price'],2) . " per vote\n1. Confirm\n2. Cancel");
    }

    // Confirmation.
    if (count($parts) === 3) {
        if ((int)$parts[2] !== 1) throw new UssdResponse('END', 'Vote cancelled. Thank you.');
        $max = max(1, (int)($state['max_votes'] ?? 20));
        throw new UssdResponse('CON', "Enter number of votes (1-$max):");
    }

    // Vote quantity.
    if (count($parts) === 4) {
        $qty = (int)$parts[3];
        $max = max(1, (int)($state['max_votes'] ?? 20));
        if ($qty < 1 || $qty > $max) throw new UssdResponse('CON', "Invalid quantity. Enter a number from 1 to $max:");
        $amount = round((float)$state['vote_price'] * $qty, 2);
        $state['vote_count'] = $qty;
        $state['amount'] = $amount;
        $pdo->prepare("UPDATE ussd_sessions SET selected_vote_count=?,current_step='provider',state_data=?,last_activity_at=NOW() WHERE session_id=?")
            ->execute([$qty,json_encode($state,JSON_UNESCAPED_SLASHES),$sessionId]);
        throw new UssdResponse('CON', "{$qty} vote(s) = GHS " . number_format($amount,2) . "\nSelect payment network:\n1. MTN\n2. Telecel\n3. ATMoney/Airtel Money");
    }

    // Provider + initiate payment.
    if (count($parts) === 5) {
        $provider = match ((int)$parts[4]) { 1=>'mtn', 2=>'vod', 3=>'atl', default=>null };
        if ($provider === null) throw new UssdResponse('CON', 'Invalid network. Choose 1, 2 or 3:');

        $selection = getVotingSelection($pdo, $eventId, (string)$state['contestant_code']);
        $qty = (int)$state['vote_count'];
        $amount = round((float)$selection['vote_price'] * $qty, 2);
        $reference = generateTransactionReference();
        $metadata = json_encode(['source'=>'USSD','session_id'=>$sessionId,'event_code'=>$selection['event_code'],'contestant_code'=>$selection['contestant_code'],'network'=>$provider],JSON_UNESCAPED_SLASHES);

        $stmt = $pdo->prepare("INSERT INTO transactions(transaction_reference,event_id,category_id,contestant_id,phone_number,vote_count,amount,payment_provider,status,metadata) VALUES(?,?,?,?,?,?,?,'Paystack Mobile Money','Pending',?)");
        $stmt->execute([$reference,$eventId,$selection['category_id'],$selection['contestant_id'],$phone,$qty,$amount,$metadata]);
        $transactionId = (int)$pdo->lastInsertId();

        try {
            $response = paystackRequest('POST','/charge',[
                'amount'=>(int)round($amount*100),
                'email'=>'voter-' . substr($phone,1) . '@votehubgh.org',
                'currency'=>'GHS',
                'reference'=>$reference,
                'mobile_money'=>['phone'=>$phone,'provider'=>$provider],
                'metadata'=>[
                    'votehub_transaction_id'=>$transactionId,
                    'session_id'=>$sessionId,
                    'event_id'=>$eventId,
                    'contestant_id'=>(int)$selection['contestant_id'],
                    'vote_count'=>$qty
                ]
            ]);
        } catch (Throwable $e) {
            $safeError = [
                'error'=>$e->getMessage(),
                'code'=>$e instanceof PaystackException ? $e->codeName : 'PAYSTACK_ERROR',
                'http'=>$e instanceof PaystackException ? $e->httpStatus : 0,
                'paystack_status'=>$e instanceof PaystackException && is_array($e->responseData) ? ($e->responseData['status'] ?? null) : null,
                'paystack_message'=>$e instanceof PaystackException && is_array($e->responseData) ? ($e->responseData['message'] ?? null) : null,
                'paystack_data'=>$e instanceof PaystackException && is_array($e->responseData) ? ($e->responseData['data'] ?? null) : null,
                'reference'=>$reference,
                'provider'=>$provider,
                'amount'=>$amount,
                'phone_last4'=>substr($phone,-4)
            ];
            $pdo->prepare("UPDATE transactions SET status='Failed',metadata=? WHERE transaction_reference=?")->execute([json_encode(['source'=>'USSD','session_id'=>$sessionId,'payment_error'=>$safeError],JSON_UNESCAPED_SLASHES),$reference]);
            error_log('[VoteHub USSD Paystack] '.json_encode($safeError,JSON_UNESCAPED_SLASHES));
            if (!headers_sent()) {
                header('X-VoteHub-Payment-Diagnostic: '.rawurlencode((string)$safeError['code'].' | HTTP '.$safeError['http'].' | '.$safeError['error']));
            }
            throw new UssdResponse('END', 'Payment could not be started. Please try again.');
        }

        $payData = $response['data'] ?? [];
        $pdo->prepare("UPDATE transactions SET payment_reference=?,metadata=? WHERE transaction_reference=?")
            ->execute([$payData['reference'] ?? $reference,json_encode(['source'=>'USSD','session_id'=>$sessionId,'network'=>$provider,'paystack'=>$payData],JSON_UNESCAPED_SLASHES),$reference]);
        $pdo->prepare("UPDATE ussd_sessions SET current_step='payment',state_data=?,last_activity_at=NOW() WHERE session_id=?")
            ->execute([json_encode(array_merge($state,['transaction_reference'=>$reference]),JSON_UNESCAPED_SLASHES),$sessionId]);
        throw new UssdResponse('END', 'Payment request sent. Approve GHS ' . number_format($amount,2) . ' on your phone. Ref: ' . $reference . '. Your votes are recorded only after payment is confirmed.');
    }

    return ussdResponse('END', 'Invalid request. Please start again.');

    } catch (UssdResponse $response) {
        return ussdResponse($response->prefix, $response->responseMessage);
    } catch (Throwable $e) {
        error_log('VoteHub USSD error: ' . $e->getMessage());
        return ussdResponse('END', 'Unable to process your request. Please try again later.');
    }
}
