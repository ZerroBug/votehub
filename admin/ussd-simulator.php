<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Live USSD Bot Simulator';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main">
<?php require_once __DIR__ . '/../includes/topbar.php'; ?>
<section class="content">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <span class="section-kicker">LIVE USSD TESTING</span>
            <h1 class="page-title mb-1">USSD Bot Simulator</h1>
            <p class="page-subtitle mb-0">Run the same USSD callback used by your real gateway, directly from this browser.</p>
        </div>
        <span class="badge bg-success-subtle text-success border px-3 py-2"><i class="bi bi-broadcast-pin me-1"></i> Backend Connected</span>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="panel ussd-panel">
                <div class="panel-head">
                    <div>
                        <h5><i class="bi bi-phone me-2"></i>Live USSD Session</h5>
                        <small class="text-secondary">Simulates a real voter session against <code>/api/ussd.php</code></small>
                    </div>
                    <span id="sessionBadge" class="badge bg-secondary">NOT STARTED</span>
                </div>
                <div class="panel-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Voter Mobile Number</label>
                            <input id="phone" class="form-control" value="0240000000" inputmode="tel" maxlength="13" placeholder="0241234567">
                            <div class="form-help mt-1">Use a Ghana number. This is sent to the same USSD endpoint as the real gateway.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Service Code</label>
                            <input id="serviceCode" class="form-control" value="*711#" maxlength="20">
                        </div>
                    </div>

                    <div class="ussd-phone-wrap">
                        <div class="ussd-phone">
                            <div class="ussd-speaker"></div>
                            <div class="ussd-screen">
                                <div class="ussd-screen-top"><span>VoteHub</span><span id="screenClock">--:--</span></div>
                                <div id="botOutput" class="ussd-output">
                                    <div class="ussd-welcome">Press <strong>START</strong> to begin a live USSD session.</div>
                                </div>
                                <div id="typing" class="ussd-typing d-none">Connecting…</div>
                            </div>
                            <div class="ussd-keypad">
                                <?php foreach (['1','2','3','4','5','6','7','8','9','*','0','#'] as $key): ?>
                                    <button type="button" class="ussd-key" data-key="<?= $key ?>"><?= $key ?></button>
                                <?php endforeach; ?>
                            </div>
                            <div class="ussd-actions">
                                <button id="sendBtn" class="btn btn-success"><i class="bi bi-send me-1"></i> Send</button>
                                <button id="startBtn" class="btn btn-primary"><i class="bi bi-play-fill me-1"></i> Start</button>
                                <button id="restartBtn" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i> Restart</button>
                            </div>
                            <input id="ussdInput" class="form-control mt-3 text-center" inputmode="numeric" autocomplete="off" placeholder="Enter response, e.g. 1 or 1001">
                        </div>
                    </div>

                    <div id="paymentPanel" class="d-none mt-4"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="panel mb-4">
                <div class="panel-head"><h5>Session Details</h5><i class="bi bi-info-circle text-primary"></i></div>
                <div class="panel-body">
                    <div class="sim-detail"><span>Session ID</span><strong id="sessionIdText">—</strong></div>
                    <div class="sim-detail"><span>USSD text</span><strong id="textText">—</strong></div>
                    <div class="sim-detail"><span>Last response</span><strong id="statusText">Ready</strong></div>
                    <div class="sim-detail"><span>Transaction</span><strong id="transactionText">—</strong></div>
                </div>
            </div>

            <div class="panel mb-4">
                <div class="panel-head"><h5>What this tests</h5><i class="bi bi-shield-check text-success"></i></div>
                <div class="panel-body">
                    <ol class="sim-flow">
                        <li>Select an active event.</li>
                        <li>Enter a 4-digit contestant code.</li>
                        <li>Confirm the contestant.</li>
                        <li>Enter the number of votes.</li>
                        <li>Select MTN, Telecel or ATMoney.</li>
                        <li>Paystack receives the payment request.</li>
                        <li>Only successful payment confirmation records the vote.</li>
                    </ol>
                </div>
            </div>

            <div class="alert alert-warning border mb-0">
                <strong><i class="bi bi-exclamation-triangle me-1"></i> Test mode</strong><br>
                Keep Paystack in <strong>Test</strong> mode while testing. Do not switch to Live until the complete flow has been verified.
            </div>
        </div>
    </div>
</section>
</main>

