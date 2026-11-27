<?php
/* =========================================================
   auth.php — who is logged in, login / logout,
   protection of pages (role-based access control).
   The session stores only the user id and role; everything
   else is read from the database on every request.
   ========================================================= */

function current_user(): ?array
{
    static $user = false;                    // cache for this request
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $row = db_one('SELECT id, first_name, last_name, email, role, phone, birth_date, photo, created_at
                           FROM users WHERE id = ?', [$_SESSION['user_id']]);
            if ($row) {
                $user = $row;
            } else {
                unset($_SESSION['user_id'], $_SESSION['role']);   // user was deleted
            }
        }
    }
    return $user;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);             // new session id after login (protection from session fixation)
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = $user['role'];
    unset($_SESSION['login_fails'], $_SESSION['login_locked_until']);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// Pages only for logged-in users: a guest is sent to the login page and comes back after login
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        $back = ltrim(substr($_SERVER['REQUEST_URI'], strlen(BASE_PATH)), '/');
        flash('info', 'Please log in to continue.');
        redirect('login.php?redirect=' . urlencode($back));
    }
    return $user;
}

// Pages only for administrators
function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        error_page(403, 'Access denied', 'This page is only for administrators.');
    }
    return $user;
}

// The same checks for the API: JSON answer instead of a redirect
function api_require_login(): array
{
    $user = current_user();
    if ($user === null) {
        json_error('Please log in first.', 401);
    }
    return $user;
}

function api_require_admin(): array
{
    $user = api_require_login();
    if ($user['role'] !== 'admin') {
        json_error('Access denied: administrators only.', 403);
    }
    return $user;
}

// Only local pages are allowed after login (protection from "open redirect" to other websites)
function safe_redirect_target(string $target): string
{
    if ($target === '' || preg_match('~^[a-z]+:|//|\\\\~i', $target) || str_starts_with($target, '/')) {
        return '';
    }
    return $target;
}
