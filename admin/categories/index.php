<?php
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../../includes/functions.php';

$eventId=(int)($_GET['event_id']??0);
$stmt=$pdo->prepare("SELECT * FROM events WHERE id=?");
$stmt->execute([$eventId]);
$event=$stmt->fetch();
if(!$event) die('Select a valid event.');

if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=trim($_POST['name']??'');
    if(!$name){
        flash('danger','Category name is required.');
    } else {
        try{
            $code=generateCategoryCode($pdo,$eventId,$name);
            $stmt=$pdo->prepare("INSERT INTO categories(event_id,name,category_code,description,vote_price,max_votes_per_transaction,max_votes_per_phone,status,display_order) VALUES(?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $eventId,$name,$code,trim($_POST['description']??''),
                (float)($_POST['vote_price']??$event['default_vote_price']),
                max(1,(int)($_POST['max_votes_per_transaction']??20)),
                ($_POST['max_votes_per_phone']??'')!==''?(int)$_POST['max_votes_per_phone']:null,
                $_POST['status']??'Active',max(1,(int)($_POST['display_order']??1))
            ]);
            flash('success',"Category created successfully. Auto-generated code: {$code}");
            redirect("index.php?event_id={$eventId}");
        }catch(Throwable $e){
            flash('danger','Could not create category: '.$e->getMessage());
        }
    }
}

$stmt=$pdo->prepare("SELECT c.*,(SELECT COUNT(*) FROM contestants x WHERE x.category_id=c.id) contestant_count FROM categories c WHERE c.event_id=? ORDER BY c.display_order,c.id");
$stmt->execute([$eventId]); $cats=$stmt->fetchAll();

$pageTitle='Categories';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="main">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>
<section class="content">
<?php showFlash(); ?>
<div class="d-flex justify-content-between align-items-end mb-4">
 <div><span class="section-kicker">EVENT CATEGORIES</span><h1 class="page-title mb-1"><?=e($event['name'])?></h1><p class="page-subtitle mb-0"><span class="code-pill"><?=e($event['event_code'])?></span> Create and manage voting categories.</p></div>
 <div class="d-flex gap-2"><a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Category</a><a href="../events/view.php?id=<?=$eventId?>" class="btn btn-light border"><i class="bi bi-arrow-left me-1"></i>Back to Event</a></div>
</div>
<div class="row g-4">
 <div class="col-xl-8">
  <div class="panel">
   <div class="panel-head"><div><h5>Category Directory</h5><small class="text-secondary">Category codes are generated automatically.</small></div><span class="mini-stat"><?=count($cats)?> categories</span></div>
   <div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><th>#</th><th>Category</th><th>Code</th><th>Price</th><th>Contestants</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach($cats as $c): ?><tr>
     <td><?=e($c['display_order'])?></td>
     <td><strong><?=e($c['name'])?></strong><small class="d-block text-secondary"><?=e($c['description'])?></small></td>
     <td><span class="code-pill"><?=e($c['category_code'])?></span></td>
     <td>GHS <?=number_format((float)$c['vote_price'],2)?></td>
     <td><span class="count-chip"><?= (int)$c['contestant_count']?></span></td>
     <td><span class="badge-soft badge-<?=$c['status']==='Active'?'live':'draft'?>"><?=e($c['status'])?></span></td>
     <td class="text-end"><a href="edit.php?id=<?=$c['id']?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a> <a href="delete.php?id=<?=$c['id']?>" onclick="return confirm('Delete/deactivate this category?')" class="btn btn-sm btn-light border"><i class="bi bi-trash"></i></a></td>
    </tr><?php endforeach; ?>
    <?php if(!$cats): ?><tr><td colspan="6" class="text-center py-5 text-secondary"><i class="bi bi-diagram-3 fs-2 d-block mb-2"></i>No categories yet.</td></tr><?php endif; ?>
    </tbody>
   </table></div>
  </div>
 </div>
 <div class="col-xl-4">
  <div class="panel sticky-xl-top" style="top:95px">
   <div class="panel-head"><div><h5>Add Category</h5><small class="text-secondary">Code is automatic.</small></div><i class="bi bi-magic text-primary"></i></div>
   <div class="panel-body"><form method="post">
    <label class="form-label">Category Name *</label>
    <input name="name" id="categoryName" class="form-control mb-2" required placeholder="Most Famous Student">
    <div class="auto-code-preview mb-3"><div><small>AUTO-GENERATED CATEGORY CODE</small><strong id="categoryCodePreview">MFS01</strong></div><i class="bi bi-shield-check"></i></div>
    <div class="form-help mb-3">Example: <strong>Most Famous Student</strong> → <strong>MFS01</strong>.</div>
    <label class="form-label">Description</label><textarea name="description" class="form-control mb-3" rows="3"></textarea>
    <div class="row g-2">
     <div class="col-6"><label class="form-label">Vote Price (GHS)</label><input name="vote_price" type="number" min="0" step=".01" value="<?=e($event['default_vote_price'])?>" class="form-control mb-3"></div>
     <div class="col-6"><label class="form-label">Max / Transaction</label><input name="max_votes_per_transaction" type="number" min="1" value="20" class="form-control mb-3"></div>
    </div>
    <div class="row g-2">
     <div class="col-6"><label class="form-label">Max / Phone</label><input name="max_votes_per_phone" type="number" min="1" class="form-control mb-3" placeholder="Optional"></div>
     <div class="col-6"><label class="form-label">Display Order</label><input name="display_order" type="number" min="1" value="<?=count($cats)+1?>" class="form-control mb-3"></div>
    </div>
    <label class="form-label">Status</label><select name="status" class="form-select mb-4"><option>Active</option><option>Inactive</option></select>
    <button class="btn btn-primary w-100"><i class="bi bi-plus-circle me-1"></i>Create Category</button>
   </form></div>
  </div>
 </div>
</div>
</section></main>
<script>
document.addEventListener('DOMContentLoaded',()=>{const i=document.getElementById('categoryName'),p=document.getElementById('categoryCodePreview');i.addEventListener('input',()=>{let a=i.value.trim().split(/\s+/).filter(Boolean),x='';a.forEach(w=>{w=w.replace(/[^a-zA-Z0-9]/g,'');if(w)x+=w[0].toUpperCase()});if(x.length<2)x=i.value.replace(/[^a-zA-Z0-9]/g,'').toUpperCase().substring(0,5);p.textContent=(x||'CAT').substring(0,5)+'01';});});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
