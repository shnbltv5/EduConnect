<?php
/* =========================================================
   admin/reviews.php — moderation of reviews: list with
   filter by rating, delete a review.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $deleted = db_exec('DELETE FROM reviews WHERE id = ?', [int_param($_POST, 'review_id')]);
    flash($deleted ? 'success' : 'error', $deleted ? 'Review deleted' : 'Review not found');
    redirect('admin/reviews.php');
}

$rating = int_param($_GET, 'rating');
$params = [];
$where = '';
if ($rating >= 1 && $rating <= 5) {
    $where = 'WHERE r.rating = ?';
    $params[] = $rating;
}
$reviews = db_all("SELECT r.*, u.first_name, u.last_name, u.email, c.title
                   FROM reviews r JOIN users u ON u.id = r.user_id JOIN courses c ON c.id = r.course_id
                   $where ORDER BY r.created_at DESC", $params);

admin_start('Reviews', 'reviews');
?>
<div class="panel">
  <form class="admin-filters" method="get">
    <select name="rating" aria-label="Rating" data-autosubmit>
      <option value="">All ratings</option>
      <?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>" <?= $rating === $s ? 'selected' : '' ?>><?= str_repeat('★', $s) ?></option><?php endfor; ?>
    </select>
    <noscript><button class="btn btn--small" type="submit">Filter</button></noscript>
  </form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Date</th><th>Student</th><th>Course</th><th>Rating</th><th>Comment</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($reviews as $r): ?>
        <tr>
          <td><?= format_date($r['created_at']) ?></td>
          <td><?= h($r['first_name'] . ' ' . $r['last_name']) ?><br><span class="text-muted small"><?= h($r['email']) ?></span></td>
          <td><?= h($r['title']) ?></td>
          <td class="stars"><?= render_stars($r['rating']) ?></td>
          <td><?= h($r['comment']) ?></td>
          <td><form method="post" class="inline-form" data-confirm="Delete this review?">
            <?= csrf_field() ?><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn--danger btn--small" type="submit">Delete</button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$reviews): ?><tr><td colspan="6" class="text-muted center">No reviews</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php admin_end(); ?>
