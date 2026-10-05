<?php
declare(strict_types=1);

/**
 * VoteHub + Arkesel USSD webhook adapter.
 * Arkesel sends JSON POST requests and expects JSON responses.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ussd_engine.php';

header('Content-Type: application/json; charset=utf-8');

function arkeselJsonResponse(string $sessionId, string $userId, string $msisdn, string $message, bool $continueSession): never
{
    echo json_encode([
        'sessionID' => $sessionId,
        'userID' => $userId,
        'msisdn' => $msisdn,
        'message' => $message,
        'continueSession' => $continueSession,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        arkeselJsonResponse('', '', '', 'Invalid request.', false);
    }

    $sessionId = trim((string)($payload['sessionID'] ?? $payload['sessionId'] ?? ''));
    $userId = trim((string)($payload['userID'] ?? ''));
    $msisdnRaw = trim((string)($payload['msisdn'] ?? $payload['phoneNumber'] ?? ''));
    $phone = normalizePhone($msisdnRaw);
    $userData = trim((string)($payload['userData'] ?? $payload['text'] ?? ''));
    $newSession = filter_var($payload['newSession'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $network = trim((string)($payload['network'] ?? ''));

    if ($sessionId === '' || $phone === '') {
        arkeselJsonResponse($sessionId, $userId, $msisdnRaw, 'Invalid USSD session.', false);
    }

    // Arkesel userData is the latest user input, so VoteHub maintains the
    // cumulative menu path in the session state_data.
    $state = [];
    $stmt = $pdo->prepare('SELECT state_data, phone_number FROM ussd_sessions WHERE session_id=? LIMIT 1');
    $stmt->execute([$sessionId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($newSession || !$existing) {
        $text = '';
        $state = ['arkesel_path' => '', 'arkesel_network' => $network];
    } else {
        $state = json_decode((string)($existing['state_data'] ?? ''), true);
        if (!is_array($state)) $state = [];
        $previousPath = trim((string)($state['arkesel_path'] ?? ''));
        $text = $previousPath === '' ? $userData : $previousPath . '*' . $userData;
        if ($existing['phone_number'] !== null && normalizePhone((string)$existing['phone_number']) !== $phone) {
            arkeselJsonResponse($sessionId, $userId, $msisdnRaw, 'Invalid session.', false);
        }
    }

    $response = runVoteHubUssd($pdo, $sessionId, $phone, $text);

    // Preserve the Arkesel cumulative path after the core engine updates the session.
    $newStateStmt = $pdo->prepare('SELECT state_data FROM ussd_sessions WHERE session_id=? LIMIT 1');
    $newStateStmt->execute([$sessionId]);
    $newState = $newStateStmt->fetchColumn();
    $newStateArr = json_decode((string)$newState, true);
    if (!is_array($newStateArr)) $newStateArr = [];
    if ($newSession) {
        $newPath = '';
    } else {
        $newPath = $text;
    }
    $newStateArr['arkesel_path'] = $newPath;
    $newStateArr['arkesel_network'] = $network;
    $pdo->prepare('UPDATE ussd_sessions SET state_data=?,network=?,last_activity_at=NOW() WHERE session_id=?')->execute([json_encode($newStateArr, JSON_UNESCAPED_SLASHES), $network !== '' ? $network : null, $sessionId]);

    $continue = str_starts_with($response, 'CON ');
    $message = preg_replace('/^(CON|END)\s+/', '', $response) ?? $response;

    arkeselJsonResponse($sessionId, $userId, $msisdnRaw, $message, $continue);
} catch (Throwable $e) {
    error_log('VoteHub Arkesel USSD error: ' . $e->getMessage());
    arkeselJsonResponse('', '', '', 'Unable to process your request. Please try again.', false);
}
