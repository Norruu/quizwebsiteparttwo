<?php
/**
 * Common Middleware
 * Use these functions to enforce auth, admin, user ownership, etc.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Auth required middleware
function requireAuth() {
    if (!Auth::check()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(baseUrl('/auth/login.php'));
        exit;
    }
}

// Admin only
function requireAdmin() {
    requireAuth();
    if (!Auth::isAdmin()) {
        http_response_code(403);
        echo "<div style='padding:2em;text-align:center;color:#e74c3c;font-weight:bold;'>Forbidden: Admins only</div>";
        exit;
    }
}

// Only allow access to own profile
function requireOwnProfile($userId) {
    requireAuth();
    if (Auth::id() !== (int)$userId && !Auth::isAdmin()) {
        http_response_code(403);
        echo "<div style='padding:2em;text-align:center;color:#e74c3c;font-weight:bold;'>Forbidden: You can only access your own profile.</div>";
        exit;
    }
}

// // CSRF protection for forms/post
// function verifyCsrf($token=null) {
//     $actual = $token ?? ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
//     return isset($_SESSION['csrf_token']) && $actual === $_SESSION['csrf_token'];
// }