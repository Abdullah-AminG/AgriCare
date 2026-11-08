<?php
// includes/security.php
// Sends security-related HTTP headers. Called once from includes/init.php,
// before any HTML is printed (headers must be sent before the page body).

function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    // The browser must trust the Content-Type we send (no "guessing" that an upload is a script).
    header('X-Content-Type-Options: nosniff');

    // Nobody may show AgriCare inside a frame on another site (clickjacking protection).
    header('X-Frame-Options: DENY');

    // Do not leak full URLs to other websites.
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content Security Policy: only load scripts, styles and images from our own site.
    // This project has no inline <script> or <style>, so this strict policy works
    // and limits the damage of any XSS bug that might exist in the future.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
         . "style-src 'self'; script-src 'self'; form-action 'self'; "
         . "frame-ancestors 'none'; base-uri 'self'");

    // Tell the browser to use HTTPS only. Sent only over HTTPS, so local HTTP development is unaffected.
    if (isHttpsRequest()) {
        header('Strict-Transport-Security: max-age=15552000');
    }

    // Logged-in pages contain private data. "no-store" stops the browser from keeping a copy,
    // so pressing the Back button after logout cannot show an old dashboard.
    if (isLoggedIn()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
