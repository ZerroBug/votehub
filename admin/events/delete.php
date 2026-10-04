<?php
require_once __DIR__ . '/../../includes/auth.php'; requireSuperAdmin();
require_once __DIR__ . '/../../includes/functions.php';
$id=(int)($_GET['id']??0);
if($id){ $stmt=$pdo->prepare("DELETE FROM events WHERE id=?"); $stmt->execute([$id]); flash('success','Event deleted.'); }
redirect('index.php');
