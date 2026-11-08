<?php
// config/database.php
// Creates the PDO connection ($pdo) used by the whole application.
// Real credentials are read from config/config.php (not stored in Git).
// This folder is outside public/, so browsers cannot request these files.

$configFile = __DIR__ . '/config.php';

if (!is_file($configFile)) {
    error_log('AgriCare: config/config.php is missing. Copy config/config.example.php to config/config.php.');
    http_response_code(500);
    exit('The application is not configured yet. Copy config/config.example.php to config/config.php.');
}

$config = require $configFile;

$dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';

try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // rows as associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared statements
    ]);

    // Pakistan time (UTC+5, no daylight saving). The PHP side uses the same zone
    // (see includes/functions.php), so dates shown and "today" checks always agree.
    $pdo->exec("SET time_zone = '+05:00'");
} catch (PDOException $exception) {
    // Technical details go to the server log only, never to the visitor.
    error_log('AgriCare database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The service is temporarily unavailable. Please try again later.');
}

// The password is no longer needed after connecting.
unset($config);
