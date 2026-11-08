<?php
// includes/csrf.php
// CSRF protection: every POST form carries a secret token that is also stored
// in the session. The server compares the two before changing any data.

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        // random_bytes() is cryptographically secure.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden input placed inside every POST form:  <?= csrfField() ?>
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrfToken(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';

    // hash_equals() compares strings in a timing-safe way.
    return is_string($submitted) && $stored !== '' && hash_equals($stored, $submitted);
}

/**
 * Call at the start of POST handling. A missing or wrong token stops the request.
 */
function requireCsrf(): void
{
    if (!verifyCsrfToken()) {
        http_response_code(403);
        exit('403 Forbidden: Invalid or missing security token. Go back, refresh the page and try again.');
    }
}
