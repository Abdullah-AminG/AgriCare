<?php
// public/farmer/crop_edit.php - edit the planting date of one crop record.
// Only the planting date can be changed. The farm and the crop type stay fixed because
// crop problem reports point to this record: changing "Wheat" into "Rice" would silently
// change the meaning of old reports. To record a different crop, add a new crop record.
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('farmer');
$userId = currentUserId();

// Validate the ID, then load the record ONLY if its farm belongs to this farmer.
$farmCropId = getIdParam('id');
$record     = $farmCropId !== null ? findFarmCropOfUser($pdo, $farmCropId, $userId) : null;

if ($record === null) {
    notFound('Crop record not found.');
}

$error        = '';
$plantingDate = $record['planting_date'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $plantingDate = postValue('planting_date');
    $error        = validatePlantingDate($plantingDate) ?? '';

    if ($error === '' && farmCropExists($pdo, (int) $record['farm_id'], (int) $record['crop_id'], $plantingDate, (int) $record['farm_crop_id'])) {
        $error = 'This crop with the same planting date is already recorded for this farm.';
    }

    if ($error === '') {
        try {
            // The JOIN with farms repeats the ownership check inside the UPDATE itself.
            $stmt = $pdo->prepare(
                'UPDATE farm_crops fc
                 JOIN farms f ON f.farm_id = fc.farm_id
                 SET fc.planting_date = :planting_date
                 WHERE fc.farm_crop_id = :id AND f.user_id = :user_id'
            );
            $stmt->execute([
                'planting_date' => $plantingDate,
                'id'            => $record['farm_crop_id'],
                'user_id'       => $userId,
            ]);

            setFlash('success', 'Planting date updated successfully.');
            redirect('/farmer/crops.php');
        } catch (PDOException $exception) {
            error_log('AgriCare edit farm crop error: ' . $exception->getMessage());
            $error = 'Could not update the crop record. Please try again.';
        }
    }
}

$pageTitle  = 'Edit Crop Record';
$activeNav  = 'crops';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-header">
    <h1>Edit Crop Record</h1>
    <a class="btn btn-secondary" href="/farmer/crops.php">Back to My Crops</a>
</div>

<div class="card">
    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <dl class="detail-list">
        <dt>Farm</dt><dd><?= e($record['farm_name']) ?></dd>
        <dt>Crop</dt><dd><?= e($record['crop_name']) ?></dd>
    </dl>

    <form method="post" action="/farmer/crop_edit.php?id=<?= (int) $record['farm_crop_id'] ?>" novalidate>
        <?= csrfField() ?>

        <div class="form-group">
            <label for="planting_date">Planting date</label>
            <input type="date" id="planting_date" name="planting_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($plantingDate) ?>" required>
            <small class="muted">The farm and crop cannot be changed because reports may refer to this record.</small>
        </div>

        <button type="submit" class="btn btn-primary">Save changes</button>
    </form>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
