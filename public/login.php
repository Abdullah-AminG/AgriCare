<?php
// public/login.php - login for farmers and admins/advisers.
require_once dirname(__DIR__) . '/includes/init.php';

redirectIfLoggedIn();

$error = '';
$info  = '';
$email = '';

if (isset($_GET['timeout'])) {
    $info = 'You were logged out because of inactivity. Please log in again.';
} elseif (isset($_GET['loggedout'])) {
    $info = 'You have been logged out successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $email    = strtolower(postValue('email'));
    $password = postRaw('password');
    $ip       = clientIp();

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        try {
            if (isLoginLocked($pdo, $email, $ip)) {
                // Too many recent failures: do not even look at the password.
                $error = 'Too many failed login attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
            } else {
                $stmt = $pdo->prepare(
                    'SELECT user_id, full_name, email, password_hash, role
                     FROM users
                     WHERE email = :email
                     LIMIT 1'
                );
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();

                // password_verify() always runs (against a dummy hash if the email is unknown),
                // so the response time does not reveal whether an email address is registered.
                $hashToCheck = $user ? $user['password_hash'] : LOGIN_DUMMY_HASH;
                $passwordOk  = strlen($password) <= MAX_PASSWORD_BYTES && password_verify($password, $hashToCheck);

                if ($user && $passwordOk) {
                    clearFailedLogins($pdo, $email, $ip);
                    loginUser($user);
                    // Farmers go to the farmer dashboard, admins to the admin dashboard.
                    redirect(dashboardUrlForRole($user['role']));
                }

                recordFailedLogin($pdo, $email, $ip);
                // Generic message: do not reveal whether the email or the password was wrong.
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $exception) {
            error_log('AgriCare login error: ' . $exception->getMessage());
            $error = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Login';
$activeNav = 'login';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="auth-card">
    <h1>Log in</h1>
    <p class="muted">Access your AgriCare account.</p>

    <?php if ($info !== ''): ?>
        <div class="alert alert-info"><?= e($info) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login.php">
        <?= csrfField() ?>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>

    <p class="auth-switch">New farmer? <a href="/register.php">Create an account</a></p>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
