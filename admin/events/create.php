<?php
require_once __DIR__ . '/../../includes/auth.php'; requireSuperAdmin();
require_once __DIR__ . '/../../includes/functions.php';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=trim($_POST['name']??''); $code=strtoupper(trim($_POST['event_code']??''));
    $description=trim($_POST['description']??''); $start=$_POST['start_date']??''; $end=$_POST['end_date']??'';
    $price=(float)($_POST['default_vote_price']??1); $status=$_POST['status']??'Draft'; $ussd=trim($_POST['ussd_code']??'');
    $adminPct=(float)($_POST['admin_revenue_percentage']??30);
    if(!$name||!$code||!$start||!$end){ flash('danger','Please complete all required fields.'); }
    elseif($adminPct < 0 || $adminPct > 100){ flash('danger','Admin/VoteHub revenue percentage must be between 0% and 100%.'); }
    else {
        try{
            $stmt=$pdo->prepare("INSERT INTO events(name,event_code,description,start_date,end_date,status,default_vote_price,admin_revenue_percentage,ussd_code,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$name,$code,$description,$start,$end,$status,$price,$adminPct,$ussd,$_SESSION['user_id']]);
            flash('success','Event created successfully.');
            redirect('view.php?id='.$pdo->lastInsertId());
        }catch(PDOException $ex){ flash('danger','Could not create event. Event code may already exist.'); }
    }
}
$pageTitle='Create Event'; require_once __DIR__ . '/../../includes/header.php'; require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="main"><?php require_once __DIR__ . '/../../includes/topbar.php'; ?><section class="content">
<?php showFlash(); ?><div class="mb-4"><h1 class="page-title mb-1">Create Event</h1><p class="page-subtitle">Set up a new voting event and its two-party revenue split.</p></div>
<div class="panel"><div class="panel-body"><form method="post"><div class="row g-3">
<div class="col-md-8"><label class="form-label">Event Name *</label><input name="name" class="form-control" required value="<?= old('name') ?>" placeholder="Carnival Chelsea Week 2026"></div>
<div class="col-md-4"><label class="form-label">Event Code *</label><input name="event_code" class="form-control" required value="<?= old('event_code') ?>" placeholder="CCW2026"></div>
<div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= old('description') ?></textarea></div>
<div class="col-md-6"><label class="form-label">Start Date *</label><input type="datetime-local" name="start_date" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">End Date *</label><input type="datetime-local" name="end_date" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">Default Vote Price (GHS)</label><input type="number" step=".01" min="0" name="default_vote_price" value="1.00" class="form-control"></div>
<div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option>Draft</option><option>Scheduled</option><option>Active</option><option>Paused</option></select></div>
<div class="col-md-4"><label class="form-label">USSD Event Code</label><input name="ussd_code" class="form-control" placeholder="Optional"></div>

<div class="col-12 mt-3"><div class="border rounded-3 p-4 bg-light-subtle">
<div class="d-flex justify-content-between align-items-start mb-3"><div><h5 class="mb-1">Revenue Distribution</h5><p class="text-secondary small mb-0">The percentage entered here is the Admin/VoteHub share of the <strong>entire successful gross revenue</strong>. The Client receives the balance automatically.</p></div><span class="badge text-bg-primary">Default: 30% / 70%</span></div>
<div class="row g-3 align-items-end">
<div class="col-md-5"><label class="form-label">Admin / VoteHub Share (%)</label><div class="input-group"><input id="admin_revenue_percentage" type="number" step="0.01" min="0" max="100" name="admin_revenue_percentage" value="<?= old('admin_revenue_percentage','30.00') ?>" class="form-control" required><span class="input-group-text">%</span></div></div>
<div class="col-md-2 text-center fw-semibold">+</div>
<div class="col-md-5"><label class="form-label">Client Share (%)</label><div class="input-group"><input id="client_revenue_percentage" type="text" class="form-control" value="70.00" readonly><span class="input-group-text">%</span></div></div>
</div>
<div class="small text-secondary mt-3">Example: if the event generates GHS 100,000 and the Admin share is 30%, Admin/VoteHub receives GHS 30,000 and the Client receives GHS 70,000.</div>
</div></div>
</div><div class="mt-4 d-flex gap-2"><a href="index.php" class="btn btn-light border">Cancel</a><button class="btn btn-primary">Create Event</button></div></form></div></div>
</section></main>
<script>document.addEventListener('DOMContentLoaded',()=>{const a=document.getElementById('admin_revenue_percentage'),c=document.getElementById('client_revenue_percentage');const sync=()=>{let v=parseFloat(a.value)||0;c.value=(100-Math.max(0,Math.min(100,v))).toFixed(2);};a.addEventListener('input',sync);sync();});</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
