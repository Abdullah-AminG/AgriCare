<?php
// public/admin/diseases.php - add, view and edit disease information.
require_once dirname(__DIR__, 2) . '/includes/init.php';

requireRole('admin');

$error        = '';
$formDiseaseId = null;
$formCropId   = '';
$diseaseName  = '';
$symptoms     = '';
$prevention   = '';

$crops = $pdo->query('SELECT crop_id, crop_name FROM crops ORDER BY crop_name')->fetchAll();

// ?edit=ID loads an existing disease into the form.
$editId = getIdParam('edit');
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT disease_id, crop_id, disease_name, symptoms, prevention FROM diseases WHERE disease_id = :id');
    $stmt->execute(['id' => $editId]);
    $disease = $stmt->fetch();

    if (!$disease) {
        notFound('Disease not found.');
    }
    $formDiseaseId = (int) $disease['disease_id'];
    $formCropId    = (string) $disease['crop_id'];
    $diseaseName   = $disease['disease_name'];
    $symptoms      = $disease['symptoms'];
    $prevention    = $disease['prevention'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $formDiseaseId = postIdParam('disease_id');    // null means "add"
    $cropIdPosted  = postIdParam('crop_id');
    $formCropId    = (string) $cropIdPosted;
    $diseaseName   = postValue('disease_name');
    $symptoms      = postValue('symptoms');
    $prevention    = postValue('prevention');

    // When EDITING: load the saved disease and count how many advice records already use it.
    // (An adviser may have told a Wheat farmer "Yellow Rust"; that record must stay consistent.)
    $existingDisease = null;
    if ($formDiseaseId !== null) {
        $stmt = $pdo->prepare(
            'SELECT d.crop_id,
                    (SELECT COUNT(*) FROM advice a WHERE a.disease_id = d.disease_id) AS advice_count
             FROM diseases d
             WHERE d.disease_id = :id'
        );
        $stmt->execute(['id' => $formDiseaseId]);
        $existingDisease = $stmt->fetch() ?: null;
    }

    // The crop must exist in the master crop list.
    $cropExists = false;
    if ($cropIdPosted !== null) {
        $stmt = $pdo->prepare('SELECT crop_id FROM crops WHERE crop_id = :id');
        $stmt->execute(['id' => $cropIdPosted]);
        $cropExists = (bool) $stmt->fetch();
    }

    if (!$cropExists) {
        $error = 'Please select a valid crop.';
    } elseif (mb_strlen($diseaseName) < 2 || mb_strlen($diseaseName) > 150) {
        $error = 'Disease name must be between 2 and 150 characters.';
    } elseif ($symptoms === '' || mb_strlen($symptoms) > 2000) {
        $error = 'Symptoms are required and must not be longer than 2000 characters.';
    } elseif ($prevention === '' || mb_strlen($prevention) > 2000) {
        $error = 'Prevention information is required and must not be longer than 2000 characters.';
    } elseif ($formDiseaseId !== null && $existingDisease === null) {
        $error = 'Disease not found.';
    } elseif ($existingDisease !== null
              && (int) $existingDisease['crop_id'] !== $cropIdPosted
              && (int) $existingDisease['advice_count'] > 0) {
        // Moving the disease to another crop would leave old advice pointing at the wrong crop.
        $error = 'This disease is already used in ' . (int) $existingDisease['advice_count']
               . ' advice record(s), so it cannot be moved to a different crop. '
               . 'You can still edit its name, symptoms and prevention, or add a new disease for the other crop.';
    } else {
        try {
            if ($formDiseaseId !== null) {
                $stmt = $pdo->prepare(
                    'UPDATE diseases
                     SET crop_id = :crop_id, disease_name = :name, symptoms = :symptoms, prevention = :prevention
                     WHERE disease_id = :id'
                );
                $stmt->execute([
                    'crop_id' => $cropIdPosted, 'name' => $diseaseName,
                    'symptoms' => $symptoms, 'prevention' => $prevention, 'id' => $formDiseaseId,
                ]);
                setFlash('success', 'Disease updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO diseases (crop_id, disease_name, symptoms, prevention)
                     VALUES (:crop_id, :name, :symptoms, :prevention)'
                );
                $stmt->execute([
                    'crop_id' => $cropIdPosted, 'name' => $diseaseName,
                    'symptoms' => $symptoms, 'prevention' => $prevention,
                ]);
                setFlash('success', 'Disease added.');
            }
            redirect('/admin/diseases.php');
        } catch (PDOException $exception) {
            error_log('AgriCare disease save error: ' . $exception->getMessage());
            $error = 'Could not save the disease. Please try again.';
        }
    }
}

