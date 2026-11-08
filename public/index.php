<?php
// public/index.php - home page.
require_once dirname(__DIR__) . '/includes/init.php';

// Real numbers from the database (not hard-coded).
$cropCount    = (int) $pdo->query('SELECT COUNT(*) FROM crops')->fetchColumn();
$diseaseCount = (int) $pdo->query('SELECT COUNT(*) FROM diseases')->fetchColumn();

$pageTitle = 'Home';
$activeNav = 'home';
$fullWidth = true;
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="hero">
    <div class="container hero-inner">
        <div class="hero-text">
            <h1>Healthier crops start with timely advice</h1>
            <p>AgriCare helps farmers report crop problems with symptoms and photographs, and receive practical guidance from agricultural advisers.</p>
            <div class="hero-actions">
                <?php if (isLoggedIn()): ?>
                    <a class="btn btn-light" href="<?= e(dashboardUrlForRole(currentRole())) ?>">Go to my dashboard</a>
                <?php else: ?>
                    <a class="btn btn-light" href="/register.php">Register as a farmer</a>
                    <a class="btn btn-outline-light" href="/login.php">Log in</a>
                <?php endif; ?>
                <a class="btn btn-outline-light" href="/guide.php">Browse crop guide</a>
            </div>
        </div>
        <div class="hero-stats">
            <div class="hero-stat"><strong><?= $cropCount ?></strong><span>Crops in the guide</span></div>
            <div class="hero-stat"><strong><?= $diseaseCount ?></strong><span>Diseases described</span></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2 class="section-title">What you can do with AgriCare</h2>
        <div class="feature-grid">
            <div class="feature-card">
                <h3>Manage farms and crops</h3>
                <p>Record your farms and the crops grown on each one in a structured, organised way.</p>
            </div>
            <div class="feature-card">
                <h3>Report crop problems</h3>
                <p>Describe the symptoms and attach a photograph so an adviser can understand the problem.</p>
            </div>
            <div class="feature-card">
                <h3>Get expert advice</h3>
                <p>An adviser reviews your report, suggests a possible disease and writes recommendations.</p>
            </div>
            <div class="feature-card">
                <h3>Track every case</h3>
                <p>Follow the status of each report and keep a complete history of problems and replies.</p>
            </div>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <h2 class="section-title">How it works</h2>
        <div class="steps">
            <div class="step"><span class="step-number">1</span><p>Create an account and add your farm and crops.</p></div>
            <div class="step"><span class="step-number">2</span><p>Submit a report with symptoms and an optional photo.</p></div>
            <div class="step"><span class="step-number">3</span><p>An adviser reviews it and replies with guidance.</p></div>
            <div class="step"><span class="step-number">4</span><p>Read the advice, add follow-up updates and keep the record.</p></div>
        </div>
    </div>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
