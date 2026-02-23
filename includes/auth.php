<?php
/**
 * Authentication Functions
 * Handles user authentication, login, registration, and session management
 */

class Auth {
    private static ?array $user = null;
    
    /**
     * Attempt to log in a user
     */
    public static function attempt(string $email, string $password, bool $remember = false): array {
        // Find user by email
        $user = Database::fetch(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }
        
        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
            return [
                'success' => false, 
                'message' => "Account is locked. Try again in $remaining minutes."
            ];
        }
        
        // Check if account is banned
        if ($user['status'] === 'banned') {
            return ['success' => false, 'message' => 'This account has been banned.'];
        }
        
        if ($user['status'] === 'suspended') {
            return ['success' => false, 'message' => 'This account is suspended.'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Increment login attempts
            self::incrementLoginAttempts($user['id']);
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }
        
        // Successful login
        self::loginUser($user, $remember);
        
        return ['success' => true, 'message' => 'Login successful.', 'user' => $user];
    }
    
    /**
     * Log in a user (set session)
     */
    private static function loginUser(array $user, bool $remember = false): void {
        // Reset login attempts
        Database::update(
            "UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?",
            [$user['id']]
        );
        
        // Regenerate session ID for security
        Session::regenerate();
        
        // Store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['logged_in_at'] = time();
        
        // Handle remember me
        if ($remember) {
            $token = randomString(64);
            $hashedToken = hash('sha256', $token);
            
            Database::update(
                "UPDATE users SET remember_token = ? WHERE id = ?",
                [$hashedToken, $user['id']]
            );
            
            // Set cookie for 30 days
            setcookie(
                'remember_token',
                $token,
                [
                    'expires' => time() + (30 * 24 * 60 * 60),
                    'path' => '/',
                    'secure' => APP_ENV === 'production',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        }
        
        // Log activity
        logActivity('login', 'User logged in', $user['id']);
        
        // Clear cached user
        self::$user = null;
    }
    
    /**
     * Increment login attempts
     */
    private static function incrementLoginAttempts(int $userId): void {
        $user = Database::fetch("SELECT login_attempts FROM users WHERE id = ?", [$userId]);
        $attempts = ($user['login_attempts'] ?? 0) + 1;
        
        $lockUntil = null;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);
        }
        
        Database::update(
            "UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?",
            [$attempts, $lockUntil, $userId]
        );
    }
    
