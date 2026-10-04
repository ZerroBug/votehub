<?php
require_once __DIR__ . '/../../includes/auth.php'; requireSuperAdmin();
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle='Events'; require_once __DIR__ . '/../../includes/header.php'; require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="main"><?php require_once __DIR__ . '/../../includes/topbar.php'; ?><section class="content">
<?php showFlash(); ?>
<div class="d-flex justify-content-between align-items-end mb-4"><div><h1 class="page-title mb-1">Events</h1><p class="page-subtitle mb-0">Create and manage multiple voting events.</p></div><a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Create Event</a></div>
<div class="panel"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Event</th><th>Code</th><th>Dates</th><th>Categories</th><th>Contestants</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php $q=$pdo->query("SELECT e.*, (SELECT COUNT(*) FROM categories c WHERE c.event_id=e.id) cc, (SELECT COUNT(*) FROM contestants x WHERE x.event_id=e.id) xc FROM events e ORDER BY e.id DESC"); foreach($q as $e): ?>
<tr><td><strong><?= e($e['name']) ?></strong><small class="d-block text-secondary"><?= e($e['description']) ?></small></td><td><?= e($e['event_code']) ?></td><td><?= date('d M Y',strtotime($e['start_date'])) ?><br><small class="text-secondary"><?= date('d M Y',strtotime($e['end_date'])) ?></small></td><td><?= $e['cc'] ?></td><td><?= $e['xc'] ?></td><td><span class="badge-soft badge-<?= strtolower($e['status']) ?>"><?= e($e['status']) ?></span></td>
<td><a href="view.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-light border"><i class="bi bi-eye"></i></a> <a href="edit.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a> <a href="delete.php?id=<?= $e['id'] ?>" data-confirm="Delete this event and all its categories/contestants?" class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash"></i></a></td></tr>
<?php endforeach; if(!$q->rowCount()): ?><tr><td colspan="7" class="text-center py-5 text-secondary">No events found.</td></tr><?php endif; ?>
</tbody></table></div></div>
</section></main><?php require_once __DIR__ . '/../../includes/footer.php'; ?>
