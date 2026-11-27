<?php
/* =========================================================
   admin_layout.php — frame of every admin page:
   page title + sidebar menu + content area.
   Usage:  admin_start('Courses', 'courses');  ...  admin_end();
   Every admin page calls require_admin() BEFORE this.
   ========================================================= */

function admin_start(string $title, string $section, string $subtitle = ''): void
{
    global $pageTitle, $activePage, $pageScripts;
    $pageTitle = 'Admin · ' . $title;
    $activePage = 'admin';
    $pageScripts = array_merge($pageScripts ?? [], ['admin.js']);
    $unread = (int)db_value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
    $pending = (int)db_value("SELECT COUNT(*) FROM enrollments WHERE status = 'Pending'");
    $menu = [
        'dashboard'   => ['index.php', '📊 Dashboard', 0],
        'users'       => ['users.php', '👥 Users', 0],
        'courses'     => ['courses.php', '📚 Courses', 0],
        'enrollments' => ['enrollments.php', '📝 Enrollments', $pending],
        'reviews'     => ['reviews.php', '⭐ Reviews', 0],
        'messages'    => ['messages.php', '✉️ Messages', $unread],
        'catalog'     => ['catalog.php', '🏷 Categories & teachers', 0],
        'reports'     => ['reports.php', '📈 Reports (SQL)', 0],
        'tests'       => ['tests.php', '🧪 Tests', 0],
    ];
    include __DIR__ . '/header.php';
    ?>
    <section class="page-header page-header--admin">
      <div class="container">
        <h1><?= h($title) ?></h1>
        <p><?= h($subtitle !== '' ? $subtitle : 'Logged in as ' . current_user()['first_name'] . ' ' . current_user()['last_name'] . ' (administrator)') ?></p>
      </div>
    </section>
    <div class="container admin">
      <aside class="admin__sidebar">
        <h2>ADMIN PANEL</h2>
        <nav class="admin__menu" aria-label="Admin menu">
          <?php foreach ($menu as $key => [$file, $label, $badge]): ?>
            <a class="admin__tab<?= $key === $section ? ' active' : '' ?>" href="<?= h(url('admin/' . $file)) ?>"<?= $key === $section ? ' aria-current="page"' : '' ?>>
              <?= h($label) ?><?php if ($badge): ?> <span class="admin__badge"><?= $badge ?></span><?php endif; ?></a>
          <?php endforeach; ?>
          <a class="admin__tab" href="<?= h(url('index.php')) ?>">🏠 Back to website</a>
        </nav>
      </aside>
      <div class="admin__content">
    <?php
}

function admin_end(): void
{
    global $pageScripts;                      // footer.php prints the scripts of the page
    echo "      </div>\n    </div>\n";
    include __DIR__ . '/footer.php';
}

// Small pagination for admin tables (keeps the other GET parameters)
function admin_pagination(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pages">';
    for ($p = 1; $p <= $pages; $p++) {
        $query = http_build_query(array_merge($_GET, ['page' => $p]));
        $html .= '<a class="pagination__link' . ($p === $page ? ' active" aria-current="page' : '') . '" href="?' . h($query) . '">' . $p . '</a>';
    }
    return $html . '</nav>';
}
