<?php

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    header("Location: $url");
    exit;
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function showFlash(): void {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        echo '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show shadow-sm">';
        echo e($f['message']);
        echo '<button class="btn-close" data-bs-dismiss="alert"></button></div>';
        unset($_SESSION['flash']);
    }
}

function old(string $key, string $default = ''): string {
    return e($_POST[$key] ?? $default);
}

function makeCodePrefix(string $name, int $maxLength = 5): string {
    $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    $letters = '';
    foreach ($words as $word) {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $word);
        if ($clean !== '') $letters .= strtoupper($clean[0]);
    }
    if (strlen($letters) < 2) {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
        $letters = substr($clean, 0, $maxLength);
    }
    return substr($letters ?: 'CAT', 0, $maxLength);
}

function generateCategoryCode(PDO $pdo, int $eventId, string $categoryName): string {
    $prefix = makeCodePrefix($categoryName, 5);

    $stmt = $pdo->prepare(
        "SELECT category_code FROM categories
         WHERE event_id=? AND category_code LIKE ?
         ORDER BY id DESC"
    );
    $stmt->execute([$eventId, $prefix . '%']);

    $max = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $code) {
        if (preg_match('/^'.preg_quote($prefix,'/').'(\d+)$/i', $code, $m)) {
            $max = max($max, (int)$m[1]);
        }
    }

    return $prefix . str_pad((string)($max + 1), 2, '0', STR_PAD_LEFT);
}

/**
 * Generates a public 4-digit contestant/ticket/voting code.
 * Codes are unique within an event.
 */
function generateContestantTicketCode(PDO $pdo, int $eventId, int $categoryId): string {
    $stmt = $pdo->prepare(
        "SELECT contestant_code FROM contestants
         WHERE event_id=? AND contestant_code REGEXP '^[0-9]{4}$'"
    );
    $stmt->execute([$eventId]);
    $used = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);

    for ($number = 1001; $number <= 9999; $number++) {
        $code = (string)$number;
        if (!isset($used[$code])) return $code;
    }

    throw new RuntimeException('All 4-digit contestant codes for this event have been used.');
}
