<header class="topbar">
    <button class="mobile-menu" id="mobileMenu"><i class="bi bi-list"></i></button>
    <div class="top-search">
        <i class="bi bi-search"></i>
        <input placeholder="Search events, contestants...">
    </div>
    <div class="top-user">
        <div class="avatar"><?= e(strtoupper(substr($_SESSION['full_name'] ?? 'SA', 0, 2))) ?></div>
        <div class="user-text">
            <strong><?= e($_SESSION['full_name'] ?? 'Super Admin') ?></strong>
            <small>Super Admin</small>
        </div>
    </div>
</header>
