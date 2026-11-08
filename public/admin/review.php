<?php
// public/admin/review.php - review one report: advice, possible disease and status.
// Disease identification is ENTERED BY THE REVIEWER. The system does not diagnose automatically.
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('admin');

$reportId = getIdParam('id');
if ($reportId === null) {
    notFound('Report not found.');
}

// Report with farmer, farm and crop information
$stmt = $pdo->prepare(
    'SELECT r.report_id, r.user_id, r.image_path, r.symptoms, r.report_date, r.status,
            u.full_name AS farmer_name, u.phone, u.email,
            f.farm_name, f.location, f.area,
            c.crop_id, c.crop_name, fc.planting_date
     FROM reports r
     JOIN users u       ON u.user_id = r.user_id
     JOIN farm_crops fc ON fc.farm_crop_id = r.farm_crop_id
     JOIN farms f       ON f.farm_id = fc.farm_id
     JOIN crops c       ON c.crop_id = fc.crop_id
     WHERE r.report_id = :id'
);
$stmt->execute(['id' => $reportId]);
$report = $stmt->fetch();

if (!$report) {
    notFound('Report not found.');
}

// Diseases of THIS crop (the reviewer chooses from the disease database)
$stmt = $pdo->prepare('SELECT disease_id, disease_name FROM diseases WHERE crop_id = :crop_id ORDER BY disease_name');
$stmt->execute(['crop_id' => $report['crop_id']]);
$cropDiseases = $stmt->fetchAll();
$validDiseaseIds = array_column($cropDiseases, 'disease_id');

// Number of advice records that already exist for this report
$stmt = $pdo->prepare('SELECT COUNT(*) FROM advice WHERE report_id = :id');
$stmt->execute(['id' => $reportId]);
$existingAdviceCount = (int) $stmt->fetchColumn();

$error      = '';
$newStatus  = $report['status'] === 'Pending' ? 'Under Review' : $report['status'];
$adviceText = '';
$diseaseId  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $newStatus  = postValue('status');
    $adviceText = postValue('advice_text');
    $diseaseId  = postValue('disease_id');
    $diseaseValue = null;

    if (!isValidStatus($newStatus)) {
        $error = 'Please select a valid status.';
    }

    // Optional disease: if chosen it must be one of this crop's diseases.
    if ($error === '' && $diseaseId !== '') {
        $diseaseInt = filter_var($diseaseId, FILTER_VALIDATE_INT);
        if ($diseaseInt === false || !in_array($diseaseInt, array_map('intval', $validDiseaseIds), true)) {
            $error = 'The selected disease is not valid for this crop.';
        } else {
            $diseaseValue = $diseaseInt;
        }
    }

    if ($error === '') {
        if ($adviceText !== '' && mb_strlen($adviceText) < 10) {
            $error = 'Advice must be at least 10 characters long.';
        } elseif (mb_strlen($adviceText) > 3000) {
            $error = 'Advice must not be longer than 3000 characters.';
        } elseif ($adviceText === '' && $diseaseValue !== null) {
            $error = 'Please write advice when you select a possible disease.';
        } elseif ($newStatus === 'Resolved' && $adviceText === '' && $existingAdviceCount === 0) {
            $error = 'Write advice for the farmer before marking the report as Resolved.';
        } elseif ($adviceText === '' && $newStatus === $report['status']) {
            $error = 'Nothing to save. Write advice or change the status.';
        }
    }

    if ($error === '') {
        try {
            // Both changes succeed together or not at all.
            $pdo->beginTransaction();

            if ($adviceText !== '') {
                // reviewer_id comes from the session. Previous advice is kept.
                $stmt = $pdo->prepare(
                    'INSERT INTO advice (report_id, reviewer_id, disease_id, advice_text)
                     VALUES (:report_id, :reviewer_id, :disease_id, :advice_text)'
                );
                $stmt->execute([
                    'report_id'   => $reportId,
                    'reviewer_id' => currentUserId(),
                    'disease_id'  => $diseaseValue,
                    'advice_text' => $adviceText,
                ]);
            }

            if ($newStatus !== $report['status']) {
                $stmt = $pdo->prepare('UPDATE reports SET status = :status WHERE report_id = :id');
                $stmt->execute(['status' => $newStatus, 'id' => $reportId]);
            }

            $pdo->commit();

            setFlash('success', 'Review saved successfully.');
            redirect('/admin/review.php?id=' . $reportId);
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('AgriCare review error: ' . $exception->getMessage());
            $error = 'Could not save the review. Please try again.';
        }
    }
}

// Advice history
$stmt = $pdo->prepare(
    'SELECT a.advice_text, a.response_date, u.full_name AS reviewer_name, d.disease_name
     FROM advice a
     JOIN users u ON u.user_id = a.reviewer_id
     LEFT JOIN diseases d ON d.disease_id = a.disease_id
     WHERE a.report_id = :id
     ORDER BY a.response_date DESC, a.advice_id DESC'
);
$stmt->execute(['id' => $reportId]);
$adviceList = $stmt->fetchAll();

