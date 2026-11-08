<?php
// public/admin/reports.php - searchable, filterable list of ALL reports.
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('admin');

// ---- Read the filters from the URL ----
$search    = getValue('q');
$status    = getValue('status');
$cropId    = getIdParam('crop_id');
$farmerId  = getIdParam('farmer_id');
$dateFrom  = getValue('date_from');
$dateTo    = getValue('date_to');

if (!isValidStatus($status)) { $status = ''; }
if ($dateFrom !== '' && !isValidDate($dateFrom)) { $dateFrom = ''; }
if ($dateTo !== ''   && !isValidDate($dateTo))   { $dateTo = ''; }

// ---- Build the query. Values are always bound as parameters. ----
$conditions = [];
$params     = [];

if ($search !== '') {
    // Escape LIKE wildcards typed by the user so they are searched literally.
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    $conditions[] = '(r.symptoms LIKE :q1 OR s.farmer_name LIKE :q2 OR s.farm_name LIKE :q3 OR s.crop_name LIKE :q4)';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}
if ($status !== '') {
    $conditions[] = 's.status = :status';
    $params['status'] = $status;
}
if ($cropId !== null) {
    $conditions[] = 's.crop_id = :crop_id';
    $params['crop_id'] = $cropId;
}
if ($farmerId !== null) {
    $conditions[] = 's.user_id = :farmer_id';
    $params['farmer_id'] = $farmerId;
}
if ($dateFrom !== '') {
    $conditions[] = 's.report_date >= :date_from';
    $params['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $conditions[] = 's.report_date < DATE_ADD(:date_to, INTERVAL 1 DAY)';
    $params['date_to'] = $dateTo;
}

$sql = 'SELECT s.report_id, s.farmer_name, s.farm_name, s.crop_name, s.report_date, s.status, r.symptoms
        FROM farmer_report_summary s
        JOIN reports r ON r.report_id = s.report_id';
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY s.report_date DESC, s.report_id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Dropdown data for the filters
$crops   = $pdo->query('SELECT crop_id, crop_name FROM crops ORDER BY crop_name')->fetchAll();
$farmers = $pdo->query("SELECT user_id, full_name FROM users WHERE role = 'farmer' ORDER BY full_name")->fetchAll();

$pageTitle  = 'All Reports';
$activeNav  = 'reports';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<h1>All Reports</h1>

<div class="card">
    <form method="get" action="/admin/reports.php" class="filter-bar">
        <div class="form-group">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="Symptoms, farmer, farm, crop">
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All</option>
                <?php foreach (REPORT_STATUSES as $statusOption): ?>
                    <option value="<?= e($statusOption) ?>" <?= $status === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="crop_id">Crop</label>
            <select id="crop_id" name="crop_id">
                <option value="">All</option>
                <?php foreach ($crops as $crop): ?>
                    <option value="<?= (int) $crop['crop_id'] ?>" <?= $cropId === (int) $crop['crop_id'] ? 'selected' : '' ?>><?= e($crop['crop_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="farmer_id">Farmer</label>
            <select id="farmer_id" name="farmer_id">
                <option value="">All</option>
                <?php foreach ($farmers as $farmer): ?>
                    <option value="<?= (int) $farmer['user_id'] ?>" <?= $farmerId === (int) $farmer['user_id'] ? 'selected' : '' ?>><?= e($farmer['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="date_from">From date</label>
            <input type="date" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="form-group">
            <label for="date_to">To date</label>
            <input type="date" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a class="btn btn-secondary" href="/admin/reports.php">Clear</a>
        </div>
    </form>
</div>

<div class="card">
    <p class="muted"><?= count($reports) ?> report(s) found.</p>

    <?php if (!$reports): ?>
        <p class="empty-state">No reports match your filters.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>ID</th><th>Farmer</th><th>Farm</th><th>Crop</th><th>Submitted</th><th>Symptoms</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td>#<?= (int) $report['report_id'] ?></td>
                        <td><?= e($report['farmer_name']) ?></td>
                        <td><?= e($report['farm_name']) ?></td>
                        <td><?= e($report['crop_name']) ?></td>
                        <td><?= e(formatDate($report['report_date'])) ?></td>
                        <td><?= e(shortText($report['symptoms'], 60)) ?></td>
                        <td><?= statusBadge($report['status']) ?></td>
                        <td><a class="btn btn-primary btn-sm" href="/admin/review.php?id=<?= (int) $report['report_id'] ?>">Review</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
