<?php
require_once __DIR__ . '/../../includes/auth.php'; requireSuperAdmin();
require_once __DIR__ . '/../../includes/functions.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=trim($_POST['name']??''); $code=strtoupper(trim($_POST['event_code']??''));
    $description=trim($_POST['description']??''); $start=$_POST['start_date']??''; $end=$_POST['end_date']??'';
    $price=(float)($_POST['default_vote_price']??1); $status=$_POST['status']??'Draft'; $ussd=trim($_POST['ussd_code']??'');
    if(!$name||!$code||!$start||!$end){ flash('danger','Please complete all required fields.'); }
    else {
        try{
            $stmt=$pdo->prepare("INSERT INTO events(name,event_code,description,start_date,end_date,status,default_vote_price,ussd_code,created_by) VALUES(?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$name,$code,$description,$start,$end,$status,$price,$ussd,$_SESSION['user_id']]);
            flash('success','Event created successfully.');
            redirect('view.php?id='.$pdo->lastInsertId());
        }catch(PDOException $ex){ flash('danger','Could not create event. Event code may already exist.'); }
    }
}
$pageTitle='Create Event'; require_once __DIR__ . '/../../includes/header.php'; require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="main"><?php require_once __DIR__ . '/../../includes/topbar.php'; ?><section class="content">
<?php showFlash(); ?><div class="mb-4"><h1 class="page-title mb-1">Create Event</h1><p class="page-subtitle">Set up a new voting event.</p></div>
<div class="panel"><div class="panel-body"><form method="post"><div class="row g-3">
<div class="col-md-8"><label class="form-label">Event Name *</label><input name="name" class="form-control" required value="<?= old('name') ?>" placeholder="Carnival Chelsea Week 2026"></div>
<div class="col-md-4"><label class="form-label">Event Code *</label><input name="event_code" class="form-control" required value="<?= old('event_code') ?>" placeholder="CCW2026"></div>
<div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= old('description') ?></textarea></div>
<div class="col-md-6"><label class="form-label">Start Date *</label><input type="datetime-local" name="start_date" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">End Date *</label><input type="datetime-local" name="end_date" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">Default Vote Price (GHS)</label><input type="number" step=".01" min="0" name="default_vote_price" value="1.00" class="form-control"></div>
<div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option>Draft</option><option>Scheduled</option><option>Active</option><option>Paused</option></select></div>
<div class="col-md-4"><label class="form-label">USSD Event Code</label><input name="ussd_code" class="form-control" placeholder="Optional"></div>
</div><div class="mt-4 d-flex gap-2"><a href="index.php" class="btn btn-light border">Cancel</a><button class="btn btn-primary">Create Event</button></div></form></div></div>
</section></main><?php require_once __DIR__ . '/../../includes/footer.php'; ?>
