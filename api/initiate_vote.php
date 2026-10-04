<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../includes/voting_functions.php';

try {
    $eventId=(int)($_POST['event_id']??0);
    $contestantCode=preg_replace('/\D/','',(string)($_POST['contestant_code']??''));
    $voteCount=(int)($_POST['vote_count']??0);
    $phone=normalizePhone((string)($_POST['phone_number']??''));
    $provider=providerCode((string)($_POST['provider']??''));
    $email=trim((string)($_POST['email']??''));
    if(!$eventId||strlen($contestantCode)!==4||$voteCount<1) jsonResponse(['success'=>false,'message'=>'Please select an event, enter a valid 4-digit contestant code and choose at least one vote.'],422);
    if(!preg_match('/^0[2-5][0-9]{8}$/',$phone)) jsonResponse(['success'=>false,'message'=>'Enter a valid Ghana mobile-money number, e.g. 024XXXXXXX.'],422);
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) jsonResponse(['success'=>false,'message'=>'Enter a valid email address.'],422);

    $selection=getVotingSelection($pdo,$eventId,$contestantCode);
    $max=(int)$selection['max_votes_per_transaction'];
    if($max>0&&$voteCount>$max) jsonResponse(['success'=>false,'message'=>'Maximum votes per transaction for this category is '.$max.'.'],422);
    $amount=round((float)$selection['vote_price']*$voteCount,2);
    if($amount<=0) jsonResponse(['success'=>false,'message'=>'This category has an invalid vote price.'],422);
    $amountSubunit=(int)round($amount*100);
    if($amountSubunit<10) jsonResponse(['success'=>false,'message'=>'The payment amount is below Paystack’s minimum for GHS.'],422);

    $reference=generateTransactionReference();
    $meta=['source'=>'VoteHub','event_code'=>$selection['event_code'],'contestant_code'=>$selection['contestant_code'],'category_code'=>$selection['category_code'],'provider'=>$provider];
    $stmt=$pdo->prepare("INSERT INTO transactions (transaction_reference,event_id,category_id,contestant_id,phone_number,vote_count,amount,payment_provider,status,metadata) VALUES (?,?,?,?,?,?,?,?, 'Pending', ?)");
    $stmt->execute([$reference,$eventId,$selection['category_id'],$selection['contestant_id'],$phone,$voteCount,$amount,'Paystack Mobile Money',json_encode($meta,JSON_UNESCAPED_SLASHES)]);
    $transactionId=(int)$pdo->lastInsertId();

    try {
        $response=paystackRequest('POST','/charge',[
            'amount'=>(string)$amountSubunit,'email'=>$email,'currency'=>'GHS','reference'=>$reference,
            'mobile_money'=>['phone'=>$phone,'provider'=>$provider],
            'metadata'=>['votehub_transaction_id'=>$transactionId,'event_id'=>$eventId,'contestant_id'=>(int)$selection['contestant_id'],'vote_count'=>$voteCount,'event_code'=>$selection['event_code'],'contestant_code'=>$selection['contestant_code']]
        ]);
    } catch(Throwable $e) {
        $safe=['error'=>$e->getMessage(),'code'=>$e instanceof PaystackException?$e->codeName:'PAYSTACK_ERROR','http'=>$e instanceof PaystackException?$e->httpStatus:0];
        $pdo->prepare("UPDATE transactions SET status='Failed',metadata=? WHERE id=?")->execute([json_encode(array_merge($meta,$safe),JSON_UNESCAPED_SLASHES),$transactionId]);
        error_log('[VoteHub Paystack] '.json_encode(array_merge(['reference'=>$reference,'transaction_id'=>$transactionId],$safe),JSON_UNESCAPED_SLASHES));
        jsonResponse(['success'=>false,'message'=>'Payment could not be started. '.$e->getMessage(),'transaction_reference'=>$reference,'diagnostic_code'=>$safe['code']],502);
    }

    $data=$response['data']??[];
    $paymentReference=(string)($data['reference']??$reference);
    $status=(string)($data['status']??'pending');
    $display=(string)($data['display_text']??'Please authorize the payment on your mobile phone.');
    $storedMeta=array_merge($meta,['paystack'=>$data]);
    $pdo->prepare("UPDATE transactions SET payment_reference=?,metadata=? WHERE id=?")->execute([$paymentReference,json_encode($storedMeta,JSON_UNESCAPED_SLASHES),$transactionId]);
    jsonResponse(['success'=>true,'message'=>$display,'transaction_reference'=>$reference,'payment_reference'=>$paymentReference,'status'=>$status,'display_text'=>$display,'event'=>$selection['event_name'],'contestant'=>$selection['full_name'],'category'=>$selection['category_name'],'vote_count'=>$voteCount,'amount'=>$amount]);
} catch(Throwable $e) {
    error_log('[VoteHub initiate_vote] '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>$e->getMessage()],500);
}
