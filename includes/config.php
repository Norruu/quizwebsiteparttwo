<?php
/**
 * Application Configuration
 * Contains all configuration constants and settings
 */

// Prevent direct access
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

// ============================================
// ENVIRONMENT SETTINGS
// ============================================
define('APP_ENV', 'development'); // 'development' or 'production'
define('APP_DEBUG', APP_ENV === 'development');
define('APP_NAME', 'Bountiful Harvest');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/game-library');

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'game_library');
define('DB_USER', 'root');
define('DB_PASS', ''); // Change in production!
define('DB_CHARSET', 'utf8mb4');

// ============================================
// SECURITY SETTINGS
// ============================================
define('HASH_COST', 12); // bcrypt cost factor
define('SESSION_LIFETIME', 7200); // 2 hours in seconds
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// Secret key for tokens (CHANGE THIS IN PRODUCTION!)
define('APP_SECRET', 'your-super-secret-key-change-this-in-production-' . md5(__FILE__));

// ============================================
// GAME SETTINGS
// ============================================
define('DEFAULT_POINTS_REWARD', 10);
define('MAX_DAILY_PLAYS_DEFAULT', 10);
define('SCORE_VALIDATION_ENABLED', true);
define('ANTI_CHEAT_ENABLED', true);

// Point multipliers
define('POINT_MULTIPLIER_EASY', 1.0);
define('POINT_MULTIPLIER_MEDIUM', 1.5);
define('POINT_MULTIPLIER_HARD', 2.0);

// ============================================
// WALLET SETTINGS
// ============================================
define('WELCOME_BONUS', 100);
define('MIN_BALANCE', 0);
define('MAX_POINTS_PER_GAME', 1000);

// ============================================
// FILE UPLOAD SETTINGS
// ============================================
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('AVATAR_PATH', 'assets/images/avatars/');
define('GAME_THUMBNAIL_PATH', 'assets/images/games/');
define('REWARD_IMAGE_PATH', 'assets/images/rewards/');

// ============================================
// PAGINATION
// ============================================
define('GAMES_PER_PAGE', 20);
define('USERS_PER_PAGE', 25);
define('TRANSACTIONS_PER_PAGE', 15);
define('LEADERBOARD_LIMIT', 100);

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set('UTC');

// ============================================
// ERROR HANDLING
// ============================================
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// ============================================
// AUTOLOAD HELPER (simple version)
// ============================================
function autoload_includes() {
    $includes = [
        'database.php',
        'functions.php',
        'validation.php',
        'auth.php',
        'session.php',
    ];
    
    foreach ($includes as $file) {
        $path = __DIR__ . '/' . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }
}