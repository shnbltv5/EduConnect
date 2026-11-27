<?php
/* =========================================================
   functions.php — small helpers used on every page:
   output escaping (XSS), links, redirects, flash messages,
   CSRF protection, formatting, JSON answers, error pages.
   ========================================================= */

// ---------- XSS protection: every value from the DB / user is printed through h() ----------
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ---------- Links ----------
// ROOT is "" for pages in the project root and "../" for pages in /pages, /admin, /api
function url(string $path = ''): string
{
    return ROOT . $path;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// ---------- Flash messages: shown once on the next page ----------
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $list = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $list;
}

// ---------- CSRF protection ----------
// A random secret token is stored in the session and added to every form.
// A request from another website cannot know it, so it is rejected.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_is_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

// For normal form pages
function verify_csrf(): void
{
    if (!csrf_is_valid()) {
        error_page(403, 'Request blocked', 'The form has expired or was sent from another website. Please reload the page and try again.');
    }
}

// ---------- Form helpers ----------
function old(array $data, string $key, $default = ''): string
{
    return h($data[$key] ?? $default);
}

// <p class="error-message"> under a field (filled by PHP after server validation)
function field_error(array $errors, string $key): string
{
    return '<p class="error-message">' . (isset($errors[$key]) ? '⚠ ' . h($errors[$key]) : '') . '</p>';
}

function group_class(array $errors, string $key): string
{
    return isset($errors[$key]) ? 'form-group has-error' : 'form-group';
}

// ---------- Formatting ----------
function format_price($price): string
{
    return (int)$price === 0 ? 'Free' : number_format((int)$price, 0, '', ' ') . ' ₸';
}

function format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    return date('d.m.Y', strtotime($date));
}

function format_datetime(?string $date): string
{
    return $date ? date('d.m.Y H:i', strtotime($date)) : '—';
}

function format_time(string $time): string
{
    return substr($time, 0, 5);              // "14:00:00" -> "14:00"
}

const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

function weekday_short(int $day): string
{
    return substr(WEEKDAYS[$day] ?? '?', 0, 3);
}

// "Mon, Wed 14:00–15:30" from a list of sessions
function format_sessions(array $sessions): string
{
    $parts = [];
    foreach ($sessions as $s) {
        $parts[] = weekday_short((int)$s['weekday']) . ' ' . format_time($s['start_time']) . '–' . format_time($s['end_time']);
    }
    return $parts ? implode(', ', $parts) : 'Schedule not set';
}

function render_stars($rating): string
{
    $full = (int)round((float)$rating);
    return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
}

function status_badge(string $status): string
{
    return '<span class="badge badge--' . h(strtolower($status)) . '">' . h($status) . '</span>';
}

function avatar_html(array $user): string
{
    if (!empty($user['photo'])) {
        return '<img src="' . h(url($user['photo'])) . '" alt="' . h($user['first_name']) . '">';
    }
    return h(mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1)));
}

// ---------- Video: local MP4 file or YouTube link ----------
function youtube_id(?string $url): ?string
{
    if ($url && preg_match('~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    return null;
}

function render_video(?string $video, string $title, string $poster = ''): string
{
    if (!$video) {
        return '';
    }
    $id = youtube_id($video);
    if ($id) {
        return '<div class="video video--youtube"><iframe src="https://www.youtube-nocookie.com/embed/' . h($id) . '" title="' . h($title) . '"'
            . ' allow="accelerometer; encrypted-media; picture-in-picture; fullscreen" referrerpolicy="strict-origin-when-cross-origin" loading="lazy" allowfullscreen></iframe></div>'
            . '<p class="video__link"><a href="https://www.youtube.com/watch?v=' . h($id) . '" target="_blank" rel="noopener">▶ Open the video on YouTube</a></p>';
    }
    return '<div class="video"><video controls preload="metadata"' . ($poster ? ' poster="' . h($poster) . '"' : '') . '>'
        . '<source src="' . h(url($video)) . '" type="video/mp4">Your browser does not support HTML5 video.</video></div>';
}

// ---------- JSON answers for the API ----------
function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $code = 400, array $extra = []): void
{
    json_response(['success' => false, 'error' => $message] + $extra, $code);
}

// ---------- Error page (404, 403, 500 ...) with the normal header and footer ----------
function error_page(int $code, string $title, string $message): void
{
    http_response_code($code);
    $pageTitle = $title;
    $activePage = '';
    include __DIR__ . '/header.php';
    echo '<section class="form-page"><div class="form-card success-card">'
        . '<div class="error-code">' . $code . '</div>'
        . '<h1>' . h($title) . '</h1>'
        . '<p class="form-card__subtitle">' . h($message) . '</p>'
        . '<div class="button-row"><a href="' . h(url('index.php')) . '" class="btn">Go to Home</a>'
        . '<a href="' . h(url('pages/courses.php')) . '" class="btn btn--outline">Browse courses</a></div>'
        . '</div></section>';
    include __DIR__ . '/footer.php';
    exit;
}

// Reads an integer id from $_GET / $_POST; returns 0 if it is missing or not a positive number
function int_param(array $source, string $key): int
{
    $value = $source[$key] ?? '';
    return (is_string($value) && ctype_digit($value)) ? (int)$value : 0;
}
