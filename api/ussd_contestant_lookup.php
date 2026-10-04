<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

$eventId=(int)($_POST['event_id']??$_GET['event_id']??0);
$code=preg_replace('/\D/','',(string)($_POST['contestant_code']??$_GET['contestant_code']??''));

if(!$eventId || strlen($code)!==4){
 http_response_code(422); echo json_encode(['success'=>false,'message'=>'Enter a valid 4-digit contestant code.']); exit;
}

$stmt=$pdo->prepare("SELECT x.id,x.event_id,x.contestant_code,x.full_name,x.gender,c.id category_id,c.name category_name,c.category_code,c.vote_price,e.name event_name,e.event_code FROM contestants x JOIN categories c ON c.id=x.category_id AND c.event_id=x.event_id JOIN events e ON e.id=x.event_id WHERE x.event_id=? AND x.contestant_code=? AND x.status='Active' AND c.status='Active' AND e.status='Active' LIMIT 1");
$stmt->execute([$eventId,$code]);$c=$stmt->fetch();

if(!$c){
 http_response_code(404); echo json_encode(['success'=>false,'message'=>'Contestant code not found or contestant is not currently available for voting.']); exit;
}

echo json_encode(['success'=>true,'message'=>'Contestant found.','contestant'=>[
 'id'=>(int)$c['id'],'code'=>$c['contestant_code'],'name'=>$c['full_name'],
 'category_id'=>(int)$c['category_id'],'category'=>$c['category_name'],'category_code'=>$c['category_code'],
 'vote_price'=>(float)$c['vote_price'],'event'=>$c['event_name'],'event_code'=>$c['event_code']
]]);
