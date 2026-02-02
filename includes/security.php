<?php
/**
 * Security Functions and Middleware
 * Implements security best practices
 */

class Security {
    
    /**
     * Rate limiting storage (in production, use Redis or database)
     */
    private static function getRateLimitKey(string $action, string $identifier): string {
        return "rate_limit:{$action}:{$identifier}";
    }
    
    /**
     * Check rate limit
     * Returns true if request is allowed, false if rate limited
     */
    public static function checkRateLimit(string $action, int $maxAttempts, int $windowSeconds, ?string $identifier = null): bool {
        $identifier = $identifier ?? getClientIp();
        $key = self::getRateLimitKey($action, $identifier);
        
        // Using session for simple rate limiting (use Redis in production)
        if (!isset($_SESSION['rate_limits'])) {
            $_SESSION['rate_limits'] = [];
        }
        
        $now = time();
        
        // Clean old entries
        if (isset($_SESSION['rate_limits'][$key])) {
            $_SESSION['rate_limits'][$key] = array_filter(
                $_SESSION['rate_limits'][$key],
                fn($timestamp) => ($now - $timestamp) < $windowSeconds
            );
        } else {
            $_SESSION['rate_limits'][$key] = [];
        }
        
        // Check limit
        if (count($_SESSION['rate_limits'][$key]) >= $maxAttempts) {
            return false;
        }
        
        // Record this attempt
        $_SESSION['rate_limits'][$key][] = $now;
        
        return true;
    }
    
    /**
     * Apply rate limit or return error
     */
    public static function rateLimit(string $action, int $maxAttempts = 60, int $windowSeconds = 60): void {
        if (!self::checkRateLimit($action, $maxAttempts, $windowSeconds)) {
            if (isAjax()) {
                jsonError('Too many requests. Please slow down.', 429);
            } else {
                http_response_code(429);
                die('Too many requests. Please try again later.');
            }
        }
    }
    
    /**
     * Validate and sanitize input
     */
    public static function sanitizeInput(mixed $input): mixed {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        if (is_string($input)) {
            // Remove null bytes
            $input = str_replace("\0", '', $input);
            // Trim whitespace
            $input = trim($input);
            // Convert special characters to HTML entities
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        return $input;
    }
    
    /**
     * Validate that request is from same origin
     */
    public static function validateOrigin(): bool {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        $allowedHost = parse_url(APP_URL, PHP_URL_HOST);
        
        if ($origin) {
            $originHost = parse_url($origin, PHP_URL_HOST);
            return $originHost === $allowedHost;
        }
        
        if ($referer) {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            return $refererHost === $allowedHost;
        }
        
        return true; // Allow if no origin/referer (could be direct request)
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders(): void {
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS filter
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy (adjust as needed)
        if (APP_ENV === 'production') {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;");
        }
        
        // Strict Transport Security (HTTPS only)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Validate score submission (anti-cheat)
     */
    public static function validateScoreSubmission(int $score, array $game, array $gameData, int $playTime): array {
        $issues = [];
        
        // Check for impossible score
        $maxScore = self::getGameMaxScore($game['slug']);
        if ($score > $maxScore) {
            $issues[] = 'score_too_high';
        }
        
        // Check play time
        $minTime = self::getGameMinTime($game['slug']);
        if ($playTime < $minTime) {
            $issues[] = 'play_time_too_short';
        }
        
        // Check score rate (points per second)
        if ($playTime > 0) {
            $scoreRate = $score / $playTime;
            $maxRate = self::getGameMaxScoreRate($game['slug']);
            if ($scoreRate > $maxRate) {
                $issues[] = 'score_rate_suspicious';
            }
        }
        
        // Check for automation patterns
        if (self::detectAutomation($gameData)) {
            $issues[] = 'automation_detected';
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'should_flag' => count($issues) > 1
        ];
    }
    
    /**
     * Get game maximum possible score
     */
    private static function getGameMaxScore(string $slug): int {
        return match($slug) {
            'fruit-catch' => 15000,
            'word-scramble' => 8000,
            'quiz-master' => 1500,
            'sliding-puzzle' => 12000,
            'memory-match' => 6000,
            default => 50000
        };
    }
    
    /**
     * Get game minimum realistic play time
     */
    private static function getGameMinTime(string $slug): int {
        return match($slug) {
            'fruit-catch' => 20,
            'word-scramble' => 15,
            'quiz-master' => 20,
            'sliding-puzzle' => 8,
            'memory-match' => 10,
            default => 5
        };
    }
    
    /**
     * Get game maximum score rate (points per second)
     */
    private static function getGameMaxScoreRate(string $slug): float {
        return match($slug) {
            'fruit-catch' => 50.0,
            'word-scramble' => 30.0,
            'quiz-master' => 15.0,
            'sliding-puzzle' => 100.0,
            'memory-match' => 40.0,
            default => 100.0
        };
    }
    
    /**
     * Detect automation/bot patterns in game data
     */
    private static function detectAutomation(array $gameData): bool {
        // Check for perfect timing patterns
        if (isset($gameData['click_intervals'])) {
            $intervals = $gameData['click_intervals'];
            if (count($intervals) > 5) {
                // Check if intervals are suspiciously consistent
                $avg = array_sum($intervals) / count($intervals);
                $variance = 0;
                foreach ($intervals as $interval) {
                    $variance += pow($interval - $avg, 2);
                }
                $variance /= count($intervals);
                
                // Very low variance suggests automation
                if ($variance < 10) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent(string $event, string $description, ?int $userId = null): void {
        Database::insert(
            "INSERT INTO activity_log (user_id, action, description, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $userId,
                'security_' . $event,
                $description,
                getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]
        );
    }
    
    /**
     * Check if IP is banned
     */
    public static function isIpBanned(string $ip): bool {
        // Check recent suspicious activity
        $recentEvents = Database::fetch(
            "SELECT COUNT(*) as count FROM activity_log 
             WHERE ip_address = ? 
             AND action LIKE 'security_%' 
             AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$ip]
        )['count'] ?? 0;
        
        return $recentEvents > 10;
    }
}

// Apply security headers on every request
Security::setSecurityHeaders();

// Check if IP is banned
if (Security::isIpBanned(getClientIp())) {
    http_response_code(403);
    die('Access denied.');
}