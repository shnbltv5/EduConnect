<?php
/* =========================================================
   register.php — create a new account.
   GET  -> show the form
   POST -> check CSRF, validate on the server, save the user
           with a HASHED password, go to the login page.
   ========================================================= */
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('index.php');                   // already logged in
}

$data = [];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = $_POST;
    $errors = validate_registration($data);

    if (!$errors) {
        try {
            db_exec('INSERT INTO users (first_name, last_name, email, password_hash, role, phone, birth_date)
                     VALUES (?, ?, ?, ?, ?, ?, ?)', [
                trim($data['first_name']),
                trim($data['last_name']),
                mb_strtolower(trim($data['email'])),
                password_hash($data['password'], PASSWORD_DEFAULT),   // bcrypt hash, never the plain password
                'student',
                trim($data['phone'] ?? '') === '' ? null : normalize_phone($data['phone']),
                trim($data['birth_date'] ?? '') === '' ? null : $data['birth_date'],
            ]);
            flash('success', '✓ Account created successfully! Now log in with your email and password.');
            redirect('login.php?email=' . urlencode(mb_strtolower(trim($data['email']))));
        } catch (PDOException $e) {
            if (!is_constraint_error($e)) {
                throw $e;
            }
            $errors['email'] = 'This email is already registered';   // two people registered at the same moment
        }
    }
}

$pageTitle = 'Create account';
$activePage = 'register';
$pageScripts = ['auth.js'];
include __DIR__ . '/includes/header.php';
?>
<section class="form-page">
  <div class="form-card form-card--wide">
    <img class="form-card__logo" src="<?= h(url('images/logo.svg')) ?>" alt="EduConnect logo">
    <h1>Create account</h1>
    <p class="form-card__subtitle">Join EduConnect and start learning today</p>

    <div class="alert <?= $errors ? 'alert--error' : 'hidden' ?>" id="registerAlert" role="alert">
      <?= $errors ? 'Please correct the errors below (' . count($errors) . ').' : '' ?></div>

    <form id="registerForm" method="post" action="register.php" novalidate>
      <?= csrf_field() ?>
      <div class="form-row">
        <div class="<?= group_class($errors, 'first_name') ?>">
          <label for="firstName">First Name <span class="required">*</span></label>
          <input type="text" id="firstName" name="first_name" value="<?= old($data, 'first_name') ?>" placeholder="Asel" autocomplete="given-name" maxlength="50">
          <?= field_error($errors, 'first_name') ?>
        </div>
        <div class="<?= group_class($errors, 'last_name') ?>">
          <label for="lastName">Last Name <span class="required">*</span></label>
          <input type="text" id="lastName" name="last_name" value="<?= old($data, 'last_name') ?>" placeholder="Bekova" autocomplete="family-name" maxlength="50">
          <?= field_error($errors, 'last_name') ?>
        </div>
      </div>

      <div class="<?= group_class($errors, 'email') ?>">
        <label for="email">Email <span class="required">*</span></label>
        <input type="email" id="email" name="email" value="<?= old($data, 'email') ?>" placeholder="student@email.com" autocomplete="email" maxlength="120">
        <p class="field-hint" id="emailStatus" aria-live="polite"></p>
        <?= field_error($errors, 'email') ?>
      </div>

      <div class="form-row">
        <div class="<?= group_class($errors, 'phone') ?>">
          <label for="phone">Phone Number <span class="optional">(optional)</span></label>
          <input type="tel" id="phone" name="phone" value="<?= old($data, 'phone') ?>" placeholder="+7 701 123 45 67" autocomplete="tel">
          <?= field_error($errors, 'phone') ?>
        </div>
        <div class="<?= group_class($errors, 'birth_date') ?>">
          <label for="birthDate">Date of Birth <span class="optional">(optional)</span></label>
          <input type="date" id="birthDate" name="birth_date" value="<?= old($data, 'birth_date') ?>" max="<?= date('Y-m-d') ?>">
          <?= field_error($errors, 'birth_date') ?>
        </div>
      </div>

      <div class="form-row">
        <div class="<?= group_class($errors, 'password') ?>">
          <label for="password">Password <span class="required">*</span></label>
          <input type="password" id="password" name="password" placeholder="8+ characters, letters and digits" autocomplete="new-password">
          <?= field_error($errors, 'password') ?>
        </div>
        <div class="<?= group_class($errors, 'confirm_password') ?>">
          <label for="confirmPassword">Confirm Password <span class="required">*</span></label>
          <input type="password" id="confirmPassword" name="confirm_password" placeholder="Repeat password" autocomplete="new-password">
          <?= field_error($errors, 'confirm_password') ?>
        </div>
      </div>

      <button type="submit" class="btn btn--full">Register</button>
    </form>

    <p class="form-footer">Already have an account? <a href="<?= h(url('login.php')) ?>">Login</a></p>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
