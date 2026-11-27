<?php
/* =========================================================
   admin/index.php — Dashboard: main numbers, revenue of the
   month, latest enrollments and groups that start soon.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$monthStart = date('Y-m-01');
$stats = db_one("SELECT
    (SELECT COUNT(*) FROM users WHERE role = 'student') AS students,
    (SELECT COUNT(*) FROM courses WHERE is_published = 1) AS courses,
    (SELECT COUNT(*) FROM enrollments WHERE status IN ('Pending', 'Confirmed')) AS active,
    (SELECT COUNT(*) FROM enrollments WHERE status = 'Pending') AS pending,
    (SELECT COALESCE(SUM(price_paid), 0) FROM enrollments
       WHERE status IN ('Confirmed', 'Completed') AND created_at >= ?) AS revenue_month,
    (SELECT COUNT(*) FROM users WHERE role = 'student' AND created_at >= ?) AS new_students", [$monthStart, $monthStart]);

$latest = db_all("SELECT e.id, e.status, e.created_at, u.first_name, u.last_name, c.title, g.name AS group_name
                  FROM enrollments e
                  JOIN users u ON u.id = e.user_id
                  JOIN course_groups g ON g.id = e.group_id
                  JOIN courses c ON c.id = g.course_id
                  ORDER BY e.created_at DESC, e.id DESC LIMIT 6");

$soon = db_all("SELECT g.id, g.name, g.start_date, g.capacity, c.id AS course_id, c.title,
                       (SELECT COUNT(*) FROM enrollments e WHERE e.group_id = g.id AND e.status IN ('Pending', 'Confirmed')) AS taken
                FROM course_groups g JOIN courses c ON c.id = g.course_id
                WHERE g.start_date BETWEEN ? AND ?
                ORDER BY g.start_date", [date('Y-m-d'), date('Y-m-d', strtotime('+14 days'))]);

admin_start('Dashboard', 'dashboard');
?>
<div class="stat-cards stat-cards--6">
  <div class="stat-card"><span>Students</span><strong><?= (int)$stats['students'] ?></strong><small>+<?= (int)$stats['new_students'] ?> this month</small></div>
  <div class="stat-card"><span>Published courses</span><strong><?= (int)$stats['courses'] ?></strong></div>
  <div class="stat-card"><span>Active enrollments</span><strong><?= (int)$stats['active'] ?></strong></div>
  <div class="stat-card"><span>Pending</span><strong><?= (int)$stats['pending'] ?></strong>
    <small><a href="enrollments.php?status=Pending">Review now →</a></small></div>
  <div class="stat-card"><span>Revenue (<?= date('F') ?>)</span><strong class="stat-card__money"><?= format_price($stats['revenue_month']) ?></strong></div>
</div>

<div class="panel panel--spaced">
  <h2 class="panel__title">Latest enrollments <a href="enrollments.php" class="btn btn--outline btn--small">All enrollments</a></h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>ID</th><th>Student</th><th>Course</th><th>Date</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($latest as $e): ?>
        <tr>
          <td>#<?= (int)$e['id'] ?></td>
          <td><?= h($e['first_name'] . ' ' . $e['last_name']) ?></td>
          <td><?= h($e['title']) ?><br><span class="text-muted small"><?= h($e['group_name']) ?></span></td>
          <td><?= format_datetime($e['created_at']) ?></td>
          <td><?= status_badge($e['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <h2 class="panel__title">Groups starting in the next 14 days</h2>
  <?php if (!$soon): ?><p class="text-muted">No groups start in the next two weeks.</p><?php endif; ?>
  <ul class="fill-list">
    <?php foreach ($soon as $g): $fill = (int)round($g['taken'] * 100 / max(1, $g['capacity'])); ?>
      <li>
        <div><a href="groups.php?course=<?= (int)$g['course_id'] ?>"><strong><?= h($g['title']) ?></strong></a> · <?= h($g['name']) ?>
          <span class="text-muted small">starts <?= format_date($g['start_date']) ?></span></div>
        <div class="fill-list__bar"><span style="width: <?= $fill ?>%"></span></div>
        <span class="small"><?= (int)$g['taken'] ?>/<?= (int)$g['capacity'] ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php admin_end(); ?>