// Farmer follow-up updates
$stmt = $pdo->prepare('SELECT update_note, update_date FROM report_updates WHERE report_id = :id ORDER BY update_date ASC, update_id ASC');
$stmt->execute(['id' => $reportId]);
$updates = $stmt->fetchAll();

// Report history: this farmer's other reports
$stmt = $pdo->prepare(
    'SELECT report_id, crop_name, report_date, status
     FROM farmer_report_summary
     WHERE user_id = :user_id AND report_id <> :id
     ORDER BY report_date DESC
     LIMIT 10'
);
$stmt->execute(['user_id' => $report['user_id'], 'id' => $reportId]);
$otherReports = $stmt->fetchAll();

$pageTitle  = 'Review Report #' . $report['report_id'];
$activeNav  = 'reports';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-header">
    <h1>Review Report #<?= (int) $report['report_id'] ?> <?= statusBadge($report['status']) ?></h1>
    <a class="btn btn-secondary" href="/admin/reports.php">Back to All Reports</a>
</div>

<div class="two-column">
    <div class="card">
        <h2>Farmer</h2>
        <dl class="detail-list">
            <dt>Name</dt><dd><?= e($report['farmer_name']) ?></dd>
            <dt>Phone</dt><dd><?= e($report['phone']) ?></dd>
            <dt>Email</dt><dd><?= e($report['email']) ?></dd>
        </dl>
    </div>
    <div class="card">
        <h2>Farm and crop</h2>
        <dl class="detail-list">
            <dt>Farm</dt><dd><?= e($report['farm_name']) ?></dd>
            <dt>Location</dt><dd><?= e($report['location']) ?></dd>
            <dt>Area</dt><dd><?= e(number_format((float) $report['area'], 2)) ?> acres</dd>
            <dt>Crop</dt><dd><?= e($report['crop_name']) ?></dd>
            <dt>Planted</dt><dd><?= e(formatDate($report['planting_date'])) ?></dd>
            <dt>Submitted</dt><dd><?= e(formatDateTime($report['report_date'])) ?></dd>
        </dl>
    </div>
</div>

<div class="card">
    <h2>Symptoms reported by the farmer</h2>
    <p><?= nl2br(e($report['symptoms'])) ?></p>

    <?php if ($report['image_path'] !== null): ?>
        <h3>Photograph</h3>
        <img class="report-image" src="/<?= e($report['image_path']) ?>" alt="Photograph of the affected crop">
    <?php else: ?>
        <p class="muted">No photograph was uploaded.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Farmer follow-up updates</h2>
    <?php if (!$updates): ?>
        <p class="empty-state">The farmer has not added any updates.</p>
    <?php else: ?>
        <?php foreach ($updates as $update): ?>
            <div class="timeline-item">
                <div class="timeline-meta"><?= e(formatDateTime($update['update_date'])) ?></div>
                <div><?= nl2br(e($update['update_note'])) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Previous advice</h2>
    <?php if (!$adviceList): ?>
        <p class="empty-state">No advice has been given yet.</p>
    <?php else: ?>
        <?php foreach ($adviceList as $advice): ?>
            <div class="timeline-item">
                <div class="timeline-meta">
                    <?= e($advice['reviewer_name']) ?> &middot; <?= e(formatDateTime($advice['response_date'])) ?>
                    <?php if ($advice['disease_name'] !== null): ?>
                        &middot; Possible disease: <strong><?= e($advice['disease_name']) ?></strong>
                    <?php endif; ?>
                </div>
                <div><?= nl2br(e($advice['advice_text'])) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Add advice / update status</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/review.php?id=<?= (int) $report['report_id'] ?>" novalidate>
        <?= csrfField() ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="disease_id">Possible disease (optional)</label>
                <select id="disease_id" name="disease_id">
                    <option value="">-- Not identified --</option>
                    <?php foreach ($cropDiseases as $disease): ?>
                        <option value="<?= (int) $disease['disease_id'] ?>" <?= $diseaseId === (string) $disease['disease_id'] ? 'selected' : '' ?>>
                            <?= e($disease['disease_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$cropDiseases): ?>
                    <small class="muted">No diseases are recorded for <?= e($report['crop_name']) ?> yet.
                        <a href="/admin/diseases.php">Add one</a>.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="status">Report status</label>
                <select id="status" name="status" required>
                    <?php foreach (REPORT_STATUSES as $statusOption): ?>
                        <option value="<?= e($statusOption) ?>" <?= $newStatus === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="advice_text">Agricultural advice</label>
            <textarea id="advice_text" name="advice_text" maxlength="3000"><?= e($adviceText) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Save review</button>
    </form>
</div>

<div class="card">
    <h2>Other reports from this farmer</h2>
    <?php if (!$otherReports): ?>
        <p class="empty-state">No other reports.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Crop</th><th>Submitted</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($otherReports as $other): ?>
                    <tr>
                        <td><a href="/admin/review.php?id=<?= (int) $other['report_id'] ?>">#<?= (int) $other['report_id'] ?></a></td>
                        <td><?= e($other['crop_name']) ?></td>
                        <td><?= e(formatDate($other['report_date'])) ?></td>
                        <td><?= statusBadge($other['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
