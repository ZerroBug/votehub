<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireSuperAdmin();
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

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
$pendingTransactions=(int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status='Pending'")->fetchColumn();
$failedTransactions=(int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status='Failed'")->fetchColumn();
$pendingCashouts=(int)$pdo->query("SELECT COUNT(*) FROM client_cashouts WHERE status IN ('Requested','Approved')")->fetchColumn();

$events=$pdo->query("SELECT e.*,
 (SELECT COUNT(*) FROM categories c WHERE c.event_id=e.id) categories_count,
 (SELECT COUNT(*) FROM contestants x WHERE x.event_id=e.id) contestants_count,
 (SELECT COALESCE(SUM(t.amount),0) FROM transactions t WHERE t.event_id=e.id AND t.status='Successful') gross_revenue,
 (SELECT COALESCE(SUM(v.vote_count),0) FROM votes v WHERE v.event_id=e.id) event_votes
 FROM events e ORDER BY CASE WHEN e.status='Active' THEN 0 WHEN e.status='Scheduled' THEN 1 ELSE 2 END,e.created_at DESC LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);

$recentTransactions=$pdo->query("SELECT t.transaction_reference,t.amount,t.status,t.created_at,e.name event_name,c.full_name contestant_name FROM transactions t LEFT JOIN events e ON e.id=t.event_id LEFT JOIN contestants c ON c.id=t.contestant_id ORDER BY t.created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

$firstName=e(explode(' ',trim($_SESSION['full_name']??'Super Admin'))[0]);
?>

<main class="main">
<?php require_once __DIR__ . '/../includes/topbar.php'; ?>
<section class="content vh-dashboard">
<?php showFlash(); ?>

<!-- HERO -->
<section class="vh-hero">
  <div class="vh-hero-glow"></div>
  <div class="vh-hero-content">
    <div class="vh-eyebrow"><span class="vh-live-pulse"></span> VOTEHUB CONTROL CENTER</div>
    <h1>Welcome back, <?= $firstName ?>.</h1>
    <p>Monitor elections, payments, revenue and client balances from one command center.</p>
  </div>
  <div class="vh-hero-actions">
    <a href="<?= APP_URL ?>/admin/results/" class="vh-btn vh-btn-glass"><i class="bi bi-broadcast"></i> Live Results</a>
    <a href="<?= APP_URL ?>/admin/events/create.php" class="vh-btn vh-btn-gold"><i class="bi bi-plus-lg"></i> Create Event</a>
  </div>
</section>

<!-- KPI GRID -->
<section class="vh-kpi-grid">
  <article class="vh-kpi vh-kpi-revenue">
    <div class="vh-kpi-icon"><i class="bi bi-currency-exchange"></i></div>
    <div class="vh-kpi-label">Gross Revenue</div>
    <div class="vh-kpi-value">GHS <?= number_format($totalRevenue,2) ?></div>
    <div class="vh-kpi-meta"><span class="vh-positive"><i class="bi bi-check-circle-fill"></i> Successful payments</span></div>
  </article>
  <article class="vh-kpi">
    <div class="vh-kpi-icon purple"><i class="bi bi-wallet2"></i></div>
    <div class="vh-kpi-label">Client Available</div>
    <div class="vh-kpi-value">GHS <?= number_format($totalClientAvailable,2) ?></div>
    <div class="vh-kpi-meta">Earned GHS <?= number_format($totalClientEarned,2) ?></div>
  </article>
  <article class="vh-kpi">
    <div class="vh-kpi-icon blue"><i class="bi bi-bar-chart-fill"></i></div>
    <div class="vh-kpi-label">Total Votes</div>
    <div class="vh-kpi-value"><?= number_format($totalVotes) ?></div>
    <div class="vh-kpi-meta"><?= number_format($totalContestants) ?> contestants · <?= number_format($totalCategories) ?> categories</div>
  </article>
  <article class="vh-kpi">
    <div class="vh-kpi-icon green"><i class="bi bi-broadcast-pin"></i></div>
    <div class="vh-kpi-label">Active Events</div>
    <div class="vh-kpi-value"><?= number_format($activeEvents) ?></div>
    <div class="vh-kpi-meta"><span class="vh-live-label"><span></span> LIVE NOW</span> · <?= number_format($totalEvents) ?> total</div>
  </article>
</section>

<!-- MAIN GRID -->
<div class="row g-4">
  <div class="col-xl-8">
    <section class="vh-card h-100">
      <div class="vh-card-head">
        <div>
          <span class="vh-card-kicker">EVENT MANAGEMENT</span>
          <h2>Live event portfolio</h2>
          <p>Track the performance of your latest voting events.</p>
        </div>
        <a href="events/" class="vh-link-btn">View all <i class="bi bi-arrow-up-right"></i></a>
      </div>
      <div class="table-responsive">
        <table class="table vh-table mb-0">
          <thead><tr><th>Event</th><th>Status</th><th>Votes</th><th>Revenue</th><th>Split</th><th></th></tr></thead>
          <tbody>
          <?php foreach($events as $event):
            $adminPct=(float)($event['admin_revenue_percentage']??30); $clientPct=max(0,100-$adminPct);
          ?>
            <tr>
              <td><div class="vh-event"><div class="vh-event-icon"><i class="bi bi-calendar2-event"></i></div><div><strong><?= e($event['name']) ?></strong><small><?= e($event['event_code']) ?></small></div></div></td>
              <td><span class="vh-status vh-status-<?= strtolower(e($event['status'])) ?>"><span></span><?= e($event['status']) ?></span></td>
              <td><strong><?= number_format((int)$event['event_votes']) ?></strong></td>
              <td><strong>GHS <?= number_format((float)$event['gross_revenue'],2) ?></strong></td>
              <td><span class="vh-split"><b><?= number_format($adminPct,0) ?>%</b> / <?= number_format($clientPct,0) ?>%</span></td>
              <td><a href="events/view.php?id=<?= (int)$event['id'] ?>" class="vh-row-arrow"><i class="bi bi-arrow-up-right"></i></a></td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$events): ?><tr><td colspan="6"><div class="vh-empty"><i class="bi bi-calendar-x"></i><strong>No events yet</strong><span>Create your first event to get started.</span></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <div class="col-xl-4">
    <section class="vh-card vh-finance-card h-100">
      <div class="vh-card-head compact">
        <div><span class="vh-card-kicker">FINANCIAL CONTROL</span><h2>Revenue distribution</h2></div>
        <span class="vh-round-icon"><i class="bi bi-pie-chart-fill"></i></span>
      </div>
      <div class="vh-finance-total"><span>Gross revenue</span><strong>GHS <?= number_format($totalRevenue,2) ?></strong></div>
      <div class="vh-progress"><span style="width:<?= $totalRevenue>0 ? min(100,($totalAdminRevenue/$totalRevenue)*100) : 0 ?>%"></span></div>
      <div class="vh-finance-row"><div><span class="vh-dot purple"></span><span>Admin / VoteHub</span></div><strong>GHS <?= number_format($totalAdminRevenue,2) ?></strong></div>
      <div class="vh-finance-row"><div><span class="vh-dot gold"></span><span>Client earned</span></div><strong>GHS <?= number_format($totalClientEarned,2) ?></strong></div>
      <div class="vh-balance">
        <div><span>CLIENT AVAILABLE</span><strong>GHS <?= number_format($totalClientAvailable,2) ?></strong></div>
        <a href="<?= APP_URL ?>/admin/cashouts/" class="vh-balance-btn"><i class="bi bi-wallet2"></i></a>
      </div>
      <div class="vh-cashout-line"><span><i class="bi bi-check-circle-fill"></i> Paid GHS <?= number_format($totalClientPaid,2) ?></span><span><i class="bi bi-clock-fill"></i> Pending GHS <?= number_format($totalClientPending,2) ?></span></div>
    </section>
  </div>
</div>

<!-- LOWER GRID -->
<div class="row g-4 mt-0">
  <div class="col-xl-4">
    <section class="vh-card h-100">
      <div class="vh-card-head compact"><div><span class="vh-card-kicker">ATTENTION CENTER</span><h2>Needs attention</h2></div></div>
      <div class="vh-attention-list">
        <a href="<?= APP_URL ?>/admin/cashouts/" class="vh-attention"><span class="vh-att-icon amber"><i class="bi bi-wallet2"></i></span><span><b><?= number_format($pendingCashouts) ?> cash-out request<?= $pendingCashouts===1?'':'s' ?></b><small>Client requests awaiting action</small></span><i class="bi bi-chevron-right"></i></a>
        <a href="<?= APP_URL ?>/admin/transactions/" class="vh-attention"><span class="vh-att-icon violet"><i class="bi bi-hourglass-split"></i></span><span><b><?= number_format($pendingTransactions) ?> pending transaction<?= $pendingTransactions===1?'':'s' ?></b><small>Payments awaiting confirmation</small></span><i class="bi bi-chevron-right"></i></a>
        <a href="<?= APP_URL ?>/admin/transactions/" class="vh-attention"><span class="vh-att-icon red"><i class="bi bi-exclamation-triangle"></i></span><span><b><?= number_format($failedTransactions) ?> failed transaction<?= $failedTransactions===1?'':'s' ?></b><small>Review payment failures</small></span><i class="bi bi-chevron-right"></i></a>
      </div>
    </section>
  </div>

  <div class="col-xl-8">
    <section class="vh-card h-100">
      <div class="vh-card-head compact"><div><span class="vh-card-kicker">PAYMENT ACTIVITY</span><h2>Recent transactions</h2></div><a href="transactions/" class="vh-link-btn">All transactions <i class="bi bi-arrow-up-right"></i></a></div>
      <div class="table-responsive">
        <table class="table vh-table mb-0">
          <thead><tr><th>Reference</th><th>Event / Contestant</th><th>Amount</th><th>Status</th><th>Time</th></tr></thead>
          <tbody>
          <?php foreach($recentTransactions as $tx): ?>
            <tr>
              <td><code class="vh-ref"><?= e($tx['transaction_reference']) ?></code></td>
              <td><div class="vh-tx"><strong><?= e($tx['event_name']??'—') ?></strong><small><?= e($tx['contestant_name']??'—') ?></small></div></td>
              <td><strong>GHS <?= number_format((float)$tx['amount'],2) ?></strong></td>
              <td><span class="vh-tx-status vh-tx-<?= strtolower(e($tx['status'])) ?>"><?= e($tx['status']) ?></span></td>
              <td class="vh-time"><?= e(date('d M, H:i',strtotime($tx['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$recentTransactions): ?><tr><td colspan="5" class="text-center text-secondary py-5">No transactions yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>

<!-- QUICK ACTIONS -->
<section class="vh-quick mt-4">
  <div><span class="vh-card-kicker">QUICK ACTIONS</span><h2>What would you like to do?</h2></div>
  <div class="vh-quick-grid">
    <a href="events/create.php"><i class="bi bi-plus-circle-fill"></i><span><b>Create Event</b><small>Launch a new voting event</small></span><i class="bi bi-arrow-up-right arrow"></i></a>
    <a href="<?= APP_URL ?>/admin/results/"><i class="bi bi-broadcast-pin"></i><span><b>Live Results</b><small>Watch voting in real time</small></span><i class="bi bi-arrow-up-right arrow"></i></a>
    <a href="<?= APP_URL ?>/admin/cashouts/"><i class="bi bi-wallet2"></i><span><b>Client Cash-outs</b><small>Review and process payouts</small></span><i class="bi bi-arrow-up-right arrow"></i></a>
    <a href="<?= APP_URL ?>/admin/reports/"><i class="bi bi-file-earmark-bar-graph-fill"></i><span><b>Reports</b><small>Analyze platform performance</small></span><i class="bi bi-arrow-up-right arrow"></i></a>
  </div>
</section>

</section>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
