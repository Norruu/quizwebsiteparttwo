<?php
/**
 * Global Helper Functions
 * Utility functions used throughout the application
 */

/**
 * Redirect to a URL
 */
function redirect(string $url, int $statusCode = 302): void {
    if (!headers_sent()) {
        header("Location: " . $url, true, $statusCode);
        exit;
    }
    echo "<script>window.location.href='" . htmlspecialchars($url) . "';</script>";
    exit;
}

/**
 * Get base URL
 */
function baseUrl(string $path = ''): string {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Get asset URL
 */
function asset(string $path): string {
    return baseUrl('assets/' . ltrim($path, '/'));
}

/**
 * Escape HTML output
 */
function e(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_LIFETIME) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output CSRF hidden input field
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Verify CSRF token
 */
function verifyCsrf(?string $token = null): bool {
    $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash message system
 */
function flash(string $key, ?string $message = null): mixed {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

/**
 * Check if flash message exists
 */
function hasFlash(string $key): bool {
    return isset($_SESSION['flash'][$key]);
}

/**
 * Get all flash messages
 */
function getFlashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    return $flashes;
}

/**
 * Format number with suffix (1K, 1M, etc.)
 */
function formatNumber(int $number): string {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    }
    if ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return (string) $number;
}

/**
 * Format points display
 */
function formatPoints(int $points): string {
    return number_format($points) . ' pts';
}

/**
 * Calculate time ago
 */
function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    
    return date('M j, Y', $time);
}

/**
 * Generate random string
 */
function randomString(int $length = 32): string {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate URL-friendly slug
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: 'n-a';
}

/**
 * Get user's IP address
 */
function getClientIp(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = explode(',', $_SERVER[$header])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0';
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Success JSON response
 */
function jsonSuccess(mixed $data = null, string $message = 'Success'): void {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * Error JSON response
 */
function jsonError(string $message, int $statusCode = 400, array $errors = []): void {
    jsonResponse([
        'success' => false,
        'message' => $message,
        'errors' => $errors
    ], $statusCode);
}

/**
 * Log activity
 */
function logActivity(string $action, ?string $description = null, ?int $userId = null): void {
    try {
        Database::insert(
            "INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) 
             VALUES (?, ?, ?, ?, ?)",
            [
                $userId ?? ($_SESSION['user_id'] ?? null),
                $action,
                $description,
                getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]
        );
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Check if request is AJAX
 */
function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get pagination data
 */
function paginate(int $total, int $perPage, int $currentPage = 1): array {
    $totalPages = (int) ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
    ];
}

/**
 * Calculate game points based on score
 */
function calculatePoints(int $score, int $baseReward, float $multiplier = 1.0, int $minScore = 0): int {
    if ($score < $minScore) {
        return 0;
    }
    
    // Base calculation: score percentage * base reward * multiplier
    // Cap at MAX_POINTS_PER_GAME
    $points = (int) round(($score / 100) * $baseReward * $multiplier);
    
    return min($points, MAX_POINTS_PER_GAME);
}
/**
 * Generate game session token
 * Used to validate score submissions
 */
function generateGameSession(int $userId, int $gameId): string {
    $data = $userId . '|' . $gameId . '|' . time() . '|' . randomString(16);
    $token = hash_hmac('sha256', $data, APP_SECRET);
    
    $_SESSION['game_sessions'][$token] = [
        'user_id' => $userId,
        'game_id' => $gameId,
        'started_at' => time(),
        'ip' => getClientIp()
    ];
    
    return $token;
}

/**
 * Validate game session token
 */
function validateGameSession(string $token, int $userId, int $gameId): bool {
    if (empty($_SESSION['game_sessions'][$token])) {
        return false;
    }
    
    $session = $_SESSION['game_sessions'][$token];
    
    // Check user and game match
    if ($session['user_id'] !== $userId || $session['game_id'] !== $gameId) {
        return false;
    }
    
    // Check IP hasn't changed (optional, can be disabled for mobile users)
    if (ANTI_CHEAT_ENABLED && $session['ip'] !== getClientIp()) {
        return false;
    }
    
    // Check session isn't too old (max 1 hour)
    if ((time() - $session['started_at']) > 3600) {
        return false;
    }
    
    return true;
}

/**
 * Invalidate game session after score submission
 */
function invalidateGameSession(string $token): void {
    unset($_SESSION['game_sessions'][$token]);
}

/**
 * Get difficulty multiplier
 */
function getDifficultyMultiplier(string $difficulty): float {
    return match($difficulty) {
        'easy' => POINT_MULTIPLIER_EASY,
        'medium' => POINT_MULTIPLIER_MEDIUM,
        'hard' => POINT_MULTIPLIER_HARD,
        default => 1.0
    };
}

/**
 * Check daily play limit
 */
function checkDailyPlayLimit(int $userId, int $gameId, int $maxPlays): array {
    $today = date('Y-m-d');
    
    $record = Database::fetch(
        "SELECT play_count, points_earned FROM daily_play_limits 
         WHERE user_id = ? AND game_id = ? AND play_date = ?",
        [$userId, $gameId, $today]
    );
    
    if (!$record) {
        return [
            'allowed' => true,
            'plays_today' => 0,
            'plays_remaining' => $maxPlays,
            'can_earn_points' => true
        ];
    }
    
    $playsRemaining = max(0, $maxPlays - $record['play_count']);
    
    return [
        'allowed' => $playsRemaining > 0,
        'plays_today' => $record['play_count'],
        'plays_remaining' => $playsRemaining,
        'can_earn_points' => $playsRemaining > 0
    ];
}

/**
 * Increment daily play count
 */
function incrementDailyPlayCount(int $userId, int $gameId, int $pointsEarned = 0): void {
    $today = date('Y-m-d');
    
    Database::query(
        "INSERT INTO daily_play_limits (user_id, game_id, play_date, play_count, points_earned) 
         VALUES (?, ?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE 
         play_count = play_count + 1,
         points_earned = points_earned + ?",
        [$userId, $gameId, $today, $pointsEarned, $pointsEarned]
    );
}

/**
 * Sanitize filename
 */
function sanitizeFilename(string $filename): string {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    $filename = preg_replace('/\.+/', '.', $filename);
    return $filename;
}

/**
 * Upload image file
 */
function uploadImage(array $file, string $destination, string $prefix = ''): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return null;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        return null;
    }
    
    $extension = match($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'jpg'
    };
    
    $filename = $prefix . randomString(16) . '.' . $extension;
    $fullPath = $destination . $filename;
    
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        return $filename;
    }
    
    return null;
}

/**
 * Delete file if exists
 */
function deleteFile(string $path): bool {
    if (file_exists($path) && is_file($path)) {
        return unlink($path);
    }
    return false;
}

/**
 * Get color class for difficulty
 */
function difficultyColor(string $difficulty): string {
    return match($difficulty) {
        'easy' => 'bg-green-500',
        'medium' => 'bg-yellow-500',
        'hard' => 'bg-red-500',
        default => 'bg-gray-500'
    };
}

/**
 * Get color class for status
 */
function statusColor(string $status): string {
    return match($status) {
        'active' => 'bg-green-500 text-white',
        'inactive', 'banned' => 'bg-red-500 text-white',
        'pending' => 'bg-yellow-500 text-black',
        'suspended', 'maintenance' => 'bg-orange-500 text-white',
        default => 'bg-gray-500 text-white'
    };
}