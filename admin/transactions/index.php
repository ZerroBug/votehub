<?php
require_once __DIR__ . '/../../includes/auth.php'; requireSuperAdmin(); require_once __DIR__ . '/../../includes/functions.php';
$pageTitle='Transactions'; require_once __DIR__.'/../../includes/header.php'; require_once __DIR__.'/../../includes/sidebar.php';
$stmt=$pdo->query("SELECT t.*,e.name event_name,c.name category_name,x.full_name contestant_name,
 (SELECT COALESCE(SUM(v.vote_count),0) FROM votes v WHERE v.transaction_id=t.id) recorded_votes
 FROM transactions t JOIN events e ON e.id=t.event_id JOIN categories c ON c.id=t.category_id JOIN contestants x ON x.id=t.contestant_id
 ORDER BY t.id DESC LIMIT 100"); $rows=$stmt->fetchAll();
?>
<main class="main"><?php require_once __DIR__.'/../../includes/topbar.php';?><section class="content">
<div class="mb-4"><h1 class="page-title mb-1">Transactions</h1><p class="page-subtitle">Monitor payments and reconcile successful Paystack transactions with VoteHub votes.</p></div>
<div id="reconcileAlert" class="alert d-none"></div>
<div class="panel"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Reference</th><th>Event</th><th>Contestant</th><th>Votes</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr>
<td><code><?= e($r['transaction_reference']) ?></code></td><td><?= e($r['event_name']) ?></td><td><?= e($r['contestant_name']) ?></td>
<td><?= (int)$r['vote_count'] ?> <small class="text-secondary">/ recorded <?= (int)$r['recorded_votes'] ?></small></td>
<td>GHS <?= number_format((float)$r['amount'],2) ?></td>
<td><span class="badge <?= $r['status']==='Successful'?'bg-success':($r['status']==='Pending'?'bg-warning text-dark':'bg-secondary') ?>"><?= e($r['status']) ?></span></td>
<td><?php if($r['status']==='Pending'): ?><button class="btn btn-sm btn-primary verify-payment" data-reference="<?= e($r['transaction_reference']) ?>">Verify with Paystack</button><?php else: ?><span class="text-secondary">—</span><?php endif; ?></td>
</tr>
<?php endforeach; if(!$rows): ?><tr><td colspan="7" class="text-center py-5 text-secondary">No transactions yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
</section></main>
<script>
document.querySelectorAll('.verify-payment').forEach(btn=>btn.addEventListener('click',async()=>{
 const ref=btn.dataset.reference; btn.disabled=true; const original=btn.textContent; btn.textContent='Verifying…';
 const alertBox=document.getElementById('reconcileAlert'); alertBox.className='alert alert-info'; alertBox.textContent='Checking Paystack for '+ref+'…';
 try { const body=new URLSearchParams({reference:ref}); const res=await fetch('<?= e(APP_URL) ?>/api/reconcile_payment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body}); const data=await res.json(); alertBox.className='alert '+(data.success?'alert-success':'alert-danger'); alertBox.textContent=data.message+(data.votes_recorded!==undefined?' Votes recorded: '+data.votes_recorded+'.':''); if(data.success && data.payment_status==='success') setTimeout(()=>location.reload(),700); } catch(e){ alertBox.className='alert alert-danger'; alertBox.textContent='Verification request failed. Please try again.'; btn.disabled=false; btn.textContent=original; }
}));
</script>
<?php require_once __DIR__.'/../../includes/footer.php';?>
