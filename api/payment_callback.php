<?php
declare(strict_types=1);
$reference = trim((string)($_GET['reference'] ?? ''));
$pageTitle = 'Payment Status';
require_once __DIR__ . '/../includes/header.php';
?>
<main class="main">
<section class="content">
<div class="row justify-content-center">
<div class="col-xl-7">
<div class="panel">
<div class="panel-head"><div><h5 class="mb-1">Payment Status</h5><small class="text-muted">Confirming your VoteHub payment</small></div><i class="bi bi-credit-card text-primary fs-4"></i></div>
<div class="panel-body text-center" id="statusBox">
<div class="spinner-border text-primary mb-3"></div>
<h4>Checking payment...</h4>
<p class="text-muted">Please wait while we confirm the transaction.</p>
</div></div></div></div>
</section>
</main>
<script>
const reference = <?= json_encode($reference) ?>;
async function check(){
 const box=document.getElementById('statusBox');
 if(!reference){box.innerHTML='<div class="alert alert-danger">No transaction reference was supplied.</div>';return;}
 try{
   const body=new URLSearchParams({reference});
   const r=await fetch('verify_payment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
   const d=await r.json();
   if(d.payment_status==='success' && d.vote_recorded){
     box.innerHTML='<div class="text-success fs-1"><i class="bi bi-check-circle-fill"></i></div><h4>Vote Recorded</h4><p class="text-muted">Payment confirmed successfully. Your votes have been recorded.</p><div class="badge bg-light text-dark">'+escapeHtml(reference)+'</div>';
   } else if(['failed','abandoned','reversed'].includes(d.payment_status)){
     box.innerHTML='<div class="text-danger fs-1"><i class="bi bi-x-circle-fill"></i></div><h4>Payment Not Successful</h4><p class="text-muted">'+escapeHtml(d.message||'The payment was not successful.')+'</p>';
   } else {
     box.innerHTML='<div class="spinner-border text-primary mb-3"></div><h4>Payment still pending</h4><p class="text-muted">Please complete authorization on the phone. This page will check again.</p>';
     setTimeout(check,10000);
   }
 }catch(e){box.innerHTML='<div class="alert alert-danger">Unable to verify the payment right now.</div>';}
}
function escapeHtml(v){const d=document.createElement('div');d.textContent=v??'';return d.innerHTML;}
check();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
