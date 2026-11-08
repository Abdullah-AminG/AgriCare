<?php
// public/farmer/crops.php - crops grown on the farmer's farms (farm_crops table).
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('farmer');
$userId = currentUserId();

$error        = '';
$selectedFarm = '';
$selectedCrop = '';
$plantingDate = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = postValue('action');

    if ($action === 'add') {
        $farmId       = postIdParam('farm_id');
        $cropId       = postIdParam('crop_id');
        $plantingDate = postValue('planting_date');
        $selectedFarm = (string) $farmId;
        $selectedCrop = (string) $cropId;
        $dateError    = validatePlantingDate($plantingDate);   // null = date is fine

        // 1) The farm must exist AND belong to the logged-in farmer.
        $farm = $farmId !== null ? findFarmOfUser($pdo, $farmId, $userId) : null;

        if ($farm === null) {
            $error = 'Please select one of your farms.';
        } elseif ($cropId === null) {
            $error = 'Please select a crop.';
        } elseif ($dateError !== null) {
            $error = $dateError;
        } else {
            // 2) The crop must exist in the master crop list.
            $stmt = $pdo->prepare('SELECT crop_id FROM crops WHERE crop_id = :crop_id');
            $stmt->execute(['crop_id' => $cropId]);

            if (!$stmt->fetch()) {
                $error = 'The selected crop does not exist.';
            } elseif (farmCropExists($pdo, (int) $farm['farm_id'], $cropId, $plantingDate)) {
                // 3) The same crop with the same planting date on the same farm is a duplicate.
                $error = 'This crop with the same planting date is already recorded for this farm.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO farm_crops (farm_id, crop_id, planting_date)
                         VALUES (:farm_id, :crop_id, :planting_date)'
                    );
                    $stmt->execute([
                        'farm_id'       => $farm['farm_id'],
                        'crop_id'       => $cropId,
                        'planting_date' => $plantingDate,
                    ]);

                    setFlash('success', 'Crop record saved.');
                    redirect('/farmer/crops.php');
                } catch (PDOException $exception) {
                    error_log('AgriCare add farm crop error: ' . $exception->getMessage());
                    $error = 'Could not save the crop record. Please try again.';
                }
            }
        }

    } elseif ($action === 'delete') {
        $farmCropId = postIdParam('farm_crop_id');

        // Load the record only if its farm belongs to this farmer.
        $record = false;
        if ($farmCropId !== null) {
            $stmt = $pdo->prepare(
                'SELECT fc.farm_crop_id
                 FROM farm_crops fc
                 JOIN farms f ON f.farm_id = fc.farm_id
                 WHERE fc.farm_crop_id = :id AND f.user_id = :uid'
            );
            $stmt->execute(['id' => $farmCropId, 'uid' => $userId]);
            $record = $stmt->fetch();
        }

        if (!$record) {
            setFlash('error', 'Crop record not found.');
        } else {
            try {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM reports WHERE farm_crop_id = :id');
                $stmt->execute(['id' => $farmCropId]);

                if ((int) $stmt->fetchColumn() > 0) {
                    setFlash('error', 'This crop record has reports, so it cannot be deleted. Report history is kept.');
                } else {
                    $stmt = $pdo->prepare('DELETE FROM farm_crops WHERE farm_crop_id = :id');
                    $stmt->execute(['id' => $farmCropId]);
                    setFlash('success', 'Crop record deleted.');
                }
            } catch (PDOException $exception) {
                error_log('AgriCare delete farm crop error: ' . $exception->getMessage());
                setFlash('error', 'Could not delete the crop record.');
            }
        }
        redirect('/farmer/crops.php');
    }
}

// Farms for the dropdown
$stmt = $pdo->prepare('SELECT farm_id, farm_name FROM farms WHERE user_id = :uid ORDER BY farm_name');
$stmt->execute(['uid' => $userId]);
$farms = $stmt->fetchAll();

// Master crop list for the dropdown
$crops = $pdo->query('SELECT crop_id, crop_name FROM crops ORDER BY crop_name')->fetchAll();

// Only crops belonging to this farmer's farms
$stmt = $pdo->prepare(
    'SELECT fc.farm_crop_id, f.farm_name, c.crop_name, fc.planting_date,
            COUNT(r.report_id) AS report_count
     FROM farm_crops fc
     JOIN farms f ON f.farm_id = fc.farm_id
     JOIN crops c ON c.crop_id = fc.crop_id
     LEFT JOIN reports r ON r.farm_crop_id = fc.farm_crop_id
     WHERE f.user_id = :uid
     GROUP BY fc.farm_crop_id, f.farm_name, c.crop_name, fc.planting_date
     ORDER BY f.farm_name, c.crop_name'
);
$stmt->execute(['uid' => $userId]);
$farmCrops = $stmt->fetchAll();

$pageTitle  = 'My Crops';
$activeNav  = 'crops';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<h1>My Crops</h1>

<div class="card">
    <h2>Add a crop to a farm</h2>

    <?php if (!$farms): ?>
        <div class="alert alert-info">You need to <a href="/farmer/farms.php">add a farm</a> before you can record crops.</div>
    <?php else: ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/farmer/crops.php" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">

            <div class="form-grid">
                <div class="form-group">
                    <label for="farm_id">Farm</label>
                    <select id="farm_id" name="farm_id" required>
                        <option value="">-- Select farm --</option>
                        <?php foreach ($farms as $farm): ?>
                            <option value="<?= (int) $farm['farm_id'] ?>" <?= $selectedFarm === (string) $farm['farm_id'] ? 'selected' : '' ?>>
                                <?= e($farm['farm_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="crop_id">Crop</label>
                    <select id="crop_id" name="crop_id" required>
                        <option value="">-- Select crop --</option>
                        <?php foreach ($crops as $crop): ?>
                            <option value="<?= (int) $crop['crop_id'] ?>" <?= $selectedCrop === (string) $crop['crop_id'] ? 'selected' : '' ?>>
                                <?= e($crop['crop_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="planting_date">Planting date</label>
                    <input type="date" id="planting_date" name="planting_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($plantingDate) ?>" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save crop record</button>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Your crop records</h2>

    <?php if (!$farmCrops): ?>
        <p class="empty-state">No crop records yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Farm</th><th>Crop</th><th>Planting date</th><th>Reports</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($farmCrops as $farmCrop): ?>
                    <tr>
                        <td><?= e($farmCrop['farm_name']) ?></td>
                        <td><?= e($farmCrop['crop_name']) ?></td>
                        <td><?= e(formatDate($farmCrop['planting_date'])) ?></td>
                        <td><?= (int) $farmCrop['report_count'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-secondary btn-sm" href="/farmer/crop_edit.php?id=<?= (int) $farmCrop['farm_crop_id'] ?>">Edit</a>
                                <form class="inline-form" method="post" action="/farmer/crops.php"
                                      data-confirm="Delete this crop record? This cannot be undone.">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="farm_crop_id" value="<?= (int) $farmCrop['farm_crop_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
