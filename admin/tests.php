<?php
/* =========================================================
   admin/tests.php — automated tests (valid / invalid / missing
   data, boundary cases, business logic, database constraints,
   security and permissions). Every test that changes the
   database cleans up after itself, so the demo data stays.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$results = [];
function test(string $name, string $expected, callable $fn): void
{
    global $results;
    try {
        [$ok, $actual] = $fn();
    } catch (Throwable $e) {
        [$ok, $actual] = [false, 'Unexpected error: ' . $e->getMessage()];
    }
    $results[] = ['name' => $name, 'expected' => $expected, 'actual' => $actual, 'ok' => $ok];
}
function group_by_name(int $courseId, string $name): int
{
    return (int)db_value('SELECT id FROM course_groups WHERE course_id = ? AND name = ?', [$courseId, $name]);
}
$demo = (int)db_value("SELECT id FROM users WHERE email = 'student@educonnect.kz'");
$free = (int)db_value("SELECT u.id FROM users u LEFT JOIN enrollments e ON e.user_id = u.id
                       WHERE u.role = 'student' AND e.id IS NULL ORDER BY u.id LIMIT 1");   // student without enrollments
$pythonId = (int)db_value("SELECT id FROM courses WHERE title = 'Introduction to Python'");

// ---------- Validation ----------
test('Email format: valid and invalid', 'student@mail.com ✓, student@ ✗', function () {
    return [valid_email('student@mail.com') && !valid_email('student@'), 'valid=' . var_export(valid_email('student@mail.com'), true) . ', invalid=' . var_export(valid_email('student@'), true)];
});
test('Phone format (Kazakhstan)', '+7 701 123 45 67 ✓, 12345 ✗', function () {
    return [valid_phone('+7 701 123 45 67') && !valid_phone('12345'), 'ok'];
});
test('Empty registration form (missing data)', 'at least 5 field errors', function () {
    $e = validate_registration([]);
    return [count($e) >= 5, count($e) . ' errors: ' . implode(', ', array_keys($e))];
});
test('Password length boundary', '7 chars ✗, 8 chars ✓', function () {
    $seven = validate_registration(['password' => 'abc1234', 'confirm_password' => 'abc1234']);
    $eight = validate_registration(['password' => 'abcd1234', 'confirm_password' => 'abcd1234']);
    return [isset($seven['password']) && !isset($eight['password']), '7: ' . ($seven['password'] ?? 'no error') . ' | 8: ' . ($eight['password'] ?? 'no error')];
});
test('Unique email on registration', '"already registered" for student@educonnect.kz', function () {
    $e = validate_registration(['email' => 'student@educonnect.kz']);
    return [($e['email'] ?? '') === 'This email is already registered', $e['email'] ?? 'no error'];
});
test('Date of birth in the future', 'error', function () {
    $e = validate_registration(['birth_date' => date('Y-m-d', strtotime('+1 day'))]);
    return [isset($e['birth_date']), $e['birth_date'] ?? 'no error'];
});

// ---------- Security ----------
test('Password hashing (bcrypt)', 'correct password ✓, wrong ✗, hash ≠ password', function () {
    $hash = db_value("SELECT password_hash FROM users WHERE email = 'admin@educonnect.kz'");
    return [password_verify('admin123', $hash) && !password_verify('wrong123', $hash) && $hash !== 'admin123', substr($hash, 0, 7) . '... (' . strlen($hash) . ' chars)'];
});
test('SQL injection in a prepared statement', "email \"' OR '1'='1\" finds 0 users", function () {
    $row = db_one('SELECT id FROM users WHERE email = ?', ["' OR '1'='1"]);
    return [$row === false, $row === false ? '0 rows' : 'FOUND A USER!'];
});
test('SQL injection in ORDER BY (catalog sort)', '"id; DROP TABLE users" → default sort', function () {
    $f = catalog_filters(['sort' => 'id; DROP TABLE users', 'page' => '-5', 'category' => '1 OR 1=1']);
    return [$f['sort'] === 'popular' && $f['page'] === 1 && $f['category'] === 0, json_encode($f)];
});
test('XSS: output escaping with h()', '<script> is printed as text', function () {
    $out = h('<script>alert(1)</script>');
    return [!str_contains($out, '<script>'), $out];
});
test('Open redirect after login is blocked', 'https://evil.com → ""', function () {
    return [safe_redirect_target('https://evil.com') === '' && safe_redirect_target('//evil.com') === '' && safe_redirect_target('pages/enroll.php?id=1') === 'pages/enroll.php?id=1', 'ok'];
});

// ---------- Business logic ----------
test('Schedule conflict: two courses at the same time', 'Web Design Group A (Mon/Wed 14:00) conflicts with Python', function () use ($demo) {
    $c = find_schedule_conflicts($demo, group_by_name(2, 'Group A'));
    return [count($c) > 0 && $c[0]['course_title'] === 'Introduction to Python', count($c) ? conflict_message($c) : 'no conflict'];
});
test('Partial overlap is also a conflict', 'UI/UX Group B (14:30–16:00) overlaps Python (14:00–15:30)', function () use ($demo) {
    $c = find_schedule_conflicts($demo, group_by_name(5, 'Group B'));
    return [count($c) > 0, count($c) ? conflict_message($c) : 'no conflict'];
});
test('No conflict for another time', 'Web Design Group B (Tue/Thu 19:00) is free', function () use ($demo) {
    $c = find_schedule_conflicts($demo, group_by_name(2, 'Group B'));
    return [count($c) === 0, count($c) . ' conflicts'];
});
test('Enrollment is rejected and rolled back on a conflict', 'exception + no new rows (transaction rollback)', function () use ($demo) {
    $before = (int)db_value('SELECT COUNT(*) FROM enrollments');
    try {
        enroll_user($demo, group_by_name(2, 'Group A'), '+77011234567', '');
        return [false, 'enrollment was created!'];
    } catch (EnrollmentException $e) {
        $after = (int)db_value('SELECT COUNT(*) FROM enrollments');
        return [$after === $before, $e->getMessage() . " | rows: $before → $after"];
    }
});
test('Full group (capacity boundary)', 'UI/UX Group A (3 of 3) → "group is full"', function () use ($free) {
    try {
        enroll_user($free, group_by_name(5, 'Group A'), '+77011234567', '');
        return [false, 'enrollment was created!'];
    } catch (EnrollmentException $e) {
        return [str_contains($e->getMessage(), 'full'), $e->getMessage()];
    }
});
test('The same course twice', 'Python Group B → "already enrolled"', function () use ($demo) {
    try {
        enroll_user($demo, group_by_name(1, 'Group B'), '+77011234567', '');
        return [false, 'enrollment was created!'];
    } catch (EnrollmentException $e) {
        return [str_contains($e->getMessage(), 'already enrolled'), $e->getMessage()];
    }
});
test('Valid enrollment (then removed)', 'new Pending enrollment with the course price', function () use ($free) {
    $id = enroll_user($free, group_by_name(2, 'Group B'), '+7 701 555 01 01', 'test');
    $row = db_one('SELECT status, price_paid FROM enrollments WHERE id = ?', [$id]);
    db_exec('DELETE FROM enrollments WHERE id = ?', [$id]);           // clean up
    return [$row['status'] === 'Pending' && (int)$row['price_paid'] === 25000, "#$id: {$row['status']}, " . format_price($row['price_paid'])];
});

// ---------- Permissions ----------
test('Lessons are locked until the enrollment is confirmed', 'Confirmed course ✓, Pending / not enrolled ✗, guest ✗', function () use ($demo, $pythonId) {
    $user = db_one('SELECT * FROM users WHERE id = ?', [$demo]);
    // a course where the student has NO confirmed/completed enrollment (pending or none)
    $closed = (int)db_value("SELECT c.id FROM courses c WHERE NOT EXISTS (
                                SELECT 1 FROM enrollments e JOIN course_groups g ON g.id = e.group_id
                                WHERE g.course_id = c.id AND e.user_id = ? AND e.status IN ('Confirmed', 'Completed'))
                             ORDER BY c.id LIMIT 1", [$demo]);
    $a = can_access_course($user, $pythonId);
    $b = can_access_course($user, $closed);
    $c = can_access_course(null, $pythonId);
    return [$a && !$b && !$c, 'confirmed=' . var_export($a, true) . ', not confirmed (#' . $closed . ')=' . var_export($b, true) . ', guest=' . var_export($c, true)];
});

// ---------- Database constraints ----------
test('FOREIGN KEY: course with enrollments cannot be deleted', 'PDOException 23000, course still exists', function () use ($pythonId) {
    try {
        db_exec('DELETE FROM courses WHERE id = ?', [$pythonId]);
        return [false, 'course was deleted!'];
    } catch (PDOException $e) {
        $exists = (int)db_value('SELECT COUNT(*) FROM courses WHERE id = ?', [$pythonId]);
        return [is_constraint_error($e) && $exists === 1, 'SQLSTATE ' . $e->getCode() . ', course exists: ' . $exists];
    }
});
test('UNIQUE: the same email twice', 'PDOException 23000', function () {
    try {
        db_exec("INSERT INTO users (first_name, last_name, email, password_hash) VALUES ('A', 'B', 'admin@educonnect.kz', 'x')");
        db_exec("DELETE FROM users WHERE email = 'admin@educonnect.kz' AND first_name = 'A'");
        return [false, 'duplicate was inserted!'];
    } catch (PDOException $e) {
        return [is_constraint_error($e), 'SQLSTATE ' . $e->getCode()];
    }
});
test('CHECK: rating must be 1..5', 'rating 6 is rejected', function () use ($demo, $pythonId) {
    try {
        db_exec('INSERT INTO reviews (user_id, course_id, rating, comment) VALUES (?, ?, 6, ?)', [$demo, $pythonId, 'test']);
        db_exec('DELETE FROM reviews WHERE rating = 6');
        return [false, 'rating 6 was saved!'];
    } catch (PDOException $e) {
        return [true, 'rejected: SQLSTATE ' . $e->getCode()];
    }
});

// ---------- Queries used by the website ----------
test('Catalog search with several conditions', 'q=python, level=Beginner → 1 course', function () {
    $r = search_courses(catalog_filters(['q' => 'python', 'level' => 'Beginner']));
    return [$r['total'] === 1 && $r['items'][0]['title'] === 'Introduction to Python', $r['total'] . ' found'];
});
test('Pagination', '6 courses per page, page 2 exists', function () {
    $r = search_courses(catalog_filters(['page' => '2']));
    return [$r['pages'] >= 2 && $r['page'] === 2 && count($r['items']) >= 1, "page {$r['page']} of {$r['pages']}, " . count($r['items']) . ' items'];
});

$passed = count(array_filter($results, fn($r) => $r['ok']));

admin_start('Automated tests', 'tests', 'Valid / invalid / missing data, boundaries, business logic, permissions and database constraints');
?>
<div class="panel">
  <h2 class="panel__title"><span>Result: <span class="<?= $passed === count($results) ? 'text-success' : 'text-danger' ?>"><?= $passed ?> / <?= count($results) ?> passed</span></span>
    <a class="btn btn--small" href="tests.php">↻ Run again</a></h2>
  <p class="text-muted small">Manual test cases (UI, AJAX, unauthorized requests) are described in docs/TEST_CASES.md.</p>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>#</th><th>Test</th><th>Expected</th><th>Actual result</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($results as $i => $r): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><strong><?= h($r['name']) ?></strong></td>
          <td class="small"><?= h($r['expected']) ?></td>
          <td class="small"><?= h($r['actual']) ?></td>
          <td><?= $r['ok'] ? '<span class="badge badge--confirmed">PASS</span>' : '<span class="badge badge--cancelled">FAIL</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php admin_end(); ?>
