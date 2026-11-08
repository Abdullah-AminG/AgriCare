<?php
// public/guide.php - public Crop and Disease Guide (data comes from MySQL).
require_once dirname(__DIR__) . '/includes/init.php';

$search     = getValue('q');
$cropFilter = getIdParam('crop');

$allCrops = $pdo->query('SELECT crop_id, crop_name FROM crops ORDER BY crop_name')->fetchAll();

// Build the crop query from the filters (values are bound as parameters).
$conditions = [];
$params     = [];

if ($cropFilter !== null) {
    $conditions[] = 'c.crop_id = :crop_id';
    $params['crop_id'] = $cropFilter;
}
if ($search !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    $conditions[] = '(c.crop_name LIKE :q1 OR c.description LIKE :q2
                      OR EXISTS (SELECT 1 FROM diseases d
                                 WHERE d.crop_id = c.crop_id
                                   AND (d.disease_name LIKE :q3 OR d.symptoms LIKE :q4)))';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}

$sql = 'SELECT c.crop_id, c.crop_name, c.description FROM crops c';
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY c.crop_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$crops = $stmt->fetchAll();

// All diseases, grouped by crop in PHP.
$diseasesByCrop = [];
$diseaseRows = $pdo->query(
    'SELECT disease_id, crop_id, disease_name, symptoms, prevention FROM diseases ORDER BY disease_name'
)->fetchAll();
foreach ($diseaseRows as $row) {
    $diseasesByCrop[$row['crop_id']][] = $row;
}

$pageTitle = 'Crop and Disease Guide';
$activeNav = 'guide';
require dirname(__DIR__) . '/includes/header.php';
?>

<h1>Crop and Disease Guide</h1>
<p class="muted">Basic information about crops, common diseases, symptoms and prevention. For a problem in your own field, submit a report and an adviser will help.</p>

<div class="card">
    <form method="get" action="/guide.php" class="filter-bar">
        <div class="form-group">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="Crop, disease or symptom">
        </div>
        <div class="form-group">
            <label for="crop">Crop</label>
            <select id="crop" name="crop">
                <option value="">All crops</option>
                <?php foreach ($allCrops as $crop): ?>
                    <option value="<?= (int) $crop['crop_id'] ?>" <?= $cropFilter === (int) $crop['crop_id'] ? 'selected' : '' ?>><?= e($crop['crop_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Search</button>
            <a class="btn btn-secondary" href="/guide.php">Clear</a>
        </div>
    </form>
</div>

<?php if (!$crops): ?>
    <div class="card"><p class="empty-state">No crops or diseases match your search.</p></div>
<?php endif; ?>

<?php foreach ($crops as $crop): ?>
    <?php
    $diseases = $diseasesByCrop[$crop['crop_id']] ?? [];

    // When the search matched only a disease (not the crop itself), show just the matching diseases.
    if ($search !== ''
        && mb_stripos($crop['crop_name'], $search) === false
        && mb_stripos($crop['description'], $search) === false) {
        $diseases = array_filter($diseases, function ($d) use ($search) {
            return mb_stripos($d['disease_name'], $search) !== false
                || mb_stripos($d['symptoms'], $search) !== false;
        });
    }
    ?>
    <div class="card">
        <h2><?= e($crop['crop_name']) ?></h2>
        <p><?= e($crop['description']) ?></p>

        <?php if (!$diseases): ?>
            <p class="muted">No diseases recorded for this crop yet.</p>
        <?php else: ?>
            <?php foreach ($diseases as $disease): ?>
                <details class="disease-item">
                    <summary><?= e($disease['disease_name']) ?></summary>
                    <p><strong>Symptoms:</strong> <?= nl2br(e($disease['symptoms'])) ?></p>
                    <p><strong>Prevention:</strong> <?= nl2br(e($disease['prevention'])) ?></p>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
