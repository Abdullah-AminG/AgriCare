<?php
// includes/init.php
// One include that loads everything a page needs.
//   Page in public/farmer/ or public/admin/ :  require_once dirname(__DIR__, 2) . '/includes/init.php';
//   Page directly in public/               :  require_once dirname(__DIR__) . '/includes/init.php';

require_once __DIR__ . '/../config/database.php';   // creates $pdo
require_once __DIR__ . '/auth.php';                 // sessions, login and role checks
require_once __DIR__ . '/csrf.php';                 // CSRF token helpers
require_once __DIR__ . '/functions.php';            // flash messages, validation, helpers
require_once __DIR__ . '/security.php';             // security headers (needs auth.php helpers)
require_once __DIR__ . '/throttle.php';             // login attempt limit

sendSecurityHeaders();
