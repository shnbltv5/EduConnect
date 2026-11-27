<?php
/* =========================================================
   header.php — the same <head>, header and navigation on every page.
   Variables set by the page before include:
   $pageTitle, $activePage ('home', 'about', 'courses', 'profile',
   'admin', 'login', 'register'), $pageDescription (optional).
   ========================================================= */
try {
    $navUser = current_user();
} catch (Throwable $e) {
    $navUser = null;                         // database is down: show the menu for guests
}
$activePage = $activePage ?? '';
$navLink = function (string $key, string $path, string $label) use ($activePage): string {
    $active = $activePage === $key;
    return '<li><a href="' . h(url($path)) . '" class="nav__link' . ($active ? ' active' : '') . '"'
        . ($active ? ' aria-current="page"' : '') . '>' . h($label) . '</a></li>';
};
$cssVersion = @filemtime(__DIR__ . '/../css/style.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= h($pageDescription ?? 'EduConnect — online course platform: catalog, enrollment, video lessons.') ?>">
  <!-- CSRF token for AJAX requests (js/main.js sends it in the X-CSRF-Token header) -->
  <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
  <meta name="base-url" content="<?= h(ROOT) ?>">
  <title><?= h(($pageTitle ?? 'Online Course Platform') . ' — ' . SITE_NAME) ?></title>
  <link rel="icon" href="<?= h(url('images/logo.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
  <link rel="stylesheet" href="<?= h(url('css/style.css')) ?>?v=<?= (int)$cssVersion ?>">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <!-- ===== Header & navigation (same on every page) ===== -->
  <header class="header">
    <div class="container header__inner">
      <a href="<?= h(url('index.php')) ?>" class="logo">
        <img src="<?= h(url('images/logo.svg')) ?>" alt="EduConnect logo">
        <span>EduConnect</span>
      </a>

      <button class="burger" id="burger" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="nav">☰</button>

      <nav class="nav" id="nav" aria-label="Main menu">
        <ul class="nav__list">
          <?= $navLink('home', 'index.php', 'Home') ?>
          <?= $navLink('about', 'pages/about.php', 'About') ?>
          <?= $navLink('courses', 'pages/courses.php', 'Courses') ?>
          <?php if ($navUser): ?>
            <?= $navLink('profile', 'profile.php', 'My Profile') ?>
            <?php if ($navUser['role'] === 'admin'): ?>
              <?= $navLink('admin', 'admin/index.php', 'Admin') ?>
            <?php endif; ?>
            <li><span class="nav__user">👤 <?= h($navUser['first_name']) ?></span></li>
            <li>
              <form action="<?= h(url('logout.php')) ?>" method="post" class="logout-form" data-confirm="Are you sure you want to log out?">
                <?= csrf_field() ?>
                <button type="submit" class="nav__logout">Logout</button>
              </form>
            </li>
          <?php else: ?>
            <?= $navLink('login', 'login.php', 'Login') ?>
            <?= $navLink('register', 'register.php', 'Register') ?>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </header>

  <!-- ===== Main content ===== -->
  <main id="main">
    <?php $flashes = take_flashes(); if ($flashes): ?>
      <div class="container flash-area" role="status">
        <?php foreach ($flashes as $f): ?>
          <div class="alert alert--<?= h($f['type']) ?> flash"><?= h($f['message']) ?>
            <button type="button" class="flash__close" aria-label="Close message">×</button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
