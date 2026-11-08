<?php
// public/farmer/dashboard.php - farmer overview. Every number comes from the database.
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('farmer');
$userId = currentUserId();

// Total farms
$stmt = $pdo->prepare('SELECT COUNT(*) FROM farms WHERE user_id = :uid');
$stmt->execute(['uid' => $userId]);
$totalFarms = (int) $stmt->fetchColumn();

// Total crop records on this farmer's farms
$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM farm_crops fc
     JOIN farms f ON f.farm_id = fc.farm_id
     WHERE f.user_id = :uid'
);
$stmt->execute(['uid' => $userId]);
$totalCrops = (int) $stmt->fetchColumn();

// Reports counted per status
$counts = ['Pending' => 0, 'Under Review' => 0, 'Resolved' => 0];
$stmt = $pdo->prepare('SELECT status, COUNT(*) AS total FROM reports WHERE user_id = :uid GROUP BY status');
$stmt->execute(['uid' => $userId]);
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['total'];
}
$totalReports = array_sum($counts);

// Recently submitted reports (uses the farmer_report_summary view)
$stmt = $pdo->prepare(
    'SELECT report_id, farm_name, crop_name, report_date, status
     FROM farmer_report_summary
     WHERE user_id = :uid
     ORDER BY report_date DESC, report_id DESC
     LIMIT 5'
);
$stmt->execute(['uid' => $userId]);
$recentReports = $stmt->fetchAll();

// Latest adviser recommendations for this farmer's report
$stmt = $pdo->prepare(
    'SELECT a.report_id, a.advice_text, a.response_date, d.disease_name, c.crop_name
     FROM advice a
     JOIN reports r     ON r.report_id = a.report_id
     JOIN farm_crops fc ON fc.farm_crop_id = r.farm_crop_id
     JOIN crops c       ON c.crop_id = fc.crop_id
     LEFT JOIN diseases d ON d.disease_id = a.disease_id
     WHERE r.user_id = :uid
     ORDER BY a.response_date DESC, a.advice_id DESC
     LIMIT 3'
);
$stmt->execute(['uid' => $userId]);
$latestAdvice = $stmt->fetchAll();

$pageTitle  = 'Farmer Dashboard';
$activeNav  = 'dashboard';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<h1>Welcome, <?= e($_SESSION['full_name'] ?? '') ?></h1>
<p class="muted">Here is an overview of your farms and crop reports.</p>

<div class="stats-grid">
    <div class="stat-card"><span class="stat-number"><?= $totalFarms ?></span><span class="stat-label">Registered farms</span></div>
    <div class="stat-card"><span class="stat-number"><?= $totalCrops ?></span><span class="stat-label">Crops being grown</span></div>
    <div class="stat-card"><span class="stat-number"><?= $totalReports ?></span><span class="stat-label">Total reports</span></div>
    <div class="stat-card pending"><span class="stat-number"><?= $counts['Pending'] ?></span><span class="stat-label">Pending</span></div>
    <div class="stat-card review"><span class="stat-number"><?= $counts['Under Review'] ?></span><span class="stat-label">Under review</span></div>
    <div class="stat-card resolved"><span class="stat-number"><?= $counts['Resolved'] ?></span><span class="stat-label">Resolved</span></div>
</div>

<div class="quick-links">
    <a class="btn btn-primary" href="/farmer/farms.php">Add Farm</a>
    <a class="btn btn-primary" href="/farmer/crops.php">Add Crop</a>
    <a class="btn btn-primary" href="/farmer/report_new.php">Submit Crop Report</a>
    <a class="btn btn-secondary" href="/farmer/reports.php">View My Reports</a>
    <a class="btn btn-secondary" href="/guide.php">Browse Crop Disease Guide</a>
</div>

<div class="card">
    <h2>Recent reports</h2>
    <?php if (!$recentReports): ?>
        <p class="empty-state">You have not submitted any reports yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>ID</th><th>Farm</th><th>Crop</th><th>Date</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentReports as $report): ?>
                    <tr>
                        <td>#<?= (int) $report['report_id'] ?></td>
                        <td><?= e($report['farm_name']) ?></td>
                        <td><?= e($report['crop_name']) ?></td>
                        <td><?= e(formatDate($report['report_date'])) ?></td>
                        <td><?= statusBadge($report['status']) ?></td>
                        <td><a href="/farmer/report_view.php?id=<?= (int) $report['report_id'] ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Latest adviser recommendations</h2>
    <?php if (!$latestAdvice): ?>
        <p class="empty-state">No recommendations yet. An adviser will respond after reviewing your report.</p>
    <?php else: ?>
        <?php foreach ($latestAdvice as $advice): ?>
            <div class="timeline-item">
                <div class="timeline-meta">
                    Report #<?= (int) $advice['report_id'] ?> (<?= e($advice['crop_name']) ?>) &middot;
                    <?= e(formatDateTime($advice['response_date'])) ?>
                    <?php if ($advice['disease_name'] !== null): ?>
                        &middot; Possible disease: <strong><?= e($advice['disease_name']) ?></strong>
                    <?php endif; ?>
                </div>
                <div><?= nl2br(e(shortText($advice['advice_text'], 220))) ?></div>
                <a href="/farmer/report_view.php?id=<?= (int) $advice['report_id'] ?>">Open report</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
