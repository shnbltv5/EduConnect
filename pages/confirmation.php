<?php
/* =========================================================
   pages/confirmation.php?id=10455 — success page after enrollment.
   A student can see ONLY his own enrollment (server check):
   changing the id in the address bar gives "Access denied".
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$id = int_param($_GET, 'id');
$e = $id ? db_one("SELECT e.*, g.name AS group_name, g.format, g.start_date, g.end_date, g.location,
                          c.id AS course_id, c.title, u.first_name, u.last_name
                   FROM enrollments e
                   JOIN course_groups g ON g.id = e.group_id
                   JOIN courses c ON c.id = g.course_id
                   JOIN users u ON u.id = e.user_id
                   WHERE e.id = ?", [$id]) : false;
if (!$e) {
    error_page(404, 'Enrollment not found', 'We could not find this enrollment.');
}
if ((int)$e['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin') {
    error_page(403, 'Access denied', 'You can see only your own enrollments.');
}
$sessions = group_sessions((int)$e['group_id']);

$pageTitle = 'Enrollment confirmed';
$activePage = 'courses';
include __DIR__ . '/../includes/header.php';
?>
<section class="form-page">
  <div class="form-card success-card">
    <div class="success-icon">✓</div>
    <h1>Success!</h1>
    <p class="form-card__subtitle">Your enrollment request has been received.<br>The administrator will confirm it soon.</p>

    <ul class="info-list">
      <li><span>Enrollment ID</span><strong>#<?= (int)$e['id'] ?></strong></li>
      <li><span>Course</span><strong><?= h($e['title']) ?></strong></li>
      <li><span>Student</span><strong><?= h($e['first_name'] . ' ' . $e['last_name']) ?></strong></li>
      <li><span>Group</span><strong><?= h($e['group_name']) ?> · <?= h($e['format']) ?></strong></li>
      <li><span>Days and time</span><strong><?= h(format_sessions($sessions)) ?></strong></li>
      <li><span>Dates</span><strong><?= format_date($e['start_date']) ?> – <?= format_date($e['end_date']) ?></strong></li>
      <li><span>Place</span><strong><?= h($e['location']) ?></strong></li>
      <li><span>Price</span><strong><?= format_price($e['price_paid']) ?></strong></li>
      <li><span>Status</span><?= status_badge($e['status']) ?></li>
    </ul>

    <div class="button-row">
      <a href="<?= h(url('profile.php#my-courses')) ?>" class="btn">View my courses</a>
      <a href="details.php?id=<?= (int)$e['course_id'] ?>#curriculum" class="btn btn--outline">Watch the free lesson</a>
    </div>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
