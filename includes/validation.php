<?php
/* =========================================================
   validation.php — SERVER-SIDE validation.
   JavaScript checks the forms in the browser too, but it can be
   switched off, so PHP always checks everything again.
   Each validate_...() returns an array: field => error message.
   An empty array means "everything is correct".
   ========================================================= */

function is_blank($value): bool
{
    return !is_string($value) || trim($value) === '';
}

function valid_email(string $email): bool
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false && mb_strlen($email) <= 120;
}

function valid_name(string $name): bool
{
    // letters (Latin / Cyrillic / Kazakh), spaces and "-", from 2 to 50 symbols
    return (bool)preg_match('/^[\p{L}\s\-]{2,50}$/u', trim($name));
}

function normalize_phone(string $phone): string
{
    return preg_replace('/[\s\-()]/', '', trim($phone));
}

function valid_phone(string $phone): bool
{
    return (bool)preg_match('/^(\+7|8)\d{10}$/', normalize_phone($phone));   // Kazakhstan format
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function valid_time(string $time): bool
{
    return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);
}

function int_between($value, int $min, int $max): bool
{
    return is_numeric($value) && (string)(int)$value === (string)$value && (int)$value >= $min && (int)$value <= $max;
}

function length_between(string $value, int $min, int $max): bool
{
    $len = mb_strlen(trim($value));
    return $len >= $min && $len <= $max;
}

// ---------- Registration ----------
function validate_registration(array $d): array
{
    $e = [];
    if (is_blank($d['first_name'] ?? ''))        $e['first_name'] = 'First name is required';
    elseif (!valid_name($d['first_name']))       $e['first_name'] = 'Use only letters (2–50)';

    if (is_blank($d['last_name'] ?? ''))         $e['last_name'] = 'Last name is required';
    elseif (!valid_name($d['last_name']))        $e['last_name'] = 'Use only letters (2–50)';

    $email = trim($d['email'] ?? '');
    if ($email === '')                           $e['email'] = 'Email is required';
    elseif (!valid_email($email))                $e['email'] = 'Please enter a valid email, e.g. student@mail.com';
    elseif (db_value('SELECT COUNT(*) FROM users WHERE email = ?', [mb_strtolower($email)]) > 0)
                                                 $e['email'] = 'This email is already registered';

    $phone = trim($d['phone'] ?? '');
    if ($phone !== '' && !valid_phone($phone))   $e['phone'] = 'Phone format: +7 701 123 45 67';

    $birth = trim($d['birth_date'] ?? '');
    if ($birth !== '') {
        if (!valid_date($birth))                 $e['birth_date'] = 'Invalid date';
        elseif ($birth > date('Y-m-d'))          $e['birth_date'] = 'Date of birth cannot be in the future';
        elseif ($birth < '1920-01-01')           $e['birth_date'] = 'Please check the year of birth';
    }

    $pass = $d['password'] ?? '';
    if ($pass === '')                            $e['password'] = 'Password is required';
    elseif (strlen($pass) < 8)                   $e['password'] = 'Password must be at least 8 characters';
    elseif (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass))
                                                 $e['password'] = 'Password must contain letters and digits';

    $confirm = $d['confirm_password'] ?? '';
    if ($confirm === '')                         $e['confirm_password'] = 'Please confirm your password';
    elseif ($confirm !== $pass)                  $e['confirm_password'] = 'Passwords do not match';
    return $e;
}

// ---------- Profile ----------
function validate_profile(array $d): array
{
    $e = [];
    if (!valid_name($d['first_name'] ?? ''))     $e['first_name'] = 'First name: only letters, at least 2';
    if (!valid_name($d['last_name'] ?? ''))      $e['last_name'] = 'Last name: only letters, at least 2';
    $phone = trim($d['phone'] ?? '');
    if ($phone !== '' && !valid_phone($phone))   $e['phone'] = 'Phone format: +7 701 123 45 67';
    $birth = trim($d['birth_date'] ?? '');
    if ($birth !== '' && (!valid_date($birth) || $birth > date('Y-m-d')))
                                                 $e['birth_date'] = 'Date of birth cannot be in the future';
    return $e;
}

function validate_password_change(array $d, string $currentHash): array
{
    $e = [];
    if (!password_verify($d['current_password'] ?? '', $currentHash)) $e['current_password'] = 'Current password is incorrect';
    $new = $d['new_password'] ?? '';
    if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new))
                                                 $e['new_password'] = 'At least 8 characters with letters and digits';
    if (($d['confirm_new_password'] ?? '') !== $new) $e['confirm_new_password'] = 'Passwords do not match';
    return $e;
}

// ---------- Enrollment ----------
function validate_enrollment(array $d): array
{
    $e = [];
    if (int_param($d, 'group_id') === 0)         $e['group_id'] = 'Please choose a group (time of classes)';
    $phone = trim($d['phone'] ?? '');
    if ($phone === '')                           $e['phone'] = 'Phone number is required so the manager can contact you';
    elseif (!valid_phone($phone))                $e['phone'] = 'Invalid phone number. Format: +7 701 123 45 67';
    if (mb_strlen($d['comment'] ?? '') > 300)    $e['comment'] = 'Comment is too long (maximum 300 characters)';
    if (empty($d['terms']))                      $e['terms'] = 'You must agree to the course terms';
    return $e;
}

// ---------- Contact form ----------
function validate_contact(array $d): array
{
    $e = [];
    if (!length_between($d['name'] ?? '', 2, 100))      $e['name'] = 'Please enter your name';
    if (!valid_email($d['email'] ?? ''))                 $e['email'] = 'Please enter a valid email';
    if (!length_between($d['message'] ?? '', 10, 2000))  $e['message'] = 'Message must be from 10 to 2000 characters';
    return $e;
}

