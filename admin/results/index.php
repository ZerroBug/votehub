<?php
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../../includes/functions.php';

$eventId = (int)($_GET['event_id'] ?? 0);
$categoryId = (int)($_GET['category_id'] ?? 0);
$autoRefresh = ($_GET['refresh'] ?? '1') !== '0';

$events = $pdo->query("SELECT id, name, event_code, status, start_date, end_date FROM events ORDER BY status='Active' DESC, created_at DESC")->fetchAll();

$selectedEvent = null;
$categories = [];
$rows = [];
$totalVotes = 0;
$totalRevenue = 0.0;
$totalContestants = 0;

if ($eventId) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id=? LIMIT 1");
    $stmt->execute([$eventId]);
    $selectedEvent = $stmt->fetch();

    if ($selectedEvent) {
        $stmt = $pdo->prepare("SELECT id, name, category_code, vote_price, display_order, status FROM categories WHERE event_id=? ORDER BY display_order, name");
        $stmt->execute([$eventId]);
        $categories = $stmt->fetchAll();

        $sql = "
            SELECT
                c.id,
                c.full_name,
                c.contestant_code,
                c.photo,
                c.gender,
                c.status AS contestant_status,
                cat.id AS category_id,
                cat.name AS category_name,
                cat.category_code,
                COALESCE((
                    SELECT SUM(v.vote_count)
                    FROM votes v
                    JOIN transactions tx ON tx.id=v.transaction_id AND tx.status='Successful'
                    WHERE v.contestant_id=c.id AND v.event_id=?
                ),0) AS votes,
                COALESCE((
                    SELECT SUM(tx.amount)
                    FROM votes v
                    JOIN transactions tx ON tx.id=v.transaction_id AND tx.status='Successful'
                    WHERE v.contestant_id=c.id AND v.event_id=?
                ),0) AS revenue
            FROM contestants c
            JOIN categories cat ON cat.id=c.category_id AND cat.event_id=c.event_id
            WHERE c.event_id=?
        ";
        $params = [$eventId, $eventId, $eventId];

        if ($categoryId) {
            $sql .= " AND c.category_id=? ";
            $params[] = $categoryId;
        }

        $sql .= " ORDER BY cat.display_order, cat.name, votes DESC, c.full_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['votes'] = (int)$r['votes'];
            $r['revenue'] = (float)$r['revenue'];
        }
        unset($r);

        $totalVotes = array_sum(array_column($rows, 'votes'));
        $totalRevenue = array_sum(array_column($rows, 'revenue'));
        $totalContestants = count($rows);
    }
}

$grouped = [];
foreach ($rows as $row) {
    $key = (int)$row['category_id'];
    $grouped[$key]['name'] = $row['category_name'];
    $grouped[$key]['code'] = $row['category_code'];
    $grouped[$key]['rows'][] = $row;
}

$pageTitle = 'Live Results';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.live-results-hero {
    background: linear-gradient(135deg, #0b4f22, #0f6b2e);
    color: #fff;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 10px 30px rgba(15, 107, 46, .15)
}

.live-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
    background: #35d07f;
    box-shadow: 0 0 0 5px rgba(53, 208, 127, .14);
    margin-right: 7px
}

.result-card {
    border: 1px solid #e8ecef;
    border-radius: 16px;
    background: #fff;
    overflow: hidden;
    height: 100%
}

.result-card-head {
    padding: 16px 18px;
    border-bottom: 1px solid #edf0f2;
    background: #fbfcfc
}

.rank-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid #f0f2f3
}

.rank-row:last-child {
    border-bottom: 0
}

.rank-no {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: 700;
    background: #f1f3f5;
    color: #495057;
    flex: 0 0 32px
}

.rank-no.top {
    background: #f4c430;
    color: #4b3b00
}

.contestant-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    background: #edf2ef;
    display: grid;
    place-items: center;
    font-weight: 700;
    color: #0f6b2e;
    flex: 0 0 44px
}

.vote-number {
    font-size: 1.08rem;
    font-weight: 800
}

.progress {
    height: 7px;
    background: #edf1ee
}

.progress-bar {
    background: #0f6b2e
}

.winner-badge {
    font-size: .7rem
}

.stat-mini {
    border: 1px solid #e9ecef;
    border-radius: 14px;
    padding: 16px;
    background: #fff
}

@media(max-width:767px) {
    .live-results-hero {
        padding: 18px
    }

    .rank-row {
        padding: 12px
    }

    .result-card-head {
        padding: 14px
    }
}
</style>

