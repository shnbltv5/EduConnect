<?php
/* =========================================================
   admin/lessons.php?course=1 — video lessons and materials of a course.
   CRUD for lessons (title, video, duration, free preview)
   and for materials (PDF files and links) of every lesson.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$courseId = int_param($_GET, 'course');
$course = $courseId ? get_course($courseId, false) : false;
if (!$course) {
    error_page(404, 'Course not found', 'Choose a course in the admin panel.');
}
$back = 'admin/lessons.php?course=' . $courseId;
$errors = [];
$materialErrors = [];
$editId = int_param($_GET, 'edit');
$editing = $editId ? db_one('SELECT * FROM lessons WHERE id = ? AND course_id = ?', [$editId, $courseId]) : null;
$data = $editing ?: ['position' => (int)db_value('SELECT COALESCE(MAX(position), 0) + 1 FROM lessons WHERE course_id = ?', [$courseId]), 'duration_min' => 10];

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_lesson') {
        $data = $_POST;
        $errors = validate_lesson($data);
        if (!$errors) {
            $values = [trim($data['title']), (int)$data['position'], trim($data['summary'] ?? ''),
                       trim($data['video_url'] ?? '') === '' ? null : trim($data['video_url']),
                       (int)$data['duration_min'], empty($data['is_preview']) ? 0 : 1];
            $lessonId = int_param($data, 'lesson_id');
            if ($lessonId) {
                // "AND course_id = ?" — the lesson must belong to this course
                db_exec('UPDATE lessons SET title = ?, position = ?, summary = ?, video_url = ?, duration_min = ?, is_preview = ?
                         WHERE id = ? AND course_id = ?', array_merge($values, [$lessonId, $courseId]));
                flash('success', '✓ Lesson updated');
            } else {
                db_exec('INSERT INTO lessons (title, position, summary, video_url, duration_min, is_preview, course_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?)', array_merge($values, [$courseId]));
                flash('success', '✓ Lesson added');
            }
            redirect($back);
        }
    }
    if ($action === 'delete_lesson') {
        db_exec('DELETE FROM lessons WHERE id = ? AND course_id = ?', [int_param($_POST, 'lesson_id'), $courseId]);
        flash('success', 'Lesson deleted with its materials and progress');
        redirect($back);
    }
    if ($action === 'add_material') {
        $lessonId = int_param($_POST, 'lesson_id');
        $materialErrors = validate_material($_POST);
        if (!db_value('SELECT COUNT(*) FROM lessons WHERE id = ? AND course_id = ?', [$lessonId, $courseId])) {
            $materialErrors['title'] = 'Lesson not found';
        }
        if (!$materialErrors) {
            db_exec('INSERT INTO materials (lesson_id, title, type, url) VALUES (?, ?, ?, ?)',
                    [$lessonId, trim($_POST['title']), $_POST['type'], trim($_POST['url'])]);
            flash('success', '✓ Material added');
            redirect($back . '#lesson-' . $lessonId);
        }
        flash('error', 'Material was not added: ' . implode(' ', $materialErrors));
        redirect($back . '#lesson-' . $lessonId);
    }
    if ($action === 'delete_material') {
        db_exec('DELETE FROM materials WHERE id = ? AND lesson_id IN (SELECT id FROM lessons WHERE course_id = ?)',
                [int_param($_POST, 'material_id'), $courseId]);
        flash('success', 'Material deleted');
        redirect($back);
    }
}

$lessons = course_lessons($courseId);
$videos = array_map(fn($f) => 'videos/' . basename($f), glob(__DIR__ . '/../videos/*.mp4'));
$pdfs = array_map(fn($f) => 'materials/' . basename($f), glob(__DIR__ . '/../materials/*.pdf'));

admin_start('Lessons & materials', 'courses', $course['title']);
?>
<p><a href="courses.php">← All courses</a> · <a href="<?= h(url('pages/learn.php?course=' . $courseId)) ?>">Open as student ▶</a></p>

<div class="panel panel--spaced">
  <h2 class="panel__title">Lessons (<?= count($lessons) ?>)</h2>
  <?php foreach ($lessons as $l): $materials = lesson_materials((int)$l['id']); ?>
    <article class="admin-lesson" id="lesson-<?= (int)$l['id'] ?>">
      <div class="admin-lesson__head">
        <strong><?= (int)$l['position'] ?>. <?= h($l['title']) ?></strong>
        <span class="text-muted small"><?= (int)$l['duration_min'] ?> min · <?= $l['video_url'] ? (youtube_id($l['video_url']) ? 'YouTube video' : 'Local video') : 'No video' ?></span>
        <?php if ($l['is_preview']): ?><span class="badge badge--confirmed">Free preview</span><?php endif; ?>
        <div class="actions">
          <a class="btn btn--outline btn--small" href="?course=<?= $courseId ?>&amp;edit=<?= (int)$l['id'] ?>#lessonForm">Edit</a>
          <form method="post" class="inline-form" data-confirm="Delete lesson &quot;<?= h($l['title']) ?>&quot; with its materials?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete_lesson"><input type="hidden" name="lesson_id" value="<?= (int)$l['id'] ?>">
            <button class="btn btn--danger btn--small" type="submit">Delete</button>
          </form>
        </div>
      </div>
      <ul class="materials materials--admin">
        <?php foreach ($materials as $m): ?>
          <li><span aria-hidden="true"><?= $m['type'] === 'pdf' ? '📕' : '🔗' ?></span>
            <a href="<?= h($m['type'] === 'pdf' ? url($m['url']) : $m['url']) ?>" target="_blank" rel="noopener"><?= h($m['title']) ?></a>
            <form method="post" class="inline-form" data-confirm="Delete material &quot;<?= h($m['title']) ?>&quot;?">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete_material"><input type="hidden" name="material_id" value="<?= (int)$m['id'] ?>">
              <button class="btn-link" type="submit" aria-label="Delete material">✕</button>
            </form></li>
        <?php endforeach; ?>
      </ul>
      <form method="post" class="material-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_material"><input type="hidden" name="lesson_id" value="<?= (int)$l['id'] ?>">
        <input type="text" name="title" placeholder="Material title" required minlength="3" maxlength="120" aria-label="Material title">
        <select name="type" aria-label="Type"><option value="link">Link</option><option value="pdf">PDF</option></select>
        <input type="text" name="url" placeholder="https://... or materials/name.pdf" list="pdfFiles" required aria-label="URL or file">
        <button class="btn btn--small" type="submit">+ Add</button>
      </form>
    </article>
  <?php endforeach; ?>
  <?php if (!$lessons): ?><p class="text-muted">No lessons yet. Add the first lesson below.</p><?php endif; ?>
  <datalist id="pdfFiles"><?php foreach ($pdfs as $p): ?><option value="<?= h($p) ?>"><?php endforeach; ?></datalist>
</div>

<div class="panel" id="lessonForm">
  <h2 class="panel__title"><?= $editing ? 'Edit lesson' : 'Add lesson' ?></h2>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_lesson">
    <input type="hidden" name="lesson_id" value="<?= (int)($editing['id'] ?? 0) ?>">
    <div class="form-row form-row--3">
      <div class="<?= group_class($errors, 'title') ?> form-group--wide">
        <label for="lTitle">Title <span class="required">*</span></label>
        <input type="text" id="lTitle" name="title" value="<?= old($data, 'title') ?>" maxlength="120">
        <?= field_error($errors, 'title') ?>
      </div>
      <div class="<?= group_class($errors, 'position') ?>">
        <label for="lPosition">Position <span class="required">*</span></label>
        <input type="number" id="lPosition" name="position" min="1" max="100" value="<?= old($data, 'position') ?>">
        <?= field_error($errors, 'position') ?>
      </div>
    </div>
    <div class="form-row">
      <div class="<?= group_class($errors, 'video_url') ?>">
        <label for="lVideo">Video <span class="optional">(videos/name.mp4 or YouTube link)</span></label>
        <input type="text" id="lVideo" name="video_url" value="<?= old($data, 'video_url') ?>" list="lessonVideos">
        <datalist id="lessonVideos"><?php foreach ($videos as $v): ?><option value="<?= h($v) ?>"><?php endforeach; ?></datalist>
        <?= field_error($errors, 'video_url') ?>
      </div>
      <div class="<?= group_class($errors, 'duration_min') ?>">
        <label for="lDuration">Duration, min <span class="required">*</span></label>
        <input type="number" id="lDuration" name="duration_min" min="1" max="600" value="<?= old($data, 'duration_min') ?>">
        <?= field_error($errors, 'duration_min') ?>
      </div>
    </div>
    <div class="form-group">
      <label for="lSummary">Description</label>
      <textarea id="lSummary" name="summary" rows="3"><?= old($data, 'summary') ?></textarea>
    </div>
    <div class="form-group">
      <label class="checkbox"><input type="checkbox" name="is_preview" value="1" <?= !empty($data['is_preview']) ? 'checked' : '' ?>>Free preview (open for everyone)</label>
    </div>
    <div class="button-row button-row--end">
      <?php if ($editing): ?><a class="btn btn--outline" href="?course=<?= $courseId ?>">Cancel</a><?php endif; ?>
      <button class="btn" type="submit"><?= $editing ? 'Save lesson' : 'Add lesson' ?></button>
    </div>
  </form>
</div>
<?php admin_end(); ?>
