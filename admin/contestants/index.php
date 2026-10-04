<?php
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../../includes/functions.php';

$selectedEventId=(int)($_GET['event_id']??$_POST['event_id']??0);
$selectedCategoryId=(int)($_GET['category_id']??$_POST['category_id']??0);

$events=$pdo->query("SELECT id,name,event_code,status FROM events ORDER BY CASE status WHEN 'Active' THEN 1 WHEN 'Scheduled' THEN 2 ELSE 3 END,name")->fetchAll();
$allCategories=$pdo->query("SELECT id,event_id,name,category_code,status FROM categories WHERE status='Active' ORDER BY event_id,display_order,name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
    $eventId=(int)($_POST['event_id']??0); $categoryId=(int)($_POST['category_id']??0); $name=trim($_POST['full_name']??'');
    if(!$eventId||!$categoryId||!$name){flash('danger','Event, category and contestant name are required.');}
    else{
        $stmt=$pdo->prepare("SELECT id FROM categories WHERE id=? AND event_id=? AND status='Active'");
        $stmt->execute([$categoryId,$eventId]);
        if(!$stmt->fetch()){flash('danger','The selected category does not belong to the selected event.');}
        else{
            try{
                $code=generateContestantTicketCode($pdo,$eventId,$categoryId);
                $stmt=$pdo->prepare("INSERT INTO contestants(event_id,category_id,contestant_code,full_name,gender,biography,status) VALUES(?,?,?,?,?,?,?)");
                $stmt->execute([$eventId,$categoryId,$code,$name,$_POST['gender']??'Female',trim($_POST['biography']??''),$_POST['status']??'Active']);
                flash('success',"Contestant added successfully. Voting/Ticket Code: {$code}");
                redirect("index.php?event_id={$eventId}&category_id={$categoryId}");
            }catch(Throwable $e){flash('danger','Could not add contestant: '.$e->getMessage());}
        }
    }
}

$event=null;$contestants=[];
if($selectedEventId){
    $stmt=$pdo->prepare("SELECT * FROM events WHERE id=?");$stmt->execute([$selectedEventId]);$event=$stmt->fetch();
    if($event){
        $sql="SELECT x.*,c.name category_name,c.category_code,(SELECT COALESCE(SUM(v.vote_count),0) FROM votes v WHERE v.contestant_id=x.id) votes FROM contestants x JOIN categories c ON c.id=x.category_id WHERE x.event_id=?";
        $params=[$selectedEventId];
        if($selectedCategoryId){$sql.=" AND x.category_id=?";$params[]=$selectedCategoryId;}
        $sql.=" ORDER BY x.id DESC";
        $stmt=$pdo->prepare($sql);$stmt->execute($params);$contestants=$stmt->fetchAll();
    }
}
$pageTitle='Contestants';require_once __DIR__.'/../../includes/header.php';require_once __DIR__.'/../../includes/sidebar.php';
?>
<main class="main"><?php require_once __DIR__.'/../../includes/topbar.php';?><section class="content">
<?php showFlash();?>
<div class="d-flex justify-content-between align-items-end mb-4"><div><span class="section-kicker">CONTESTANT MANAGEMENT</span><h1 class="page-title mb-1">Contestants</h1><p class="page-subtitle mb-0">Each contestant receives a unique <strong>4-digit voting/ticket code</strong>.</p></div></div>

<div class="panel mb-4"><div class="panel-body"><div class="row g-3 align-items-end">
<div class="col-lg-5"><label class="form-label">Select Event</label><select id="eventFilter" class="form-select"><option value="">Choose an event...</option><?php foreach($events as $ev):?><option value="<?=$ev['id']?>" <?=$selectedEventId==(int)$ev['id']?'selected':''?>><?=e($ev['name'])?> — <?=e($ev['event_code'])?> (<?=e($ev['status'])?>)</option><?php endforeach;?></select></div>
<div class="col-lg-5"><label class="form-label">Filter by Category</label><select id="categoryFilter" class="form-select" <?=$selectedEventId?'':'disabled'?>><option value="">All categories</option><?php foreach($allCategories as $c): if((int)$c['event_id']===$selectedEventId):?><option value="<?=$c['id']?>" <?=$selectedCategoryId==(int)$c['id']?'selected':''?>><?=e($c['name'])?> — <?=e($c['category_code'])?></option><?php endif; endforeach;?></select></div>
<div class="col-lg-2"><a id="filterButton" href="<?=$selectedEventId?'index.php?event_id='.$selectedEventId:'#'?>" class="btn btn-primary w-100 <?=$selectedEventId?'':'disabled'?>"><i class="bi bi-funnel me-1"></i>View</a></div>
</div></div></div>

