<?php
/* =========================================================
   admin/reports.php — statistical reports (ADVANCED SQL).
   Every report is a real query to MySQL; its text can be opened
   with "Show SQL". Used techniques: multi-table JOIN, LEFT JOIN,
   subqueries, COUNT / SUM / AVG / MAX, GROUP BY + HAVING,
   CASE, date-based filtering and grouping by month.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$today = date('Y-m-d');
$from = date('Y-m-01', strtotime('-5 months'));          // the last 6 months

$reports = [
    'popular' => [
        'title' => '1. Popular courses: students, revenue and rating',
        'note'  => 'JOIN of 5 tables + LEFT JOIN (courses without students are shown too) + subquery for the rating + COUNT / SUM.',
        'sql'   => "SELECT c.title, cat.name AS category, t.full_name AS teacher,
       COUNT(e.id) AS students,
       COALESCE(SUM(e.price_paid), 0) AS revenue,
       (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.course_id = c.id) AS rating
FROM courses c
JOIN categories cat ON cat.id = c.category_id
JOIN teachers t ON t.id = c.teacher_id
LEFT JOIN course_groups g ON g.course_id = c.id
LEFT JOIN enrollments e ON e.group_id = g.id AND e.status IN ('Confirmed', 'Completed')
GROUP BY c.id, c.title, cat.name, t.full_name
ORDER BY students DESC, revenue DESC",
        'params' => [],
        'money' => ['revenue'],
    ],
    'category' => [
        'title' => '2. Revenue by category (GROUP BY + HAVING)',
        'note'  => 'Only categories that earned money (HAVING SUM > 0). AVG = average check, MAX = the most expensive enrollment.',
        'sql'   => "SELECT cat.name AS category, COUNT(e.id) AS paid_enrollments,
       SUM(e.price_paid) AS revenue, ROUND(AVG(e.price_paid)) AS average_check,
       MAX(e.price_paid) AS max_price
FROM categories cat
JOIN courses c ON c.category_id = cat.id
JOIN course_groups g ON g.course_id = c.id
JOIN enrollments e ON e.group_id = g.id
WHERE e.status IN ('Confirmed', 'Completed')
GROUP BY cat.id, cat.name
HAVING SUM(e.price_paid) > 0
ORDER BY revenue DESC",
        'params' => [],
        'money' => ['revenue', 'average_check', 'max_price'],
        'chart' => ['label' => 'category', 'value' => 'revenue'],
    ],
    'monthly' => [
        'title' => '3. Enrollments by month (date-based report, last 6 months)',
        'note'  => 'DATE_FORMAT groups the dates by month; CASE counts cancelled enrollments and revenue in the same query.',
        'sql'   => "SELECT DATE_FORMAT(e.created_at, '%Y-%m') AS month,
       COUNT(*) AS enrollments,
       SUM(CASE WHEN e.status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
       SUM(CASE WHEN e.status IN ('Confirmed', 'Completed') THEN e.price_paid ELSE 0 END) AS revenue
FROM enrollments e
WHERE e.created_at >= ?
GROUP BY DATE_FORMAT(e.created_at, '%Y-%m')
ORDER BY month",
        'params' => [$from],
        'money' => ['revenue'],
        'chart' => ['label' => 'month', 'value' => 'enrollments'],
    ],
    'teachers' => [
        'title' => '4. Teachers: courses, groups, active students and rating',
        'note'  => 'LEFT JOIN of 4 tables with COUNT(DISTINCT ...) + correlated subquery for the average rating of all courses of the teacher.',
        'sql'   => "SELECT t.full_name AS teacher,
       COUNT(DISTINCT c.id) AS courses,
       COUNT(DISTINCT g.id) AS groups_count,
       COUNT(DISTINCT e.id) AS active_students,
       (SELECT ROUND(AVG(r.rating), 1) FROM reviews r
          JOIN courses c2 ON c2.id = r.course_id WHERE c2.teacher_id = t.id) AS rating
FROM teachers t
LEFT JOIN courses c ON c.teacher_id = t.id
LEFT JOIN course_groups g ON g.course_id = c.id
LEFT JOIN enrollments e ON e.group_id = g.id AND e.status IN ('Pending', 'Confirmed')
GROUP BY t.id, t.full_name
ORDER BY active_students DESC, courses DESC",
        'params' => [],
    ],
    'fill' => [
        'title' => '5. Fill rate of running and upcoming groups',
        'note'  => 'How full every group is (taken / capacity). Date filter: only groups that are not finished.',
        'sql'   => "SELECT c.title, g.name AS group_name, g.start_date, g.capacity,
       COUNT(e.id) AS taken,
       ROUND(COUNT(e.id) * 100.0 / g.capacity) AS fill_percent
FROM course_groups g
JOIN courses c ON c.id = g.course_id
LEFT JOIN enrollments e ON e.group_id = g.id AND e.status IN ('Pending', 'Confirmed')
WHERE g.end_date >= ?
GROUP BY g.id, c.title, g.name, g.start_date, g.capacity
ORDER BY fill_percent DESC, g.start_date",
        'params' => [$today],
        'dates' => ['start_date'],
    ],
    'inactive' => [
        'title' => '6. Students who have not enrolled in any course',
        'note'  => 'LEFT JOIN ... WHERE e.id IS NULL — users without any matching row in enrollments. Useful for marketing emails.',
        'sql'   => "SELECT u.first_name, u.last_name, u.email, u.created_at AS registered
FROM users u
LEFT JOIN enrollments e ON e.user_id = u.id
WHERE u.role = 'student' AND e.id IS NULL
ORDER BY u.created_at DESC",
        'params' => [],
        'dates' => ['registered'],
    ],
    'above_avg' => [
        'title' => '7. Courses rated above the platform average',
        'note'  => 'Subquery inside HAVING: the average of the course is compared with the average of all reviews.',
        'sql'   => "SELECT c.title, ROUND(AVG(r.rating), 2) AS avg_rating, COUNT(r.id) AS reviews,
       (SELECT ROUND(AVG(rating), 2) FROM reviews) AS platform_avg
FROM courses c
JOIN reviews r ON r.course_id = c.id
GROUP BY c.id, c.title
HAVING AVG(r.rating) > (SELECT AVG(rating) FROM reviews)
ORDER BY avg_rating DESC",
        'params' => [],
    ],
    'progress' => [
        'title' => '8. Learning progress of students (confirmed enrollments)',
        'note'  => 'JOIN enrollments → users → groups → courses, LEFT JOIN lessons and lesson_progress, percent of completed lessons.',
        'sql'   => "SELECT u.first_name, u.last_name, c.title, e.status,
       COUNT(DISTINCT l.id) AS lessons,
       COUNT(DISTINCT p.lesson_id) AS completed,
       ROUND(COUNT(DISTINCT p.lesson_id) * 100.0 / NULLIF(COUNT(DISTINCT l.id), 0)) AS percent
FROM enrollments e
JOIN users u ON u.id = e.user_id
JOIN course_groups g ON g.id = e.group_id
JOIN courses c ON c.id = g.course_id
LEFT JOIN lessons l ON l.course_id = c.id
LEFT JOIN lesson_progress p ON p.lesson_id = l.id AND p.user_id = u.id
WHERE e.status IN ('Confirmed', 'Completed')
GROUP BY e.id, u.first_name, u.last_name, c.title, e.status
ORDER BY percent DESC, u.last_name",
        'params' => [],
    ],
    'slots' => [
        'title' => '9. The busiest time slots of the week',
        'note'  => 'group_sessions + groups + enrollments: how many students study at the same weekday and time (HAVING ≥ 2).',
        'sql'   => "SELECT s.weekday, s.start_time, s.end_time,
       COUNT(DISTINCT e.user_id) AS students,
       COUNT(DISTINCT g.id) AS groups_count
FROM group_sessions s
JOIN course_groups g ON g.id = s.group_id
JOIN enrollments e ON e.group_id = g.id AND e.status IN ('Pending', 'Confirmed')
WHERE g.end_date >= ?
GROUP BY s.weekday, s.start_time, s.end_time
HAVING COUNT(DISTINCT e.user_id) >= 2
ORDER BY students DESC, s.weekday
LIMIT 8",
        'params' => [$today],
        'weekday' => ['weekday'],
    ],
];

// run every query (prepared statements)
foreach ($reports as $key => &$report) {
    $report['rows'] = db_all($report['sql'], $report['params']);
}
unset($report);

function report_cell(array $report, string $column, $value): string
{
    if ($value === null) {
        return '—';
    }
    if (in_array($column, $report['money'] ?? [], true)) {
        return format_price($value);
    }
    if (in_array($column, $report['dates'] ?? [], true)) {
        return format_date($value);
    }
    if (in_array($column, $report['weekday'] ?? [], true)) {
        return h(WEEKDAYS[(int)$value] ?? $value);
    }
    if ($column === 'start_time' || $column === 'end_time') {
        return h(format_time($value));
    }
    if ($column === 'percent' || $column === 'fill_percent') {
        return '<span class="mini-bar"><span style="width:' . min(100, (int)$value) . '%"></span></span> ' . (int)$value . '%';
    }
    return h($value);
}

admin_start('Reports (SQL)', 'reports', 'Statistics calculated by MySQL. Open "Show SQL" to see the query.');
?>
<nav class="report-nav" aria-label="Reports">
  <?php foreach ($reports as $key => $r): ?><a href="#report-<?= $key ?>"><?= h(strtok($r['title'], ':(')) ?></a><?php endforeach; ?>
</nav>

<?php foreach ($reports as $key => $r): ?>
  <section class="panel panel--spaced report" id="report-<?= $key ?>">
    <h2 class="panel__title"><?= h($r['title']) ?> <span class="text-muted small"><?= count($r['rows']) ?> rows</span></h2>
    <p class="text-muted small"><?= h($r['note']) ?></p>

    <?php if (!empty($r['chart']) && $r['rows']):
        $max = max(1, max(array_map(fn($row) => (float)$row[$r['chart']['value']], $r['rows']))); ?>
      <div class="bar-chart" role="img" aria-label="Chart: <?= h($r['title']) ?>">
        <?php foreach ($r['rows'] as $row): $v = (float)$row[$r['chart']['value']]; ?>
          <div class="bar-chart__item">
            <span class="bar-chart__value"><?= in_array($r['chart']['value'], $r['money'] ?? [], true) ? format_price($v) : (int)$v ?></span>
            <span class="bar-chart__bar" style="height: <?= max(4, round($v * 100 / $max)) ?>%"></span>
            <span class="bar-chart__label"><?= h($row[$r['chart']['label']]) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><?php foreach (array_keys($r['rows'][0] ?? []) as $col): ?><th><?= h(ucfirst(str_replace('_', ' ', $col))) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($r['rows'] as $row): ?>
            <tr><?php foreach ($row as $col => $value): ?><td><?= report_cell($r, $col, $value) ?></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
          <?php if (!$r['rows']): ?><tr><td class="text-muted">No rows: the query returned an empty result.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <details class="sql-box">
      <summary>Show SQL</summary>
      <pre><code><?= h($r['sql']) ?></code></pre>
    </details>
  </section>
<?php endforeach; ?>
<?php admin_end(); ?>
