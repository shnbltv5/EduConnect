<?php
/* =========================================================
   config.php — settings of the website.
   Change the database settings here when you move the site
   to a hosting (see README.md → Deployment).
   ========================================================= */

// Database (XAMPP default: user "root" without a password)
define('DB_HOST', 'localhost');
define('DB_NAME', 'educonnect');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SITE_NAME', 'EduConnect');
define('PAGE_SIZE', 6);                         // courses per page in the catalog
define('MAX_PHOTO_SIZE', 1024 * 1024);          // profile photo: max 1 MB
define('LOGIN_MAX_ATTEMPTS', 5);                // wrong passwords before a short lock
define('LOGIN_LOCK_SECONDS', 60);

date_default_timezone_set('Asia/Almaty');

// Errors are written to the PHP log, the user sees a friendly page instead
ini_set('display_errors', '0');
error_reporting(E_ALL);
