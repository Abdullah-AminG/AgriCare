<?php
// public/account.php - Account Settings for both farmers and admins/advisers.
// A user can only change their OWN account (the ID comes from the session).
require_once dirname(__DIR__) . '/includes/init.php';

requireLogin();

$userId = currentUserId();

$stmt = $pdo->prepare('SELECT user_id, full_name, phone, email, password_hash, role FROM users WHERE user_id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    // The account no longer exists.
    logoutUser();
    redirect('/login.php');
}

$profileError  = '';
$passwordError = '';
$fullName = $user['full_name'];
$phone    = $user['phone'];
$email    = $user['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = postValue('action');

    if ($action === 'profile') {
        $fullName = postValue('full_name');
        $phone    = postValue('phone');
        $email    = strtolower(postValue('email'));

        $profileError = validateProfileFields($fullName, $phone, $email) ?? '';

        if ($profileError === '') {
            try {
                // The email must not belong to a DIFFERENT user.
                $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email AND user_id <> :id LIMIT 1');
                $stmt->execute(['email' => $email, 'id' => $userId]);

                if ($stmt->fetch()) {
                    $profileError = 'This email is already used by another account.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE users SET full_name = :full_name, phone = :phone, email = :email
                         WHERE user_id = :id'
                    );
                    $stmt->execute([
                        'full_name' => $fullName,
                        'phone'     => $phone,
                        'email'     => $email,
                        'id'        => $userId,
                    ]);

                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['email']     = $email;

                    setFlash('success', 'Your account information has been updated.');
                    redirect('/account.php');
                }
            } catch (PDOException $exception) {
                error_log('AgriCare account update error: ' . $exception->getMessage());
                $profileError = 'Could not update your account. Please try again.';
            }
        }

    } elseif ($action === 'password') {
        $currentPassword = postRaw('current_password');
        $newPassword     = postRaw('new_password');
        $confirmPassword = postRaw('confirm_password');

        if (!password_verify($currentPassword, $user['password_hash'])) {
            $passwordError = 'Your current password is incorrect.';
        } elseif (!isStrongPassword($newPassword)) {
            $passwordError = passwordRuleMessage();
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'New password and confirm password do not match.';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :id');
                $stmt->execute([
                    'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'id'   => $userId,
                ]);

                session_regenerate_id(true);
                setFlash('success', 'Your password has been changed.');
                redirect('/account.php');
            } catch (PDOException $exception) {
                error_log('AgriCare password change error: ' . $exception->getMessage());
                $passwordError = 'Could not change the password. Please try again.';
            }
        }
    }
}

$pageTitle  = 'Account Settings';
$activeNav  = 'account';
$useSidebar = true;
require dirname(__DIR__) . '/includes/header.php';
?>

<h1>Account Settings</h1>

<div class="card">
    <h2>Basic information</h2>

    <?php if ($profileError !== ''): ?>
        <div class="alert alert-error"><?= e($profileError) ?></div>
    <?php endif; ?>

    <form method="post" action="/account.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="profile">

        <div class="form-grid">
            <div class="form-group">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" maxlength="100" value="<?= e($fullName) ?>" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone number</label>
                <input type="tel" id="phone" name="phone" maxlength="20" value="<?= e($phone) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="150" value="<?= e($email) ?>" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <input type="text" value="<?= $user['role'] === 'admin' ? 'Admin / Adviser' : 'Farmer' ?>" disabled>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save changes</button>
    </form>
</div>

<div class="card">
    <h2>Change password</h2>

    <?php if ($passwordError !== ''): ?>
        <div class="alert alert-error"><?= e($passwordError) ?></div>
    <?php endif; ?>

    <form method="post" action="/account.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="password">

        <div class="form-grid">
            <div class="form-group">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
            </div>
            <div class="form-group"></div>
            <div class="form-group">
                <label for="new_password">New password</label>
                <input type="password" id="new_password" name="new_password" autocomplete="new-password" maxlength="72" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm new password</label>
                <input type="password" id="confirm_password" name="confirm_password" data-match="new_password" autocomplete="new-password" maxlength="72" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Change password</button>
    </form>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
