<?php
/**
 * User Login Page
 */

$pageTitle = 'Login';
require_once __DIR__ . '/../includes/header.php';

// Redirect if already logged in
Auth::guestOnly();

// Handle form submission
$errors = [];
$old = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!verifyCsrf()) {
        $errors['csrf'] = 'Invalid request. Please try again.';
    } else {
        $old['email'] = $_POST['email'] ?? '';
        $remember = isset($_POST['remember']);
        
        $result = Auth::attempt(
            $_POST['email'] ?? '',
            $_POST['password'] ?? '',
            $remember
        );
        
        if ($result['success']) {
            flash('success', 'Welcome back, ' . $result['user']['username'] . '!');
            redirect(Auth::intended());
        } else {
            $errors['login'] = $result['message'];
        }
    }
}
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-gradient-to-br from-friv-orange to-friv-yellow rounded-2xl flex items-center justify-center mx-auto mb-4 transform hover:rotate-12 transition-transform">
                <span class="text-4xl">🎮</span>
            </div>
            <h1 class="font-game text-4xl gradient-text">Welcome Back!</h1>
            <p class="text-gray-600 mt-2">Login to continue your gaming journey</p>
        </div>
        
        <!-- Login Form -->
        <div class="bg-white rounded-3xl shadow-xl p-8">
            <?php if (!empty($errors['login'])): ?>
                <div class="bg-red-100 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <span>⚠️</span>
                    <span><?= e($errors['login']) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="space-y-5">
                <?= csrfField() ?>
                
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-bold text-gray-700 mb-2">
                        📧 Email Address
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?= e($old['email']) ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-friv-orange focus:ring-4 focus:ring-friv-orange/20 outline-none transition-all"
                           placeholder="your@email.com"
                           required
                           autofocus>
                </div>
                
                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-bold text-gray-700 mb-2">
                        🔒 Password
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-friv-orange focus:ring-4 focus:ring-friv-orange/20 outline-none transition-all"
                           placeholder="Enter your password"
                           required>
                </div>
                
                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" 
                               name="remember" 
                               class="w-5 h-5 text-friv-orange border-2 border-gray-300 rounded focus:ring-friv-orange">
                        <span class="text-sm text-gray-600">Remember me</span>
                    </label>
                    <a href="<?= baseUrl('/auth/forgot-password.php') ?>" class="text-sm text-friv-blue hover:underline">
                        Forgot password?
                    </a>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-friv-orange to-friv-yellow text-white font-bold py-4 px-6 rounded-xl hover:shadow-lg hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                    <span>Login</span>
                    <span>→</span>
                </button>
            </form>
            
            <!-- Divider -->
            <div class="flex items-center gap-4 my-6">
                <hr class="flex-1 border-gray-200">
                <span class="text-gray-400 text-sm">or</span>
                <hr class="flex-1 border-gray-200">
            </div>
            
            <!-- Register Link -->
            <p class="text-center text-gray-600">
                Don't have an account? 
                <a href="<?= baseUrl('/auth/register.php') ?>" class="text-friv-blue font-bold hover:underline">
                    Sign up for free
                </a>
            </p>
        </div>
        
        <!-- Demo Accounts (Remove in production) -->
        <!-- 
        <div class="mt-6 bg-blue-50 rounded-2xl p-4">
            <p class="text-blue-800 font-bold text-sm mb-2">🧪 Demo Accounts (Dev Only)</p>
            <div class="text-sm text-blue-700 space-y-1">
                <p><strong>Admin:</strong> admin@gamelibrary.com / Admin@123</p>
                <p><strong>Player:</strong> player@gamelibrary.com / Player@123</p>
            </div>
        </div>
        -->
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>