<main class="main">
    <?php require __DIR__ . '/../../includes/topbar.php'; ?>
    <section class="content">

        <div class="live-results-hero mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="small text-uppercase opacity-75 fw-semibold mb-1">Live Voting</div>
                    <h1 class="mb-1 fw-bold">Live Results</h1>
                    <p class="mb-0 opacity-75">Only successfully confirmed payments are counted as votes.</p>
                </div>
                <div class="text-md-end">
                    <span class="badge bg-light text-success px-3 py-2">
                        <span class="live-dot"></span>LIVE
                    </span>
                    <?php if ($autoRefresh && $eventId): ?>
                    <div class="small opacity-75 mt-2">Auto-refresh: every 10 seconds</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="panel mb-4">
            <div class="panel-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-lg-6">
                        <label class="form-label fw-semibold">Event</label>
                        <select name="event_id" class="form-select" onchange="this.form.submit()" required>
                            <option value="">Select event</option>
                            <?php foreach ($events as $e): ?>
                            <option value="<?= (int)$e['id'] ?>" <?= $eventId === (int)$e['id'] ? 'selected' : '' ?>>
                                <?= e($e['name']) ?> — <?= e($e['event_code']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"
                                <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?> — <?= e($cat['category_code']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 d-flex gap-2">
                        <input type="hidden" name="refresh" value="<?= $autoRefresh ? '1' : '0' ?>">
                        <button class="btn btn-primary flex-grow-1">View</button>
                        <?php if ($eventId): ?>
                        <a href="?event_id=<?= $eventId ?>&category_id=<?= $categoryId ?>&refresh=<?= $autoRefresh ? '0' : '1' ?>"
                            class="btn btn-light border" title="Toggle auto-refresh">
                            <i class="bi bi-arrow-repeat"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!$eventId): ?>
        <div class="panel">
            <div class="panel-body text-center py-5">
                <i class="bi bi-bar-chart-line display-5 text-success"></i>
                <h4 class="mt-3">Select an event</h4>
                <p class="text-secondary mb-0">Choose an event above to display its live voting standings.</p>
            </div>
        </div>
        <?php elseif (!$selectedEvent): ?>
        <div class="alert alert-danger">The selected event could not be found.</div>
        <?php else: ?>

        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <h4 class="mb-1"><?= e($selectedEvent['name']) ?></h4>
                <div class="text-secondary small">
                    <?= e($selectedEvent['event_code']) ?> · <?= e($selectedEvent['status']) ?>
                </div>
            </div>
            <div class="text-end">
                <div class="small text-secondary">Last page refresh</div>
                <strong><?= date('d M Y, H:i:s') ?></strong>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-mini">
                    <div class="small text-secondary">Total Votes</div>
                    <div class="fs-4 fw-bold mt-1"><?= number_format($totalVotes) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-mini">
                    <div class="small text-secondary">Successful Voting Revenue</div>
                    <div class="fs-4 fw-bold mt-1">GHS <?= number_format($totalRevenue,2) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-mini">
                    <div class="small text-secondary">Contestants Showing</div>
                    <div class="fs-4 fw-bold mt-1"><?= number_format($totalContestants) ?></div>
                </div>
            </div>
        </div>

        <?php if (!$rows): ?>
        <div class="panel">
            <div class="panel-body text-center py-5 text-secondary">No contestants or confirmed votes are available for
                this selection yet.</div>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($grouped as $group):
                    $categoryRows = $group['rows'];
                    $categoryTotal = array_sum(array_column($categoryRows, 'votes'));
                    $rank = 0;
                ?>
            <div class="col-12 col-xl-6">
                <div class="result-card">
                    <div class="result-card-head d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold"><?= e($group['name']) ?></div>
                            <small class="text-secondary"><?= e($group['code']) ?> ·
                                <?= number_format($categoryTotal) ?> votes</small>
                        </div>
                        <span class="badge bg-success-subtle text-success">LIVE</span>
                    </div>

                    <?php foreach ($categoryRows as $r): $rank++; $percentage = $categoryTotal > 0 ? ($r['votes'] / $categoryTotal) * 100 : 0; ?>
                    <div class="rank-row">
                        <div class="rank-no <?= $rank <= 3 ? 'top' : '' ?>"><?= $rank ?></div>

                        <?php if (!empty($r['photo'])): ?>
                        <img class="contestant-avatar" src="<?= e(APP_URL . '/' . ltrim($r['photo'], '/')) ?>" alt="">
                        <?php else: ?>
                        <div class="contestant-avatar"><?= e(strtoupper(substr($r['full_name'],0,1))) ?></div>
                        <?php endif; ?>

                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between gap-2">
                                <div class="fw-semibold text-truncate"><?= e($r['full_name']) ?></div>
                                <?php if ($rank === 1): ?><span
                                    class="badge bg-warning text-dark winner-badge">WINNER</span><?php endif; ?>
                            </div>
                            <small class="text-secondary">Code: <?= e($r['contestant_code']) ?></small>
                            <div class="progress mt-2">
                                <div class="progress-bar" style="width: <?= min(100, max(0, $percentage)) ?>%"></div>
                            </div>
                        </div>

                        <div class="text-end ms-2">
                            <div class="vote-number"><?= number_format($r['votes']) ?></div>
                            <small class="text-secondary">votes</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </section>
</main>

<?php if ($autoRefresh && $eventId && $selectedEvent): ?>
<script>
setTimeout(function() {
    const url = new URL(window.location.href);
    url.searchParams.set('refresh', '1');
    window.location.replace(url.toString());
}, 10000);
</script>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>