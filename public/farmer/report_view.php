<?php
// public/farmer/report_view.php - report details, adviser responses and follow-up notes.
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('farmer');
$userId = currentUserId();

$reportId = getIdParam('id');
if ($reportId === null) {
    notFound('Report not found.');
}

// The report is loaded ONLY if it belongs to the logged-in farmer.
$stmt = $pdo->prepare(
    'SELECT r.report_id, r.image_path, r.symptoms, r.report_date, r.status,
            f.farm_name, f.location, c.crop_name, fc.planting_date
     FROM reports r
     JOIN farm_crops fc ON fc.farm_crop_id = r.farm_crop_id
     JOIN farms f       ON f.farm_id = fc.farm_id
     JOIN crops c       ON c.crop_id = fc.crop_id
     WHERE r.report_id = :report_id AND r.user_id = :user_id'
);
$stmt->execute(['report_id' => $reportId, 'user_id' => $userId]);
$report = $stmt->fetch();

if (!$report) {
    notFound('Report not found.');
}

$noteError = '';
$noteText  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $noteText = postValue('update_note');

    if (mb_strlen($noteText) < 5) {
        $noteError = 'Please write at least 5 characters.';
    } elseif (mb_strlen($noteText) > 1500) {
        $noteError = 'The note must not be longer than 1500 characters.';
    } else {
        try {
            // New notes are added; old notes are never replaced.
            $stmt = $pdo->prepare('INSERT INTO report_updates (report_id, update_note) VALUES (:report_id, :note)');
            $stmt->execute(['report_id' => $report['report_id'], 'note' => $noteText]);

            setFlash('success', 'Your progress update was added.');
            redirect('/farmer/report_view.php?id=' . (int) $report['report_id']);
        } catch (PDOException $exception) {
            error_log('AgriCare add update error: ' . $exception->getMessage());
            $noteError = 'Could not save your update. Please try again.';
        }
    }
}

// Adviser responses (newest first)
$stmt = $pdo->prepare(
    'SELECT a.advice_text, a.response_date, u.full_name AS reviewer_name, d.disease_name
     FROM advice a
     JOIN users u ON u.user_id = a.reviewer_id
     LEFT JOIN diseases d ON d.disease_id = a.disease_id
     WHERE a.report_id = :report_id
     ORDER BY a.response_date DESC, a.advice_id DESC'
);
$stmt->execute(['report_id' => $report['report_id']]);
$adviceList = $stmt->fetchAll();

// Latest possible disease named by an adviser
$possibleDisease = null;
foreach ($adviceList as $advice) {
    if ($advice['disease_name'] !== null) {
        $possibleDisease = $advice['disease_name'];
        break;
    }
}

// Farmer progress notes (oldest first = progress history)
$stmt = $pdo->prepare(
    'SELECT update_note, update_date
     FROM report_updates
     WHERE report_id = :report_id
     ORDER BY update_date ASC, update_id ASC'
);
$stmt->execute(['report_id' => $report['report_id']]);
$updates = $stmt->fetchAll();

$pageTitle  = 'Report #' . $report['report_id'];
$activeNav  = 'reports';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-header">
    <h1>Report #<?= (int) $report['report_id'] ?> <?= statusBadge($report['status']) ?></h1>
    <a class="btn btn-secondary" href="/farmer/reports.php">Back to My Reports</a>
</div>

<div class="card">
    <h2>Report information</h2>
    <dl class="detail-list">
        <dt>Farm</dt><dd><?= e($report['farm_name']) ?> (<?= e($report['location']) ?>)</dd>
        <dt>Crop</dt><dd><?= e($report['crop_name']) ?> (planted <?= e(formatDate($report['planting_date'])) ?>)</dd>
        <dt>Submitted</dt><dd><?= e(formatDateTime($report['report_date'])) ?></dd>
        <dt>Status</dt><dd><?= statusBadge($report['status']) ?></dd>
        <dt>Possible disease</dt>
        <dd><?= $possibleDisease !== null ? e($possibleDisease) : '<span class="muted">Not identified yet</span>' ?></dd>
    </dl>

    <h3>Symptoms</h3>
    <p><?= nl2br(e($report['symptoms'])) ?></p>

    <?php if ($report['image_path'] !== null): ?>
        <h3>Photograph</h3>
        <img class="report-image" src="/<?= e($report['image_path']) ?>" alt="Photograph of the affected crop">
    <?php endif; ?>
</div>

<div class="card">
    <h2>Adviser recommendations</h2>
    <?php if (!$adviceList): ?>
        <p class="empty-state">No response yet. An adviser will review your report soon.</p>
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
    <h2>Your progress updates</h2>
    <?php if (!$updates): ?>
        <p class="empty-state">You have not added any updates yet.</p>
    <?php else: ?>
        <?php foreach ($updates as $update): ?>
            <div class="timeline-item">
                <div class="timeline-meta"><?= e(formatDateTime($update['update_date'])) ?></div>
                <div><?= nl2br(e($update['update_note'])) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3>Add a follow-up update</h3>
    <?php if ($noteError !== ''): ?>
        <div class="alert alert-error"><?= e($noteError) ?></div>
    <?php endif; ?>

    <form method="post" action="/farmer/report_view.php?id=<?= (int) $report['report_id'] ?>" novalidate>
        <?= csrfField() ?>
        <div class="form-group">
            <label for="update_note">How is the crop now? What did you do?</label>
            <textarea id="update_note" name="update_note" maxlength="1500" required><?= e($noteText) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Add update</button>
    </form>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
