<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireSuperAdmin();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$totalEvents = (int)$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$activeEvents = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status='Active'")->fetchColumn();
$totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalContestants = (int)$pdo->query("SELECT COUNT(*) FROM contestants")->fetchColumn();
$totalVotes = (int)$pdo->query("SELECT COALESCE(SUM(vote_count),0) FROM votes")->fetchColumn();
$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='Successful'")->fetchColumn();
$totalAdminRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount * admin_revenue_percentage / 100),0) FROM transactions WHERE status='Successful'")->fetchColumn();
$totalClientEarned = round($totalRevenue - $totalAdminRevenue, 2);
$totalClientPaid = (float)$pdo->query("SELECT COALESCE(SUM(requested_amount),0) FROM client_cashouts WHERE status='Paid'")->fetchColumn();
$totalClientPending = (float)$pdo->query("SELECT COALESCE(SUM(requested_amount),0) FROM client_cashouts WHERE status IN ('Requested','Approved')")->fetchColumn();
$totalClientAvailable = max(0, round($totalClientEarned - $totalClientPaid - $totalClientPending, 2));

$pendingTransactions = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status='Pending'")->fetchColumn();
$failedTransactions = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status='Failed'")->fetchColumn();
$pendingCashouts = (int)$pdo->query("SELECT COUNT(*) FROM client_cashouts WHERE status IN ('Requested','Approved')")->fetchColumn();

