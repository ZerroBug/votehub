<?php
require_once __DIR__ . '/../includes/auth.php'; requireSuperAdmin();
require_once __DIR__ . '/../includes/functions.php';
$events=$pdo->query("SELECT id,name,event_code FROM events WHERE status='Active' ORDER BY name")->fetchAll();
$pageTitle='USSD Contestant Lookup';require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<main class="main"><?php require_once __DIR__.'/../includes/topbar.php';?><section class="content">
<div class="mb-4"><span class="section-kicker">USSD TEST CONSOLE</span><h1 class="page-title mb-1">Contestant Code Lookup</h1><p class="page-subtitle">Test the lookup step voters complete before selecting vote quantity.</p></div>
<div class="row justify-content-center"><div class="col-xl-7"><div class="panel"><div class="panel-head"><h5>Find Contestant</h5><i class="bi bi-phone text-primary"></i></div><div class="panel-body">
<div class="alert alert-light border"><strong>Flow:</strong> Event → 4-digit code → Confirm contestant → Vote quantity → Payment.</div>
<label class="form-label">Active Event</label><select id="eventId" class="form-select mb-3"><option value="">Select event</option><?php foreach($events as $e):?><option value="<?=$e['id']?>"><?=e($e['name'])?> — <?=e($e['event_code'])?></option><?php endforeach;?></select>
<label class="form-label">Contestant 4-Digit Code</label><div class="input-group mb-3"><input id="contestantCode" class="form-control form-control-lg text-center letter-spacing" maxlength="4" inputmode="numeric" placeholder="1001"><button id="lookupBtn" class="btn btn-primary px-4">Lookup</button></div>
<div id="result" class="d-none"></div>
</div></div></div></div>
</section></main>
<script>
document.getElementById('lookupBtn').addEventListener('click',async()=>{
 const eventId=document.getElementById('eventId').value,code=document.getElementById('contestantCode').value.replace(/\D/g,'');
 const r=document.getElementById('result');r.classList.remove('d-none');r.innerHTML='<div class="alert alert-info">Looking up contestant...</div>';
 if(!eventId||code.length!==4){r.innerHTML='<div class="alert alert-warning">Select an event and enter exactly 4 digits.</div>';return;}
 try{
  const q=new URLSearchParams({event_id:eventId,contestant_code:code});
  const res=await fetch('ussd_contestant_lookup.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:q});
  const d=await res.json();
  if(!d.success){r.innerHTML='<div class="alert alert-danger">'+d.message+'</div>';return;}
  const c=d.contestant;
  r.innerHTML='<div class="lookup-result"><div class="lookup-code">'+c.code+'</div><h4>'+c.name+'</h4><p class="mb-1"><strong>Category:</strong> '+c.category+' ('+c.category_code+')</p><p class="mb-1"><strong>Event:</strong> '+c.event+'</p><p class="mb-3"><strong>Vote price:</strong> GHS '+Number(c.vote_price).toFixed(2)+' per vote</p><div class="alert alert-success mb-0">Contestant verified. The USSD flow can now request the number of votes.</div></div>';
 }catch(e){r.innerHTML='<div class="alert alert-danger">Unable to contact lookup service.</div>';}
});
document.getElementById('contestantCode').addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,4);});
</script>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
