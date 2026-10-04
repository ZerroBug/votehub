<?php
require_once __DIR__ . '/../../includes/auth.php'; requireSuperAdmin();
$pageTitle='Reports';require_once __DIR__.'/../../includes/header.php';require_once __DIR__.'/../../includes/sidebar.php';
?>
<main class="main"><?php require_once __DIR__.'/../../includes/topbar.php';?><section class="content">
<div class="mb-4"><h1 class="page-title mb-1">Reports</h1><p class="page-subtitle">Reporting module placeholder — ready for PDF/Excel exports.</p></div>
<div class="row g-3"><div class="col-md-4"><div class="card-box p-4"><i class="bi bi-file-earmark-spreadsheet fs-2 text-success"></i><h5 class="mt-3">Voting Report</h5><p class="text-secondary small">Votes by event, category and contestant.</p><button class="btn btn-primary btn-sm" disabled>Coming Next</button></div></div>
<div class="col-md-4"><div class="card-box p-4"><i class="bi bi-cash-stack fs-2 text-primary"></i><h5 class="mt-3">Revenue Report</h5><p class="text-secondary small">Successful payment and revenue analysis.</p><button class="btn btn-primary btn-sm" disabled>Coming Next</button></div></div>
<div class="col-md-4"><div class="card-box p-4"><i class="bi bi-file-earmark-pdf fs-2 text-danger"></i><h5 class="mt-3">Event Report</h5><p class="text-secondary small">Complete event performance report.</p><button class="btn btn-primary btn-sm" disabled>Coming Next</button></div></div></div>
</section></main><?php require_once __DIR__.'/../../includes/footer.php';?>