    /**
     * Register a new user
     */
    public static function register(array $data): array {
        // Validate input
        $validator = validate($data)
            ->required('username')
            ->username('username')
            ->unique('username', 'users')
            ->required('email')
            ->email('email')
            ->unique('email', 'users')
            ->required('password')
            ->password('password')
            ->confirmed('password', 'password_confirm');
        
        if ($validator->fails()) {
            return ['success' => false, 'errors' => $validator->errors()];
        }
        
        try {
            Database::beginTransaction();
            
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            
            // Create user
            $userId = Database::insert(
                "INSERT INTO users (username, email, password, role, status, created_at) 
                 VALUES (?, ?, ?, 'player', 'active', NOW())",
                [
                    sanitize($data['username']),
                    sanitize($data['email']),
                    $hashedPassword
                ]
            );
            
            // Create wallet with welcome bonus
            Database::insert(
                "INSERT INTO wallet (user_id, balance, total_earned) VALUES (?, ?, ?)",
                [$userId, WELCOME_BONUS, WELCOME_BONUS]
            );
            
            // Get wallet ID and record welcome bonus transaction
            $wallet = Database::fetch("SELECT id FROM wallet WHERE user_id = ?", [$userId]);
            
            Database::insert(
                "INSERT INTO transactions (wallet_id, type, amount, balance_after, description, reference_type) 
                 VALUES (?, 'bonus', ?, ?, 'Welcome bonus for new registration', 'bonus')",
                [$wallet['id'], WELCOME_BONUS, WELCOME_BONUS]
            );
            
            Database::commit();
            
            // Log activity
            logActivity('register', 'New user registered', $userId);
            
            return [
                'success' => true,
                'message' => 'Registration successful! You received ' . WELCOME_BONUS . ' bonus points.',
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    /**
     * Log out the current user
     */
    public static function logout(): void {
        $userId = $_SESSION['user_id'] ?? null;
        
        if ($userId) {
            // Clear remember token
            Database::update(
                "UPDATE users SET remember_token = NULL WHERE id = ?",
                [$userId]
            );
            
            logActivity('logout', 'User logged out', $userId);
        }
        
        // Clear remember cookie
        setcookie('remember_token', '', time() - 3600, '/');
        
        // Destroy session
        Session::destroy();
        
        // Clear cached user
        self::$user = null;
    }
    
    /**
     * Check if user is logged in
     */
    public static function check(): bool {
        if (isset($_SESSION['user_id'])) {
            return true;
        }
        
        // Check remember token
        if (isset($_COOKIE['remember_token'])) {
            return self::loginWithRememberToken($_COOKIE['remember_token']);
        }
        
        return false;
    }
    
    /**
     * Attempt login with remember token
     */
    private static function loginWithRememberToken(string $token): bool {
        $hashedToken = hash('sha256', $token);
        
        $user = Database::fetch(
            "SELECT * FROM users WHERE remember_token = ? AND status = 'active'",
            [$hashedToken]
        );
        
        if ($user) {
            self::loginUser($user, true);
            return true;
        }
        
        // Invalid token - clear cookie
        setcookie('remember_token', '', time() - 3600, '/');
        return false;
    }
    
    /**
     * Get current authenticated user
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        
        if (self::$user === null) {
            self::$user = Database::fetch(
                "SELECT u.*, w.balance as wallet_balance 
                 FROM users u 
                 LEFT JOIN wallet w ON u.id = w.user_id 
                 WHERE u.id = ?",
                [$_SESSION['user_id']]
            );
        }
        
        return self::$user;
    }
    
    /**
     * Get current user ID
     */
    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Check if current user is admin
     */
    public static function isAdmin(): bool {
        return self::check() && ($_SESSION['user_role'] ?? '') === 'admin';
    }
    
    /**
     * Check if current user is player
     */
    public static function isPlayer(): bool {
        return self::check() && ($_SESSION['user_role'] ?? '') === 'player';
    }
    
    /**
     * Require authentication (redirect if not logged in)
     */
    public static function requireLogin(string $redirect = '/auth/login.php'): void {
        if (!self::check()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            flash('error', 'Please log in to continue.');
            redirect(baseUrl($redirect));
        }
    }
    
    /**
     * Require admin role
     */
    public static function requireAdmin(): void {
        self::requireLogin();
        
        if (!self::isAdmin()) {
            flash('error', 'Access denied. Admin privileges required.');
            redirect(baseUrl('/dashboard.php'));
        }
    }
    
    /**
     * Require specific role
     */
    public static function requireRole(string|array $roles): void {
        self::requireLogin();
        
        $roles = (array) $roles;
        $userRole = $_SESSION['user_role'] ?? '';
        
        if (!in_array($userRole, $roles)) {
            flash('error', 'Access denied. Insufficient privileges.');
            redirect(baseUrl('/dashboard.php'));
        }
    }
    
    /**
     * Guest only (redirect if logged in)
     */
    public static function guestOnly(string $redirect = '/dashboard.php'): void {
        if (self::check()) {
            redirect(baseUrl($redirect));
        }
    }
    
    /**
     * Get intended URL after login
     */
    public static function intended(string $default = '/game-library/dashboard.php'): string {
        $url = $_SESSION['intended_url'] ?? $default;
        unset($_SESSION['intended_url']);
        return $url;
    }
    
    /**
     * Update user password
     */
    public static function updatePassword(int $userId, string $newPassword): bool {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        
        return Database::update(
            "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
            [$hashedPassword, $userId]
        ) > 0;
    }
    
    /**
     * Verify current password
     */
    public static function verifyPassword(string $password): bool {
        $user = self::user();
        
        if (!$user) {
            return false;
        }
        
        return password_verify($password, $user['password']);
    }
}

// Global helper functions
function auth(): ?array {
    return Auth::user();
}

function isLoggedIn(): bool {
    return Auth::check();
}

function isAdmin(): bool {
    return Auth::isAdmin();
}

function userId(): ?int {
    return Auth::id();
}