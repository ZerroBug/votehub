<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/functions.php';

$events = $pdo->query("SELECT id,name,event_code,status FROM events ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle='Vote & Payment Test';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main">
<?php require_once __DIR__ . '/../includes/topbar.php'; ?>
<section class="content">
<div class="mb-4"><span class="section-kicker">PAYMENT TEST CONSOLE</span><h1 class="page-title mb-1">Vote & Payment Test</h1><p class="page-subtitle">Test the complete contestant, quantity and Paystack Mobile Money flow using test credentials.</p></div>
<div class="row justify-content-center"><div class="col-xl-8"><div class="panel"><div class="panel-head"><div><h5 class="mb-1">Create Test Vote</h5><small class="text-muted">A transaction remains Pending until Paystack confirms payment.</small></div><i class="bi bi-wallet2 text-primary fs-4"></i></div><div class="panel-body">
<div class="alert alert-warning small"><strong>Test mode:</strong> Configure a Paystack TEST secret key before pressing Pay & Vote. Never expose the secret key in JavaScript.</div>
<form id="voteForm">
<div class="row g-3">
<div class="col-md-7"><label class="form-label">Event</label><select id="eventId" name="event_id" class="form-select" required><option value="">Select event</option><?php foreach($events as $e): ?><option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?> — <?= e($e['event_code']) ?> (<?= e($e['status']) ?>)</option><?php endforeach; ?></select></div>
<div class="col-md-5"><label class="form-label">Contestant Code</label><input id="contestantCode" name="contestant_code" class="form-control" maxlength="4" inputmode="numeric" placeholder="1001" required></div>
<div class="col-md-12" id="contestantBox"></div>
<div class="col-md-4"><label class="form-label">Number of Votes</label><input id="voteCount" name="vote_count" type="number" min="1" max="20" class="form-control" value="1" required></div>
<div class="col-md-4"><label class="form-label">Mobile Money Provider</label><select id="provider" name="provider" class="form-select" required><option value="mtn">MTN</option><option value="vod">Telecel</option><option value="atl">ATMoney / Airtel Money</option></select></div>
<div class="col-md-4"><label class="form-label">Phone Number</label><input id="phone" name="phone_number" class="form-control" placeholder="0551234567" required></div>
<div class="col-md-8"><label class="form-label">Customer Email</label><input id="email" name="email" type="email" class="form-control" placeholder="voter@example.com" required></div>
<div class="col-md-4"><label class="form-label">Total Amount</label><input id="amount" class="form-control fw-bold" value="GHS 0.00" readonly></div>
</div>
<div class="mt-4"><button class="btn btn-primary px-4" id="payBtn" type="submit"><i class="bi bi-phone me-1"></i> Pay & Vote</button></div>
</form>
<div id="result" class="mt-4"></div>
</div></div></div></div>
</section></main>
<script>
const form=document.getElementById('voteForm'), eventId=document.getElementById('eventId'), code=document.getElementById('contestantCode'), count=document.getElementById('voteCount'), amount=document.getElementById('amount'), result=document.getElementById('result'), box=document.getElementById('contestantBox');
let price=0;
async function lookup(){
 const eid=eventId.value, c=code.value.replace(/\D/g,'').slice(0,4); code.value=c;
 if(!eid||c.length!==4){price=0;box.innerHTML='';calc();return;}
 try{const p=new URLSearchParams({event_id:eid,contestant_code:c});const r=await fetch('ussd_contestant_lookup.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p});const d=await r.json();if(!d.success){price=0;box.innerHTML='<div class="alert alert-danger">'+escapeHtml(d.message)+'</div>';calc();return;}price=Number(d.contestant.vote_price);box.innerHTML='<div class="alert alert-success"><strong>'+escapeHtml(d.contestant.name)+'</strong> — '+escapeHtml(d.contestant.category)+' — GHS '+price.toFixed(2)+' per vote.</div>';calc();}catch(e){price=0;box.innerHTML='<div class="alert alert-danger">Lookup service unavailable.</div>';calc();}}
function calc(){amount.value='GHS '+(price*Math.max(0,Number(count.value)||0)).toFixed(2);}
code.addEventListener('input',lookup);eventId.addEventListener('change',lookup);count.addEventListener('input',calc);
form.addEventListener('submit',async e=>{e.preventDefault();if(!price){await lookup();if(!price)return;}const btn=document.getElementById('payBtn');btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Starting payment...';result.innerHTML='';try{const r=await fetch('initiate_vote.php',{method:'POST',body:new FormData(form)});const d=await r.json();if(!d.success)throw new Error(d.message||'Payment could not be started.');result.innerHTML='<div class="alert alert-info"><strong>Payment initiated.</strong><div class="mt-1">'+escapeHtml(d.display_text||d.message)+'</div><div class="small mt-2">Reference: '+escapeHtml(d.transaction_reference)+'</div></div><div class="alert alert-warning small">Complete the authorization on the phone. Then use Verify Payment or the webhook will finalize the vote.</div>';}catch(err){result.innerHTML='<div class="alert alert-danger">'+escapeHtml(err.message)+'</div>';}finally{btn.disabled=false;btn.innerHTML='<i class="bi bi-phone me-1"></i> Pay & Vote';}});
function escapeHtml(v){const d=document.createElement('div');d.textContent=v??'';return d.innerHTML;}calc();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
