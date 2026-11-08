<?php // includes/sidebar_farmer.php - navigation for farmer pages ?>
<aside class="sidebar">
    <div class="sidebar-user">
        <span class="muted">Signed in as</span>
        <strong><?= e($_SESSION['full_name'] ?? '') ?></strong>
        <span class="role-tag">Farmer</span>
    </div>
    <nav class="sidebar-nav">
        <a class="<?= activeClass('dashboard', $activeNav) ?>"  href="/farmer/dashboard.php">Dashboard</a>
        <a class="<?= activeClass('farms', $activeNav) ?>"      href="/farmer/farms.php">My Farms</a>
        <a class="<?= activeClass('crops', $activeNav) ?>"      href="/farmer/crops.php">My Crops</a>
        <a class="<?= activeClass('report_new', $activeNav) ?>" href="/farmer/report_new.php">Submit Report</a>
        <a class="<?= activeClass('reports', $activeNav) ?>"    href="/farmer/reports.php">My Reports</a>
        <a class="<?= activeClass('guide', $activeNav) ?>"      href="/guide.php">Crop Disease Guide</a>
        <a class="<?= activeClass('account', $activeNav) ?>"    href="/account.php">Account Settings</a>
    </nav>
</aside>
