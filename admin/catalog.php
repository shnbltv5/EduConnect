<?php
/* =========================================================
   admin/catalog.php — CRUD for categories and teachers.
   A category / teacher used by a course cannot be deleted
   (FOREIGN KEY ... ON DELETE RESTRICT): the error is shown nicely.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = int_param($_POST, 'id');
    try {
        if ($action === 'save_category') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            if (!length_between($name, 2, 50)) {
                flash('error', 'Category name: 2–50 characters');
            } elseif ($id) {
                db_exec('UPDATE categories SET name = ?, description = ? WHERE id = ?', [$name, $description, $id]);
                flash('success', '✓ Category updated');
            } else {
                db_exec('INSERT INTO categories (name, description) VALUES (?, ?)', [$name, $description]);
                flash('success', '✓ Category added');
            }
        } elseif ($action === 'delete_category') {
            db_exec('DELETE FROM categories WHERE id = ?', [$id]);
            flash('success', 'Category deleted');
        } elseif ($action === 'save_teacher') {
            $name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            if (!length_between($name, 3, 100)) {
                flash('error', 'Teacher name: letters, 3–100 characters');
            } elseif ($email !== '' && !valid_email($email)) {
                flash('error', 'Invalid teacher email');
            } elseif ($id) {
                db_exec('UPDATE teachers SET full_name = ?, email = ?, bio = ? WHERE id = ?', [$name, $email ?: null, trim($_POST['bio'] ?? ''), $id]);
                flash('success', '✓ Teacher updated');
            } else {
                db_exec('INSERT INTO teachers (full_name, email, bio) VALUES (?, ?, ?)', [$name, $email ?: null, trim($_POST['bio'] ?? '')]);
                flash('success', '✓ Teacher added');
            }
        } elseif ($action === 'delete_teacher') {
            db_exec('DELETE FROM teachers WHERE id = ?', [$id]);
            flash('success', 'Teacher deleted');
        }
    } catch (PDOException $e) {
        if (!is_constraint_error($e)) {
            throw $e;
        }
        // UNIQUE (same name / email) or FOREIGN KEY (used by a course)
        flash('error', str_starts_with($action, 'delete')
            ? 'Cannot delete: it is used by at least one course. Change those courses first.'
            : 'This name or email already exists.');
    }
    redirect('admin/catalog.php');
}

// LEFT JOIN + COUNT: how many courses use every category / teacher
$categories = db_all('SELECT cat.id, cat.name, cat.description, COUNT(c.id) AS courses
                      FROM categories cat LEFT JOIN courses c ON c.category_id = cat.id
                      GROUP BY cat.id, cat.name, cat.description ORDER BY cat.name');
$teachers = db_all('SELECT t.id, t.full_name, t.email, t.bio, COUNT(c.id) AS courses
                    FROM teachers t LEFT JOIN courses c ON c.teacher_id = t.id
                    GROUP BY t.id, t.full_name, t.email, t.bio ORDER BY t.full_name');
$editCategory = int_param($_GET, 'category');
$editTeacher = int_param($_GET, 'teacher');

admin_start('Categories & teachers', 'catalog');
?>
<div class="admin-two">
  <section class="panel">
    <h2 class="panel__title">Categories (<?= count($categories) ?>)</h2>
    <ul class="crud-list">
      <?php foreach ($categories as $cat): ?>
        <li>
          <?php if ($editCategory === (int)$cat['id']): ?>
            <form method="post" class="crud-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
              <input type="text" name="name" value="<?= h($cat['name']) ?>" required maxlength="50" aria-label="Category name">
              <input type="text" name="description" value="<?= h($cat['description']) ?>" maxlength="255" aria-label="Description">
              <button class="btn btn--small" type="submit">Save</button> <a href="catalog.php">Cancel</a>
            </form>
          <?php else: ?>
            <div><strong><?= h($cat['name']) ?></strong> <span class="text-muted small">· <?= (int)$cat['courses'] ?> courses</span><br>
              <span class="text-muted small"><?= h($cat['description']) ?></span></div>
            <div class="actions">
              <a class="btn btn--outline btn--small" href="?category=<?= (int)$cat['id'] ?>">Edit</a>
              <form method="post" class="inline-form" data-confirm="Delete category <?= h($cat['name']) ?>?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                <button class="btn btn--danger btn--small" type="submit">Delete</button></form>
            </div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <form method="post" class="crud-form crud-form--add">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_category">
      <input type="text" name="name" placeholder="New category" required minlength="2" maxlength="50" aria-label="New category name">
      <input type="text" name="description" placeholder="Description" maxlength="255" aria-label="New category description">
      <button class="btn btn--small" type="submit">+ Add</button>
    </form>
  </section>

  <section class="panel">
    <h2 class="panel__title">Teachers (<?= count($teachers) ?>)</h2>
    <ul class="crud-list">
      <?php foreach ($teachers as $t): ?>
        <li>
          <?php if ($editTeacher === (int)$t['id']): ?>
            <form method="post" class="crud-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="save_teacher"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <input type="text" name="full_name" value="<?= h($t['full_name']) ?>" required maxlength="100" aria-label="Full name">
              <input type="email" name="email" value="<?= h($t['email']) ?>" maxlength="120" aria-label="Email">
              <input type="text" name="bio" value="<?= h($t['bio']) ?>" aria-label="Bio">
              <button class="btn btn--small" type="submit">Save</button> <a href="catalog.php">Cancel</a>
            </form>
          <?php else: ?>
            <div><strong><?= h($t['full_name']) ?></strong> <span class="text-muted small">· <?= (int)$t['courses'] ?> courses</span><br>
              <span class="text-muted small"><?= h($t['email']) ?></span></div>
            <div class="actions">
              <a class="btn btn--outline btn--small" href="?teacher=<?= (int)$t['id'] ?>">Edit</a>
              <form method="post" class="inline-form" data-confirm="Delete teacher <?= h($t['full_name']) ?>?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete_teacher"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button class="btn btn--danger btn--small" type="submit">Delete</button></form>
            </div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <form method="post" class="crud-form crud-form--add">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_teacher">
      <input type="text" name="full_name" placeholder="Full name" required maxlength="100" aria-label="New teacher name">
      <input type="email" name="email" placeholder="Email" maxlength="120" aria-label="New teacher email">
      <input type="text" name="bio" placeholder="Short bio" aria-label="New teacher bio">
      <button class="btn btn--small" type="submit">+ Add</button>
    </form>
  </section>
</div>
<?php admin_end(); ?>
