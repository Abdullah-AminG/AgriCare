<?php
// includes/throttle.php
// Simple login-attempt limit: after 5 FAILED logins for the same email from the
// same IP address within 15 minutes, further login attempts are refused.
// Failed attempts are stored in the login_attempts table (see database/schema.sql).

const LOGIN_MAX_ATTEMPTS   = 5;
const LOGIN_WINDOW_MINUTES = 15;

// A valid bcrypt hash of a known sample password. It is used ONLY so that a login
// attempt for an unknown email takes about as long as one for a real email
// (password_verify is always executed). It never grants access to anything.
const LOGIN_DUMMY_HASH = '$2b$12$svmnaNn1ntZi6NNb7C1yFu1x5dbMAPgUdmvnajH.ldhClkLjdegx6';

/** The visitor's IP address as seen by Apache. X-Forwarded-For is NOT trusted (it can be faked). */
function clientIp(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return substr($ip !== '' ? $ip : '0.0.0.0', 0, 45);
}

/** Emails are stored in a VARCHAR(150) column, so cut very long input to fit. */
function throttleEmail(string $email): string
{
    return substr($email, 0, 150);
}

/** How many failed logins this email + IP made in the last 15 minutes. */
function countRecentFailedLogins(PDO $pdo, string $email, string $ip): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM login_attempts
         WHERE email = :email
           AND ip_address = :ip
           AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)'
    );
    $stmt->execute(['email' => throttleEmail($email), 'ip' => $ip]);

    return (int) $stmt->fetchColumn();
}

function isLoginLocked(PDO $pdo, string $email, string $ip): bool
{
    return countRecentFailedLogins($pdo, $email, $ip) >= LOGIN_MAX_ATTEMPTS;
}

/** Remember one failed login (and delete records older than one day so the table stays small). */
function recordFailedLogin(PDO $pdo, string $email, string $ip): void
{
    $stmt = $pdo->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip)');
    $stmt->execute(['email' => throttleEmail($email), 'ip' => $ip]);

    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
}

/** A successful login wipes the failed attempts of this email + IP. */
function clearFailedLogins(PDO $pdo, string $email, string $ip): void
{
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE email = :email AND ip_address = :ip');
    $stmt->execute(['email' => throttleEmail($email), 'ip' => $ip]);
}
