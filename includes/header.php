<?php
// includes/header.php
// Variables a page sets BEFORE including this file:
//   $pageTitle  - text for <title>
//   $activeNav  - key of the current navigation item
//   $useSidebar - true for farmer/admin dashboard pages
//   $fullWidth  - true when the page draws its own full-width sections (home page)
$pageTitle  = $pageTitle  ?? APP_NAME;
$activeNav  = $activeNav  ?? '';
$useSidebar = $useSidebar ?? false;
$fullWidth  = $fullWidth  ?? false;
$flash      = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="/index.php"><span class="brand-mark">A</span> AgriCare</a>

        <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle menu">&#9776;</button>

        <nav class="main-nav" id="mainNav">
            <a class="<?= activeClass('home', $activeNav) ?>" href="/index.php">Home</a>
            <a class="<?= activeClass('about', $activeNav) ?>" href="/about.php">About</a>
            <a class="<?= activeClass('guide', $activeNav) ?>" href="/guide.php">Crop Guide</a>

            <?php if (isLoggedIn()): ?>
                <a class="<?= activeClass('dashboard', $activeNav) ?>" href="<?= e(dashboardUrlForRole(currentRole())) ?>">Dashboard</a>
                <a class="<?= activeClass('account', $activeNav) ?>" href="/account.php">Account</a>
                <form class="logout-form" method="post" action="/logout.php">
                    <?= csrfField() ?>
                    <button type="submit" class="nav-button">Logout</button>
                </form>
            <?php else: ?>
                <a class="<?= activeClass('login', $activeNav) ?>" href="/login.php">Login</a>
                <a class="nav-button" href="/register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php if ($useSidebar): ?>
<div class="dashboard-layout">
    <?php
    if (currentRole() === 'admin') {
        include __DIR__ . '/sidebar_admin.php';
    } else {
        include __DIR__ . '/sidebar_farmer.php';
    }
    ?>
    <main class="dashboard-main">
<?php else: ?>
    <main class="<?= $fullWidth ? '' : 'container page-main' ?>">
<?php endif; ?>

<?php if ($flash !== null): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
