<?php
/* =========================================================
   pages/about.php — About, team, FAQ, Contact form, Privacy, Terms.
   The contact form is saved to the contact_messages table;
   the administrator reads the messages in the admin panel.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';

$user = current_user();
$data = $user ? ['name' => $user['first_name'] . ' ' . $user['last_name'], 'email' => $user['email']] : [];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = $_POST;
    $errors = validate_contact($data);
    if (!$errors) {
        db_exec('INSERT INTO contact_messages (user_id, name, email, message) VALUES (?, ?, ?, ?)',
                [$user['id'] ?? null, trim($data['name']), mb_strtolower(trim($data['email'])), trim($data['message'])]);
        flash('success', '✓ Thank you, ' . trim($data['name']) . '! We will answer within 24 hours.');
        redirect('pages/about.php#contact');
    }
}

$team = [
    ['Aidana Shynbulatova', 'Team lead · JavaScript & AJAX', 'AS'],
    ['Nazerke Kenzhieva', 'Forms · Validation · Security', 'NK'],
    ['Aruzhan Sakenova', 'CSS · Responsive design · Video lessons', 'AS'],
    ['Kirill Link', 'Admin panel · CRUD · Database', 'KL'],
];

$pageTitle = 'About';
$activePage = 'about';
$pageScripts = ['contact.js'];
include __DIR__ . '/../includes/header.php';
?>
<section class="page-header">
  <div class="container">
    <h1>About EduConnect</h1>
    <p>Who we are, how to contact us and the rules of the platform</p>
  </div>
</section>

<!-- About -->
<section class="section" id="about">
  <div class="container text-block">
    <h2 class="section__title">Our mission</h2>
    <p>EduConnect is an online course platform created as a student web programming project at KBTU. A student can browse the catalog, choose a group with a convenient schedule, enroll, watch video lessons and download materials, while the administrator manages courses, groups, lessons and enrollments.</p>
    <h3 class="text-block__subtitle">How it works</h3>
    <ol>
      <li>Create an account on the registration page.</li>
      <li>Find a course in the catalog using search, filters and sorting.</li>
      <li>Open the course details, watch the free preview lesson and press "Enroll now".</li>
      <li>Choose a group: the system checks free seats and that the time does not overlap with your other courses.</li>
      <li>After the administrator confirms the enrollment, all video lessons and materials open in "My Profile".</li>
    </ol>
  </div>
</section>

<!-- Team -->
<section class="section section--white">
  <div class="container">
    <h2 class="section__title">Our team</h2>
    <p class="section__subtitle">The students who built this project</p>
    <div class="team">
      <?php foreach ($team as $member): ?>
        <div class="team__member"><div class="avatar"><?= h($member[2]) ?></div><h3><?= h($member[0]) ?></h3><p class="text-muted"><?= h($member[1]) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section">
  <div class="container text-block faq">
    <h2 class="section__title">FAQ</h2>
    <details>
      <summary>Is registration free?</summary>
      <p>Yes. You pay only for paid courses; some courses are completely free.</p>
    </details>
    <details>
      <summary>Why can't I choose a group?</summary>
      <p>A group can be full, already finished, or its time can overlap with another course you study. Choose another group of the same course.</p>
    </details>
    <details>
      <summary>When do the video lessons open?</summary>
      <p>The first lesson of every course is a free preview. All other lessons open after the administrator confirms your enrollment.</p>
    </details>
    <details>
      <summary>Can I cancel my enrollment?</summary>
      <p>Yes, open "My Profile" and press "Cancel" next to the enrollment.</p>
    </details>
  </div>
</section>

<!-- Contact -->
<section class="section section--white" id="contact">
  <div class="container">
    <h2 class="section__title">Contact us</h2>
    <p class="section__subtitle">Have a question? Write to us</p>
    <div class="contact">
      <ul class="contact__info">
        <li>📍 Almaty, Tole bi street 59 (KBTU)</li>
        <li>📞 <a href="tel:+77271234567">+7 727 123 45 67</a></li>
        <li>✉️ <a href="mailto:support@educonnect.kz">support@educonnect.kz</a></li>
        <li>🕒 Mon–Fri, 09:00–18:00</li>
      </ul>
      <form id="contactForm" class="panel" method="post" action="about.php#contact" novalidate>
        <?= csrf_field() ?>
        <div class="alert <?= $errors ? 'alert--error' : 'hidden' ?>" id="contactAlert" role="alert"><?= $errors ? 'Please correct the errors below.' : '' ?></div>
        <div class="<?= group_class($errors, 'name') ?>">
          <label for="contactName">Name <span class="required">*</span></label>
          <input type="text" id="contactName" name="name" value="<?= old($data, 'name') ?>" maxlength="100">
          <?= field_error($errors, 'name') ?>
        </div>
        <div class="<?= group_class($errors, 'email') ?>">
          <label for="contactEmail">Email <span class="required">*</span></label>
          <input type="email" id="contactEmail" name="email" value="<?= old($data, 'email') ?>" maxlength="120">
          <?= field_error($errors, 'email') ?>
        </div>
        <div class="<?= group_class($errors, 'message') ?>">
          <label for="contactMessage">Message <span class="required">*</span></label>
          <textarea id="contactMessage" name="message" maxlength="2000"><?= old($data, 'message') ?></textarea>
          <?= field_error($errors, 'message') ?>
        </div>
        <button type="submit" class="btn">Send message</button>
      </form>
    </div>
  </div>
</section>

<!-- Privacy -->
<section class="section" id="privacy">
  <div class="container text-block">
    <h2 class="section__title">Privacy policy</h2>
    <p>We store only the data you enter: name, email, phone, date of birth, profile photo, your enrollments, lesson progress and reviews. The data is stored in our MySQL database and is used only to organise your studies. Passwords are never stored as text: we keep only a secure hash (bcrypt). Your data is never shared with third parties.</p>
  </div>
</section>

<!-- Terms -->
<section class="section section--white" id="terms">
  <div class="container text-block">
    <h2 class="section__title">Terms of use</h2>
    <ol>
      <li>One student can enroll in the same course only once (while the enrollment is active).</li>
      <li>You cannot study two courses at the same time: groups with overlapping lessons are blocked.</li>
      <li>An enrollment becomes active after it is confirmed by the administrator; then all lessons open.</li>
      <li>You can cancel an enrollment at any time before the course is completed.</li>
      <li>Please be polite with teachers and other students in reviews and messages.</li>
    </ol>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
