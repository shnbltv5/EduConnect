<?php
/* =========================================================
   admin/users.php — users: search, filter by role, pagination,
   change role, delete (an admin cannot delete himself).
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
$admin = require_admin();

if (is_post()) {
    verify_csrf();
    $id = int_param($_POST, 'user_id');
    $action = $_POST['action'] ?? '';
    if ($id === (int)$admin['id']) {
        flash('error', 'You cannot change or delete your own account here.');
    } elseif ($action === 'role' && in_array($_POST['role'] ?? '', ['student', 'admin'], true)) {
        db_exec('UPDATE users SET role = ? WHERE id = ?', [$_POST['role'], $id]);
        flash('success', 'Role of user #' . $id . ' changed to ' . $_POST['role']);
    } elseif ($action === 'delete') {
        // ON DELETE CASCADE removes the user's enrollments, progress and reviews
        $deleted = db_exec('DELETE FROM users WHERE id = ?', [$id]);
        flash($deleted ? 'success' : 'error', $deleted ? 'User #' . $id . ' deleted together with enrollments and reviews' : 'User not found');
    }
    redirect('admin/users.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'role', 'page']))));
}

$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60);
$role = in_array($_GET['role'] ?? '', ['student', 'admin'], true) ? $_GET['role'] : '';
$page = max(1, int_param($_GET, 'page'));
$perPage = 10;

$where = '1 = 1';
$params = [];
if ($q !== '') {
    $where .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($role !== '') {
    $where .= ' AND u.role = ?';
    $params[] = $role;
}
$total = (int)db_value("SELECT COUNT(*) FROM users u WHERE $where", $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);

// LEFT JOIN + GROUP BY: users without enrollments are shown too (count = 0)
$users = db_all("SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.phone, u.created_at,
                        COUNT(e.id) AS enrollments,
                        SUM(CASE WHEN e.status IN ('Pending', 'Confirmed') THEN 1 ELSE 0 END) AS active
                 FROM users u
                 LEFT JOIN enrollments e ON e.user_id = u.id
                 WHERE $where
                 GROUP BY u.id, u.first_name, u.last_name, u.email, u.role, u.phone, u.created_at
                 ORDER BY u.created_at DESC, u.id DESC
                 LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);

admin_start('Users', 'users');
?>
<div class="panel">
  <form class="admin-filters" method="get">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="Name or email..." aria-label="Search users">
    <select name="role" aria-label="Role">
      <option value="">All roles</option>
      <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Students</option>
      <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admins</option>
    </select>
    <button class="btn btn--small" type="submit">Filter</button>
    <a class="btn btn--outline btn--small" href="users.php">Reset</a>
  </form>
  <p class="results-info"><?= $total ?> users found</p>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Registered</th><th>Enrollments</th><th>Role</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $self = (int)$u['id'] === (int)$admin['id']; ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td><?= h($u['first_name'] . ' ' . $u['last_name']) ?></td>
          <td><?= h($u['email']) ?></td>
          <td><?= h($u['phone'] ?: '—') ?></td>
          <td><?= format_date($u['created_at']) ?></td>
          <td><?= (int)$u['enrollments'] ?> <span class="text-muted small">(<?= (int)$u['active'] ?> active)</span></td>
          <td>
            <?php if ($self): ?><span class="badge badge--admin">admin (you)</span>
            <?php else: ?>
              <form method="post" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <select name="role" aria-label="Role of <?= h($u['email']) ?>" data-autosubmit>
                  <option value="student" <?= $u['role'] === 'student' ? 'selected' : '' ?>>student</option>
                  <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                </select>
              </form>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$self): ?>
              <form method="post" class="inline-form" data-confirm="Delete user <?= h($u['email']) ?> with all enrollments, progress and reviews?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn--danger btn--small" type="submit">Delete</button>
              </form>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="8" class="text-muted center">No users found</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= admin_pagination($page, $pages) ?>
</div>
<?php admin_end(); ?>
