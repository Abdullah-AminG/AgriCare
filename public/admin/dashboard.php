<?php
// public/admin/dashboard.php - overview for admins/advisers. All numbers come from the database.
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('admin');

$counts = ['Pending' => 0, 'Under Review' => 0, 'Resolved' => 0];
$rows = $pdo->query('SELECT status, COUNT(*) AS total FROM reports GROUP BY status')->fetchAll();
foreach ($rows as $row) {
    $counts[$row['status']] = (int) $row['total'];
}
$totalReports = array_sum($counts);

$totalFarmers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer'")->fetchColumn();

// Recently submitted reports (view)
$recentReports = $pdo->query(
    'SELECT report_id, farmer_name, crop_name, report_date, status
     FROM farmer_report_summary
     ORDER BY report_date DESC, report_id DESC
     LIMIT 5'
)->fetchAll();

// Oldest pending reports first (view)
$pendingReports = $pdo->query(
    'SELECT report_id, farmer_name, crop_name, symptoms, report_date
     FROM reviewer_pending_reports
     ORDER BY report_date ASC
     LIMIT 5'
)->fetchAll();

// Recent reviewer activity (real records from the advice table)
$activity = $pdo->query(
    'SELECT a.report_id, a.response_date, u.full_name AS reviewer_name, c.crop_name
     FROM advice a
     JOIN users u       ON u.user_id = a.reviewer_id
     JOIN reports r     ON r.report_id = a.report_id
     JOIN farm_crops fc ON fc.farm_crop_id = r.farm_crop_id
     JOIN crops c       ON c.crop_id = fc.crop_id
     ORDER BY a.response_date DESC, a.advice_id DESC
     LIMIT 5'
)->fetchAll();

$pageTitle  = 'Admin Dashboard';
$activeNav  = 'dashboard';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<h1>Admin / Adviser Dashboard</h1>

<div class="stats-grid">
    <div class="stat-card"><span class="stat-number"><?= $totalReports ?></span><span class="stat-label">Total reports</span></div>
    <div class="stat-card pending"><span class="stat-number"><?= $counts['Pending'] ?></span><span class="stat-label">Pending</span></div>
    <div class="stat-card review"><span class="stat-number"><?= $counts['Under Review'] ?></span><span class="stat-label">Under review</span></div>
    <div class="stat-card resolved"><span class="stat-number"><?= $counts['Resolved'] ?></span><span class="stat-label">Resolved</span></div>
    <div class="stat-card"><span class="stat-number"><?= $totalFarmers ?></span><span class="stat-label">Registered farmers</span></div>
</div>

<div class="quick-links">
    <a class="btn btn-primary" href="/admin/reports.php?status=Pending">Review pending reports</a>
    <a class="btn btn-secondary" href="/admin/reports.php">All reports</a>
    <a class="btn btn-secondary" href="/admin/crops.php">Manage crops</a>
    <a class="btn btn-secondary" href="/admin/diseases.php">Manage diseases</a>
</div>

<div class="card">
    <h2>Waiting for review</h2>
    <?php if (!$pendingReports): ?>
        <p class="empty-state">No pending reports.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Farmer</th><th>Crop</th><th>Symptoms</th><th>Submitted</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pendingReports as $report): ?>
                    <tr>
                        <td>#<?= (int) $report['report_id'] ?></td>
                        <td><?= e($report['farmer_name']) ?></td>
                        <td><?= e($report['crop_name']) ?></td>
                        <td><?= e(shortText($report['symptoms'], 70)) ?></td>
                        <td><?= e(formatDate($report['report_date'])) ?></td>
                        <td><a class="btn btn-primary btn-sm" href="/admin/review.php?id=<?= (int) $report['report_id'] ?>">Review</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="two-column">
    <div class="card">
        <h2>Recently submitted</h2>
        <?php if (!$recentReports): ?>
            <p class="empty-state">No reports yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Farmer</th><th>Crop</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentReports as $report): ?>
                        <tr>
                            <td><a href="/admin/review.php?id=<?= (int) $report['report_id'] ?>">#<?= (int) $report['report_id'] ?></a></td>
                            <td><?= e($report['farmer_name']) ?></td>
                            <td><?= e($report['crop_name']) ?></td>
                            <td><?= statusBadge($report['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Recent reviewer activity</h2>
        <?php if (!$activity): ?>
            <p class="empty-state">No advice has been given yet.</p>
        <?php else: ?>
            <?php foreach ($activity as $item): ?>
                <div class="timeline-item">
                    <div class="timeline-meta"><?= e(formatDateTime($item['response_date'])) ?></div>
                    <?= e($item['reviewer_name']) ?> responded to
                    <a href="/admin/review.php?id=<?= (int) $item['report_id'] ?>">report #<?= (int) $item['report_id'] ?></a>
                    (<?= e($item['crop_name']) ?>)
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
