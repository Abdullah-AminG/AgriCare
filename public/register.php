<?php
// public/register.php - farmer registration.
// The role is ALWAYS 'farmer'. A role sent by the browser is never used.
require_once dirname(__DIR__) . '/includes/init.php';

redirectIfLoggedIn();

$errors   = [];
$fullName = '';
$phone    = '';
$email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $fullName = postValue('full_name');
    $phone    = postValue('phone');
    $email    = strtolower(postValue('email'));
    $password = postRaw('password');
    $confirm  = postRaw('confirm_password');

    $profileError = validateProfileFields($fullName, $phone, $email);
    if ($profileError !== null) {
        $errors[] = $profileError;
    }
    if (!isStrongPassword($password)) {
        $errors[] = passwordRuleMessage();
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if (!$errors) {
        try {
            // Prevent duplicate email registrations.
            $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);

            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (full_name, phone, email, password_hash, role)
                     VALUES (:full_name, :phone, :email, :password_hash, :role)'
                );
                $stmt->execute([
                    'full_name'     => $fullName,
                    'phone'         => $phone,
                    'email'         => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role'          => 'farmer',
                ]);

                setFlash('success', 'Account created successfully. Please log in.');
                redirect('/login.php');
            }
        } catch (PDOException $exception) {
            error_log('AgriCare register error: ' . $exception->getMessage());
            $errors[] = 'Could not create the account. Please try again.';
        }
    }
}

$pageTitle = 'Register';
$activeNav = 'register';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="auth-card">
    <h1>Create a farmer account</h1>
    <p class="muted">Register to manage your farms and report crop problems.</p>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $message): ?>
                <div><?= e($message) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/register.php" novalidate>
        <?= csrfField() ?>

        <div class="form-group">
            <label for="full_name">Full name</label>
            <input type="text" id="full_name" name="full_name" maxlength="100" value="<?= e($fullName) ?>" required>
        </div>

        <div class="form-group">
            <label for="phone">Phone number</label>
            <input type="tel" id="phone" name="phone" maxlength="20" value="<?= e($phone) ?>" placeholder="03001234567" required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="150" value="<?= e($email) ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="new-password" maxlength="72" required>
            <small class="muted">8 to 72 characters, with at least one letter and one number.</small>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" data-match="password" autocomplete="new-password" maxlength="72" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Register</button>
    </form>

    <p class="auth-switch">Already registered? <a href="/login.php">Log in</a></p>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