<style>
.ussd-phone-wrap{display:flex;justify-content:center;padding:10px 0 2px}.ussd-phone{width:330px;background:#111827;border-radius:34px;padding:18px 15px 20px;box-shadow:0 20px 45px rgba(15,23,42,.18)}
.ussd-speaker{width:70px;height:5px;border-radius:20px;background:#374151;margin:0 auto 13px}.ussd-screen{height:330px;background:#f8fafc;border:6px solid #273244;border-radius:17px;padding:12px;display:flex;flex-direction:column}.ussd-screen-top{display:flex;justify-content:space-between;font-size:9px;font-weight:800;color:#667085;border-bottom:1px solid #e5e7eb;padding-bottom:8px}.ussd-output{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;line-height:1.55;white-space:pre-wrap;overflow:auto;flex:1;padding:12px 3px;color:#172033}.ussd-welcome{color:#667085;text-align:center;margin-top:75px;font-family:'Poppins',sans-serif}.ussd-typing{font-size:9px;color:#667085;padding:3px 0}.ussd-keypad{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:15px}.ussd-key{height:42px;border:0;border-radius:12px;background:#273244;color:#fff;font-weight:800;font-size:15px}.ussd-key:active{transform:scale(.97);background:#39475c}.ussd-actions{display:flex;gap:7px;margin-top:13px}.ussd-actions .btn{flex:1;font-size:10px}.sim-detail{display:flex;justify-content:space-between;gap:12px;padding:11px 0;border-bottom:1px solid #eef1f5}.sim-detail:last-child{border-bottom:0}.sim-detail span{color:#7a8495;font-size:10px}.sim-detail strong{font-size:10px;text-align:right;word-break:break-all}.sim-flow{margin:0;padding-left:20px}.sim-flow li{font-size:11px;color:#5d6879;padding:6px 0}.transaction-success{background:#ecfdf3;border:1px solid #b7ebcc;border-radius:12px;padding:14px}.transaction-pending{background:#eff6ff;border:1px solid #cfe1ff;border-radius:12px;padding:14px}.transaction-failed{background:#fff1f2;border:1px solid #fecdd3;border-radius:12px;padding:14px}
</style>
<script>
(() => {
    const $ = id => document.getElementById(id);
    let sessionId = '';
    let ussdText = '';
    let ended = false;
    let pollTimer = null;

    function newSessionId(){
        return 'WEB-' + Date.now() + '-' + Math.random().toString(36).slice(2,8).toUpperCase();
    }
    function setClock(){
        const d = new Date();
        $('screenClock').textContent = d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    }
    setClock(); setInterval(setClock, 30000);

    function appendOutput(text, cls=''){
        const div = document.createElement('div');
        div.className = cls;
        div.textContent = text;
        $('botOutput').appendChild(div);
        $('botOutput').scrollTop = $('botOutput').scrollHeight;
    }
    function resetDisplay(){
        $('botOutput').innerHTML = '<div class="ussd-welcome">Press <strong>START</strong> to begin a live USSD session.</div>';
        $('sessionBadge').className = 'badge bg-secondary';
        $('sessionBadge').textContent = 'NOT STARTED';
        $('sessionIdText').textContent = '—'; $('textText').textContent = '—';
        $('statusText').textContent = 'Ready'; $('transactionText').textContent = '—';
        $('paymentPanel').classList.add('d-none'); $('paymentPanel').innerHTML = '';
        $('ussdInput').value = ''; ended = false;
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    async function callUssd(text, isStart=false){
        const phone = $('phone').value.trim();
        if (!phone) { alert('Enter the voter mobile number first.'); $('phone').focus(); return; }
        if (!sessionId || isStart) sessionId = newSessionId();
        $('typing').classList.remove('d-none');
        $('sendBtn').disabled = true; $('startBtn').disabled = true;
        try {
            const body = new URLSearchParams({sessionId, serviceCode:$('serviceCode').value.trim() || '*711#', phoneNumber:phone, text});
            const res = await fetch('../api/ussd.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body});
            const raw = await res.text();
            if (!res.ok) throw new Error('USSD endpoint returned HTTP ' + res.status);
            const mode = raw.startsWith('CON ') ? 'CON' : raw.startsWith('END ') ? 'END' : '';
            const message = mode ? raw.slice(4).trim() : raw.trim();
            if (!mode) throw new Error(message || 'Unexpected response from USSD endpoint.');

            $('botOutput').innerHTML = '';
            appendOutput(message);
            $('sessionIdText').textContent = sessionId;
            $('textText').textContent = ussdText || '(start)';
            $('statusText').textContent = mode === 'CON' ? 'Waiting for voter input' : 'Session ended';
            $('sessionBadge').className = mode === 'CON' ? 'badge bg-primary' : 'badge bg-dark';
            $('sessionBadge').textContent = mode;
            ended = mode === 'END';

            const ref = message.match(/Ref:\s*([A-Z0-9-]+)/i);
            if (ref) {
                $('transactionText').textContent = ref[1];
                showPaymentStatus(ref[1]);
            }
            if (mode === 'END') $('ussdInput').focus();
        } catch (err) {
            $('statusText').textContent = 'Error';
            $('sessionBadge').className = 'badge bg-danger'; $('sessionBadge').textContent = 'ERROR';
            $('botOutput').innerHTML = '<div class="alert alert-danger py-2 mb-0">' + escapeHtml(err.message) + '</div>';
        } finally {
            $('typing').classList.add('d-none');
            $('sendBtn').disabled = false; $('startBtn').disabled = false;
        }
    }

    function escapeHtml(s){return String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}

    async function start(){
        sessionId = newSessionId(); ussdText = ''; ended = false;
        $('botOutput').innerHTML = '';
        $('paymentPanel').classList.add('d-none'); $('paymentPanel').innerHTML='';
        $('transactionText').textContent='—';
        await callUssd('', true);
    }

    async function send(){
        if (ended) return;
        const input = $('ussdInput').value.trim();
        if (!input) return;
        if (!sessionId) { await start(); return; }
        ussdText = ussdText ? ussdText + '*' + input : input;
        appendOutput('> ' + input, 'text-secondary');
        $('ussdInput').value='';
        await callUssd(ussdText);
    }

    function showPaymentStatus(reference){
        const panel = $('paymentPanel'); panel.classList.remove('d-none');
        panel.innerHTML = '<div class="transaction-pending"><strong><i class="bi bi-hourglass-split me-1"></i> Payment pending</strong><div class="small mt-1">Checking Paystack for <code>'+escapeHtml(reference)+'</code>…</div></div>';
        if (pollTimer) clearInterval(pollTimer);
        let attempts = 0;
        const poll = async () => {
            attempts++;
            try {
                const body = new URLSearchParams({reference});
                const res = await fetch('../api/verify_payment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
                const d = await res.json();
                if (d.payment_status === 'success' && d.vote_recorded) {
                    panel.innerHTML='<div class="transaction-success"><strong><i class="bi bi-check-circle-fill me-1"></i> Payment successful</strong><div class="small mt-1">The transaction has been fulfilled and the vote has been recorded.</div></div>';
                    $('statusText').textContent='Payment successful — vote recorded';
                    clearInterval(pollTimer); pollTimer=null; return;
                }
                if (['failed','abandoned','reversed'].includes(d.payment_status)) {
                    panel.innerHTML='<div class="transaction-failed"><strong><i class="bi bi-x-circle-fill me-1"></i> Payment '+escapeHtml(d.payment_status)+'</strong><div class="small mt-1">No vote was recorded.</div></div>';
                    $('statusText').textContent='Payment failed — no vote recorded';
                    clearInterval(pollTimer); pollTimer=null; return;
                }
                if (attempts >= 15) {
                    panel.innerHTML='<div class="transaction-pending"><strong>Still pending</strong><div class="small mt-1">The payment has not reached a successful state yet. You can use the Transactions page to verify it later.</div></div>';
                    clearInterval(pollTimer); pollTimer=null;
                }
            } catch(e) {
                if (attempts >= 15) clearInterval(pollTimer);
            }
        };
        poll(); pollTimer = setInterval(poll, 4000);
    }

    $('startBtn').addEventListener('click', start);
    $('sendBtn').addEventListener('click', send);
    $('restartBtn').addEventListener('click', resetDisplay);
    $('ussdInput').addEventListener('keydown', e => { if(e.key === 'Enter') send(); });
    $('ussdInput').addEventListener('input', function(){ this.value=this.value.replace(/[^0-9*#]/g,'').slice(0,30); });
    document.querySelectorAll('.ussd-key').forEach(btn => btn.addEventListener('click', () => {
        $('ussdInput').value += btn.dataset.key;
        $('ussdInput').focus();
    }));
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
