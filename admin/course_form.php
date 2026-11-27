<?php
/* =========================================================
   admin/course_form.php        -> CREATE a new course
   admin/course_form.php?id=3   -> UPDATE course #3
   The course and its features (course_features table) are
   saved in ONE transaction: either everything or nothing.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$id = int_param($_GET, 'id');
$course = $id ? get_course($id, false) : null;
if ($id && !$course) {
    error_page(404, 'Course not found', 'This course does not exist.');
}

$data = $course ? $course + ['features' => implode("\n", array_column(course_features($id), 'feature'))] : ['price' => 0, 'duration_weeks' => 6, 'image' => 'default.svg', 'is_published' => 1];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = $_POST;
    $errors = validate_course($data, $id);
    if (!$errors) {
        $values = [
            int_param($data, 'category_id'), int_param($data, 'teacher_id'), trim($data['title']), $data['level'],
            (int)$data['price'], (int)$data['duration_weeks'], $data['image'],
            trim($data['intro_video'] ?? '') === '' ? null : trim($data['intro_video']),
            trim($data['short_description']), trim($data['description']), empty($data['is_published']) ? 0 : 1,
        ];
        $features = array_values(array_filter(array_map('trim', explode("\n", $data['features']))));

        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($id) {
                db_exec('UPDATE courses SET category_id = ?, teacher_id = ?, title = ?, level = ?, price = ?, duration_weeks = ?,
                         image = ?, intro_video = ?, short_description = ?, description = ?, is_published = ? WHERE id = ?',
                         array_merge($values, [$id]));
                db_exec('DELETE FROM course_features WHERE course_id = ?', [$id]);
            } else {
                db_exec('INSERT INTO courses (category_id, teacher_id, title, level, price, duration_weeks, image, intro_video,
                         short_description, description, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $values);
                $id = (int)$pdo->lastInsertId();
            }
            foreach ($features as $i => $feature) {
                db_exec('INSERT INTO course_features (course_id, feature, position) VALUES (?, ?, ?)', [$id, mb_substr($feature, 0, 120), $i + 1]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        flash('success', $course ? '✓ Course updated' : '✓ Course added. Now add groups (schedule) and lessons.');
        redirect($course ? 'admin/courses.php' : 'admin/groups.php?course=' . $id);
    }
}

$categories = get_categories();
$teachers = db_all('SELECT id, full_name FROM teachers ORDER BY full_name');
$images = array_map('basename', glob(__DIR__ . '/../images/courses/*.svg'));
$videos = array_map(fn($f) => 'videos/' . basename($f), glob(__DIR__ . '/../videos/*.mp4'));
$sel = fn($a, $b) => (string)$a === (string)$b ? 'selected' : '';

admin_start($course ? 'Edit course' : 'Add course', 'courses', $course ? $course['title'] : 'Create a new course for the catalog');
?>
<div class="panel">
  <form id="courseForm" method="post" novalidate>
    <?= csrf_field() ?>
    <div class="<?= group_class($errors, 'title') ?>">
      <label for="cTitle">Title <span class="required">*</span></label>
      <input type="text" id="cTitle" name="title" value="<?= old($data, 'title') ?>" maxlength="120" placeholder="e.g. React for Beginners">
      <?= field_error($errors, 'title') ?>
    </div>
    <div class="form-row form-row--3">
      <div class="<?= group_class($errors, 'category_id') ?>">
        <label for="cCategory">Category <span class="required">*</span></label>
        <select id="cCategory" name="category_id">
          <option value="">-- choose --</option>
          <?php foreach ($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>" <?= $sel($cat['id'], $data['category_id'] ?? '') ?>><?= h($cat['name']) ?></option><?php endforeach; ?>
        </select>
        <?= field_error($errors, 'category_id') ?>
      </div>
      <div class="<?= group_class($errors, 'teacher_id') ?>">
        <label for="cTeacher">Teacher <span class="required">*</span></label>
        <select id="cTeacher" name="teacher_id">
          <option value="">-- choose --</option>
          <?php foreach ($teachers as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $sel($t['id'], $data['teacher_id'] ?? '') ?>><?= h($t['full_name']) ?></option><?php endforeach; ?>
        </select>
        <?= field_error($errors, 'teacher_id') ?>
      </div>
      <div class="<?= group_class($errors, 'level') ?>">
        <label for="cLevel">Level <span class="required">*</span></label>
        <select id="cLevel" name="level">
          <option value="">-- choose --</option>
          <?php foreach (['Beginner', 'Intermediate', 'Advanced'] as $lv): ?><option <?= $sel($lv, $data['level'] ?? '') ?>><?= $lv ?></option><?php endforeach; ?>
        </select>
        <?= field_error($errors, 'level') ?>
      </div>
    </div>
    <div class="form-row form-row--3">
      <div class="<?= group_class($errors, 'price') ?>">
        <label for="cPrice">Price, ₸ <span class="required">*</span></label>
        <input type="number" id="cPrice" name="price" min="0" max="1000000" step="1000" value="<?= old($data, 'price') ?>" placeholder="0 = free">
        <?= field_error($errors, 'price') ?>
      </div>
      <div class="<?= group_class($errors, 'duration_weeks') ?>">
        <label for="cDuration">Duration (weeks) <span class="required">*</span></label>
        <input type="number" id="cDuration" name="duration_weeks" min="1" max="52" value="<?= old($data, 'duration_weeks') ?>">
        <?= field_error($errors, 'duration_weeks') ?>
      </div>
      <div class="<?= group_class($errors, 'image') ?>">
        <label for="cImage">Picture</label>
        <select id="cImage" name="image">
          <?php foreach ($images as $img): ?><option value="<?= h($img) ?>" <?= $sel($img, $data['image'] ?? '') ?>><?= h(str_replace('.svg', '', $img)) ?></option><?php endforeach; ?>
        </select>
        <?= field_error($errors, 'image') ?>
      </div>
    </div>
    <div class="<?= group_class($errors, 'intro_video') ?>">
      <label for="cVideo">Intro video <span class="optional">(local file or YouTube link)</span></label>
      <input type="text" id="cVideo" name="intro_video" value="<?= old($data, 'intro_video') ?>" list="videoFiles" placeholder="videos/python-intro.mp4 or https://www.youtube.com/watch?v=...">
      <datalist id="videoFiles"><?php foreach ($videos as $v): ?><option value="<?= h($v) ?>"><?php endforeach; ?></datalist>
      <?= field_error($errors, 'intro_video') ?>
    </div>
    <div class="<?= group_class($errors, 'short_description') ?>">
      <label for="cShort">Short description (for the card) <span class="required">*</span></label>
      <input type="text" id="cShort" name="short_description" value="<?= old($data, 'short_description') ?>" maxlength="160">
      <?= field_error($errors, 'short_description') ?>
    </div>
    <div class="<?= group_class($errors, 'description') ?>">
      <label for="cDescription">Full description <span class="required">*</span></label>
      <textarea id="cDescription" name="description" rows="5"><?= old($data, 'description') ?></textarea>
      <?= field_error($errors, 'description') ?>
    </div>
    <div class="<?= group_class($errors, 'features') ?>">
      <label for="cFeatures">Features (one per line) <span class="required">*</span></label>
      <textarea id="cFeatures" name="features" rows="4" placeholder="12 video lessons&#10;Homework&#10;Certificate"><?= old($data, 'features') ?></textarea>
      <?= field_error($errors, 'features') ?>
    </div>
    <div class="form-group">
      <label class="checkbox"><input type="checkbox" name="is_published" value="1" <?= !empty($data['is_published']) ? 'checked' : '' ?>>Published (visible in the catalog)</label>
    </div>
    <div class="button-row button-row--end">
      <a class="btn btn--outline" href="courses.php">Cancel</a>
      <button type="submit" class="btn">Save course</button>
    </div>
  </form>
</div>
<?php admin_end(); ?>
