<?php
/* =========================================================
   profile.php — personal account:
   - personal information + Edit profile (with photo upload)
   - change password (current password is required)
   - My courses: status, progress, Cancel (only own enrollments)
   - My weekly schedule (all lessons of active groups)
   Every action is a POST form with a CSRF token.
   ========================================================= */
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$userId = (int)$user['id'];
$errors = [];
$passErrors = [];
$data = $user;
$openPanel = '';

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ---------- 1) Edit profile ----------
    if ($action === 'profile') {
        $data = array_merge($user, $_POST);
        $errors = validate_profile($_POST);
        $newPhoto = null;
        if (!empty($_FILES['photo']['name'])) {
            $newPhoto = save_profile_photo($_FILES['photo'], $userId, $errors);
        }
        if (!$errors) {
            $phone = trim($_POST['phone'] ?? '');
            $birth = trim($_POST['birth_date'] ?? '');
            db_exec('UPDATE users SET first_name = ?, last_name = ?, phone = ?, birth_date = ? WHERE id = ?', [
                trim($_POST['first_name']), trim($_POST['last_name']),
                $phone === '' ? null : normalize_phone($phone), $birth === '' ? null : $birth, $userId]);
            if ($newPhoto) {
                if (!empty($user['photo']) && str_starts_with($user['photo'], 'uploads/avatars/')) {
                    @unlink(__DIR__ . '/' . $user['photo']);          // delete the old photo file
                }
                db_exec('UPDATE users SET photo = ? WHERE id = ?', [$newPhoto, $userId]);
            }
            flash('success', '✓ Profile updated');
            redirect('profile.php');
        }
        $openPanel = 'edit';
    }

    // ---------- 2) Change password ----------
    if ($action === 'password') {
        $hash = db_value('SELECT password_hash FROM users WHERE id = ?', [$userId]);
        $passErrors = validate_password_change($_POST, $hash);
        if (!$passErrors) {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($_POST['new_password'], PASSWORD_DEFAULT), $userId]);
            session_regenerate_id(true);
            flash('success', '✓ Password changed');
            redirect('profile.php');
        }
        $openPanel = 'password';
    }

    // ---------- 3) Cancel my enrollment ----------
    if ($action === 'cancel') {
        $enrollmentId = int_param($_POST, 'enrollment_id');
        // "AND user_id = ?" — a student can cancel ONLY his own enrollment
        $changed = db_exec("UPDATE enrollments SET status = 'Cancelled', updated_at = ?
                            WHERE id = ? AND user_id = ? AND status IN ('Pending', 'Confirmed')",
                            [date('Y-m-d H:i:s'), $enrollmentId, $userId]);
        if ($changed === 1) {
            flash('success', 'Enrollment #' . $enrollmentId . ' cancelled');
        } else {
            flash('error', 'This enrollment cannot be cancelled.');
        }
        redirect('profile.php#my-courses');
    }
}

// Saves an uploaded photo: checks size, real file type (MIME) and gives it a random name
function save_profile_photo(array $file, int $userId, array &$errors): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $errors['photo'] = 'Upload failed. Please try another file.';
        return null;
    }
    if ($file['size'] > MAX_PHOTO_SIZE) {
        $errors['photo'] = 'Image is too big — maximum 1 MB';
        return null;
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);   // real type, not the file name
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        $errors['photo'] = 'Please choose an image file (jpg, png, webp, gif)';
        return null;
    }
    $name = 'u' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/avatars/' . $name)) {
        $errors['photo'] = 'Could not save the file on the server.';
        return null;
    }
    return 'uploads/avatars/' . $name;
}

$enrollments = $user['role'] === 'student' ? user_enrollments($userId) : [];

// Weekly schedule: lessons of active groups that are not finished
$week = array_fill(1, 7, []);
foreach ($enrollments as $e) {
    if (in_array($e['status'], ['Pending', 'Confirmed'], true) && $e['end_date'] >= date('Y-m-d')) {
        foreach ($e['sessions'] as $s) {
            $week[(int)$s['weekday']][] = $s + ['title' => $e['title'], 'status' => $e['status'], 'format' => $e['format'], 'course_id' => $e['course_id']];
        }
    }
}
foreach ($week as &$daySessions) {
    usort($daySessions, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));
}
unset($daySessions);

$pageTitle = 'My Profile';
$activePage = 'profile';
$pageScripts = ['profile.js'];
include __DIR__ . '/includes/header.php';
?>
<section class="page-header">
  <div class="container">
    <h1>My Profile</h1>
    <p>Your personal information, courses, progress and weekly schedule</p>
  </div>