$events = $pdo->query("SELECT e.*, (SELECT COUNT(*) FROM categories c WHERE c.event_id=e.id) categories_count, (SELECT COUNT(*) FROM contestants x WHERE x.event_id=e.id) contestants_count, (SELECT COALESCE(SUM(t.amount),0) FROM transactions t WHERE t.event_id=e.id AND t.status='Successful') gross_revenue, (SELECT COALESCE(SUM(v.vote_count),0) FROM votes v WHERE v.event_id=e.id) event_votes FROM events e ORDER BY CASE WHEN e.status='Active' THEN 0 WHEN e.status='Scheduled' THEN 1 ELSE 2 END, e.created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

$recentTransactions = $pdo->query("SELECT t.transaction_reference,t.amount,t.status,t.created_at,e.name event_name,c.full_name contestant_name FROM transactions t LEFT JOIN events e ON e.id=t.event_id LEFT JOIN contestants c ON c.id=t.contestant_id ORDER BY t.created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="main">
<?php require_once __DIR__ . '/../includes/topbar.php'; ?>
<section class="content dashboard-content">

<div class="dashboard-hero mb-4">
    <div>
        <span class="section-kicker dashboard-kicker">CONTROL CENTER</span>
        <h1 class="dashboard-title">Good day, <?= e(explode(' ', trim($_SESSION['full_name'] ?? 'Super Admin'))[0]) ?>.</h1>
        <p class="dashboard-subtitle">A live overview of your voting platform, revenue and client balances.</p>
    </div>
    <div class="dashboard-actions">
        <a href="<?= APP_URL ?>/admin/results/" class="btn btn-light dashboard-btn"><i class="bi bi-bar-chart-line me-2"></i>Live Results</a>
        <a href="<?= APP_URL ?>/admin/events/create.php" class="btn btn-primary dashboard-btn"><i class="bi bi-plus-lg me-2"></i>Create Event</a>
    </div>
</div>

<?php showFlash(); ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="dashboard-stat primary-stat">
            <div class="stat-top"><span>Gross Revenue</span><div class="stat-symbol"><i class="bi bi-cash-stack"></i></div></div>
            <div class="stat-number">GHS <?= number_format($totalRevenue,2) ?></div>
            <div class="stat-foot"><span><i class="bi bi-check-circle-fill"></i> Successful payments</span></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="dashboard-stat success-stat">
            <div class="stat-top"><span>Client Available</span><div class="stat-symbol"><i class="bi bi-wallet2"></i></div></div>
            <div class="stat-number">GHS <?= number_format($totalClientAvailable,2) ?></div>
            <div class="stat-foot"><span>Earned GHS <?= number_format($totalClientEarned,2) ?></span><span class="text-warning">− <?= number_format($totalClientPaid+$totalClientPending,2) ?></span></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="dashboard-stat dark-stat">
            <div class="stat-top"><span>Total Votes</span><div class="stat-symbol"><i class="bi bi-check2-square"></i></div></div>
            <div class="stat-number"><?= number_format($totalVotes) ?></div>
            <div class="stat-foot"><span><?= number_format($totalContestants) ?> contestants</span><span><?= number_format($totalCategories) ?> categories</span></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="dashboard-stat orange-stat">
            <div class="stat-top"><span>Active Events</span><div class="stat-symbol"><i class="bi bi-broadcast-pin"></i></div></div>
            <div class="stat-number"><?= number_format($activeEvents) ?></div>
            <div class="stat-foot"><span><?= number_format($totalEvents) ?> total events</span><span class="live-dot">LIVE</span></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="panel dashboard-panel h-100">
            <div class="panel-head dashboard-panel-head">
                <div><span class="section-kicker">EVENT PORTFOLIO</span><h5>Events at a glance</h5></div>
                <a href="events/" class="btn btn-sm btn-light border">View all <i class="bi bi-arrow-up-right ms-1"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table dashboard-table mb-0">
                    <thead><tr><th>Event</th><th>Status</th><th>Votes</th><th>Revenue</th><th>Split</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($events as $event):
                        $adminPct = isset($event['admin_revenue_percentage']) ? (float)$event['admin_revenue_percentage'] : 30;
                        $clientPct = max(0, 100 - $adminPct);
                    ?>
                        <tr>
                            <td><div class="event-name-cell"><div class="event-mini-icon"><i class="bi bi-calendar2-event"></i></div><div><strong><?= e($event['name']) ?></strong><small><?= e($event['event_code']) ?></small></div></div></td>
                            <td><span class="badge-soft badge-<?= strtolower(e($event['status'])) ?>"><?= e($event['status']) ?></span></td>
                            <td><strong><?= number_format((int)$event['event_votes']) ?></strong></td>
                            <td><strong>GHS <?= number_format((float)$event['gross_revenue'],2) ?></strong></td>
                            <td><span class="split-pill"><b><?= number_format($adminPct,0) ?>%</b><span>/</span><?= number_format($clientPct,0) ?>%</span></td>
                            <td><a href="events/view.php?id=<?= (int)$event['id'] ?>" class="action-btn"><i class="bi bi-chevron-right"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$events): ?><tr><td colspan="6" class="text-center text-secondary py-5">No events yet. Create your first event.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="panel dashboard-panel h-100">
            <div class="panel-head dashboard-panel-head"><div><span class="section-kicker">FINANCIAL CONTROL</span><h5>Revenue distribution</h5></div></div>
            <div class="panel-body">
                <div class="revenue-total"><div><small>Gross revenue</small><strong>GHS <?= number_format($totalRevenue,2) ?></strong></div><div class="revenue-icon"><i class="bi bi-graph-up-arrow"></i></div></div>
                <div class="revenue-row"><div><span class="revenue-dot admin-dot"></span>Admin / VoteHub</div><strong>GHS <?= number_format($totalAdminRevenue,2) ?></strong></div>
                <div class="revenue-row"><div><span class="revenue-dot client-dot"></span>Client earned</div><strong>GHS <?= number_format($totalClientEarned,2) ?></strong></div>
                <div class="balance-box"><div><small>Client available balance</small><strong>GHS <?= number_format($totalClientAvailable,2) ?></strong></div><a href="<?= APP_URL ?>/admin/cashouts/" class="btn btn-sm btn-primary">Cash-outs</a></div>
                <div class="cashout-summary"><span><i class="bi bi-check-circle"></i> Paid GHS <?= number_format($totalClientPaid,2) ?></span><span><i class="bi bi-clock"></i> Pending GHS <?= number_format($totalClientPending,2) ?></span></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="panel dashboard-panel h-100">
            <div class="panel-head dashboard-panel-head"><div><span class="section-kicker">ATTENTION</span><h5>Needs attention</h5></div></div>
            <div class="panel-body p-0">
                <a class="attention-item" href="<?= APP_URL ?>/admin/cashouts/"><span class="attention-icon warning"><i class="bi bi-wallet2"></i></span><span><strong><?= number_format($pendingCashouts) ?> cash-out request<?= $pendingCashouts === 1 ? '' : 's' ?></strong><small>Requested or approved client payments</small></span><i class="bi bi-chevron-right"></i></a>
                <a class="attention-item" href="<?= APP_URL ?>/admin/transactions/"><span class="attention-icon danger"><i class="bi bi-hourglass-split"></i></span><span><strong><?= number_format($pendingTransactions) ?> pending transaction<?= $pendingTransactions === 1 ? '' : 's' ?></strong><small>Payments awaiting confirmation</small></span><i class="bi bi-chevron-right"></i></a>
                <a class="attention-item" href="<?= APP_URL ?>/admin/transactions/"><span class="attention-icon muted"><i class="bi bi-x-circle"></i></span><span><strong><?= number_format($failedTransactions) ?> failed transaction<?= $failedTransactions === 1 ? '' : 's' ?></strong><small>Review payment failures</small></span><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="panel dashboard-panel h-100">
            <div class="panel-head dashboard-panel-head"><div><span class="section-kicker">PAYMENT ACTIVITY</span><h5>Recent transactions</h5></div><a href="transactions/" class="btn btn-sm btn-light border">View transactions</a></div>
            <div class="table-responsive">
                <table class="table dashboard-table mb-0"><thead><tr><th>Reference</th><th>Event / Contestant</th><th>Amount</th><th>Status</th><th>Time</th></tr></thead><tbody>
                <?php foreach ($recentTransactions as $tx): ?>
                    <tr><td><span class="reference-code"><?= e($tx['transaction_reference']) ?></span></td><td><strong><?= e($tx['contestant_name'] ?: '—') ?></strong><small class="d-block text-secondary"><?= e($tx['event_name'] ?: '—') ?></small></td><td><strong>GHS <?= number_format((float)$tx['amount'],2) ?></strong></td><td><span class="tx-status tx-<?= strtolower(e($tx['status'])) ?>"><?= e($tx['status']) ?></span></td><td class="text-secondary"><?= date('d M, H:i', strtotime($tx['created_at'])) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$recentTransactions): ?><tr><td colspan="5" class="text-center text-secondary py-5">No transactions yet.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
        </div>
    </div>
</div>

</section>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
