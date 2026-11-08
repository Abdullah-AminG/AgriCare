<?php
// public/admin/crops.php - add, view and edit the master crop list.
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('admin');

$error       = '';
$formCropId  = null;
$cropName    = '';
$description = '';

// ?edit=ID loads an existing crop into the form.
$editId = getIdParam('edit');
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT crop_id, crop_name, description FROM crops WHERE crop_id = :id');
    $stmt->execute(['id' => $editId]);
    $crop = $stmt->fetch();

    if (!$crop) {
        notFound('Crop not found.');
    }
    $formCropId  = (int) $crop['crop_id'];
    $cropName    = $crop['crop_name'];
    $description = $crop['description'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $formCropId  = postIdParam('crop_id');     // null means "add a new crop"
    $cropName    = postValue('crop_name');
    $description = postValue('description');

    if (mb_strlen($cropName) < 2 || mb_strlen($cropName) > 100) {
        $error = 'Crop name must be between 2 and 100 characters.';
    } elseif ($description === '' || mb_strlen($description) > 1000) {
        $error = 'Description is required and must not be longer than 1000 characters.';
    } else {
        try {
            if ($formCropId !== null) {
                $stmt = $pdo->prepare('UPDATE crops SET crop_name = :name, description = :description WHERE crop_id = :id');
                $stmt->execute(['name' => $cropName, 'description' => $description, 'id' => $formCropId]);
                setFlash('success', 'Crop updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO crops (crop_name, description) VALUES (:name, :description)');
                $stmt->execute(['name' => $cropName, 'description' => $description]);
                setFlash('success', 'Crop added.');
            }
            redirect('/admin/crops.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'A crop with this name already exists.';     // UNIQUE constraint
            } else {
                error_log('AgriCare crop save error: ' . $exception->getMessage());
                $error = 'Could not save the crop. Please try again.';
            }
        }
    }
}

$crops = $pdo->query(
    'SELECT c.crop_id, c.crop_name, c.description,
            (SELECT COUNT(*) FROM diseases d WHERE d.crop_id = c.crop_id)    AS disease_count,
            (SELECT COUNT(*) FROM farm_crops fc WHERE fc.crop_id = c.crop_id) AS farm_count
     FROM crops c
     ORDER BY c.crop_name'
)->fetchAll();

$pageTitle  = 'Crop Management';
$activeNav  = 'crops';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<h1>Crop Management</h1>

<div class="card">
    <h2><?= $formCropId !== null ? 'Edit crop' : 'Add a crop' ?></h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/crops.php" novalidate>
        <?= csrfField() ?>
        <?php if ($formCropId !== null): ?>
            <input type="hidden" name="crop_id" value="<?= (int) $formCropId ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="crop_name">Crop name</label>
            <input type="text" id="crop_name" name="crop_name" maxlength="100" value="<?= e($cropName) ?>" required>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" maxlength="1000" required><?= e($description) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary"><?= $formCropId !== null ? 'Update crop' : 'Add crop' ?></button>
        <?php if ($formCropId !== null): ?>
            <a class="btn btn-secondary" href="/admin/crops.php">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>All crops</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Crop</th><th>Description</th><th>Diseases</th><th>Used on farms</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($crops as $crop): ?>
                <tr>
                    <td><?= e($crop['crop_name']) ?></td>
                    <td><?= e(shortText($crop['description'], 100)) ?></td>
                    <td><?= (int) $crop['disease_count'] ?></td>
                    <td><?= (int) $crop['farm_count'] ?></td>
                    <td><a class="btn btn-secondary btn-sm" href="/admin/crops.php?edit=<?= (int) $crop['crop_id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
