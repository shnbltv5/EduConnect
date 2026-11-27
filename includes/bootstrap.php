<?php
/* =========================================================
   bootstrap.php — the first file included by every page.
   Loads settings and helpers, starts a secure session and
   turns any unexpected error into a friendly error page.
   ========================================================= */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/courses.php';
require_once __DIR__ . '/enrollment.php';

// ROOT: path from the current page to the project root ("" or "../")
// BASE_PATH: the project folder in the URL, e.g. "/EduConnect"
$projectDir = realpath(__DIR__ . '/..');
$scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
define('ROOT', $scriptDir === $projectDir ? '' : '../');
define('BASE_PATH', rtrim(str_replace('\\', '/', $scriptDir === $projectDir
    ? dirname($_SERVER['SCRIPT_NAME'])
    : dirname(dirname($_SERVER['SCRIPT_NAME']))), '/'));
define('IS_API', basename($scriptDir) === 'api');

// Session cookie: not readable from JavaScript (httponly), not sent from other sites (SameSite)
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => BASE_PATH . '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_name('EDUCONNECTSESSID');
session_start();

// Any exception that was not caught on the page (e.g. the database is down)
set_exception_handler(function (Throwable $e) {
    error_log('[EduConnect] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $dbDown = $e instanceof PDOException && in_array((int)$e->getCode(), [2002, 1045, 1049, 2006], true);
    $message = $dbDown
        ? 'The database is not available right now. Check that MySQL is running in XAMPP and that database.sql was imported.'
        : 'Something went wrong on the server. Please try again later.';
    if (IS_API) {
        json_error($message, 500);
    }
    error_page(500, $dbDown ? 'Database error' : 'Server error', $message);
});