</section>

<div class="container profile">
  <!-- Left: personal information -->
  <aside class="panel">
    <div class="avatar"><?= avatar_html($user) ?></div>
    <h2 class="profile__name"><?= h($user['first_name'] . ' ' . $user['last_name']) ?></h2>
    <p class="profile__role"><span class="badge badge--<?= h($user['role']) ?>"><?= $user['role'] === 'admin' ? 'Administrator' : 'Student' ?></span></p>
    <ul class="profile__info">
      <li><span>Email</span><span><?= h($user['email']) ?></span></li>
      <li><span>Phone</span><span><?= h($user['phone'] ?: '—') ?></span></li>
      <li><span>Date of birth</span><span><?= format_date($user['birth_date']) ?></span></li>
      <li><span>Member since</span><span><?= format_date($user['created_at']) ?></span></li>
    </ul>
    <div class="profile__buttons">
      <button class="btn" type="button" data-open="editPanel">✎ Edit profile</button>
      <button class="btn btn--outline" type="button" data-open="passwordPanel">🔑 Change password</button>
      <?php if ($user['role'] === 'admin'): ?><a class="btn btn--outline" href="<?= h(url('admin/index.php')) ?>">Admin panel</a><?php endif; ?>
      <form action="<?= h(url('logout.php')) ?>" method="post" data-confirm="Are you sure you want to log out?">
        <?= csrf_field() ?><button type="submit" class="btn btn--danger btn--full">Logout</button>
      </form>
    </div>
  </aside>

  <div>
    <!-- Edit profile form -->
    <section class="panel panel--spaced <?= $openPanel === 'edit' ? '' : 'hidden' ?>" id="editPanel">
      <h2 class="panel__title">Edit profile</h2>
      <form id="editForm" method="post" action="profile.php" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <div class="form-row">
          <div class="<?= group_class($errors, 'first_name') ?>">
            <label for="editFirstName">First Name <span class="required">*</span></label>
            <input type="text" id="editFirstName" name="first_name" value="<?= old($data, 'first_name') ?>" maxlength="50">
            <?= field_error($errors, 'first_name') ?>
          </div>
          <div class="<?= group_class($errors, 'last_name') ?>">
            <label for="editLastName">Last Name <span class="required">*</span></label>
            <input type="text" id="editLastName" name="last_name" value="<?= old($data, 'last_name') ?>" maxlength="50">
            <?= field_error($errors, 'last_name') ?>
          </div>
        </div>
        <div class="form-row">
          <div class="<?= group_class($errors, 'phone') ?>">
            <label for="editPhone">Phone Number</label>
            <input type="tel" id="editPhone" name="phone" value="<?= old($data, 'phone') ?>" placeholder="+7 701 123 45 67">
            <?= field_error($errors, 'phone') ?>
          </div>
          <div class="<?= group_class($errors, 'birth_date') ?>">
            <label for="editBirth">Date of Birth</label>
            <input type="date" id="editBirth" name="birth_date" value="<?= old($data, 'birth_date') ?>" max="<?= date('Y-m-d') ?>">
            <?= field_error($errors, 'birth_date') ?>
          </div>
        </div>
        <div class="<?= group_class($errors, 'photo') ?>">
          <label for="editPhoto">Profile Photo <span class="optional">(jpg, png, webp, gif · max 1 MB)</span></label>
          <input type="file" id="editPhoto" name="photo" accept="image/jpeg,image/png,image/webp,image/gif">
          <img class="photo-preview hidden" id="photoPreview" alt="Preview of the new photo">
          <?= field_error($errors, 'photo') ?>
        </div>
        <div class="button-row button-row--end">
          <button type="button" class="btn btn--outline" data-close="editPanel">Cancel</button>
          <button type="submit" class="btn">Save changes</button>
        </div>
      </form>
    </section>

    <!-- Change password form -->
    <section class="panel panel--spaced <?= $openPanel === 'password' ? '' : 'hidden' ?>" id="passwordPanel">
      <h2 class="panel__title">Change password</h2>
      <form id="passwordForm" method="post" action="profile.php" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <div class="<?= group_class($passErrors, 'current_password') ?>">
          <label for="currentPassword">Current password <span class="required">*</span></label>
          <input type="password" id="currentPassword" name="current_password" autocomplete="current-password">
          <?= field_error($passErrors, 'current_password') ?>
        </div>
        <div class="form-row">
          <div class="<?= group_class($passErrors, 'new_password') ?>">
            <label for="newPassword">New password <span class="required">*</span></label>
            <input type="password" id="newPassword" name="new_password" autocomplete="new-password" placeholder="8+ characters, letters and digits">
            <?= field_error($passErrors, 'new_password') ?>
          </div>
          <div class="<?= group_class($passErrors, 'confirm_new_password') ?>">
            <label for="confirmNewPassword">Repeat new password <span class="required">*</span></label>
            <input type="password" id="confirmNewPassword" name="confirm_new_password" autocomplete="new-password">
            <?= field_error($passErrors, 'confirm_new_password') ?>
          </div>
        </div>
        <div class="button-row button-row--end">
          <button type="button" class="btn btn--outline" data-close="passwordPanel">Cancel</button>
          <button type="submit" class="btn">Change password</button>
        </div>
      </form>
    </section>

    <?php if ($user['role'] === 'student'): ?>
    <!-- My courses = enrollment history -->
    <section class="panel panel--spaced" id="my-courses">
      <h2 class="panel__title"><span>My courses (<?= count($enrollments) ?>)</span>
        <a href="<?= h(url('pages/courses.php')) ?>" class="btn btn--small">+ Enroll in a course</a>
      </h2>

      <?php if (!$enrollments): ?>
        <div class="empty-state">
          <p class="empty-state__icon">📚</p>
          <p>You have not enrolled in any course yet.</p>
          <p><a href="<?= h(url('pages/courses.php')) ?>" class="btn">Browse courses</a></p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>ID</th><th>Course</th><th>Group &amp; schedule</th><th>Progress</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($enrollments as $e):
                $percent = $e['lessons_total'] ? (int)round($e['lessons_done'] * 100 / $e['lessons_total']) : 0;
                $canLearn = in_array($e['status'], ['Confirmed', 'Completed'], true); ?>
              <tr>
                <td>#<?= (int)$e['id'] ?></td>
                <td><a href="<?= h(url('pages/details.php?id=' . (int)$e['course_id'])) ?>"><?= h($e['title']) ?></a><br>
                  <span class="text-muted small"><?= format_date($e['start_date']) ?> – <?= format_date($e['end_date']) ?></span></td>
                <td><?= h($e['group_name']) ?> · <?= h($e['format']) ?><br><span class="text-muted small"><?= h(format_sessions($e['sessions'])) ?></span></td>
                <td class="progress-cell">
                  <div class="progress__bar progress__bar--small"><span style="width: <?= $percent ?>%"></span></div>
                  <span class="small text-muted"><?= (int)$e['lessons_done'] ?>/<?= (int)$e['lessons_total'] ?></span>
                </td>
                <td><?= status_badge($e['status']) ?></td>
                <td>
                  <div class="actions">
                    <?php if ($canLearn): ?>
                      <a class="btn btn--small" href="<?= h(url('pages/learn.php?course=' . (int)$e['course_id'])) ?>">▶ Lessons</a>
                    <?php endif; ?>
                    <?php if (in_array($e['status'], ['Pending', 'Confirmed'], true)): ?>
                      <form method="post" action="profile.php" data-confirm="Cancel enrollment #<?= (int)$e['id'] ?> (<?= h($e['title']) ?>)?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="enrollment_id" value="<?= (int)$e['id'] ?>">
                        <button type="submit" class="btn btn--danger btn--small">Cancel</button>
                      </form>
                    <?php endif; ?>
                    <?php if (!$canLearn && !in_array($e['status'], ['Pending', 'Confirmed'], true)): ?>—<?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <!-- Weekly schedule -->
    <section class="panel" id="schedule">
      <h2 class="panel__title">My weekly schedule</h2>
      <p class="text-muted small">Lessons of your active groups. The system does not allow two courses at the same time.</p>
      <div class="week">
        <?php foreach ($week as $day => $items): ?>
          <div class="week__day<?= (int)date('N') === $day ? ' week__day--today' : '' ?>">
            <h3><?= h(weekday_short($day)) ?></h3>
            <?php foreach ($items as $s): ?>
              <a class="week__item week__item--<?= h(strtolower($s['status'])) ?>" href="<?= h(url('pages/details.php?id=' . (int)$s['course_id'])) ?>">
                <strong><?= h(format_time($s['start_time'])) ?>–<?= h(format_time($s['end_time'])) ?></strong>
                <span><?= h($s['title']) ?></span>
                <span class="small"><?= h($s['format']) ?> · <?= h($s['status']) ?></span>
              </a>
            <?php endforeach; ?>
            <?php if (!$items): ?><p class="week__free">free</p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
