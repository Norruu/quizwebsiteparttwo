<?php
/**
 * Authentication API
 * Handles login, register, logout, password reset, and session management
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/security.php';

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Fall back to POST data
    $input = $_POST;
}

$action = $input['action'] ?? '';

// Rate limiting for auth endpoints
$rateLimitConfig = [
    'login' => ['attempts' => 5, 'window' => 300],      // 5 attempts per 5 minutes
    'register' => ['attempts' => 3, 'window' => 3600],  // 3 registrations per hour
    'forgot_password' => ['attempts' => 3, 'window' => 3600],
    'default' => ['attempts' => 30, 'window' => 60],
];

$rateLimit = $rateLimitConfig[$action] ?? $rateLimitConfig['default'];
Security::rateLimit("auth_$action", $rateLimit['attempts'], $rateLimit['window']);

switch ($action) {
    case 'login':
        handleLogin($input);
        break;
        
    case 'register':
        handleRegister($input);
        break;
        
    case 'logout':
        handleLogout();
        break;
        
    case 'check':
        handleCheckAuth();
        break;
        
    case 'forgot_password':
        handleForgotPassword($input);
        break;
        
    case 'reset_password':
        handleResetPassword($input);
        break;
        
    case 'change_password':
        handleChangePassword($input);
        break;
        
    case 'update_profile':
        handleUpdateProfile($input);
        break;
        
    case 'refresh_session':
        handleRefreshSession();
        break;
        
    default:
        jsonError('Invalid action');
}

/**
 * Handle user login
 */
function handleLogin(array $input): void {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $remember = (bool)($input['remember'] ?? false);
    
    // Validate input
    if (empty($email) || empty($password)) {
        jsonError('Email and password are required');
    }
    
    // Attempt login
    $result = Auth::attempt($email, $password, $remember);
    
    if ($result['success']) {
        $user = Auth::user();
        
        jsonSuccess([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'avatar' => $user['avatar'],
                'role' => $user['role'],
                'wallet_balance' => $user['wallet_balance'] ?? 0,
            ],
            'redirect' => Auth::intended('/dashboard.php')
        ], 'Login successful');
    } else {
        jsonError($result['message'], 401);
    }
}

/**
 * Handle user registration
 */
function handleRegister(array $input): void {
    // Check if registration is enabled
    $registrationEnabled = Database::fetch(
        "SELECT setting_value FROM settings WHERE setting_key = 'registration_enabled'"
    );
    
    if ($registrationEnabled && $registrationEnabled['setting_value'] === 'false') {
        jsonError('Registration is currently disabled', 403);
    }
    
    // Validate input
    $validator = validate($input)
        ->required('username', 'Username')
        ->username('username')
        ->minLength('username', 3)
        ->maxLength('username', 30)
        ->unique('username', 'users')
        ->required('email', 'Email')
        ->email('email')
        ->unique('email', 'users')
        ->required('password', 'Password')
        ->password('password')
        ->confirmed('password', 'password_confirm');
    
    if ($validator->fails()) {
        jsonError('Validation failed', 422, ['errors' => $validator->errors()]);
    }
    
    // Attempt registration
    $result = Auth::register($input);
    
    if ($result['success']) {
        // Auto-login after registration (optional)
        $loginResult = Auth::attempt($input['email'], $input['password']);
        
        jsonSuccess([
            'user_id' => $result['user_id'],
            'logged_in' => $loginResult['success'],
            'redirect' => '/dashboard.php'
        ], $result['message']);
    } else {
        jsonError($result['message'] ?? 'Registration failed', 422, [
            'errors' => $result['errors'] ?? []
        ]);
    }
}

/**
 * Handle user logout
 */
function handleLogout(): void {
    Auth::logout();
    jsonSuccess(['redirect' => '/'], 'Logged out successfully');
}

/**
 * Check authentication status
 */
function handleCheckAuth(): void {
    if (Auth::check()) {
        $user = Auth::user();
        
        jsonSuccess([
            'authenticated' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'avatar' => $user['avatar'],
                'role' => $user['role'],
                'wallet_balance' => $user['wallet_balance'] ?? 0,
            ]
        ]);
    } else {
        jsonSuccess([
            'authenticated' => false,
            'user' => null
        ]);
    }
}

/**
 * Handle forgot password request
 */
function handleForgotPassword(array $input): void {
    $email = trim($input['email'] ?? '');
    
    if (empty($email)) {
        jsonError('Email is required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Invalid email address');
    }
    
    // Check if user exists
    $user = Database::fetch(
        "SELECT id, username, email FROM users WHERE email = ? AND status = 'active'",
        [$email]
    );
    
    // Always return success to prevent email enumeration
    if (!$user) {
        jsonSuccess([], 'If an account exists with this email, a reset link has been sent.');
        return;
    }
    
    // Generate reset token
    $token = randomString(64);
    $hashedToken = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Delete old tokens for this email
    Database::update("DELETE FROM password_resets WHERE email = ?", [$email]);
    
    // Insert new token
    Database::insert(
        "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)",
        [$email, $hashedToken, $expiresAt]
    );
    
    // In production, send email with reset link
    // For now, we'll just log it (in development)
    $resetLink = APP_URL . "/auth/reset-password.php?token=$token&email=" . urlencode($email);
    
    if (APP_DEBUG) {
        // In development, return the link (remove in production!)
        jsonSuccess([
            'debug_reset_link' => $resetLink
        ], 'If an account exists with this email, a reset link has been sent.');
    } else {
        // In production, send actual email
        // mail($email, 'Password Reset', "Click here to reset: $resetLink");
        jsonSuccess([], 'If an account exists with this email, a reset link has been sent.');
    }
    
    logActivity('password_reset_requested', "Password reset requested for $email", null);
}

