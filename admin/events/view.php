<?php
require_once __DIR__ . '/../../includes/auth.php'; requireSuperAdmin();
require_once __DIR__ . '/../../includes/functions.php';
$id=(int)($_GET['id']??0); $stmt=$pdo->prepare("SELECT * FROM events WHERE id=?"); $stmt->execute([$id]); $event=$stmt->fetch();
if(!$event) die('Event not found.');
$pageTitle=$event['name']; require_once __DIR__ . '/../../includes/header.php'; require_once __DIR__ . '/../../includes/sidebar.php';
$catCount=(int)$pdo->prepare("SELECT COUNT(*) FROM categories WHERE event_id=?")->execute([$id]) ?: 0;
$stmt=$pdo->prepare("SELECT COUNT(*) FROM categories WHERE event_id=?");$stmt->execute([$id]);$catCount=(int)$stmt->fetchColumn();
$stmt=$pdo->prepare("SELECT COUNT(*) FROM contestants WHERE event_id=?");$stmt->execute([$id]);$contestantCount=(int)$stmt->fetchColumn();
$stmt=$pdo->prepare("SELECT COALESCE(SUM(vote_count),0) FROM votes WHERE event_id=?");$stmt->execute([$id]);$votes=(int)$stmt->fetchColumn();
?>
<main class="main"><?php require_once __DIR__ . '/../../includes/topbar.php'; ?><section class="content">
<?php showFlash(); ?>
<div class="event-banner mb-3"><div class="position-relative z-2"><span class="badge bg-light text-dark mb-3"><?= e($event['status']) ?></span><div class="event-title"><?= e($event['name']) ?></div><div class="opacity-75"><?= e($event['event_code']) ?></div></div></div>
<div class="panel mb-3"><div class="panel-body"><div class="row text-center">
<div class="col-3 metric"><strong><?= $catCount ?></strong><small>Categories</small></div>
<div class="col-3 metric"><strong><?= $contestantCount ?></strong><small>Contestants</small></div>
<div class="col-3 metric"><strong><?= number_format($votes) ?></strong><small>Votes</small></div>
<div class="col-3 metric"><strong>GHS <?= number_format((float)$event['default_vote_price'],2) ?></strong><small>Default Vote Price</small></div>
</div></div></div>
<div class="d-flex gap-2 mb-3 flex-wrap">
<a href="../categories/?event_id=<?= $id ?>" class="btn btn-primary"><i class="bi bi-diagram-3 me-1"></i> Manage Categories</a>
<a href="../contestants/?event_id=<?= $id ?>" class="btn btn-light border"><i class="bi bi-people me-1"></i> Manage Contestants</a>
<a href="edit.php?id=<?= $id ?>" class="btn btn-light border"><i class="bi bi-pencil me-1"></i> Edit Event</a>
</div>
<div class="panel"><div class="panel-head"><h5>Event Information</h5></div><div class="panel-body">
<p class="text-secondary mb-2"><?= nl2br(e($event['description'])) ?></p>
<div class="row mt-4"><div class="col-md-6"><strong>Start:</strong> <?= date('d M Y, h:i A',strtotime($event['start_date'])) ?></div><div class="col-md-6"><strong>End:</strong> <?= date('d M Y, h:i A',strtotime($event['end_date'])) ?></div></div>
</div></div>
</section></main><?php require_once __DIR__ . '/../../includes/footer.php'; ?>