// ---------- Admin: course ----------
function validate_course(array $d, int $editingId = 0): array
{
    $e = [];
    $title = trim($d['title'] ?? '');
    if (!length_between($title, 3, 120))         $e['title'] = 'Title must be from 3 to 120 characters';
    elseif (db_value('SELECT COUNT(*) FROM courses WHERE title = ? AND id <> ?', [$title, $editingId]) > 0)
                                                 $e['title'] = 'A course with this title already exists';
    if (!db_value('SELECT COUNT(*) FROM categories WHERE id = ?', [int_param($d, 'category_id')]))
                                                 $e['category_id'] = 'Choose a category';
    if (!db_value('SELECT COUNT(*) FROM teachers WHERE id = ?', [int_param($d, 'teacher_id')]))
                                                 $e['teacher_id'] = 'Choose a teacher';
    if (!in_array($d['level'] ?? '', ['Beginner', 'Intermediate', 'Advanced'], true))
                                                 $e['level'] = 'Choose a level';
    if (!int_between($d['price'] ?? '', 0, 1000000)) $e['price'] = 'Price: a whole number from 0 to 1 000 000 (0 = free)';
    if (!int_between($d['duration_weeks'] ?? '', 1, 52)) $e['duration_weeks'] = 'Duration: from 1 to 52 weeks';
    if (!preg_match('/^[a-z0-9_-]+\.svg$/', $d['image'] ?? '') || !is_file(__DIR__ . '/../images/courses/' . ($d['image'] ?? '')))
                                                 $e['image'] = 'Choose a picture from the list';
    $video = trim($d['intro_video'] ?? '');
    if ($video !== '' && !youtube_id($video) && !preg_match('~^videos/[a-z0-9_-]+\.mp4$~', $video))
                                                 $e['intro_video'] = 'Use a YouTube link or a file from the videos folder (videos/name.mp4)';
    if (!length_between($d['short_description'] ?? '', 10, 160)) $e['short_description'] = 'Short description: 10–160 characters';
    if (!length_between($d['description'] ?? '', 20, 5000))      $e['description'] = 'Description must be at least 20 characters';
    $features = array_filter(array_map('trim', explode("\n", $d['features'] ?? '')));
    if (count($features) === 0)                  $e['features'] = 'Add at least one feature (one per line)';
    return $e;
}

// ---------- Admin: group (schedule) ----------
function validate_group(array $d): array
{
    $e = [];
    if (!length_between($d['name'] ?? '', 2, 40)) $e['name'] = 'Group name: 2–40 characters';
    if (!in_array($d['format'] ?? '', ['Online', 'Offline', 'Hybrid'], true)) $e['format'] = 'Choose a format';
    $start = $d['start_date'] ?? '';
    $end = $d['end_date'] ?? '';
    if (!valid_date($start))                     $e['start_date'] = 'Choose a start date';
    if (!valid_date($end))                       $e['end_date'] = 'Choose an end date';
    elseif (valid_date($start) && $end < $start) $e['end_date'] = 'End date must be after the start date';
    if (!int_between($d['capacity'] ?? '', 1, 200)) $e['capacity'] = 'Capacity: from 1 to 200 students';
    $sessions = group_sessions_from_post($d);
    if (!$sessions)                              $e['sessions'] = 'Add at least one lesson time (day, start, end)';
    foreach ($sessions as $s) {
        if (!$s['valid'])                        $e['sessions'] = 'Every lesson time needs a day, a start and an end (end after start)';
    }
    return $e;
}

// rows "weekday[] / start_time[] / end_time[]" from the group form
function group_sessions_from_post(array $d): array
{
    $list = [];
    $days = $d['weekday'] ?? [];
    for ($i = 0; $i < (is_array($days) ? count($days) : 0); $i++) {
        $day = (string)($days[$i] ?? '');
        $start = (string)($d['start_time'][$i] ?? '');
        $end = (string)($d['end_time'][$i] ?? '');
        if ($day === '' && $start === '' && $end === '') {
            continue;                            // empty row is ignored
        }
        $valid = int_between($day, 1, 7) && valid_time($start) && valid_time($end) && $end > $start;
        $list[] = ['weekday' => (int)$day, 'start_time' => $start, 'end_time' => $end, 'valid' => $valid];
    }
    return $list;
}

// ---------- Admin: lesson / material ----------
function validate_lesson(array $d): array
{
    $e = [];
    if (!length_between($d['title'] ?? '', 3, 120))  $e['title'] = 'Title: 3–120 characters';
    if (!int_between($d['position'] ?? '', 1, 100))   $e['position'] = 'Position: from 1 to 100';
    if (!int_between($d['duration_min'] ?? '', 1, 600)) $e['duration_min'] = 'Duration: from 1 to 600 minutes';
    $video = trim($d['video_url'] ?? '');
    if ($video !== '' && !youtube_id($video) && !preg_match('~^videos/[a-z0-9_-]+\.mp4$~', $video))
                                                 $e['video_url'] = 'Use a YouTube link or videos/name.mp4';
    return $e;
}

function validate_material(array $d): array
{
    $e = [];
    if (!length_between($d['title'] ?? '', 3, 120))   $e['title'] = 'Title: 3–120 characters';
    $type = $d['type'] ?? '';
    $link = trim($d['url'] ?? '');
    if (!in_array($type, ['pdf', 'link'], true))      $e['type'] = 'Choose a type';
    elseif ($type === 'link' && !preg_match('~^https?://~', $link)) $e['url'] = 'A link must start with http:// or https://';
    elseif ($type === 'pdf' && !preg_match('~^materials/[a-z0-9_-]+\.pdf$~', $link)) $e['url'] = 'A PDF must be in the materials folder: materials/name.pdf';
    return $e;
}
