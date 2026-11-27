<?php
/* =========================================================
   GET api/check_email.php?email=...
   AJAX check used by the registration form.
   Answer: {"success": true, "available": true|false}
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed. Use GET.', 405);
}
$email = trim((string)($_GET['email'] ?? ''));
if (!valid_email($email)) {
    json_error('Invalid email format.', 422);
}
$taken = (int)db_value('SELECT COUNT(*) FROM users WHERE email = ?', [mb_strtolower($email)]);
json_response(['success' => true, 'available' => $taken === 0]);
