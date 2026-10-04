<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/voting_functions.php';

try {
    $events = $pdo->query("SELECT id,name,event_code,description,logo,start_date,end_date,default_vote_price,status FROM events WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $categories = $pdo->query("SELECT id,event_id,name,category_code,description,vote_price,max_votes_per_transaction,status,display_order FROM categories WHERE status='Active' ORDER BY event_id,display_order,name")->fetchAll(PDO::FETCH_ASSOC);
    $contestants = $pdo->query("SELECT x.id,x.event_id,x.category_id,x.contestant_code,x.full_name,x.photo,x.gender,x.biography,x.status,c.name category_name,c.category_code,c.vote_price FROM contestants x JOIN categories c ON c.id=x.category_id AND c.event_id=x.event_id WHERE x.status='Active' AND c.status='Active' ORDER BY x.category_id,x.full_name")->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['success'=>true,'events'=>$events,'categories'=>$categories,'contestants'=>$contestants]);
} catch(Throwable $e) {
    jsonResponse(['success'=>false,'message'=>'Voting catalogue is temporarily unavailable.'],500);
}
