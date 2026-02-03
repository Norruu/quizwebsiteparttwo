<?php
/**
 * Reset Password Page
 * Allows user to set new password using a secure token from email
 */

$pageTitle = 'Reset Password';
require_once __DIR__ . '/../includes/header.php';

if (Auth::check()) {
    redirect(baseUrl('/dashboard.php'));
}

// Get token/email from query
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$showForm = true;
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $email = trim($_POST['email'] ?? '');
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (empty($email) || empty($token) || empty($password) || empty($passwordConfirm)) {
        $errorMessage = 'Please complete all fields.';
    } elseif ($password !== $passwordConfirm) {
        $errorMessage = 'Passwords do not match.';
    } elseif (strlen($password) < (int)getSetting('min_password_length',8)) {
        $errorMessage = 'Password must be at least ' . getSetting('min_password_length',8) . ' characters.';
    } else {
        // Verify reset token
        $hashedToken = hash('sha256', $token);
        $resetRecord = Database::fetch(
            "SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() AND used_at IS NULL",
            [$email, $hashedToken]
        );
        if (!$resetRecord) {
            $errorMessage = 'Invalid or expired reset link.';
            $showForm = false;
        } else {
            $user = Database::fetch("SELECT id FROM users WHERE email = ?", [$email]);
            if (!$user) {
                $errorMessage = 'User not found.';
                $showForm = false;
            } else {
                $hashedPass = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                Database::update(
                    "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
                    [$hashedPass, $user['id']]
                );
                Database::update(
                    "UPDATE password_resets SET used_at = NOW() WHERE id = ?",
                    [$resetRecord['id']]
                );
                logActivity('password_reset_completed', "Password reset completed for $email", $user['id']);
                $successMessage = 'Your password has been reset successfully! <a class="text-blue-500 underline" href="' . baseUrl('/auth/login.php') . '">Login now</a>.';
                $showForm = false;
            }
        }
    }
}
?>

<div class="min-h-screen flex items-center justify-center px-3 py-12 bg-gray-100">
    <div class="w-full max-w-md bg-white rounded-xl shadow-xl p-8">
        <div class="text-center mb-6">
            <div class="text-4xl mb-2">🔒</div>
            <h1 class="text-2xl font-bold font-game gradient-text">Reset Password</h1>
        </div>

        <?php if ($errorMessage): ?>
            <div class="bg-red-50 text-red-700 p-3 mb-5 rounded-lg"><?= $errorMessage ?></div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="bg-green-50 text-green-700 p-4 mb-3 rounded-lg text-center"><?= $successMessage ?></div>
        <?php endif; ?>

        <?php if ($showForm): ?>
            <form method="POST" class="space-y-6">
                <?= csrfField() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="email" value="<?= e($email) ?>">

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">New Password</label>
                    <input type="password" name="password" required autocomplete="off" minlength="<?=(int)getSetting('min_password_length',8)?>"
                        class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none" placeholder="New password">
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" name="password_confirm" required autocomplete="off" minlength="<?=(int)getSetting('min_password_length',8)?>"
                        class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none" placeholder="Repeat new password">
                </div>

                <button type="submit" class="w-full bg-friv-blue text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors">
                    Reset Password
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-8 text-center text-sm text-gray-500">
            <a href="<?= baseUrl('/auth/login.php') ?>" class="text-blue-500 hover:underline">← Back to Login</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>