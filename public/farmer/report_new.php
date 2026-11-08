<?php
// public/farmer/report_new.php - submit a crop problem report (with optional photo).
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('farmer');
$userId = currentUserId();

$errors       = [];
$selectedFarm = '';
$selectedCrop = '';
$symptoms     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $farmId       = postIdParam('farm_id');
    $farmCropId   = postIdParam('farm_crop_id');
    $symptoms     = postValue('symptoms');
    $selectedFarm = (string) $farmId;
    $selectedCrop = (string) $farmCropId;

    // 1) A valid farm that belongs to this farmer.
    $farm = $farmId !== null ? findFarmOfUser($pdo, $farmId, $userId) : null;
    if ($farm === null) {
        $errors[] = 'Please select a valid farm.';
    } elseif ($farmCropId === null) {
        $errors[] = 'Please select the crop with the problem.';
    } else {
        // 2) The crop record must belong to the SELECTED farm (and therefore to this farmer).
        $stmt = $pdo->prepare(
            'SELECT fc.farm_crop_id
             FROM farm_crops fc
             JOIN farms f ON f.farm_id = fc.farm_id
             WHERE fc.farm_crop_id = :farm_crop_id
               AND fc.farm_id = :farm_id
               AND f.user_id = :user_id'
        );
        $stmt->execute([
            'farm_crop_id' => $farmCropId,
            'farm_id'      => $farm['farm_id'],
            'user_id'      => $userId,
        ]);
        if (!$stmt->fetch()) {
            $errors[] = 'The selected crop does not belong to the selected farm.';
        }
    }

    // 3) Symptoms
    if ($symptoms === '') {
        $errors[] = 'Please describe the symptoms.';
    } elseif (mb_strlen($symptoms) < 10) {
        $errors[] = 'Please describe the symptoms in at least 10 characters.';
    } elseif (mb_strlen($symptoms) > 3000) {
        $errors[] = 'Symptoms must not be longer than 3000 characters.';
    }

    // 4) Save the photo only after every other check has passed (no orphan files).
    $imagePath = null;
    if (!$errors) {
        $upload = handleReportImageUpload($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        if ($upload['error'] !== null) {
            $errors[] = $upload['error'];
        } else {
            $imagePath = $upload['path'];
        }
    }

    // 5) Insert the report. The status always starts as Pending.
    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO reports (user_id, farm_crop_id, image_path, symptoms, status)
                 VALUES (:user_id, :farm_crop_id, :image_path, :symptoms, :status)'
            );
            $stmt->execute([
                'user_id'      => $userId,
                'farm_crop_id' => $farmCropId,
                'image_path'   => $imagePath,
                'symptoms'     => $symptoms,
                'status'       => 'Pending',
            ]);
            $newReportId = (int) $pdo->lastInsertId();

            setFlash('success', 'Your report was submitted successfully. Its status is Pending.');
            redirect('/farmer/report_view.php?id=' . $newReportId);
        } catch (PDOException $exception) {
            error_log('AgriCare submit report error: ' . $exception->getMessage());
            deleteUploadedImage($imagePath);   // do not keep a photo without a report
            $errors[] = 'Could not save your report. Please try again.';
        }
    }
}

// Dropdown data (only this farmer's farms and crop records)
$stmt = $pdo->prepare('SELECT farm_id, farm_name FROM farms WHERE user_id = :uid ORDER BY farm_name');
$stmt->execute(['uid' => $userId]);
$farms = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT fc.farm_crop_id, fc.farm_id, c.crop_name, fc.planting_date
     FROM farm_crops fc
     JOIN farms f ON f.farm_id = fc.farm_id
     JOIN crops c ON c.crop_id = fc.crop_id
     WHERE f.user_id = :uid
     ORDER BY c.crop_name'
);
$stmt->execute(['uid' => $userId]);
$farmCrops = $stmt->fetchAll();

$pageTitle  = 'Submit Report';
$activeNav  = 'report_new';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<h1>Submit a Crop Report</h1>

<?php if (!$farmCrops): ?>
    <div class="alert alert-info">
        Before submitting a report, add a <a href="/farmer/farms.php">farm</a>
        and record a <a href="/farmer/crops.php">crop</a> grown on it.
    </div>
<?php else: ?>
<div class="card">
    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $message): ?>
                <div><?= e($message) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/farmer/report_new.php" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="report_farm_id">Farm</label>
                <select id="report_farm_id" name="farm_id" required>
                    <option value="">-- Select farm --</option>
                    <?php foreach ($farms as $farm): ?>
                        <option value="<?= (int) $farm['farm_id'] ?>" <?= $selectedFarm === (string) $farm['farm_id'] ? 'selected' : '' ?>>
                            <?= e($farm['farm_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="report_farm_crop_id">Crop with the problem</label>
                <select id="report_farm_crop_id" name="farm_crop_id" required>
                    <option value="">-- Select crop --</option>
                    <?php foreach ($farmCrops as $farmCrop): ?>
                        <option value="<?= (int) $farmCrop['farm_crop_id'] ?>"
                                data-farm="<?= (int) $farmCrop['farm_id'] ?>"
                                <?= $selectedCrop === (string) $farmCrop['farm_crop_id'] ? 'selected' : '' ?>>
                            <?= e($farmCrop['crop_name']) ?> (planted <?= e(formatDate($farmCrop['planting_date'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="symptoms">Describe the symptoms</label>
            <textarea id="symptoms" name="symptoms" maxlength="3000" required
                      placeholder="What do you see? Which part of the plant, how many plants, since when?"><?= e($symptoms) ?></textarea>
        </div>

        <div class="form-group">
            <label for="image">Photograph (optional)</label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
            <small class="muted">JPG, PNG or WEBP, maximum 2 MB.</small>
        </div>

        <button type="submit" class="btn btn-primary">Submit report</button>
    </form>
</div>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
