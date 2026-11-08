<?php
// public/farmer/reports.php - the farmer's report history.
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('farmer');
$userId = currentUserId();

// Optional status filter from the URL (only known statuses are accepted).
$statusFilter = getValue('status');
if (!isValidStatus($statusFilter)) {
    $statusFilter = '';
}

$sql = 'SELECT s.report_id, s.farm_name, s.crop_name, s.report_date, s.status, r.symptoms
        FROM farmer_report_summary s
        JOIN reports r ON r.report_id = s.report_id
        WHERE s.user_id = :uid';
$params = ['uid' => $userId];

if ($statusFilter !== '') {
    $sql .= ' AND s.status = :status';
    $params['status'] = $statusFilter;
}
$sql .= ' ORDER BY s.report_date DESC, s.report_id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

$pageTitle  = 'My Reports';
$activeNav  = 'reports';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-header">
    <h1>My Reports</h1>
    <a class="btn btn-primary" href="/farmer/report_new.php">Submit new report</a>
</div>

<div class="card">
    <form method="get" action="/farmer/reports.php" class="filter-bar">
        <div class="form-group">
            <label for="status">Filter by status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (REPORT_STATUSES as $status): ?>
                    <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a class="btn btn-secondary" href="/farmer/reports.php">Clear</a>
        </div>
    </form>
</div>

<div class="card">
    <?php if (!$reports): ?>
        <p class="empty-state">No reports found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>ID</th><th>Farm</th><th>Crop</th><th>Submitted</th><th>Symptoms</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td>#<?= (int) $report['report_id'] ?></td>
                        <td><?= e($report['farm_name']) ?></td>
                        <td><?= e($report['crop_name']) ?></td>
                        <td><?= e(formatDate($report['report_date'])) ?></td>
                        <td><?= e(shortText($report['symptoms'], 80)) ?></td>
                        <td><?= statusBadge($report['status']) ?></td>
                        <td><a class="btn btn-secondary btn-sm" href="/farmer/report_view.php?id=<?= (int) $report['report_id'] ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
