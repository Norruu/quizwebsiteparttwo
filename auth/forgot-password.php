<?php
/**
 * Forgot Password Page
 * Request password reset link by email
 */

$pageTitle = 'Forgot Password';
require_once __DIR__ . '/../includes/header.php';

if (Auth::check()) {
    redirect(baseUrl('/dashboard.php'));
}

$successMessage = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $errorMessage = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } else {
        // Generate reset token and send email (see api/auth.php for full logic)
        $token = randomString(64);
        $hashedToken = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        Database::update("DELETE FROM password_resets WHERE email = ?", [$email]);
        Database::insert(
            "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)",
            [$email, $hashedToken, $expiresAt]
        );
        // In production, send $resetLink via email
        $resetLink = APP_URL . "/auth/reset-password.php?token=$token&email=" . urlencode($email);
        logActivity('password_reset_requested', "Password reset requested for $email", null);

        $successMessage = 'If an account exists with this email, a reset link has been sent.';
        if (APP_DEBUG) {
            $successMessage .= "<br><b>Reset Link:</b> <a class='text-blue-500 underline' href='$resetLink'>$resetLink</a>";
        }
    }
}
?>

<div class="min-h-screen flex items-center justify-center px-3 py-12 bg-gray-100">
    <div class="w-full max-w-md bg-white rounded-xl shadow-xl p-8">
        <div class="text-center mb-6">
            <div class="text-4xl mb-2">🔑</div>
            <h1 class="text-2xl font-bold font-game gradient-text">Forgot Password</h1>
        </div>

        <?php if ($successMessage): ?>
            <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 text-center">
                <?= $successMessage ?>
            </div>
        <?php else: ?>
            <?php if ($errorMessage): ?>
                <div class="bg-red-50 text-red-700 p-3 mb-4 rounded-lg">
                    <?= $errorMessage ?>
                </div>
            <?php endif; ?>
            <form method="POST" class="space-y-6">
                <?= csrfField() ?>
                <div>
                    <label for="email" class="block font-semibold text-gray-700 mb-1">Your Email</label>
                    <input type="email" id="email" name="email" required
                        class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none"
                        placeholder="you@example.com" autocomplete="off">
                </div>
                <button type="submit" class="w-full bg-friv-blue text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors">
                    Send Reset Link
                </button>
            </form>
            <div class="mt-8 text-center text-sm text-gray-500">
                <a href="<?= baseUrl('/auth/login.php') ?>" class="text-blue-500 hover:underline">← Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>