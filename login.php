<?php
/* =========================================================
   login.php — log in by email and password.
   - password_verify() compares the password with the hash
   - after 5 wrong passwords the form is locked for 60 seconds
   - "Remember my email" saves the email in a cookie for 30 days
   - after login the session id is regenerated (auth.php)
   ========================================================= */
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$errors = [];
$redirectTo = safe_redirect_target((string)($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
$email = (string)($_POST['email'] ?? $_GET['email'] ?? ($_COOKIE['remember_email'] ?? ''));

if (is_post()) {
    verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $lockedUntil = $_SESSION['login_locked_until'] ?? 0;

    if ($lockedUntil > time()) {
        $errors['form'] = 'Too many failed attempts. Please wait ' . ($lockedUntil - time()) . ' seconds and try again.';
    } else {
        if ($email === '')                $errors['email'] = 'Please enter your email';
        elseif (!valid_email($email))     $errors['email'] = 'Incorrect email format';
        if ($password === '')             $errors['password'] = 'Please enter your password';

        if (!$errors) {
            $user = db_one('SELECT id, role, first_name, password_hash FROM users WHERE email = ?', [mb_strtolower($email)]);
            if (!$user) {
                $errors['email'] = 'No account found with this email. Please register first.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $errors['password'] = 'Incorrect password. Please try again.';
                $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
                if ($_SESSION['login_fails'] >= LOGIN_MAX_ATTEMPTS) {
                    $_SESSION['login_locked_until'] = time() + LOGIN_LOCK_SECONDS;
                    $_SESSION['login_fails'] = 0;
                    $errors['form'] = 'Too many failed attempts. The form is locked for ' . LOGIN_LOCK_SECONDS . ' seconds.';
                }
            } else {
                login_user($user);
                // Cookie: remember the email (only the email, never the password)
                if (!empty($_POST['remember'])) {
                    setcookie('remember_email', mb_strtolower($email), [
                        'expires' => time() + 30 * 24 * 3600, 'path' => BASE_PATH . '/', 'httponly' => true, 'samesite' => 'Lax']);
                } else {
                    setcookie('remember_email', '', ['expires' => time() - 3600, 'path' => BASE_PATH . '/']);
                }
                flash('success', 'Welcome, ' . $user['first_name'] . '!');
                redirect($redirectTo !== '' ? $redirectTo : ($user['role'] === 'admin' ? 'admin/index.php' : 'index.php'));
            }
        }
    }
}

$pageTitle = 'Login';
$activePage = 'login';
$pageScripts = ['auth.js'];
include __DIR__ . '/includes/header.php';
?>
<section class="form-page">
  <div class="form-card">
    <img class="form-card__logo" src="<?= h(url('images/logo.svg')) ?>" alt="EduConnect logo">
    <h1>Welcome back</h1>
    <p class="form-card__subtitle">Log in to continue learning</p>

    <div class="alert <?= isset($errors['form']) ? 'alert--error' : 'hidden' ?>" id="loginAlert" role="alert"><?= h($errors['form'] ?? '') ?></div>

    <!-- novalidate: the form is checked by our JavaScript and by PHP -->
    <form id="loginForm" method="post" action="login.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= h($redirectTo) ?>">
      <div class="<?= group_class($errors, 'email') ?>">
        <label for="loginEmail">Email <span class="required">*</span></label>
        <input type="email" id="loginEmail" name="email" value="<?= h($email) ?>" placeholder="student@email.com" autocomplete="email">
        <?= field_error($errors, 'email') ?>
      </div>

      <div class="<?= group_class($errors, 'password') ?>">
        <label for="loginPassword">Password <span class="required">*</span></label>
        <input type="password" id="loginPassword" name="password" placeholder="••••••••" autocomplete="current-password">
        <?= field_error($errors, 'password') ?>
      </div>

      <div class="form-group">
        <label class="checkbox"><input type="checkbox" name="remember" value="1" <?= isset($_COOKIE['remember_email']) ? 'checked' : '' ?>>Remember my email on this computer</label>
      </div>

      <button type="submit" class="btn btn--full">Login</button>
    </form>

    <p class="form-footer"><a href="#" id="forgotLink">Forgot password?</a></p>
    <p class="form-footer">Don't have an account? <a href="<?= h(url('register.php')) ?>">Register</a></p>

    <div class="alert alert--info demo-accounts">
      <strong>Demo accounts:</strong><br>
      Student: student@educonnect.kz / student123<br>
      Admin: admin@educonnect.kz / admin123
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
