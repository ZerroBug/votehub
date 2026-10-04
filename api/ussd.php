<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ussd_engine.php';

header('Content-Type: text/plain; charset=utf-8');

$sessionId = trim((string)($_POST['sessionId'] ?? $_POST['session_id'] ?? ''));
$phone = normalizePhone((string)($_POST['phoneNumber'] ?? $_POST['phone_number'] ?? ''));
$text = trim((string)($_POST['text'] ?? ''));

echo runVoteHubUssd($pdo, $sessionId, $phone, $text);
