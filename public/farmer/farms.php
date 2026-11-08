<?php
// public/farmer/farms.php - list, add and delete the logged-in farmer's farms.
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('farmer');
$userId = currentUserId();

$error    = '';
$farmName = '';
$location = '';
$area     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = postValue('action');

    if ($action === 'add') {
        $farmName = postValue('farm_name');
        $location = postValue('location');
        $area     = postValue('area');

        $error = validateFarmFields($farmName, $location, $area) ?? '';

        if ($error === '') {
            try {
                // user_id comes from the session, never from the form.
                $stmt = $pdo->prepare(
                    'INSERT INTO farms (user_id, farm_name, location, area)
                     VALUES (:user_id, :farm_name, :location, :area)'
                );
                $stmt->execute([
                    'user_id'   => $userId,
                    'farm_name' => $farmName,
                    'location'  => $location,
                    'area'      => (float) $area,
                ]);

                setFlash('success', 'Farm added successfully.');
                redirect('/farmer/farms.php');
            } catch (PDOException $exception) {
                error_log('AgriCare add farm error: ' . $exception->getMessage());
                $error = 'Could not add the farm. Please try again.';
            }
        }

    } elseif ($action === 'delete') {
        $farmId = postIdParam('farm_id');
        $farm   = $farmId !== null ? findFarmOfUser($pdo, $farmId, $userId) : null;

        if ($farm === null) {
            setFlash('error', 'Farm not found.');
        } else {
            try {
                // A farm that already has reports cannot be deleted (keeps report history).
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM reports r
                     JOIN farm_crops fc ON fc.farm_crop_id = r.farm_crop_id
                     WHERE fc.farm_id = :farm_id'
                );
                $stmt->execute(['farm_id' => $farm['farm_id']]);

                if ((int) $stmt->fetchColumn() > 0) {
                    setFlash('error', 'This farm has crop reports, so it cannot be deleted. Report history is kept.');
                } else {
                    $stmt = $pdo->prepare('DELETE FROM farms WHERE farm_id = :farm_id AND user_id = :user_id');
                    $stmt->execute(['farm_id' => $farm['farm_id'], 'user_id' => $userId]);
                    setFlash('success', 'Farm deleted.');
                }
            } catch (PDOException $exception) {
                error_log('AgriCare delete farm error: ' . $exception->getMessage());
                setFlash('error', 'Could not delete the farm.');
            }
        }
        redirect('/farmer/farms.php');
    }
}

// All farms of this farmer with the crops grown on each one.
$stmt = $pdo->prepare(
    'SELECT f.farm_id, f.farm_name, f.location, f.area,
            COUNT(fc.farm_crop_id) AS crop_count,
            GROUP_CONCAT(c.crop_name ORDER BY c.crop_name SEPARATOR ", ") AS crop_list
     FROM farms f
     LEFT JOIN farm_crops fc ON fc.farm_id = f.farm_id
     LEFT JOIN crops c       ON c.crop_id = fc.crop_id
     WHERE f.user_id = :uid
     GROUP BY f.farm_id, f.farm_name, f.location, f.area
     ORDER BY f.farm_name'
);
$stmt->execute(['uid' => $userId]);
$farms = $stmt->fetchAll();

$pageTitle  = 'My Farms';
$activeNav  = 'farms';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<h1>My Farms</h1>

<div class="card">
    <h2>Add a farm</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/farmer/farms.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">

        <div class="form-grid">
            <div class="form-group">
                <label for="farm_name">Farm name</label>
                <input type="text" id="farm_name" name="farm_name" maxlength="100" value="<?= e($farmName) ?>" required>
            </div>
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" maxlength="150" value="<?= e($location) ?>" placeholder="Village / city, district" required>
            </div>
            <div class="form-group">
                <label for="area">Area (acres)</label>
                <input type="number" id="area" name="area" step="0.01" min="0.01" value="<?= e($area) ?>" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Add farm</button>
    </form>
</div>

<div class="card">
    <h2>Your farms</h2>

    <?php if (!$farms): ?>
        <p class="empty-state">You have not added any farms yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Farm</th><th>Location</th><th>Area (acres)</th><th>Crops</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($farms as $farm): ?>
                    <tr>
                        <td><?= e($farm['farm_name']) ?></td>
                        <td><?= e($farm['location']) ?></td>
                        <td><?= e(number_format((float) $farm['area'], 2)) ?></td>
                        <td><?= $farm['crop_list'] !== null ? e($farm['crop_list']) : '<span class="muted">No crops yet</span>' ?></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-secondary btn-sm" href="/farmer/farm_edit.php?id=<?= (int) $farm['farm_id'] ?>">Edit</a>
                                <form class="inline-form" method="post" action="/farmer/farms.php"
                                      data-confirm="Delete this farm and its crop records? This cannot be undone.">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="farm_id" value="<?= (int) $farm['farm_id'] ?>">
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
