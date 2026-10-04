<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-icon"><i class="bi bi-broadcast-pin"></i></div>
        <div><strong>VoteHub</strong><small>USSD VOTING PLATFORM</small></div>
    </div>

    <div class="nav-label">MAIN</div>
    <nav>
        <a href="<?= APP_URL ?>/admin/dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
    <a href="<?= APP_URL ?>/admin/ussd-simulator.php"><i class="bi bi-broadcast-pin"></i><span>Live USSD Bot</span></a>
    <a href="<?= APP_URL ?>/api/ussd_test.php"><i class="bi bi-phone"></i><span>USSD Lookup Test</span></a>
    <a href="<?= APP_URL ?>/api/vote_test.php"><i class="bi bi-wallet2"></i> Vote & Payment Test</a>
</nav>

    <div class="nav-label">EVENT MANAGEMENT</div>
    <nav>
        <a href="<?= APP_URL ?>/admin/events/"><i class="bi bi-calendar-event"></i> Events</a>
        <a href="<?= APP_URL ?>/admin/categories/"><i class="bi bi-diagram-3"></i> Categories</a>
        <a href="<?= APP_URL ?>/admin/contestants/"><i class="bi bi-people"></i> Contestants</a>
        <a href="<?= APP_URL ?>/admin/votes/"><i class="bi bi-bar-chart-line"></i> Results</a>
    </nav>

    <div class="nav-label">FINANCE</div>
    <nav>
        <a href="<?= APP_URL ?>/admin/transactions/"><i class="bi bi-receipt"></i> Transactions</a>
        <a href="<?= APP_URL ?>/admin/reports/"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
    </nav>

    <div class="nav-label">SYSTEM</div>
    <nav>
        <a href="<?= APP_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>

    <div class="sidebar-status">
        <div class="d-flex justify-content-between">
            <small>USSD Service</small><span class="badge bg-success">ONLINE</span>
        </div>
        <div class="small text-secondary mt-2">Ready for gateway integration</div>
    </div>
</aside>