// Optional crop filter for the list
$filterCrop = getIdParam('crop');

$sql = 'SELECT d.disease_id, d.disease_name, d.symptoms, d.prevention, c.crop_name
        FROM diseases d
        JOIN crops c ON c.crop_id = d.crop_id';
$params = [];
if ($filterCrop !== null) {
    $sql .= ' WHERE d.crop_id = :crop_id';
    $params['crop_id'] = $filterCrop;
}
$sql .= ' ORDER BY c.crop_name, d.disease_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$diseases = $stmt->fetchAll();

$pageTitle  = 'Disease Management';
$activeNav  = 'diseases';
$useSidebar = true;
require dirname(__DIR__, 2) . '/includes/header.php';
?>

<h1>Disease Management</h1>

<div class="card">
    <h2><?= $formDiseaseId !== null ? 'Edit disease' : 'Add a disease' ?></h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/diseases.php" novalidate>
        <?= csrfField() ?>
        <?php if ($formDiseaseId !== null): ?>
            <input type="hidden" name="disease_id" value="<?= (int) $formDiseaseId ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="crop_id">Crop</label>
                <select id="crop_id" name="crop_id" required>
                    <option value="">-- Select crop --</option>
                    <?php foreach ($crops as $crop): ?>
                        <option value="<?= (int) $crop['crop_id'] ?>" <?= $formCropId === (string) $crop['crop_id'] ? 'selected' : '' ?>>
                            <?= e($crop['crop_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="disease_name">Disease name</label>
                <input type="text" id="disease_name" name="disease_name" maxlength="150" value="<?= e($diseaseName) ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="symptoms">Symptoms</label>
            <textarea id="symptoms" name="symptoms" maxlength="2000" required><?= e($symptoms) ?></textarea>
        </div>
        <div class="form-group">
            <label for="prevention">Prevention</label>
            <textarea id="prevention" name="prevention" maxlength="2000" required><?= e($prevention) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary"><?= $formDiseaseId !== null ? 'Update disease' : 'Add disease' ?></button>
        <?php if ($formDiseaseId !== null): ?>
            <a class="btn btn-secondary" href="/admin/diseases.php">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="page-header">
        <h2>All diseases</h2>
        <form method="get" action="/admin/diseases.php" class="actions">
            <select name="crop" aria-label="Filter by crop">
                <option value="">All crops</option>
                <?php foreach ($crops as $crop): ?>
                    <option value="<?= (int) $crop['crop_id'] ?>" <?= $filterCrop === (int) $crop['crop_id'] ? 'selected' : '' ?>><?= e($crop['crop_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        </form>
    </div>

    <?php if (!$diseases): ?>
        <p class="empty-state">No diseases found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Crop</th><th>Disease</th><th>Symptoms</th><th>Prevention</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($diseases as $disease): ?>
                    <tr>
                        <td><?= e($disease['crop_name']) ?></td>
                        <td><?= e($disease['disease_name']) ?></td>
                        <td><?= e(shortText($disease['symptoms'], 90)) ?></td>
                        <td><?= e(shortText($disease['prevention'], 90)) ?></td>
                        <td><a class="btn btn-secondary btn-sm" href="/admin/diseases.php?edit=<?= (int) $disease['disease_id'] ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
