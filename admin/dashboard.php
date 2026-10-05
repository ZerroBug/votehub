<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireSuperAdmin();

$pageTitle='Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main">
<?php require_once __DIR__ . '/../includes/topbar.php'; ?>
<section class="content">
<?php showFlash(); ?>
<div class="d-flex justify-content-between align-items-end mb-4">
<div><h1 class="page-title mb-1">Super Admin Dashboard</h1><p class="page-subtitle mb-0">Manage all voting events from one place.</p></div>
<a href="<?= APP_URL ?>/admin/events/create.php" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Create Event</a>
</div>

<?php
$totalEvents=(int)$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$activeEvents=(int)$pdo->query("SELECT COUNT(*) FROM events WHERE status='Active'")->fetchColumn();
$totalCategories=(int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalContestants=(int)$pdo->query("SELECT COUNT(*) FROM contestants")->fetchColumn();
$totalVotes=(int)$pdo->query("SELECT COALESCE(SUM(vote_count),0) FROM votes")->fetchColumn();
$totalRevenue=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='Successful'")->fetchColumn();
$totalAdminRevenue=(float)$pdo->query("SELECT COALESCE(SUM(amount * admin_revenue_percentage / 100),0) FROM transactions WHERE status='Successful'")->fetchColumn();
$totalClientEarned=round($totalRevenue-$totalAdminRevenue,2);
$totalClientPaid=(float)$pdo->query("SELECT COALESCE(SUM(requested_amount),0) FROM client_cashouts WHERE status='Paid'")->fetchColumn();
$totalClientPending=(float)$pdo->query("SELECT COALESCE(SUM(requested_amount),0) FROM client_cashouts WHERE status IN ('Requested','Approved')")->fetchColumn();
$totalClientAvailable=max(0,round($totalClientEarned-$totalClientPaid-$totalClientPending,2));
?>
<div class="row g-3 mb-4">
<div class="col-xl-3 col-md-6"><div class="card-box stat"><div class="stat-icon"><i class="bi bi-calendar-event"></i></div><div class="stat-label">Total Events</div><div class="stat-value"><?= number_format($totalEvents) ?></div></div></div>
<div class="col-xl-3 col-md-6"><div class="card-box stat"><div class="stat-icon"><i class="bi bi-broadcast"></i></div><div class="stat-label">Active Events</div><div class="stat-value"><?= number_format($activeEvents) ?></div></div></div>
<div class="col-xl-3 col-md-6"><div class="card-box stat"><div class="stat-icon"><i class="bi bi-people"></i></div><div class="stat-label">Contestants</div><div class="stat-value"><?= number_format($totalContestants) ?></div></div></div>
<div class="col-xl-3 col-md-6"><div class="card-box stat"><div class="stat-icon"><i class="bi bi-check2-square"></i></div><div class="stat-label">Total Votes</div><div class="stat-value"><?= number_format($totalVotes) ?></div></div></div>
</div>

<div class="row g-3">
<div class="col-xl-8"><div class="panel"><div class="panel-head"><h5>Events</h5><a href="events/" class="btn btn-sm btn-light border">Manage Events</a></div>
<div class="table-responsive"><table class="table mb-0"><thead><tr><th>Event</th><th>Dates</th><th>Status</th><th>Categories</th><th>Contestants</th><th>Revenue Split</th><th></th></tr></thead><tbody>
<?php
$q=$pdo->query("SELECT e.*, (SELECT COUNT(*) FROM categories c WHERE c.event_id=e.id) categories_count, (SELECT COUNT(*) FROM contestants x WHERE x.event_id=e.id) contestants_count FROM events e ORDER BY e.created_at DESC LIMIT 8");
foreach($q as $e):
?>
<tr><td><strong><?= e($e['name']) ?></strong><small class="d-block text-secondary"><?= e($e['event_code']) ?></small></td>
<td><?= date('d M Y',strtotime($e['start_date'])) ?><small class="d-block text-secondary">to <?= date('d M Y',strtotime($e['end_date'])) ?></small></td>
<td><span class="badge-soft badge-<?= strtolower($e['status']) ?>"><?= e($e['status']) ?></span></td>
<td><?= $e['categories_count'] ?></td><td><?= $e['contestants_count'] ?></td><td><?= number_format((float)$e['admin_revenue_percentage'],2) ?>% / <?= number_format(100-(float)$e['admin_revenue_percentage'],2) ?>%</td>
<td><a href="events/view.php?id=<?= $e['id'] ?>" class="action-btn d-inline-grid place-items-center"><i class="bi bi-arrow-right"></i></a></td></tr>
<?php endforeach; if(!$totalEvents): ?><tr><td colspan="7" class="text-center text-secondary py-5">No events yet. Create your first event.</td></tr><?php endif; ?>
</tbody></table></div></div></div>

<div class="col-xl-4"><div class="panel"><div class="panel-head"><h5>Platform Summary</h5></div><div class="panel-body">
<div class="d-flex justify-content-between border-bottom py-3"><span class="text-secondary">Categories</span><strong><?= $totalCategories ?></strong></div>
<div class="d-flex justify-content-between border-bottom py-3"><span class="text-secondary">Contestants</span><strong><?= $totalContestants ?></strong></div>
<div class="d-flex justify-content-between border-bottom py-3"><span class="text-secondary">Votes</span><strong><?= number_format($totalVotes) ?></strong></div>
<div class="d-flex justify-content-between border-bottom py-3"><span class="text-secondary">Gross Revenue</span><strong>GHS <?= number_format($totalRevenue,2) ?></strong></div><div class="d-flex justify-content-between border-bottom py-3"><span class="text-secondary">Admin / VoteHub</span><strong>GHS <?= number_format($totalAdminRevenue,2) ?></strong></div><div class="d-flex justify-content-between border-bottom py-3"><span class="text-secondary">Client Earned</span><strong>GHS <?= number_format($totalClientEarned,2) ?></strong></div><div class="d-flex justify-content-between border-bottom py-3"><span class="text-secondary">Client Cashed Out (Paid)</span><strong>GHS <?= number_format($totalClientPaid,2) ?></strong></div><div class="d-flex justify-content-between border-bottom py-3"><span class="text-secondary">Cash-out Pending/Approved</span><strong>GHS <?= number_format($totalClientPending,2) ?></strong></div><div class="d-flex justify-content-between py-3"><span class="text-secondary">Client Available Balance</span><strong>GHS <?= number_format($totalClientAvailable,2) ?></strong></div>
<a href="events/create.php" class="btn btn-primary w-100 mt-2">Create New Event</a>
</div></div></div>
</div>
</section></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
