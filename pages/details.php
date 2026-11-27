<?php
/* =========================================================
   pages/details.php?id=3 — one course: intro video, description,
   curriculum (video lessons), groups with schedule and seats,
   reviews (new review is sent with AJAX by js/details.js).
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';

$id = int_param($_GET, 'id');
$user = current_user();
$course = $id ? get_course($id, !($user && $user['role'] === 'admin')) : false;
if (!$course) {
    error_page(404, 'Course not found', 'This course does not exist or was removed from the catalog.');
}

$features = course_features($id);
$groups = course_groups($id);
$lessons = course_lessons($id);
$reviews = course_reviews($id);
$distribution = rating_distribution($id);

$enrollment = null;
$hasAccess = can_access_course($user, $id);
$canReview = false;
if ($user && $user['role'] === 'student') {
    $enrollment = db_one("SELECT e.id, e.status FROM enrollments e JOIN course_groups g ON g.id = e.group_id
                          WHERE e.user_id = ? AND g.course_id = ? AND e.status <> 'Cancelled'
                          ORDER BY e.id DESC LIMIT 1", [$user['id'], $id]);
    $canReview = $hasAccess && !db_value('SELECT COUNT(*) FROM reviews WHERE user_id = ? AND course_id = ?', [$user['id'], $id]);
}
$openGroups = array_filter($groups, fn($g) => !$g['is_over'] && $g['seats_left'] > 0);

$pageTitle = $course['title'];
$activePage = 'courses';
$pageScripts = ['details.js'];
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <p class="breadcrumbs">
    <a href="<?= h(url('index.php')) ?>">Home</a> › <a href="courses.php">Courses</a> › <span><?= h($course['title']) ?></span>
  </p>

  <div class="details">
    <article class="details__main">
      <?php if ($course['intro_video']): ?>
        <?= render_video($course['intro_video'], $course['title'] . ' — introduction', url('images/courses/' . $course['image'])) ?>
      <?php else: ?>
        <img class="details__image" src="<?= h(url('images/courses/' . $course['image'])) ?>" alt="<?= h($course['title']) ?>">
      <?php endif; ?>

      <div class="details__content">
        <span class="tag"><?= h($course['category']) ?></span>
        <span class="tag"><?= h($course['level']) ?></span>
        <?php if (!$course['is_published']): ?><span class="tag tag--warning">Not published</span><?php endif; ?>
        <h1><?= h($course['title']) ?></h1>
        <p class="stars"><?= render_stars($course['rating']) ?>
          <span class="text-muted"><?= $course['rating'] > 0 ? h($course['rating']) . ' / 5 · ' . (int)$course['reviews_count'] . ' reviews' : 'No reviews yet' ?>
            · <?= (int)$course['students'] ?> students</span></p>

        <h2>Description</h2>
        <p><?= nl2br(h($course['description'])) ?></p>

        <h2>What you will get</h2>
        <ul class="feature-list">
          <?php foreach ($features as $f): ?><li><?= h($f['feature']) ?></li><?php endforeach; ?>
        </ul>

        <!-- Curriculum: video lessons and materials -->
        <h2 id="curriculum">Curriculum · <?= count($lessons) ?> lessons</h2>
        <ol class="lesson-list">
          <?php foreach ($lessons as $l): $open = $hasAccess || $l['is_preview']; ?>
            <li class="lesson-list__item<?= $open ? '' : ' is-locked' ?>">
              <span class="lesson-list__icon" aria-hidden="true"><?= $l['video_url'] ? '▶' : '📄' ?></span>
              <span class="lesson-list__title">
                <?php if ($open): ?>
                  <a href="learn.php?course=<?= $id ?>&amp;lesson=<?= (int)$l['id'] ?>"><?= h($l['title']) ?></a>
                <?php else: ?>
                  <?= h($l['title']) ?>
                <?php endif; ?>
                <?php if ($l['is_preview']): ?><span class="badge badge--confirmed">Free preview</span><?php endif; ?>
              </span>
              <span class="text-muted"><?= (int)$l['duration_min'] ?> min <?= $open ? '' : '🔒' ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
        <?php if (!$hasAccess): ?>
          <p class="text-muted small">🔒 Lessons open after the administrator confirms your enrollment. The first lesson is a free preview.</p>
        <?php endif; ?>

        <!-- Groups and schedule -->
        <h2 id="groups">Groups and schedule</h2>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Group</th><th>Days and time</th><th>Dates</th><th>Format</th><th>Seats</th></tr></thead>
            <tbody>
            <?php foreach ($groups as $g): ?>
              <tr>
                <td><strong><?= h($g['name']) ?></strong></td>
                <td><?= h(format_sessions($g['sessions'])) ?></td>
                <td><?= format_date($g['start_date']) ?> – <?= format_date($g['end_date']) ?></td>
                <td><?= h($g['format']) ?><br><span class="text-muted"><?= h($g['location']) ?></span></td>
                <td>
                  <?php if ($g['is_over']): ?><span class="badge badge--completed">Finished</span>
                  <?php elseif ($g['seats_left'] === 0): ?><span class="badge badge--cancelled">Full</span>
                  <?php else: ?><span class="badge badge--confirmed"><?= $g['seats_left'] ?> of <?= (int)$g['capacity'] ?> left</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$groups): ?><tr><td colspan="5" class="text-muted">No groups yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>

        <h2>Teacher</h2>
        <div class="teacher-box">
          <div class="avatar avatar--small"><?= h(mb_substr($course['teacher'], 0, 1)) ?></div>
          <div><strong><?= h($course['teacher']) ?></strong><p class="text-muted"><?= h($course['teacher_bio']) ?></p></div>
        </div>

        <!-- Reviews -->
        <h2 id="reviews">Reviews</h2>
        <div class="reviews-summary">
          <div class="reviews-summary__avg">
            <strong id="avgRating"><?= $course['rating'] > 0 ? h($course['rating']) : '—' ?></strong>
            <span class="stars" id="avgStars"><?= render_stars($course['rating']) ?></span>
            <span class="text-muted"><span id="reviewsCount"><?= (int)$course['reviews_count'] ?></span> reviews</span>
          </div>
          <ul class="rating-bars" aria-label="Rating distribution">
            <?php $maxCount = max(1, max($distribution)); foreach ($distribution as $stars => $count): ?>
              <li><span><?= $stars ?> ★</span><span class="rating-bars__bar"><span style="width: <?= round($count * 100 / $maxCount) ?>%"></span></span><span><?= $count ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <?php if ($canReview): ?>
          <form class="review-form panel" id="reviewForm" data-course="<?= $id ?>" novalidate>
            <h3>Leave a review</h3>
            <div class="form-group">
              <span class="form-label" id="ratingLabel">Your rating <span class="required">*</span></span>
              <div class="star-input" role="radiogroup" aria-labelledby="ratingLabel">
                <?php for ($s = 5; $s >= 1; $s--): ?>
                  <input type="radio" id="star<?= $s ?>" name="rating" value="<?= $s ?>"><label for="star<?= $s ?>" title="<?= $s ?> stars">★</label>
                <?php endfor; ?>
              </div>
              <p class="error-message"></p>
            </div>
            <div class="form-group">
              <label for="reviewComment">Comment <span class="required">*</span></label>
              <textarea id="reviewComment" name="comment" maxlength="500" placeholder="What did you like? What can be better?"></textarea>
              <p class="char-counter" id="reviewCounter">0 / 500</p>
              <p class="error-message"></p>
            </div>
            <button type="submit" class="btn" id="reviewSubmit">Send review</button>
          </form>
        <?php endif; ?>

        <ul class="reviews" id="reviewsList">
          <?php foreach ($reviews as $r): ?>
            <li class="review">
              <div class="review__head"><strong><?= h($r['first_name'] . ' ' . mb_substr($r['last_name'], 0, 1) . '.') ?></strong>
                <span class="stars"><?= render_stars($r['rating']) ?></span>
                <span class="text-muted"><?= format_date($r['created_at']) ?></span></div>
              <p><?= h($r['comment']) ?></p>
            </li>
          <?php endforeach; ?>
          <?php if (!$reviews): ?><li class="text-muted" id="noReviews">No reviews yet. Students who study this course can leave the first review.</li><?php endif; ?>
        </ul>
      </div>
    </article>

    <aside class="details__aside">
      <p class="details__price <?= (int)$course['price'] === 0 ? 'price--free' : '' ?>"><?= format_price($course['price']) ?></p>
      <ul class="details__facts">
        <li><span>Teacher</span><span><?= h($course['teacher']) ?></span></li>
        <li><span>Duration</span><span><?= (int)$course['duration_weeks'] ?> weeks</span></li>
        <li><span>Lessons</span><span><?= count($lessons) ?> (video + materials)</span></li>
        <li><span>Level</span><span><?= h($course['level']) ?></span></li>
        <li><span>Open groups</span><span><?= count($openGroups) ?></span></li>
        <li><span>Certificate</span><span>Yes</span></li>
      </ul>
      <div class="details__actions">
        <?php if (!$user): ?>
          <a href="<?= h(url('login.php?redirect=' . urlencode('pages/enroll.php?id=' . $id))) ?>" class="btn btn--full">Log in to enroll</a>
          <p class="text-muted center small">No account? <a href="<?= h(url('register.php')) ?>">Register for free</a></p>
        <?php elseif ($user['role'] === 'admin'): ?>
          <div class="alert alert--info">You are logged in as administrator.</div>
          <a href="<?= h(url('admin/course_form.php?id=' . $id)) ?>" class="btn btn--full">✎ Edit course</a>
          <a href="<?= h(url('admin/groups.php?course=' . $id)) ?>" class="btn btn--outline btn--full">Manage groups</a>
          <a href="learn.php?course=<?= $id ?>" class="btn btn--outline btn--full">▶ Open lessons</a>
        <?php elseif ($enrollment): ?>
          <div class="alert alert--success">✓ You are enrolled (#<?= (int)$enrollment['id'] ?>, status: <?= h($enrollment['status']) ?>)</div>
          <?php if ($hasAccess): ?>
            <a href="learn.php?course=<?= $id ?>" class="btn btn--full">▶ Go to lessons</a>
          <?php else: ?>
            <p class="text-muted small">Lessons will open after the administrator confirms your enrollment.</p>
          <?php endif; ?>
          <a href="<?= h(url('profile.php#my-courses')) ?>" class="btn btn--outline btn--full">View my courses</a>
        <?php elseif ($openGroups): ?>
          <a href="enroll.php?id=<?= $id ?>" class="btn btn--full">Enroll now</a>
        <?php else: ?>
          <div class="alert alert--info">There are no open groups right now. Please check later.</div>
        <?php endif; ?>
        <a href="courses.php" class="btn btn--outline btn--full">← Back to courses</a>
      </div>
    </aside>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
