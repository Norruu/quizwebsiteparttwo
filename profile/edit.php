<?php
/**
 * Edit Profile Page
 * Allows users to update username, avatar, email, etc.
 */
$pageTitle = 'Edit Profile';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/middleware.php';
requireAuth();

$user = Auth::user();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $avatar = $_POST['avatar'] ?? $user['avatar'];

    // Validate basic fields
    if (!$username) $error = 'Username required.';
    elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Valid email required.';
    else {
        // Update fields (handle unique checks as needed)
        $res = Database::update("UPDATE users SET username=?, email=?, avatar=?, updated_at=NOW() WHERE id=?",
            [$username, $email, $avatar, $user['id']]
        );
        $success = 'Profile updated.';
        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$user['id']]);
        $_SESSION['user'] = $user;
    }
}
?>

<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-xl mx-auto">
        <h1 class="font-game text-3xl mb-6 gradient-text">👤 Edit Profile</h1>
        <?php if ($error): ?>
            <div class="bg-red-50 text-red-700 p-3 mb-3 rounded-lg"><?= $error ?></div>
        <?php elseif ($success): ?>
            <div class="bg-green-50 text-green-700 p-3 mb-3 rounded-lg"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <?= csrfField() ?>
            <div>
                <label class="font-semibold text-gray-700 block mb-1">Username</label>
                <input type="text" name="username" required value="<?= e($user['username']) ?>"
                    class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
            </div>
            <div>
                <label class="font-semibold text-gray-700 block mb-1">Email</label>
                <input type="email" name="email" required value="<?= e($user['email']) ?>"
                    class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
            </div>
            <div>
                <label class="font-semibold text-gray-700 block mb-1">Avatar</label>
                <select name="avatar" class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                    <?php
                    $avatars = glob(__DIR__.'/../assets/images/avatars/*.png');
                    foreach ($avatars as $path):
                        $fname = basename($path);
                    ?>
                        <option value="<?= $fname ?>" <?= $user['avatar'] === $fname ? 'selected' : '' ?>>
                            <?= $fname ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="w-full bg-friv-blue text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors">
                Save Changes
            </button>
        </form>
    </div>
</div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>