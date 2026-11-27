<?php
/* =========================================================
   index.php — Home page: hero, user information, features,
   statistics and popular courses (all numbers come from MySQL).
   ========================================================= */
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$pageTitle = 'Online Course Platform';
$activePage = 'home';

// Statistics (COUNT queries)
$stats = db_one("SELECT
    (SELECT COUNT(*) FROM courses WHERE is_published = 1) AS courses,
    (SELECT COUNT(*) FROM users WHERE role = 'student') AS students,
    (SELECT COUNT(*) FROM enrollments WHERE status <> 'Cancelled') AS enrollments,
    (SELECT COUNT(*) FROM lessons WHERE video_url IS NOT NULL) AS videos");

$popular = popular_courses(3);

// Groups that start in the next 30 days (date-based query)
$upcoming = db_all("
    SELECT g.id, g.name, g.format, g.start_date, c.id AS course_id, c.title,
           g.capacity - (SELECT COUNT(*) FROM enrollments e
                         WHERE e.group_id = g.id AND e.status IN ('Pending', 'Confirmed')) AS seats_left
    FROM course_groups g
    JOIN courses c ON c.id = g.course_id
    WHERE c.is_published = 1 AND g.start_date BETWEEN ? AND ?
    ORDER BY g.start_date, c.title
    LIMIT 5", [date('Y-m-d'), date('Y-m-d', strtotime('+30 days'))]);

if ($user) {
    $activeCount = (int)db_value("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND status IN ('Pending', 'Confirmed')", [$user['id']]);
    $nextClass = next_class((int)$user['id']);
}

include __DIR__ . '/includes/header.php';
?>
<!-- Hero: main title + call to action -->
<section class="hero">
  <div class="container hero__inner">
    <div>
      <h1>Learn new skills online with EduConnect</h1>
      <p>Programming, design, data, marketing and language courses with video lessons, materials and a convenient schedule. Register, pick a group and enroll in less than two minutes.</p>
      <div class="hero__buttons">
        <?php if ($user): ?>
          <a href="<?= h(url($user['role'] === 'admin' ? 'admin/index.php' : 'profile.php#my-courses')) ?>" class="btn btn--light">
            <?= $user['role'] === 'admin' ? 'Open admin panel' : 'Continue learning' ?></a>
        <?php else: ?>
          <a href="<?= h(url('register.php')) ?>" class="btn btn--light">Get started</a>
        <?php endif; ?>
        <a href="<?= h(url('pages/courses.php')) ?>" class="btn btn--outline btn--on-dark">View courses</a>
      </div>
    </div>
    <img class="hero__image" src="<?= h(url('images/hero.svg')) ?>" alt="Online learning illustration">
  </div>
</section>

<?php if ($user): ?>
<!-- User information -->
<div class="container">
  <section class="user-box" aria-label="User information">
    <div class="user-box__info">
      <div class="avatar avatar--small"><?= avatar_html($user) ?></div>
      <div>
        <h2 class="user-box__title">Welcome back, <?= h($user['first_name'] . ' ' . $user['last_name']) ?>!</h2>
        <p class="text-muted"><?= h($user['email']) ?>
          <?php if ($user['role'] === 'student'): ?> · Active courses: <strong><?= $activeCount ?></strong><?php endif; ?></p>
        <?php if (!empty($nextClass)): ?>
          <p class="user-box__next">📅 Next class: <strong><?= h($nextClass['title']) ?></strong> —
            <?= h(WEEKDAYS[(int)date('N', $nextClass['timestamp'])]) ?>, <?= date('d.m', $nextClass['timestamp']) ?>
            at <?= h(format_time($nextClass['start_time'])) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <div class="user-box__actions">
      <a href="<?= h(url('profile.php')) ?>" class="btn btn--small">My profile</a>
      <form action="<?= h(url('logout.php')) ?>" method="post" data-confirm="Are you sure you want to log out?">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--danger btn--small">Logout</button>
      </form>
    </div>
  </section>
</div>
<?php endif; ?>

<!-- Features -->
<section class="section">
  <div class="container">
    <h2 class="section__title">Why EduConnect?</h2>
    <p class="section__subtitle">Everything you need to learn comfortably</p>
    <div class="features">
      <article class="feature">
        <div class="feature__icon">🎬</div>
        <h3>Video lessons & materials</h3>
        <p>Every course has video lessons, PDF syllabi and links to the best documentation.</p>
      </article>
      <article class="feature">
        <div class="feature__icon">🕒</div>
        <h3>Smart schedule</h3>
        <p>Choose a group with a convenient time. The system will not let two courses overlap.</p>
      </article>
      <article class="feature">
        <div class="feature__icon">🎓</div>
        <h3>Progress & certificates</h3>
        <p>Mark lessons as completed, follow your progress and get a certificate at the end.</p>
      </article>
    </div>
  </div>
</section>

<!-- Statistics (numbers come from the database) -->
<section class="section section--white">
  <div class="container stats">
    <div class="stats__item"><strong><?= (int)$stats['courses'] ?></strong>courses</div>
    <div class="stats__item"><strong><?= (int)$stats['students'] ?></strong>students</div>
    <div class="stats__item"><strong><?= (int)$stats['enrollments'] ?></strong>enrollments</div>
    <div class="stats__item"><strong><?= (int)$stats['videos'] ?></strong>video lessons</div>
  </div>
</section>

<!-- Popular courses -->
<section class="section">
  <div class="container">
    <h2 class="section__title">Popular courses</h2>
    <p class="section__subtitle">Courses with the most students and the best reviews</p>
    <div class="cards">
      <?php foreach ($popular as $course): ?>
        <?= course_card_html($course) ?>
      <?php endforeach; ?>
    </div>
    <p class="center-block"><a href="<?= h(url('pages/courses.php')) ?>" class="btn">See all courses</a></p>
  </div>
</section>

<?php if ($upcoming): ?>
<!-- Groups that start soon -->
<section class="section section--white">
  <div class="container">
    <h2 class="section__title">Groups starting soon</h2>
    <p class="section__subtitle">Join a new group in the next 30 days</p>
    <ul class="upcoming">
      <?php foreach ($upcoming as $g): ?>
        <li class="upcoming__item">
          <div class="upcoming__date"><strong><?= date('d', strtotime($g['start_date'])) ?></strong><?= date('M', strtotime($g['start_date'])) ?></div>
          <div class="upcoming__info">
            <a href="<?= h(url('pages/details.php?id=' . (int)$g['course_id'])) ?>"><strong><?= h($g['title']) ?></strong></a>
            <span class="text-muted"><?= h($g['name']) ?> · <?= h($g['format']) ?></span>
          </div>
          <span class="badge <?= (int)$g['seats_left'] > 0 ? 'badge--confirmed' : 'badge--cancelled' ?>">
            <?= (int)$g['seats_left'] > 0 ? (int)$g['seats_left'] . ' seats left' : 'Full' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<!-- Call to action -->
<section class="container">
  <div class="cta">
    <h2>Ready to start learning?</h2>
    <p>Create a free account and enroll in your first course today.</p>
    <a href="<?= h(url('pages/courses.php')) ?>" class="btn">Find a course</a>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
