<?php
/**
 * Score Submission API
 * Handles game score submissions with validation
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Require authentication
if (!Auth::check()) {
    jsonError('Unauthorized', 401);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonError('Invalid JSON input');
}

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
if (!verifyCsrf($csrfToken)) {
    jsonError('Invalid CSRF token', 403);
}

$action = $input['action'] ?? '';
$userId = Auth::id();

switch ($action) {
    case 'submit':
        handleScoreSubmission($input, $userId);
        break;
        
    case 'get_high_score':
        handleGetHighScore($input, $userId);
        break;
        
    default:
        jsonError('Invalid action');
}

/**
 * Handle score submission
 */
function handleScoreSubmission(array $input, int $userId): void {
    // Validate required fields
    $gameId = (int)($input['game_id'] ?? 0);
    $sessionToken = $input['session_token'] ?? '';
    $score = (int)($input['score'] ?? 0);
    $gameData = $input['game_data'] ?? [];
    $playTime = (int)($input['play_time'] ?? 0);
    
    if (!$gameId || !$sessionToken) {
        jsonError('Missing required fields');
    }
    
    // Validate game session token
    if (SCORE_VALIDATION_ENABLED && !validateGameSession($sessionToken, $userId, $gameId)) {
        jsonError('Invalid game session', 403);
    }
    
    // Get game details
    $game = Database::fetch(
        "SELECT * FROM games WHERE id = ? AND status = 'active'",
        [$gameId]
    );
    
    if (!$game) {
        jsonError('Game not found');
    }
    
    // Check daily play limit
    $playLimit = checkDailyPlayLimit($userId, $gameId, $game['max_plays_per_day']);
    
    if (!$playLimit['can_earn_points']) {
        // Still record the score but no points
        $pointsEarned = 0;
    } else {
        // Calculate points earned
        $multiplier = getDifficultyMultiplier($game['difficulty']);
        $pointsEarned = calculatePoints(
            $score,
            $game['points_reward'],
            $multiplier,
            $game['min_score_for_points']
        );
    }
    
    // Validate score (basic anti-cheat)
    if (ANTI_CHEAT_ENABLED) {
        $validationResult = validateScore($score, $game, $gameData, $playTime);
        if (!$validationResult['valid']) {
            logActivity('suspicious_score', "User $userId submitted suspicious score: {$validationResult['reason']}", $userId);
            // Reduce points or reject
            $pointsEarned = 0;
        }
    }
    
    try {
        Database::beginTransaction();
        
        // Record the score
        $scoreId = Database::insert(
            "INSERT INTO scores (user_id, game_id, score, points_earned, play_time, completed, game_data, ip_address, user_agent, session_token, validated, created_at) 
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, NOW())",
            [
                $userId,
                $gameId,
                $score,
                $pointsEarned,
                $playTime,
                json_encode($gameData),
                getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $sessionToken,
                ANTI_CHEAT_ENABLED ? 1 : 0
            ]
        );
        
        // Update daily play count
        incrementDailyPlayCount($userId, $gameId, $pointsEarned);
        
        // Add points to wallet if earned
        $newBalance = 0;
        if ($pointsEarned > 0) {
            $wallet = Database::fetch(
                "SELECT id, balance FROM wallet WHERE user_id = ?",
                [$userId]
            );
            
            if ($wallet) {
                $newBalance = $wallet['balance'] + $pointsEarned;
                
                Database::update(
                    "UPDATE wallet SET balance = balance + ?, total_earned = total_earned + ?, last_transaction_at = NOW() WHERE id = ?",
                    [$pointsEarned, $pointsEarned, $wallet['id']]
                );
                
                // Record transaction
                Database::insert(
                    "INSERT INTO transactions (wallet_id, type, amount, balance_after, description, reference_type, reference_id) 
                     VALUES (?, 'earn', ?, ?, ?, 'game', ?)",
                    [
                        $wallet['id'],
                        $pointsEarned,
                        $newBalance,
                        "Earned from playing {$game['title']} (Score: $score)",
                        $scoreId
                    ]
                );
            }
        } else {
            $wallet = Database::fetch("SELECT balance FROM wallet WHERE user_id = ?", [$userId]);
            $newBalance = $wallet['balance'] ?? 0;
        }
        
        // Invalidate game session (one-time use)
        invalidateGameSession($sessionToken);
        
        Database::commit();
        
        // Check for achievements (optional)
        // checkAchievements($userId, $gameId, $score, $pointsEarned);
        
        jsonSuccess([
            'score_id' => $scoreId,
            'score' => $score,
            'points_earned' => $pointsEarned,
            'new_balance' => formatNumber($newBalance),
            'plays_remaining' => max(0, $playLimit['plays_remaining'] - 1)
        ], $pointsEarned > 0 ? "Great job! You earned $pointsEarned points!" : "Score recorded!");
        
    } catch (Exception $e) {
        Database::rollback();
        error_log("Score submission error: " . $e->getMessage());
        jsonError('Failed to submit score. Please try again.');
    }
}

/**
 * Validate score (basic anti-cheat)
 */
function validateScore(int $score, array $game, array $gameData, int $playTime): array {
    // Check for impossible scores
    $maxPossibleScore = getMaxPossibleScore($game['slug']);
    if ($score > $maxPossibleScore) {
        return ['valid' => false, 'reason' => 'Score exceeds maximum possible'];
    }
    
    // Check play time (too fast is suspicious)
    $minPlayTime = getMinPlayTime($game['slug']);
    if ($playTime > 0 && $playTime < $minPlayTime) {
        return ['valid' => false, 'reason' => 'Play time too short'];
    }
    
    // Check for score progression anomalies
    // (e.g., if game data shows inconsistencies)
    
    return ['valid' => true, 'reason' => ''];
}

/**
 * Get maximum possible score for a game
 */
function getMaxPossibleScore(string $gameSlug): int {
    return match($gameSlug) {
        'fruit-catch' => 10000,
        'word-scramble' => 5000,
        'quiz-master' => 1000,
        'sliding-puzzle' => 10000,
        'memory-match' => 5000,
        default => 100000
    };
}

/**
 * Get minimum realistic play time for a game (seconds)
 */
function getMinPlayTime(string $gameSlug): int {
    return match($gameSlug) {
        'fruit-catch' => 30,
        'word-scramble' => 20,
        'quiz-master' => 30,
        'sliding-puzzle' => 10,
        'memory-match' => 15,
        default => 10
    };
}

/**
 * Handle get high score request
 */
function handleGetHighScore(array $input, int $userId): void {
    $gameId = (int)($input['game_id'] ?? 0);
    
    if (!$gameId) {
        jsonError('Game ID required');
    }
    
    $highScore = Database::fetch(
        "SELECT MAX(score) as high_score FROM scores WHERE user_id = ? AND game_id = ?",
        [$userId, $gameId]
    )['high_score'] ?? 0;
    
    jsonSuccess(['high_score' => $highScore]);
}