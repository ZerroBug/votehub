<?php
require_once __DIR__ . '/../../includes/auth.php'; requireSuperAdmin(); require_once __DIR__ . '/../../includes/functions.php';
$eventId=(int)($_GET['event_id']??0);
$where='';$params=[];
if($eventId){$where='WHERE x.event_id=?';$params[]=$eventId;}
$sql="SELECT x.id,x.full_name,x.contestant_code,c.name category_name,e.name event_name,COALESCE(SUM(v.vote_count),0) votes FROM contestants x JOIN categories c ON c.id=x.category_id JOIN events e ON e.id=x.event_id LEFT JOIN votes v ON v.contestant_id=x.id $where GROUP BY x.id ORDER BY votes DESC";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
$pageTitle='Results';require_once __DIR__.'/../../includes/header.php';require_once __DIR__.'/../../includes/sidebar.php';
?>
<main class="main"><?php require_once __DIR__.'/../../includes/topbar.php';?><section class="content">
<div class="mb-4"><h1 class="page-title mb-1">Live Results</h1><p class="page-subtitle">Current contestant rankings. Votes will populate after USSD integration.</p></div>
<div class="panel"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Rank</th><th>Contestant</th><th>Event</th><th>Category</th><th>Votes</th></tr></thead><tbody>
<?php $rank=1;foreach($rows as $r): ?><tr><td><strong>#<?= $rank++ ?></strong></td><td><strong><?= e($r['full_name']) ?></strong><small class="d-block text-secondary"><?= e($r['contestant_code']) ?></small></td><td><?= e($r['event_name']) ?></td><td><?= e($r['category_name']) ?></td><td><strong><?= number_format($r['votes']) ?></strong></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="5" class="text-center py-5 text-secondary">No contestants available.</td></tr><?php endif; ?>
</tbody></table></div></div></section></main><?php require_once __DIR__.'/../../includes/footer.php';?>
