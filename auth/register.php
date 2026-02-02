<?php
/**
 * User Registration Page
 */

$pageTitle = 'Create Account';
require_once __DIR__ . '/../includes/header.php';

// Redirect if already logged in
Auth::guestOnly();

// Handle form submission
$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!verifyCsrf()) {
        $errors['csrf'] = 'Invalid request. Please try again.';
    } else {
        $old = [
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
        ];
        
        $result = Auth::register($_POST);
        
        if ($result['success']) {
            flash('success', $result['message']);
            redirect(baseUrl('/auth/login.php'));
        } else {
            $errors = $result['errors'] ?? ['general' => $result['message']];
        }
    }
}
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-gradient-to-br from-friv-blue to-friv-purple rounded-2xl flex items-center justify-center mx-auto mb-4 transform hover:rotate-12 transition-transform">
                <span class="text-4xl">🚀</span>
            </div>
            <h1 class="font-game text-4xl gradient-text">Join the Fun!</h1>
            <p class="text-gray-600 mt-2">Create your account and start playing</p>
        </div>
        
        <!-- Registration Form -->
        <div class="bg-white rounded-3xl shadow-xl p-8">
            <?php if (!empty($errors['general'])): ?>
                <div class="bg-red-100 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <span>⚠️</span>
                    <span><?= e($errors['general']) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="space-y-5">
                <?= csrfField() ?>
                
                <!-- Username -->
                <div>
                    <label for="username" class="block text-sm font-bold text-gray-700 mb-2">
                        👤 Username
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           value="<?= e($old['username'] ?? '') ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-friv-blue focus:ring-4 focus:ring-friv-blue/20 outline-none transition-all <?= isset($errors['username']) ? 'border-red-500' : '' ?>"
                           placeholder="Choose a cool username"
                           required
                           autofocus>
                    <?php if (isset($errors['username'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?= e($errors['username']) ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-bold text-gray-700 mb-2">
                        📧 Email Address
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?= e($old['email'] ?? '') ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-friv-blue focus:ring-4 focus:ring-friv-blue/20 outline-none transition-all <?= isset($errors['email']) ? 'border-red-500' : '' ?>"
                           placeholder="your@email.com"
                           required>
                    <?php if (isset($errors['email'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?= e($errors['email']) ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-bold text-gray-700 mb-2">
                        🔒 Password
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-friv-blue focus:ring-4 focus:ring-friv-blue/20 outline-none transition-all <?= isset($errors['password']) ? 'border-red-500' : '' ?>"
                           placeholder="At least 8 characters"
                           required>
                    <?php if (isset($errors['password'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?= e($errors['password']) ?></p>
                    <?php endif; ?>
                    <p class="text-gray-500 text-xs mt-1">
                        Use 8+ characters with uppercase, lowercase, number & symbol
                    </p>
                </div>
                
                <!-- Confirm Password -->
                <div>
                    <label for="password_confirm" class="block text-sm font-bold text-gray-700 mb-2">
                        🔒 Confirm Password
                    </label>
                                        <input type="password" 
                           id="password_confirm" 
                           name="password_confirm"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-friv-blue focus:ring-4 focus:ring-friv-blue/20 outline-none transition-all <?= isset($errors['password_confirm']) ? 'border-red-500' : '' ?>"
                           placeholder="Re-enter your password"
                           required>
                    <?php if (isset($errors['password_confirm'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?= e($errors['password_confirm']) ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Terms Agreement -->
                <div class="flex items-start gap-3">
                    <input type="checkbox" 
                           id="terms" 
                           name="terms" 
                           class="mt-1 w-5 h-5 text-friv-blue border-2 border-gray-300 rounded focus:ring-friv-blue"
                           required>
                    <label for="terms" class="text-sm text-gray-600">
                        I agree to the <a href="#" class="text-friv-blue hover:underline">Terms of Service</a> 
                        and <a href="#" class="text-friv-blue hover:underline">Privacy Policy</a>
                    </label>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-friv-blue to-friv-purple text-white font-bold py-4 px-6 rounded-xl hover:shadow-lg hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                    <span>Create Account</span>
                    <span>🎮</span>
                </button>
            </form>
            
            <!-- Divider -->
            <div class="flex items-center gap-4 my-6">
                <hr class="flex-1 border-gray-200">
                <span class="text-gray-400 text-sm">or</span>
                <hr class="flex-1 border-gray-200">
            </div>
            
            <!-- Login Link -->
            <p class="text-center text-gray-600">
                Already have an account? 
                <a href="<?= baseUrl('/auth/login.php') ?>" class="text-friv-blue font-bold hover:underline">
                    Login here
                </a>
            </p>
        </div>
        
        <!-- Bonus Info -->
        <div class="mt-6 bg-gradient-to-r from-friv-yellow/20 to-friv-orange/20 rounded-2xl p-4 text-center">
            <p class="text-gray-700">
                🎁 <strong>Welcome Bonus!</strong> Get <span class="text-friv-orange font-bold"><?= WELCOME_BONUS ?> points</span> when you sign up!
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>