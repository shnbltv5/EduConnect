<?php
/* =========================================================
   admin/enrollments.php — all enrollments: filters by status,
   course and student, pagination. The status is changed with
   AJAX (js/admin.js -> api/admin_enrollment.php): the row and
   the counters are updated without reloading the page.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

const STATUSES = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
$status = in_array($_GET['status'] ?? '', STATUSES, true) ? $_GET['status'] : '';
$courseId = int_param($_GET, 'course');
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60);
$page = max(1, int_param($_GET, 'page'));
$perPage = 12;

$where = '1 = 1';
$params = [];
if ($status !== '') {
    $where .= ' AND e.status = ?';
    $params[] = $status;
}
if ($courseId) {
    $where .= ' AND c.id = ?';
    $params[] = $courseId;
}
if ($q !== '') {
    $where .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$from = 'FROM enrollments e JOIN users u ON u.id = e.user_id JOIN course_groups g ON g.id = e.group_id JOIN courses c ON c.id = g.course_id';
$total = (int)db_value("SELECT COUNT(*) $from WHERE $where", $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$rows = db_all("SELECT e.*, u.first_name, u.last_name, u.email, c.title, g.name AS group_name, g.format, g.id AS gid
                $from WHERE $where ORDER BY e.created_at DESC, e.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
$counts = [];
foreach (db_all('SELECT status, COUNT(*) AS cnt FROM enrollments GROUP BY status') as $r) {
    $counts[$r['status']] = (int)$r['cnt'];
}
$courses = db_all('SELECT id, title FROM courses ORDER BY title');

admin_start('Enrollments', 'enrollments');
?>
<div class="status-tabs" id="statusCounters">
  <a href="enrollments.php" class="status-tab<?= $status === '' ? ' active' : '' ?>">All <strong><?= array_sum($counts) ?></strong></a>
  <?php foreach (STATUSES as $s): ?>
    <a href="?status=<?= $s ?>" class="status-tab<?= $status === $s ? ' active' : '' ?>"><?= $s ?> <strong data-count="<?= $s ?>"><?= $counts[$s] ?? 0 ?></strong></a>
  <?php endforeach; ?>
</div>

<div class="panel">
  <form class="admin-filters" method="get">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="Student name or email..." aria-label="Search student">
    <select name="course" aria-label="Course">
      <option value="">All courses</option>
      <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $courseId === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['title']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn--small" type="submit">Filter</button>
    <a class="btn btn--outline btn--small" href="enrollments.php">Reset</a>
  </form>
  <p class="results-info"><?= $total ?> enrollments</p>
  <div class="table-wrap">
    <table class="table" id="enrollmentsTable">
      <thead><tr><th>ID</th><th>Student</th><th>Course / group</th><th>Created</th><th>Paid</th><th>Comment</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $e): ?>
        <tr data-row="<?= (int)$e['id'] ?>">
          <td>#<?= (int)$e['id'] ?></td>
          <td><?= h($e['first_name'] . ' ' . $e['last_name']) ?><br><span class="text-muted small"><?= h($e['email']) ?> · <?= h($e['phone']) ?></span></td>
          <td><?= h($e['title']) ?><br><span class="text-muted small"><?= h($e['group_name']) ?> · <?= h(format_sessions(group_sessions((int)$e['gid']))) ?></span></td>
          <td><?= format_datetime($e['created_at']) ?></td>
          <td><?= format_price($e['price_paid']) ?></td>
          <td class="small"><?= h($e['comment'] ?: '—') ?></td>
          <td>
            <select class="status-select status-select--<?= h(strtolower($e['status'])) ?>" data-status-id="<?= (int)$e['id'] ?>" data-current="<?= h($e['status']) ?>" aria-label="Status of enrollment #<?= (int)$e['id'] ?>">
              <?php foreach (STATUSES as $s): ?><option <?= $s === $e['status'] ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
            </select>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" class="text-muted center">No enrollments</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= admin_pagination($page, $pages) ?>
</div>
<?php admin_end(); ?>
