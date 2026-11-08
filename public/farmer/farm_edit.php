<?php
// public/farmer/farm_edit.php - edit one farm that belongs to the logged-in farmer.
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('farmer');
$userId = currentUserId();

// Validate the ID, then load the farm ONLY if it belongs to this farmer.
$farmId = getIdParam('id');
$farm   = $farmId !== null ? findFarmOfUser($pdo, $farmId, $userId) : null;

if ($farm === null) {
    notFound('Farm not found.');
}

$error    = '';
$farmName = $farm['farm_name'];
$location = $farm['location'];
$area     = (string) $farm['area'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $farmName = postValue('farm_name');
    $location = postValue('location');
    $area     = postValue('area');

    $error = validateFarmFields($farmName, $location, $area) ?? '';

    if ($error === '') {
        try {
            $stmt = $pdo->prepare(
                'UPDATE farms
                 SET farm_name = :farm_name, location = :location, area = :area
                 WHERE farm_id = :farm_id AND user_id = :user_id'
            );
            $stmt->execute([
                'farm_name' => $farmName,
                'location'  => $location,
                'area'      => (float) $area,
                'farm_id'   => $farm['farm_id'],
                'user_id'   => $userId,
            ]);

            setFlash('success', 'Farm updated successfully.');
            redirect('/farmer/farms.php');
        } catch (PDOException $exception) {
            error_log('AgriCare edit farm error: ' . $exception->getMessage());
            $error = 'Could not update the farm. Please try again.';
        }
    }
}

// Crops grown on this farm
$stmt = $pdo->prepare(
    'SELECT fc.farm_crop_id, c.crop_name, fc.planting_date
     FROM farm_crops fc
     JOIN crops c ON c.crop_id = fc.crop_id
     WHERE fc.farm_id = :farm_id
     ORDER BY fc.planting_date DESC'
);
$stmt->execute(['farm_id' => $farm['farm_id']]);
$farmCrops = $stmt->fetchAll();

$pageTitle  = 'Edit Farm';
$activeNav  = 'farms';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-header">
    <h1>Edit Farm</h1>
    <a class="btn btn-secondary" href="/farmer/farms.php">Back to My Farms</a>
</div>

<div class="card">
    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/farmer/farm_edit.php?id=<?= (int) $farm['farm_id'] ?>" novalidate>
        <?= csrfField() ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="farm_name">Farm name</label>
                <input type="text" id="farm_name" name="farm_name" maxlength="100" value="<?= e($farmName) ?>" required>
            </div>
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" maxlength="150" value="<?= e($location) ?>" required>
            </div>
            <div class="form-group">
                <label for="area">Area (acres)</label>
                <input type="number" id="area" name="area" step="0.01" min="0.01" value="<?= e($area) ?>" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save changes</button>
    </form>
</div>

<div class="card">
    <h2>Crops on this farm</h2>
    <?php if (!$farmCrops): ?>
        <p class="empty-state">No crops recorded for this farm. <a href="/farmer/crops.php">Add a crop</a>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Crop</th><th>Planting date</th></tr></thead>
                <tbody>
                <?php foreach ($farmCrops as $farmCrop): ?>
                    <tr>
                        <td><?= e($farmCrop['crop_name']) ?></td>
                        <td><?= e(formatDate($farmCrop['planting_date'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
