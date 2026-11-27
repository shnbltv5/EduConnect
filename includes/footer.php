<?php
/* =========================================================
   footer.php — the same footer and scripts on every page.
   $pageScripts: list of page-specific JS files, e.g. ['courses.js'].
   ========================================================= */
$scripts = array_merge(['validation.js', 'main.js'], $pageScripts ?? []);
?>
  </main>

  <!-- ===== Footer (same on every page) ===== -->
  <footer class="footer">
    <div class="container footer__inner">
      <div class="footer__brand">
        <a href="<?= h(url('index.php')) ?>" class="logo">
          <img src="<?= h(url('images/logo.svg')) ?>" alt="EduConnect logo">
          <span>EduConnect</span>
        </a>
        <p>Online course platform: choose a course, enroll in a few clicks and learn with video lessons and materials.</p>
      </div>

      <div>
        <h3 class="footer__title">Information</h3>
        <ul class="footer__links">
          <li><a href="<?= h(url('pages/about.php#about')) ?>">About</a></li>
          <li><a href="<?= h(url('pages/about.php#contact')) ?>">Contact</a></li>
          <li><a href="<?= h(url('pages/about.php#privacy')) ?>">Privacy</a></li>
          <li><a href="<?= h(url('pages/about.php#terms')) ?>">Terms</a></li>
        </ul>
      </div>

      <div>
        <h3 class="footer__title">Follow us</h3>
        <ul class="footer__links">
          <li><a href="https://www.instagram.com/" target="_blank" rel="noopener">Instagram</a></li>
          <li><a href="https://www.facebook.com/" target="_blank" rel="noopener">Facebook</a></li>
          <li><a href="https://t.me/" target="_blank" rel="noopener">Telegram</a></li>
        </ul>
      </div>
    </div>
    <p class="footer__copy">© <?= date('Y') ?> EduConnect — Student Project, KBTU</p>
  </footer>

  <?php foreach ($scripts as $js): ?>
  <script src="<?= h(url('js/' . $js)) ?>?v=<?= (int)@filemtime(__DIR__ . '/../js/' . $js) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
