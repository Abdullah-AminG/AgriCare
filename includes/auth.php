<?php
// includes/auth.php
// Session handling, authentication helpers and role-based access control (RBAC).

/**
 * True when the current request arrived over HTTPS.
 * (If the site is ever placed behind a reverse proxy, this check must be adapted.)
 */
function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    // The session ID may only travel in the cookie, never in the URL.
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    // Strict mode: PHP refuses a session ID that it did not create itself
    // (stops an attacker from "planting" a known ID in the victim's browser).
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,                    // cookie lasts until the browser is closed
        'path'     => '/',
        'httponly' => true,                 // JavaScript cannot read the session cookie
        'secure'   => isHttpsRequest(),     // Secure flag ON over HTTPS, OFF on plain-HTTP local development
        'samesite' => 'Lax',                // helps against cross-site request forgery
    ]);

    session_start();
}

// A logged-in user is logged out after 30 minutes without activity.
define('SESSION_TIMEOUT', 1800);

/**
 * Escape text before displaying it in HTML (prevents XSS).
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * The logged-in user's ID (0 if nobody is logged in).
 * Always use THIS value for ownership checks, never an ID from the URL or a form.
 */
function currentUserId(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

/**
 * The logged-in user's role ('farmer' or 'admin'), or '' if not logged in.
 */
function currentRole(): string
{
    return $_SESSION['role'] ?? '';
}

function dashboardUrlForRole(string $role): string
{
    if ($role === 'admin') {
        return '/admin/dashboard.php';
    }
    return '/farmer/dashboard.php';
}

/**
 * Save the user's identity in the session after a successful login.
 * $user is a row from the users table.
 */
function loginUser(array $user): void
{
    // A new session ID after login prevents session fixation.
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int) $user['user_id'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['last_activity'] = time();

    // The old CSRF token belonged to the anonymous session: create a fresh one.
    unset($_SESSION['csrf_token']);
}

/**
 * Completely destroy the session and its cookie.
 */
function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Require a logged-in user. Visitors are sent to the login page.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Require a specific role ('farmer' or 'admin').
 *   Not logged in -> redirect to login.
 *   Wrong role    -> HTTP 403 Forbidden.
 */
function requireRole(string $requiredRole): void
{
    requireLogin();

    if (currentRole() !== $requiredRole) {
        http_response_code(403);
        exit('403 Forbidden: You do not have permission to access this page.');
    }
}

/**
 * Used on the login and register pages:
 * a user who is already logged in goes straight to their dashboard.
 */
function redirectIfLoggedIn(): void
{
    if (isLoggedIn()) {
        header('Location: ' . dashboardUrlForRole(currentRole()));
        exit;
    }
}

// Idle-timeout check: runs on every request that includes this file.
if (isLoggedIn()) {
    $lastActivity = $_SESSION['last_activity'] ?? time();

    if (time() - $lastActivity > SESSION_TIMEOUT) {
        logoutUser();
        header('Location: /login.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}
