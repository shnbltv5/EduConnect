<?php
/* =========================================================
   admin/courses.php — courses: list (READ), publish/hide,
   DELETE. Create/Update are in course_form.php.
   A course with enrollments cannot be deleted (FOREIGN KEY
   enrollments.group_id ... ON DELETE RESTRICT) — the database
   error is caught and shown as a clear message.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = int_param($_POST, 'course_id');
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle') {
        db_exec('UPDATE courses SET is_published = 1 - is_published WHERE id = ?', [$id]);
        flash('success', 'Course #' . $id . ' visibility changed');
    } elseif ($action === 'delete') {
        try {
            $deleted = db_exec('DELETE FROM courses WHERE id = ?', [$id]);
            flash($deleted ? 'success' : 'error', $deleted ? 'Course deleted together with its groups, lessons and reviews' : 'Course not found');
        } catch (PDOException $e) {
            if (!is_constraint_error($e)) {
                throw $e;
            }
            flash('error', 'This course cannot be deleted: students are enrolled in its groups. Hide it from the catalog instead (Unpublish).');
        }
    }
    redirect('admin/courses.php');
}

$courses = db_all(COURSE_SELECT . ' ORDER BY c.id');
$counts = [];
foreach (db_all('SELECT course_id, COUNT(*) AS groups_count FROM course_groups GROUP BY course_id') as $r) {
    $counts[(int)$r['course_id']]['groups'] = (int)$r['groups_count'];
}
foreach (db_all('SELECT course_id, COUNT(*) AS lessons_count FROM lessons GROUP BY course_id') as $r) {
    $counts[(int)$r['course_id']]['lessons'] = (int)$r['lessons_count'];
}

admin_start('Courses', 'courses');
?>
<div class="panel">
  <h2 class="panel__title">All courses (<?= count($courses) ?>)
    <a class="btn btn--small" href="course_form.php">+ Add course</a></h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Image</th><th>Title</th><th>Category</th><th>Price</th><th>Groups</th><th>Lessons</th><th>Students</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($courses as $c): $cid = (int)$c['id']; ?>
        <tr>
          <td><img class="thumb" src="<?= h(url('images/courses/' . $c['image'])) ?>" alt=""></td>
          <td><a href="<?= h(url('pages/details.php?id=' . $cid)) ?>"><strong><?= h($c['title']) ?></strong></a><br><span class="text-muted small"><?= h($c['teacher']) ?></span></td>
          <td><?= h($c['category']) ?><br><span class="text-muted small"><?= h($c['level']) ?></span></td>
          <td><?= format_price($c['price']) ?></td>
          <td><a href="groups.php?course=<?= $cid ?>"><?= $counts[$cid]['groups'] ?? 0 ?></a></td>
          <td><a href="lessons.php?course=<?= $cid ?>"><?= $counts[$cid]['lessons'] ?? 0 ?></a></td>
          <td><?= (int)$c['students'] ?></td>
          <td><?= $c['rating'] > 0 ? '★ ' . h($c['rating']) : '—' ?></td>
          <td><?= $c['is_published'] ? '<span class="badge badge--confirmed">Published</span>' : '<span class="badge badge--pending">Hidden</span>' ?></td>
          <td>
            <div class="actions actions--wrap">
              <a class="btn btn--outline btn--small" href="course_form.php?id=<?= $cid ?>">Edit</a>
              <a class="btn btn--outline btn--small" href="lessons.php?course=<?= $cid ?>">Lessons</a>
              <a class="btn btn--outline btn--small" href="groups.php?course=<?= $cid ?>">Groups</a>
              <form method="post" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="course_id" value="<?= $cid ?>">
                <button class="btn btn--outline btn--small" type="submit"><?= $c['is_published'] ? 'Unpublish' : 'Publish' ?></button>
              </form>
              <form method="post" class="inline-form" data-confirm="Delete course &quot;<?= h($c['title']) ?>&quot;?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="course_id" value="<?= $cid ?>">
                <button class="btn btn--danger btn--small" type="submit">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php admin_end(); ?>
