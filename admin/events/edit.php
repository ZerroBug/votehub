<?php
require_once __DIR__ . '/../../includes/auth.php'; requireSuperAdmin();
require_once __DIR__ . '/../../includes/functions.php';
$id=(int)($_GET['id']??0); $stmt=$pdo->prepare("SELECT * FROM events WHERE id=?"); $stmt->execute([$id]); $event=$stmt->fetch();
if(!$event) die('Event not found.');
if($_SERVER['REQUEST_METHOD']==='POST'){
    $adminPct=(float)($_POST['admin_revenue_percentage']??30);
    if($adminPct < 0 || $adminPct > 100){ flash('danger','Admin/VoteHub revenue percentage must be between 0% and 100%.'); }
    else {
        try{
            $stmt=$pdo->prepare("UPDATE events SET name=?,event_code=?,description=?,start_date=?,end_date=?,status=?,default_vote_price=?,admin_revenue_percentage=?,ussd_code=? WHERE id=?");
            $stmt->execute([trim($_POST['name']),strtoupper(trim($_POST['event_code'])),trim($_POST['description']),$_POST['start_date'],$_POST['end_date'],$_POST['status'],(float)$_POST['default_vote_price'],$adminPct,trim($_POST['ussd_code']),$id]);
            flash('success','Event updated successfully.'); redirect('view.php?id='.$id);
        }catch(PDOException $ex){ flash('danger','Could not update event. Event code may already exist.'); }
    }
}
$split=getEventRevenueSplit($event);
$pageTitle='Edit Event'; require_once __DIR__ . '/../../includes/header.php'; require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="main"><?php require_once __DIR__ . '/../../includes/topbar.php'; ?><section class="content">
<?php showFlash(); ?><h1 class="page-title mb-1">Edit Event</h1><p class="page-subtitle mb-4"><?= e($event['name']) ?></p>
<div class="panel"><div class="panel-body"><form method="post"><div class="row g-3">
<div class="col-md-8"><label class="form-label">Event Name</label><input name="name" class="form-control" required value="<?= e($event['name']) ?>"></div>
<div class="col-md-4"><label class="form-label">Event Code</label><input name="event_code" class="form-control" required value="<?= e($event['event_code']) ?>"></div>
<div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= e($event['description']) ?></textarea></div>
<div class="col-md-6"><label class="form-label">Start Date</label><input type="datetime-local" name="start_date" class="form-control" value="<?= date('Y-m-d\TH:i',strtotime($event['start_date'])) ?>"></div>
<div class="col-md-6"><label class="form-label">End Date</label><input type="datetime-local" name="end_date" class="form-control" value="<?= date('Y-m-d\TH:i',strtotime($event['end_date'])) ?>"></div>
<div class="col-md-4"><label class="form-label">Vote Price</label><input type="number" step=".01" min="0" name="default_vote_price" class="form-control" value="<?= e($event['default_vote_price']) ?>"></div>
<div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach(['Draft','Scheduled','Active','Paused','Closed','Archived'] as $s): ?><option <?= $event['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">USSD Code</label><input name="ussd_code" class="form-control" value="<?= e($event['ussd_code']) ?>"></div>

<div class="col-12 mt-3"><div class="border rounded-3 p-4 bg-light-subtle">
<div class="d-flex justify-content-between align-items-start mb-3"><div><h5 class="mb-1">Revenue Distribution</h5><p class="text-secondary small mb-0">This percentage applies to the event's <strong>entire successful gross revenue</strong>.</p></div><span class="badge text-bg-primary">Two-party split</span></div>
<div class="row g-3 align-items-end">
<div class="col-md-5"><label class="form-label">Admin / VoteHub Share (%)</label><div class="input-group"><input id="admin_revenue_percentage" type="number" step="0.01" min="0" max="100" name="admin_revenue_percentage" value="<?= number_format($split['admin_percentage'],2,'.','') ?>" class="form-control" required><span class="input-group-text">%</span></div></div>
<div class="col-md-2 text-center fw-semibold">+</div>
<div class="col-md-5"><label class="form-label">Client Share (%)</label><div class="input-group"><input id="client_revenue_percentage" type="text" class="form-control" value="<?= number_format($split['client_percentage'],2,'.','') ?>" readonly><span class="input-group-text">%</span></div></div>
</div>
<div class="small text-secondary mt-3">Changing the Admin/VoteHub percentage automatically changes the Client percentage so the total always remains 100%.</div>
</div></div>
</div><div class="mt-4"><button class="btn btn-primary">Save Changes</button> <a href="view.php?id=<?= $id ?>" class="btn btn-light border">Cancel</a></div></form></div></div>
</section></main>
<script>document.addEventListener('DOMContentLoaded',()=>{const a=document.getElementById('admin_revenue_percentage'),c=document.getElementById('client_revenue_percentage');const sync=()=>{let v=parseFloat(a.value)||0;c.value=(100-Math.max(0,Math.min(100,v))).toFixed(2);};a.addEventListener('input',sync);sync();});</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
