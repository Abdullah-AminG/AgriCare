<?php
// config/config.example.php
// SAMPLE configuration file (safe to commit: it contains no real password).
//
// HOW TO USE:
//   1. Copy this file:   cp config/config.example.php config/config.php
//   2. Edit config/config.php and enter your real local database details.
//   3. config/config.php is listed in .gitignore, so it is never uploaded to GitHub.

return [
    'db_host' => 'localhost',
    'db_name' => 'agricare',
    'db_user' => 'agricare_user',
    'db_pass' => 'YOUR_LOCAL_DATABASE_PASSWORD',
];
