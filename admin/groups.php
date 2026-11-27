<?php
/* =========================================================
   admin/groups.php?course=1 — groups (schedule) of a course.
   A group = format, dates, capacity and lesson times
   (group_sessions: weekday + start + end). CRUD in one page.
   The group and its sessions are saved in a TRANSACTION.
   A group with enrollments cannot be deleted (FOREIGN KEY RESTRICT).
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$courseId = int_param($_GET, 'course');
$course = $courseId ? get_course($courseId, false) : false;
if (!$course) {
    error_page(404, 'Course not found', 'Choose a course in the admin panel.');
}
$back = 'admin/groups.php?course=' . $courseId;
$errors = [];
$editId = int_param($_GET, 'edit');
$editing = $editId ? db_one('SELECT * FROM course_groups WHERE id = ? AND course_id = ?', [$editId, $courseId]) : null;
$data = $editing ?: ['format' => 'Online', 'capacity' => 20, 'start_date' => date('Y-m-d', strtotime('+7 days')),
                     'end_date' => date('Y-m-d', strtotime('+' . ((int)$course['duration_weeks'] * 7 - 1 + 7) . ' days'))];
$sessions = $editing ? group_sessions($editId) : [];

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save_group') {
        $data = $_POST;
        $errors = validate_group($data);
        $sessions = group_sessions_from_post($data);
        if (!$errors) {
            $groupId = int_param($data, 'group_id');
            $values = [trim($data['name']), $data['format'], $data['start_date'], $data['end_date'], (int)$data['capacity'], trim($data['location'] ?? '')];
            $pdo = db();
            $pdo->beginTransaction();
            try {
                if ($groupId) {
                    $taken = seats_taken($groupId);
                    if ((int)$data['capacity'] < $taken) {
                        throw new EnrollmentException('Capacity cannot be less than the number of enrolled students (' . $taken . ').');
                    }
                    db_exec('UPDATE course_groups SET name = ?, format = ?, start_date = ?, end_date = ?, capacity = ?, location = ?
                             WHERE id = ? AND course_id = ?', array_merge($values, [$groupId, $courseId]));
                    db_exec('DELETE FROM group_sessions WHERE group_id = ?', [$groupId]);
                } else {
                    db_exec('INSERT INTO course_groups (name, format, start_date, end_date, capacity, location, course_id)
                             VALUES (?, ?, ?, ?, ?, ?, ?)', array_merge($values, [$courseId]));
                    $groupId = (int)$pdo->lastInsertId();
                }
                foreach ($sessions as $s) {
                    db_exec('INSERT INTO group_sessions (group_id, weekday, start_time, end_time) VALUES (?, ?, ?, ?)',
                            [$groupId, $s['weekday'], $s['start_time'] . ':00', $s['end_time'] . ':00']);
                }
                $pdo->commit();
                flash('success', $editing ? '✓ Group updated' : '✓ Group added');
                redirect($back);
            } catch (EnrollmentException $e) {
                $pdo->rollBack();
                $errors['capacity'] = $e->getMessage();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
    if ($action === 'delete_group') {
        try {
            db_exec('DELETE FROM course_groups WHERE id = ? AND course_id = ?', [int_param($_POST, 'group_id'), $courseId]);
            flash('success', 'Group deleted');
        } catch (PDOException $e) {
            if (!is_constraint_error($e)) {
                throw $e;
            }
            flash('error', 'This group cannot be deleted: it has enrollments. Change their status or move the students first.');
        }
        redirect($back);
    }
}

$groups = course_groups($courseId);
// at least 3 rows in the form (empty rows are ignored)
$rows = array_map(fn($s) => ['weekday' => $s['weekday'], 'start_time' => format_time($s['start_time']), 'end_time' => format_time($s['end_time'])], $sessions);
while (count($rows) < 3) {
    $rows[] = ['weekday' => '', 'start_time' => '', 'end_time' => ''];
}

admin_start('Groups & schedule', 'courses', $course['title']);
?>
<p><a href="courses.php">← All courses</a> · <a href="lessons.php?course=<?= $courseId ?>">Lessons of this course</a></p>

<div class="panel panel--spaced">
  <h2 class="panel__title">Groups (<?= count($groups) ?>)</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Group</th><th>Days and time</th><th>Dates</th><th>Format</th><th>Students</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($groups as $g): ?>
        <tr>
          <td><strong><?= h($g['name']) ?></strong><?php if ($g['is_over']): ?><br><span class="badge badge--completed">Finished</span><?php endif; ?></td>
          <td><?= h(format_sessions($g['sessions'])) ?></td>
          <td><?= format_date($g['start_date']) ?> – <?= format_date($g['end_date']) ?></td>
          <td><?= h($g['format']) ?><br><span class="text-muted small"><?= h($g['location']) ?></span></td>
          <td><?= (int)$g['taken'] ?> / <?= (int)$g['capacity'] ?></td>
          <td><div class="actions">
            <a class="btn btn--outline btn--small" href="?course=<?= $courseId ?>&amp;edit=<?= (int)$g['id'] ?>#groupForm">Edit</a>
            <form method="post" class="inline-form" data-confirm="Delete <?= h($g['name']) ?>?">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete_group"><input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
              <button class="btn btn--danger btn--small" type="submit">Delete</button>
            </form></div></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$groups): ?><tr><td colspan="6" class="text-muted center">No groups yet</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel" id="groupForm">
  <h2 class="panel__title"><?= $editing ? 'Edit ' . h($editing['name']) : 'Add group' ?></h2>
  <form method="post" id="groupFormEl" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_group">
    <input type="hidden" name="group_id" value="<?= (int)($editing['id'] ?? 0) ?>">
    <div class="form-row form-row--3">
      <div class="<?= group_class($errors, 'name') ?>">
        <label for="gName">Name <span class="required">*</span></label>
        <input type="text" id="gName" name="name" value="<?= old($data, 'name') ?>" placeholder="Group C" maxlength="40">
        <?= field_error($errors, 'name') ?>
      </div>
      <div class="<?= group_class($errors, 'format') ?>">
        <label for="gFormat">Format <span class="required">*</span></label>
        <select id="gFormat" name="format">
          <?php foreach (['Online', 'Offline', 'Hybrid'] as $f): ?><option <?= ($data['format'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option><?php endforeach; ?>
        </select>
        <?= field_error($errors, 'format') ?>
      </div>
      <div class="<?= group_class($errors, 'capacity') ?>">
        <label for="gCapacity">Capacity <span class="required">*</span></label>
        <input type="number" id="gCapacity" name="capacity" min="1" max="200" value="<?= old($data, 'capacity') ?>">
        <?= field_error($errors, 'capacity') ?>
      </div>
    </div>
    <div class="form-row form-row--3">
      <div class="<?= group_class($errors, 'start_date') ?>">
        <label for="gStart">Start date <span class="required">*</span></label>
        <input type="date" id="gStart" name="start_date" value="<?= old($data, 'start_date') ?>">
        <?= field_error($errors, 'start_date') ?>
      </div>
      <div class="<?= group_class($errors, 'end_date') ?>">
        <label for="gEnd">End date <span class="required">*</span></label>
        <input type="date" id="gEnd" name="end_date" value="<?= old($data, 'end_date') ?>">
        <?= field_error($errors, 'end_date') ?>
      </div>
      <div class="form-group">
        <label for="gLocation">Place / link</label>
        <input type="text" id="gLocation" name="location" value="<?= old($data, 'location') ?>" maxlength="100" placeholder="Zoom / KBTU, room 301">
      </div>
    </div>

    <div class="<?= group_class($errors, 'sessions') ?>">
      <span class="form-label">Lesson times <span class="required">*</span> <span class="optional">(empty rows are ignored)</span></span>
      <div id="sessionRows">
        <?php foreach ($rows as $i => $r): ?>
          <div class="session-row">
            <select name="weekday[]" aria-label="Day <?= $i + 1 ?>">
              <option value="">Day</option>
              <?php foreach (WEEKDAYS as $n => $name): ?><option value="<?= $n ?>" <?= (string)$r['weekday'] === (string)$n ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?>
            </select>
            <input type="time" name="start_time[]" value="<?= h($r['start_time']) ?>" aria-label="Start <?= $i + 1 ?>">
            <input type="time" name="end_time[]" value="<?= h($r['end_time']) ?>" aria-label="End <?= $i + 1 ?>">
            <button type="button" class="btn-link" data-remove-row aria-label="Remove this time">✕</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn--outline btn--small" id="addSessionRow">+ Add time</button>
      <?= field_error($errors, 'sessions') ?>
    </div>

    <div class="button-row button-row--end">
      <?php if ($editing): ?><a class="btn btn--outline" href="?course=<?= $courseId ?>">Cancel</a><?php endif; ?>
      <button class="btn" type="submit"><?= $editing ? 'Save group' : 'Add group' ?></button>
    </div>
  </form>
</div>
<?php admin_end(); ?>
