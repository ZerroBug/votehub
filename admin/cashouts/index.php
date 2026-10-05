<?php
require_once __DIR__ . '/../../includes/auth.php'; requireSuperAdmin();
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'request') {
            $eventId = (int)($_POST['event_id'] ?? 0);
            $amount = round((float)($_POST['amount'] ?? 0), 2);
            $method = trim($_POST['payment_method'] ?? '');
            $accountName = trim($_POST['account_name'] ?? '');
            $accountNumber = trim($_POST['account_number'] ?? '');
            $mobile = trim($_POST['mobile_number'] ?? '');
            if ($eventId <= 0 || $amount <= 0) throw new RuntimeException('Select an event and enter a valid cash-out amount.');
            $stmt = $pdo->prepare("SELECT e.*, COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.event_id=e.id AND t.status='Successful'),0) gross FROM events e WHERE e.id=?");
            $stmt->execute([$eventId]); $event = $stmt->fetch();
            if (!$event) throw new RuntimeException('Event not found.');
            $split = getEventRevenueSplit($event);
            $clientEarned = round((float)$event['gross'] * $split['client_percentage'] / 100, 2);
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(requested_amount),0) FROM client_cashouts WHERE event_id=? AND status IN ('Requested','Approved','Paid')");
            $stmt->execute([$eventId]); $reserved = round((float)$stmt->fetchColumn(), 2);
            $available = round($clientEarned - $reserved, 2);
            if ($amount > $available + 0.001) throw new RuntimeException('Cash-out exceeds the client available balance of GHS ' . number_format($available,2) . '.');
            $stmt = $pdo->prepare("INSERT INTO client_cashouts(event_id,requested_amount,status,payment_method,account_name,account_number,mobile_number,notes) VALUES(?,?,?,?,?,?,?,?)");
            $stmt->execute([$eventId,$amount,'Requested',$method ?: null,$accountName ?: null,$accountNumber ?: null,$mobile ?: null,trim($_POST['notes'] ?? '') ?: null]);
            flash('success','Client cash-out request recorded. The amount is now reserved from the client available balance.');
        } elseif ($action === 'status') {
            $id=(int)($_POST['id']??0); $status=$_POST['status']??'';
            $allowed=['Approved','Paid','Rejected','Cancelled'];
            if(!in_array($status,$allowed,true)) throw new RuntimeException('Invalid cash-out status.');
            $stmt=$pdo->prepare("SELECT * FROM client_cashouts WHERE id=?"); $stmt->execute([$id]); $cash=$stmt->fetch();
            if(!$cash) throw new RuntimeException('Cash-out not found.');
            if($status==='Paid') {
                $ref=trim($_POST['reference']??'');
                $stmt=$pdo->prepare("UPDATE client_cashouts SET status='Paid', reference=?, paid_at=NOW(), processed_by=? WHERE id=?");
                $stmt->execute([$ref ?: null,$_SESSION['user_id']??null,$id]);
            } elseif($status==='Approved') {
                $stmt=$pdo->prepare("UPDATE client_cashouts SET status='Approved', approved_at=NOW(), processed_by=? WHERE id=? AND status='Requested'");
                $stmt->execute([$_SESSION['user_id']??null,$id]);
            } else {
                $stmt=$pdo->prepare("UPDATE client_cashouts SET status=?, processed_by=? WHERE id=? AND status IN ('Requested','Approved')");
                $stmt->execute([$status,$_SESSION['user_id']??null,$id]);
            }
            flash('success','Cash-out status updated.');
        }
    } catch (Throwable $e) { flash('danger',$e->getMessage()); }
    redirect('index.php');
}

$events=$pdo->query("SELECT e.*, COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.event_id=e.id AND t.status='Successful'),0) gross FROM events e ORDER BY e.name")->fetchAll();
$rows=$pdo->query("SELECT co.*,e.name event_name,e.admin_revenue_percentage FROM client_cashouts co JOIN events e ON e.id=co.event_id ORDER BY co.id DESC LIMIT 200")->fetchAll();
$pageTitle='Client Cash-outs'; require_once __DIR__.'/../../includes/header.php'; require_once __DIR__.'/../../includes/sidebar.php';
?>
<main class="main"><?php require_once __DIR__.'/../../includes/topbar.php'; ?><section class="content">
<?php showFlash(); ?>
<div class="d-flex justify-content-between align-items-end mb-4"><div><span class="section-kicker">FINANCE</span><h1 class="page-title mb-1">Client Cash-outs</h1><p class="page-subtitle mb-0">Record, approve and pay client cash-out requests. Approved and paid amounts reduce the client's available balance.</p></div></div>
<div class="panel mb-4"><div class="panel-head"><h5>Record Client Cash-out Request</h5><span class="badge text-bg-primary">Client share only</span></div><div class="panel-body"><form method="post" class="row g-3"><input type="hidden" name="action" value="request"><div class="col-md-4"><label class="form-label">Event</label><select name="event_id" id="event_id" class="form-select" required><option value="">Select event</option><?php foreach($events as $e):?><option value="<?=$e['id']?>"><?=e($e['name'])?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">Cash-out Amount (GHS)</label><input name="amount" type="number" step="0.01" min="0.01" class="form-control" required></div><div class="col-md-5"><label class="form-label">Payment Method</label><input name="payment_method" class="form-control" placeholder="Mobile Money / Bank Transfer"></div><div class="col-md-4"><label class="form-label">Account Name</label><input name="account_name" class="form-control"></div><div class="col-md-4"><label class="form-label">Account / MoMo Number</label><input name="account_number" class="form-control"></div><div class="col-md-4"><label class="form-label">Mobile Number</label><input name="mobile_number" class="form-control"></div><div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div><div class="col-12"><button class="btn btn-primary"><i class="bi bi-cash-coin me-1"></i> Record Cash-out Request</button></div></form></div></div>
<div class="panel"><div class="panel-head"><h5>Cash-out Ledger</h5><span class="text-secondary small">Requested → Approved → Paid</span></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Event</th><th>Amount</th><th>Method</th><th>Account</th><th>Status</th><th>Reference</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><strong><?=e($r['event_name'])?></strong></td><td>GHS <?=number_format((float)$r['requested_amount'],2)?></td><td><?=e($r['payment_method']??'—')?></td><td><?=e($r['account_number']??$r['mobile_number']??'—')?></td><td><span class="badge <?= $r['status']==='Paid'?'bg-success':($r['status']==='Rejected'?'bg-danger':'bg-warning text-dark') ?>"><?=e($r['status'])?></span></td><td class="font-monospace small"><?=e($r['reference']??'')?></td><td><?php if(in_array($r['status'],['Requested','Approved'],true)): ?><form method="post" class="d-flex gap-1"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=$r['id']?>"><?php if($r['status']==='Requested'): ?><button name="status" value="Approved" class="btn btn-sm btn-outline-primary">Approve</button><?php endif; ?><?php if($r['status']==='Approved'): ?><input name="reference" class="form-control form-control-sm" placeholder="Payment ref"><button name="status" value="Paid" class="btn btn-sm btn-success">Paid</button><?php endif; ?><button name="status" value="Rejected" class="btn btn-sm btn-outline-danger">Reject</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="7" class="text-center py-5 text-muted">No client cash-outs recorded.</td></tr><?php endif; ?></tbody></table></div></div>
</section></main><?php require_once __DIR__.'/../../includes/footer.php'; ?>
