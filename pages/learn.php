<?php
/* =========================================================
   pages/learn.php?course=1&lesson=2 — lessons of a course:
   video (local MP4 or YouTube), description, materials (PDF,
   links), list of lessons with progress.
   ACCESS: the free preview lesson is open for everyone; all other
   lessons only for students with a Confirmed/Completed enrollment
   (and for admins). The check is done on the server.
   "Mark as completed" is sent with AJAX (js/learn.js).
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';

$user = current_user();
$courseId = int_param($_GET, 'course');
$course = $courseId ? get_course($courseId, !($user && $user['role'] === 'admin')) : false;
if (!$course) {
    error_page(404, 'Course not found', 'This course does not exist.');
}
$lessons = course_lessons($courseId);
if (!$lessons) {
    error_page(404, 'No lessons yet', 'The teacher has not added lessons to this course yet.');
}
$hasAccess = can_access_course($user, $courseId);

// Which lesson to show: from the URL, otherwise the first lesson
$lessonId = int_param($_GET, 'lesson');
$current = null;
foreach ($lessons as $i => $l) {
    if ((int)$l['id'] === $lessonId || ($lessonId === 0 && $i === 0)) {
        $current = $l;
        $index = $i;
    }
}
if ($current === null) {
    error_page(404, 'Lesson not found', 'This lesson does not belong to the course.');
}
$isOpen = $hasAccess || $current['is_preview'];
$materials = $isOpen ? lesson_materials((int)$current['id']) : [];

$canTrack = $user && $user['role'] === 'student' && $hasAccess;
$done = $canTrack ? completed_lessons((int)$user['id'], $courseId) : [];
$progress = $canTrack ? course_progress((int)$user['id'], $courseId) : null;
$prev = $lessons[$index - 1] ?? null;
$next = $lessons[$index + 1] ?? null;

$pageTitle = $current['title'] . ' — ' . $course['title'];
$activePage = $user ? 'profile' : 'courses';
$pageScripts = ['learn.js'];
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <p class="breadcrumbs">
    <a href="courses.php">Courses</a> › <a href="details.php?id=<?= $courseId ?>"><?= h($course['title']) ?></a> › <span>Lessons</span>
  </p>

  <div class="learn">
    <!-- Lessons list -->
    <aside class="learn__sidebar panel">
      <h2 class="learn__course"><?= h($course['title']) ?></h2>
      <?php if ($progress): ?>
        <div class="progress" aria-label="Course progress">
          <div class="progress__bar"><span id="progressBar" style="width: <?= $progress['percent'] ?>%"></span></div>
          <p class="text-muted small"><span id="progressText"><?= $progress['done'] ?> of <?= $progress['total'] ?> lessons completed (<?= $progress['percent'] ?>%)</span></p>
        </div>
      <?php endif; ?>
      <ol class="learn__lessons">
        <?php foreach ($lessons as $l): $open = $hasAccess || $l['is_preview']; ?>
          <li class="<?= (int)$l['id'] === (int)$current['id'] ? 'active' : '' ?><?= $open ? '' : ' is-locked' ?>" data-lesson="<?= (int)$l['id'] ?>">
            <a href="learn.php?course=<?= $courseId ?>&amp;lesson=<?= (int)$l['id'] ?>"<?= (int)$l['id'] === (int)$current['id'] ? ' aria-current="page"' : '' ?>>
              <span class="learn__check" aria-hidden="true" data-icon="<?= $l['video_url'] ? '▶' : '📄' ?>"><?= isset($done[(int)$l['id']]) ? '✓' : ($open ? ($l['video_url'] ? '▶' : '📄') : '🔒') ?></span>
              <span><?= (int)$l['position'] ?>. <?= h($l['title']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ol>
    </aside>

    <!-- Current lesson -->
    <article class="learn__main panel">
      <p class="text-muted small">Lesson <?= (int)$current['position'] ?> of <?= count($lessons) ?> · <?= (int)$current['duration_min'] ?> min
        <?php if ($current['is_preview']): ?><span class="badge badge--confirmed">Free preview</span><?php endif; ?></p>
      <h1 class="learn__title"><?= h($current['title']) ?></h1>

      <?php if (!$isOpen): ?>
        <div class="locked-lesson">
          <p class="locked-lesson__icon">🔒</p>
          <h2>This lesson is locked</h2>
          <?php if (!$user): ?>
            <p>Log in and enroll in the course to watch all lessons.</p>
            <a class="btn" href="<?= h(url('login.php?redirect=' . urlencode('pages/learn.php?course=' . $courseId . '&lesson=' . $current['id']))) ?>">Log in</a>
          <?php elseif (active_enrollment_in_course((int)$user['id'], $courseId)): ?>
            <p>Your enrollment is waiting for confirmation by the administrator. After that all lessons will open.</p>
            <a class="btn" href="<?= h(url('profile.php#my-courses')) ?>">My courses</a>
          <?php else: ?>
            <p>Enroll in the course to get access to the video lessons and materials.</p>
            <a class="btn" href="enroll.php?id=<?= $courseId ?>">Enroll now</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?= render_video($current['video_url'], $current['title'], url('images/courses/' . $course['image'])) ?>

        <?php if ($current['summary']): ?><p class="learn__summary"><?= nl2br(h($current['summary'])) ?></p><?php endif; ?>

        <?php if ($materials): ?>
          <h2 class="learn__subtitle">Materials</h2>
          <ul class="materials">
            <?php foreach ($materials as $m): ?>
              <li>
                <span class="materials__icon" aria-hidden="true"><?= $m['type'] === 'pdf' ? '📕' : '🔗' ?></span>
                <a href="<?= h($m['type'] === 'pdf' ? url($m['url']) : $m['url']) ?>" target="_blank" rel="noopener"<?= $m['type'] === 'pdf' ? ' download' : '' ?>><?= h($m['title']) ?></a>
                <span class="text-muted small"><?= $m['type'] === 'pdf' ? 'PDF' : 'Link' ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if ($canTrack): ?>
          <?php $isDone = isset($done[(int)$current['id']]); ?>
          <button type="button" class="btn <?= $isDone ? 'btn--success' : '' ?>" id="completeButton"
                  data-lesson="<?= (int)$current['id'] ?>" data-done="<?= $isDone ? '1' : '0' ?>" aria-pressed="<?= $isDone ? 'true' : 'false' ?>">
            <?= $isDone ? '✓ Completed' : 'Mark as completed' ?></button>
        <?php elseif (!$hasAccess && $current['is_preview']): ?>
          <div class="alert alert--info">This is a free preview. <a href="details.php?id=<?= $courseId ?>">Enroll in the course</a> to open all lessons.</div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="learn__nav">
        <?php if ($prev): ?><a class="btn btn--outline btn--small" href="learn.php?course=<?= $courseId ?>&amp;lesson=<?= (int)$prev['id'] ?>">← Previous</a><?php else: ?><span></span><?php endif; ?>
        <?php if ($next): ?><a class="btn btn--small" href="learn.php?course=<?= $courseId ?>&amp;lesson=<?= (int)$next['id'] ?>">Next lesson →</a><?php endif; ?>
      </div>
    </article>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