/**
 * Handle password reset
 */
function handleResetPassword(array $input): void {
    $email = trim($input['email'] ?? '');
    $token = $input['token'] ?? '';
    $password = $input['password'] ?? '';
    $passwordConfirm = $input['password_confirm'] ?? '';
    
    // Validate input
    if (empty($email) || empty($token) || empty($password)) {
        jsonError('All fields are required');
    }
    
    if ($password !== $passwordConfirm) {
        jsonError('Passwords do not match');
    }
    
    // Validate password strength
    $validator = validate(['password' => $password])->password('password');
    if ($validator->fails()) {
        jsonError($validator->firstError());
    }
    
    // Verify token
    $hashedToken = hash('sha256', $token);
    $resetRecord = Database::fetch(
        "SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() AND used_at IS NULL",
        [$email, $hashedToken]
    );
    
    if (!$resetRecord) {
        jsonError('Invalid or expired reset token', 400);
    }
    
    // Get user
    $user = Database::fetch("SELECT id FROM users WHERE email = ?", [$email]);
    
    if (!$user) {
        jsonError('User not found', 404);
    }
    
    // Update password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
    
    Database::update(
        "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
        [$hashedPassword, $user['id']]
    );
    
    // Mark token as used
    Database::update(
        "UPDATE password_resets SET used_at = NOW() WHERE id = ?",
        [$resetRecord['id']]
    );
    
    logActivity('password_reset_completed', "Password reset completed for $email", $user['id']);
    
    jsonSuccess(['redirect' => '/auth/login.php'], 'Password has been reset successfully. You can now login.');
}

/**
 * Handle password change (for logged in users)
 */
function handleChangePassword(array $input): void {
    if (!Auth::check()) {
        jsonError('Authentication required', 401);
    }
    
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    // Validate input
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        jsonError('All fields are required');
    }
    
    if ($newPassword !== $confirmPassword) {
        jsonError('New passwords do not match');
    }
    
    // Verify current password
    if (!Auth::verifyPassword($currentPassword)) {
        jsonError('Current password is incorrect');
    }
    
    // Validate new password strength
    $validator = validate(['password' => $newPassword])->password('password');
    if ($validator->fails()) {
        jsonError($validator->firstError());
    }
    
    // Update password
    $result = Auth::updatePassword(Auth::id(), $newPassword);
    
    if ($result) {
        logActivity('password_changed', 'User changed their password', Auth::id());
        jsonSuccess([], 'Password changed successfully');
    } else {
        jsonError('Failed to change password');
    }
}

/**
 * Handle profile update
 */
function handleUpdateProfile(array $input): void {
    if (!Auth::check()) {
        jsonError('Authentication required', 401);
    }
    
    $userId = Auth::id();
    $user = Auth::user();
    
    $updates = [];
    $params = [];
    
    // Username update
    if (isset($input['username']) && $input['username'] !== $user['username']) {
        $username = sanitize($input['username']);
        
        $validator = validate(['username' => $username])
            ->username('username')
            ->unique('username', 'users', 'username', $userId);
        
        if ($validator->fails()) {
            jsonError($validator->firstError());
        }
        
        $updates[] = "username = ?";
        $params[] = $username;
    }
    
    // Email update
    if (isset($input['email']) && $input['email'] !== $user['email']) {
        $email = sanitize($input['email']);
        
        $validator = validate(['email' => $email])
            ->email('email')
            ->unique('email', 'users', 'email', $userId);
        
        if ($validator->fails()) {
            jsonError($validator->firstError());
        }
        
        $updates[] = "email = ?";
        $params[] = $email;
    }
    
    // Avatar update (handle file upload separately)
    if (isset($input['avatar']) && $input['avatar'] !== $user['avatar']) {
        $avatar = sanitize($input['avatar']);
        $updates[] = "avatar = ?";
        $params[] = $avatar;
    }
    
    if (empty($updates)) {
        jsonSuccess(['user' => $user], 'No changes to save');
        return;
    }
    
    $params[] = $userId;
    
    Database::update(
        "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?",
        $params
    );
    
    // Refresh user data
    $updatedUser = Database::fetch(
        "SELECT u.*, w.balance as wallet_balance FROM users u LEFT JOIN wallet w ON u.id = w.user_id WHERE u.id = ?",
        [$userId]
    );
    
    logActivity('profile_updated', 'User updated their profile', $userId);
    
    jsonSuccess([
        'user' => [
            'id' => $updatedUser['id'],
            'username' => $updatedUser['username'],
            'email' => $updatedUser['email'],
            'avatar' => $updatedUser['avatar'],
            'role' => $updatedUser['role'],
            'wallet_balance' => $updatedUser['wallet_balance'] ?? 0,
        ]
    ], 'Profile updated successfully');
}

/**
 * Refresh session (extend session lifetime)
 */
function handleRefreshSession(): void {
    if (!Auth::check()) {
        jsonError('Authentication required', 401);
    }
    
    Session::regenerate();
    $_SESSION['_last_activity'] = time();
    
    jsonSuccess([
        'session_refreshed' => true,
        'expires_in' => SESSION_LIFETIME
    ]);
}