<?php
// public/about.php - about the project.
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'About';
$activeNav = 'about';
require dirname(__DIR__) . '/includes/header.php';
?>

<h1>About AgriCare</h1>

<div class="card">
    <h2>The problem</h2>
    <p>Farmers often struggle to identify crop diseases, find reliable advice on time and keep records of the problems affecting their fields. These difficulties can delay treatment and lead to crop damage or reduced production.</p>
    <h2>Our solution</h2>
    <p>AgriCare is a web-based Crop Health and Advisory System. Farmers register, manage their farms and crops, and submit reports with symptoms and photographs. An administrator or agricultural adviser reviews each report, provides recommendations and updates its status.</p>
    <p><strong>Important:</strong> AgriCare does not diagnose diseases automatically. Every disease identification and recommendation is entered by an authorised human reviewer.</p>
</div>

<div class="two-column">
    <div class="card">
        <h2>Objectives</h2>
        <ul>
            <li>Give farmers an easy way to report crop-related problems.</li>
            <li>Keep farmer, farm, crop and report information in a structured database.</li>
            <li>Let an adviser review reports and share guidance.</li>
            <li>Keep previous reports so farmers can track issues and responses.</li>
            <li>Make basic crop-health information easy to find and understand.</li>
        </ul>
    </div>
    <div class="card">
        <h2>User roles</h2>
        <p><strong>Farmer:</strong> manages farms and crops, submits reports, checks status and reads advice.</p>
        <p><strong>Admin / Adviser:</strong> reviews reports, gives advice, changes report status and maintains crop and disease information.</p>
    </div>
</div>

<div class="card">
    <h2>Report statuses</h2>
    <p><?= statusBadge('Pending') ?> The report is waiting to be reviewed.</p>
    <p><?= statusBadge('Under Review') ?> An adviser is checking the report.</p>
    <p><?= statusBadge('Resolved') ?> Advice has been provided and the case is closed.</p>
</div>

<div class="card">
    <h2>Project information</h2>
    <p>Web Technologies project, Department of Computer Science, University of the Punjab, Lahore.</p>
    <p>Team: Muhammad Rayyan (BCSF24A020) and Muhammad Abdullah Amin (BCSF24A037). Instructor: Sir Numan Shafi.</p>
    <p>Built with Linux, Apache, MySQL, PHP, HTML, CSS and JavaScript.</p>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
