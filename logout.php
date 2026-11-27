<?php
/* =========================================================
   logout.php — log out (only POST with a CSRF token, so another
   website cannot log the user out with a simple link or image).
   ========================================================= */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    error_page(405, 'Method not allowed', 'Please use the Logout button.');
}
verify_csrf();
logout_user();

session_start();                             // a new empty session only for the message
session_regenerate_id(true);
flash('info', 'You have logged out. See you soon!');
redirect('login.php');
