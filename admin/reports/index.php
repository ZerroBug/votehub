<?php
require_once __DIR__ . '/../../includes/auth.php'; requireSuperAdmin();
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle='Reports';require_once __DIR__.'/../../includes/header.php';require_once __DIR__.'/../../includes/sidebar.php';
$rows=[];
$q=$pdo->query("SELECT e.id,e.name,e.event_code,e.admin_revenue_percentage,COALESCE(SUM(CASE WHEN t.status='Successful' THEN t.amount ELSE 0 END),0) gross_revenue,COALESCE(SUM(CASE WHEN t.status='Successful' THEN t.vote_count ELSE 0 END),0) paid_votes FROM events e LEFT JOIN transactions t ON t.event_id=e.id GROUP BY e.id,e.name,e.event_code,e.admin_revenue_percentage ORDER BY e.id DESC");
foreach($q as $r){$split=getEventRevenueSplit($r);$gross=(float)$r['gross_revenue'];$admin=round($gross*$split['admin_percentage']/100,2);$rows[]=[$r,$gross,$admin,round($gross-$admin,2),$split];}
?>
<main class="main"><?php require_once __DIR__.'/../../includes/topbar.php';?><section class="content">
<div class="mb-4"><h1 class="page-title mb-1">Revenue Reports</h1><p class="page-subtitle">Gross successful revenue and the two-party event revenue distribution.</p></div>
<div class="panel"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Event</th><th>Successful Votes</th><th>Gross Revenue</th><th>Admin / VoteHub</th><th>Client</th><th>Split</th></tr></thead><tbody>
<?php foreach($rows as [$r,$gross,$admin,$client,$split]): ?><tr><td><strong><?= e($r['name']) ?></strong><small class="d-block text-secondary"><?= e($r['event_code']) ?></small></td><td><?= number_format((int)$r['paid_votes']) ?></td><td>GHS <?= number_format($gross,2) ?></td><td>GHS <?= number_format($admin,2) ?><small class="d-block text-secondary"><?= number_format($split['admin_percentage'],2) ?>%</small></td><td>GHS <?= number_format($client,2) ?><small class="d-block text-secondary"><?= number_format($split['client_percentage'],2) ?>%</small></td><td><?= number_format($split['admin_percentage'],2) ?>% / <?= number_format($split['client_percentage'],2) ?>%</td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="6" class="text-center py-5 text-secondary">No events found.</td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="alert alert-info mt-3">Revenue is calculated from successful transactions only. Pending, failed, cancelled and refunded transactions do not form part of the gross revenue split.</div>
</section></main><?php require_once __DIR__.'/../../includes/footer.php';?>
