<?php
// public/logout.php - destroys the session completely.
// Logout must be a POST request with a valid CSRF token. A plain link (GET) must not
// log anybody out, otherwise another website could log users out by embedding
// <img src="https://agricare/logout.php">.
require_once dirname(__DIR__) . '/includes/init.php';

if (!isLoggedIn()) {
    redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Someone opened /logout.php directly: just send them back to their dashboard.
    redirect(dashboardUrlForRole(currentRole()));
}

requireCsrf();
logoutUser();
redirect('/login.php?loggedout=1');
