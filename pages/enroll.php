<?php
/* =========================================================
   pages/enroll.php?id=3 — enrollment form.
   The student chooses a GROUP (days and time of classes).
   js/enroll.js checks the chosen group with AJAX (free seats,
   schedule conflicts) before sending; PHP checks everything
   again in enroll_user() inside a transaction.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$id = int_param($_GET, 'id');
$course = $id ? get_course($id) : false;
if (!$course) {
    error_page(404, 'Course not found', 'Please choose a course from the catalog.');
}
if ($user['role'] === 'admin') {
    flash('info', 'Administrators cannot enroll in courses. Please log in as a student.');
    redirect('pages/details.php?id=' . $id);
}
$existing = active_enrollment_in_course((int)$user['id'], $id);
if ($existing) {
    flash('info', 'You are already enrolled in this course (#' . $existing['id'] . ', ' . $existing['status'] . ').');
    redirect('pages/details.php?id=' . $id);
}

$groups = course_groups($id);
$data = ['phone' => $user['phone'] ?? ''];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = $_POST;
    $errors = validate_enrollment($data);
    $groupId = int_param($data, 'group_id');
    $groupIds = array_map(fn($g) => (int)$g['id'], $groups);
    if ($groupId && !in_array($groupId, $groupIds, true)) {
        $errors['group_id'] = 'This group does not belong to the course';    // changed form value
    }
    if (!$errors) {
        try {
            $enrollmentId = enroll_user((int)$user['id'], $groupId, $data['phone'], $data['comment'] ?? '');
            redirect('pages/confirmation.php?id=' . $enrollmentId);
        } catch (EnrollmentException $e) {
            $errors['form'] = $e->getMessage();                              // business rule was broken
        }
    }
}
$selectedGroup = int_param($data, 'group_id');

$pageTitle = 'Enroll — ' . $course['title'];
$activePage = 'courses';
$pageScripts = ['enroll.js'];
include __DIR__ . '/../includes/header.php';
?>
<section class="form-page">
  <div class="form-card form-card--wide">
    <h1>Enrollment form</h1>
    <p class="form-card__subtitle">Choose a group with a convenient time and confirm your enrollment</p>

    <div class="alert <?= $errors ? 'alert--error' : 'hidden' ?>" id="enrollAlert" role="alert">
      <?= h($errors['form'] ?? ($errors ? 'Please correct the errors below.' : '')) ?></div>

    <form id="enrollForm" method="post" action="enroll.php?id=<?= $id ?>" novalidate>
      <?= csrf_field() ?>
      <!-- Selected course -->
      <div class="course-summary">
        <img src="<?= h(url('images/courses/' . $course['image'])) ?>" alt="<?= h($course['title']) ?>">
        <div>
          <strong><?= h($course['title']) ?></strong>
          <p class="text-muted"><?= h($course['teacher']) ?> · <?= (int)$course['duration_weeks'] ?> weeks · <?= format_price($course['price']) ?></p>
        </div>
      </div>

      <!-- Group = days, time, dates and format -->
      <div class="<?= group_class($errors, 'group_id') ?>">
        <span class="form-label" id="groupLabel">Group (days and time) <span class="required">*</span></span>
        <div class="group-options" role="radiogroup" aria-labelledby="groupLabel">
          <?php foreach ($groups as $g):
              $disabled = $g['is_over'] || $g['seats_left'] === 0; ?>
            <label class="group-option<?= $disabled ? ' is-disabled' : '' ?>">
              <input type="radio" name="group_id" value="<?= (int)$g['id'] ?>" <?= $disabled ? 'disabled' : '' ?>
                     <?= $selectedGroup === (int)$g['id'] ? 'checked' : '' ?>>
              <span class="group-option__body">
                <strong><?= h($g['name']) ?> · <?= h($g['format']) ?></strong>
                <span>🗓 <?= h(format_sessions($g['sessions'])) ?></span>
                <span class="text-muted"><?= format_date($g['start_date']) ?> – <?= format_date($g['end_date']) ?> · <?= h($g['location']) ?></span>
                <span class="group-option__seats">
                  <?php if ($g['is_over']): ?>Finished<?php elseif ($g['seats_left'] === 0): ?>Group is full<?php else: ?><?= $g['seats_left'] ?> of <?= (int)$g['capacity'] ?> seats left<?php endif; ?>
                </span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'group_id') ?>
        <!-- result of the AJAX check (js/enroll.js) -->
        <div class="group-check hidden" id="groupCheck" aria-live="polite"></div>
      </div>

      <div class="<?= group_class($errors, 'phone') ?>">
        <label for="enrollPhone">Phone number <span class="required">*</span></label>
        <input type="tel" id="enrollPhone" name="phone" value="<?= old($data, 'phone') ?>" placeholder="+7 701 123 45 67">
        <?= field_error($errors, 'phone') ?>
      </div>

      <div class="<?= group_class($errors, 'comment') ?>">
        <label for="comment">Additional information <span class="optional">(optional)</span></label>
        <textarea id="comment" name="comment" placeholder="Your goals, questions or wishes..."><?= old($data, 'comment') ?></textarea>
        <p class="char-counter" id="commentCounter">0 / 300</p>
        <?= field_error($errors, 'comment') ?>
      </div>

      <div class="<?= group_class($errors, 'terms') ?>">
        <label class="checkbox"><input type="checkbox" id="terms" name="terms" value="1" <?= !empty($data['terms']) ? 'checked' : '' ?>>
          I agree with the <a href="about.php#terms" target="_blank">course terms</a> <span class="required">*</span></label>
        <?= field_error($errors, 'terms') ?>
      </div>

      <div class="button-row">
        <a href="details.php?id=<?= $id ?>" class="btn btn--outline">← Back</a>
        <button type="submit" class="btn" id="enrollSubmit">Confirm enrollment</button>
      </div>
    </form>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