<?php if(!$event):?>
<div class="empty-state"><div class="empty-icon"><i class="bi bi-calendar2-event"></i></div><h5>Select an event</h5><p class="text-secondary mb-0">Choose an event above to manage its contestants.</p></div>
<?php else:?>
<div class="row g-4">
<div class="col-xl-8"><div class="panel"><div class="panel-head"><div><h5><?=e($event['name'])?></h5><small class="text-secondary"><?=count($contestants)?> contestant(s) shown</small></div><span class="code-pill"><?=e($event['event_code'])?></span></div>
<div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Contestant</th><th>Category</th><th>4-Digit Code</th><th>Votes</th><th>Status</th></tr></thead><tbody>
<?php foreach($contestants as $x):?><tr>
<td><div class="d-flex align-items-center gap-2"><div class="contestant-photo"><?=e(strtoupper(substr($x['full_name'],0,2)))?></div><div><strong><?=e($x['full_name'])?></strong><small class="d-block text-secondary"><?=e($x['gender'])?></small></div></div></td>
<td><strong><?=e($x['category_name'])?></strong><small class="d-block text-secondary"><?=e($x['category_code'])?></small></td>
<td><span class="ticket-code"><?=e($x['contestant_code'])?></span></td><td><strong><?=number_format((int)$x['votes'])?></strong></td>
<td><span class="badge-soft badge-live"><?=e($x['status'])?></span></td></tr><?php endforeach;?>
<?php if(!$contestants):?><tr><td colspan="6" class="text-center py-5 text-secondary"><i class="bi bi-people fs-2 d-block mb-2"></i>No contestants found.</td></tr><?php endif;?>
</tbody></table></div></div></div>

<div class="col-xl-4"><div class="panel sticky-xl-top" style="top:95px"><div class="panel-head"><div><h5>Add Contestant</h5><small class="text-secondary">Code is generated automatically.</small></div><i class="bi bi-person-plus text-primary"></i></div><div class="panel-body">
<form method="post">
<label class="form-label">Event *</label><select name="event_id" id="eventSelect" class="form-select mb-3" required><option value="">Select event</option><?php foreach($events as $ev):?><option value="<?=$ev['id']?>" <?=$selectedEventId==(int)$ev['id']?'selected':''?>><?=e($ev['name'])?> — <?=e($ev['event_code'])?></option><?php endforeach;?></select>
<label class="form-label">Category *</label><select name="category_id" id="categorySelect" class="form-select mb-2" required><option value="">Select event first</option><?php foreach($allCategories as $c): if((int)$c['event_id']===$selectedEventId):?><option value="<?=$c['id']?>" <?=$selectedCategoryId==(int)$c['id']?'selected':''?>><?=e($c['name'])?> — <?=e($c['category_code'])?></option><?php endif; endforeach;?></select>
<div class="form-help mb-3">All active categories belonging to the selected event populate automatically.</div>
<div class="ticket-preview mb-3"><div><small>AUTO-GENERATED 4-DIGIT VOTING CODE</small><strong id="ticketPreview">----</strong></div><i class="bi bi-ticket-perforated"></i></div>
<div class="form-help mb-3"><strong>Voter use:</strong> the voter enters this 4-digit code during USSD to retrieve the contestant before voting.</div>
<label class="form-label">Full Name *</label><input name="full_name" class="form-control mb-3" required placeholder="Ama Serwaa">
<label class="form-label">Gender</label><select name="gender" class="form-select mb-3"><option>Female</option><option>Male</option><option>Other</option></select>

<label class="form-label">Biography</label><textarea name="biography" class="form-control mb-3" rows="3"></textarea>
<label class="form-label">Status</label><select name="status" class="form-select mb-4"><option>Active</option><option>Inactive</option><option>Disqualified</option></select>
<button class="btn btn-primary w-100"><i class="bi bi-person-plus me-1"></i>Add Contestant</button>
</form></div></div></div>
</div>

<div class="panel mt-4"><div class="panel-body"><div class="d-flex gap-3 align-items-start"><div class="info-icon"><i class="bi bi-phone"></i></div><div><h6 class="mb-1">USSD Voting Flow</h6><p class="text-secondary mb-0">Voter selects the event → enters the contestant's <strong>4-digit code</strong> → system retrieves contestant name and category → voter confirms → enters vote quantity → payment is initiated → only successful payment creates the vote.</p></div></div></div></div>
<?php endif;?>
</section></main>

<script>
const allCategories=<?=json_encode($allCategories,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
function populateCategories(eventId,selectedId=''){const s=document.getElementById('categorySelect');if(!s)return;s.innerHTML='<option value="">Select category</option>';allCategories.filter(c=>String(c.event_id)===String(eventId)).forEach(c=>{let o=document.createElement('option');o.value=c.id;o.textContent=c.name+' — '+c.category_code;if(String(c.id)===String(selectedId))o.selected=true;s.appendChild(o);});document.getElementById('ticketPreview').textContent=eventId?'AUTO':'----';}
document.addEventListener('DOMContentLoaded',()=>{
 const ef=document.getElementById('eventFilter'),cf=document.getElementById('categoryFilter'),fb=document.getElementById('filterButton'),es=document.getElementById('eventSelect'),cs=document.getElementById('categorySelect');
 ef?.addEventListener('change',()=>{window.location.href=ef.value?'index.php?event_id='+ef.value:'index.php';});
 cf?.addEventListener('change',()=>{if(ef.value)fb.href='index.php?event_id='+ef.value+(cf.value?'&category_id='+cf.value:'');});
 es?.addEventListener('change',()=>populateCategories(es.value));
 cs?.addEventListener('change',()=>document.getElementById('ticketPreview').textContent=cs.value?'AUTO':'----');
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
