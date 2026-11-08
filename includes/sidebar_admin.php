<?php // includes/sidebar_admin.php - navigation for admin/adviser pages ?>
<aside class="sidebar">
    <div class="sidebar-user">
        <span class="muted">Signed in as</span>
        <strong><?= e($_SESSION['full_name'] ?? '') ?></strong>
        <span class="role-tag">Admin / Adviser</span>
    </div>
    <nav class="sidebar-nav">
        <a class="<?= activeClass('dashboard', $activeNav) ?>" href="/admin/dashboard.php">Dashboard</a>
        <a class="<?= activeClass('reports', $activeNav) ?>"   href="/admin/reports.php">All Reports</a>
        <a class="<?= activeClass('crops', $activeNav) ?>"     href="/admin/crops.php">Crop Management</a>
        <a class="<?= activeClass('diseases', $activeNav) ?>"  href="/admin/diseases.php">Disease Management</a>
        <a class="<?= activeClass('guide', $activeNav) ?>"     href="/guide.php">Crop Disease Guide</a>
        <a class="<?= activeClass('account', $activeNav) ?>"   href="/account.php">Account Settings</a>
    </nav>
</aside>
