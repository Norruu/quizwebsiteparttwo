<?php
/**
 * Leaderboard API
 * Handles leaderboard queries, rankings, and statistics
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/leaderboard.php';
require_once __DIR__ . '/../includes/security.php';

// Set JSON response header
header('Content-Type: application/json');

// Only accept GET requests for leaderboard
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

// Rate limiting
Security::rateLimit('leaderboard_api', 60, 60); // 60 requests per minute

// Get action
$action = $_GET['action'] ?? 'global';

switch ($action) {
    case 'global':
        getGlobalLeaderboard();
        break;
        
    case 'game':
        getGameLeaderboard();
        break;
        
    case 'user_rank':
        getUserRank();
        break;
        
    case 'user_stats':
        getUserStats();
        break;
        
    case 'top_players':
        getTopPlayers();
        break;
        
    case 'recent_scores':
        getRecentHighScores();
        break;
        
    case 'stats':
        getLeaderboardStats();
        break;
        
    case 'weekly':
        getWeeklyLeaderboard();
        break;
        
    case 'monthly':
        getMonthlyLeaderboard();
        break;
        
    case 'around_user':
        getLeaderboardAroundUser();
        break;
        
    default:
        jsonError('Invalid action');
}

/**
 * Get global leaderboard
 */
function getGlobalLeaderboard(): void {
    $limit = min(100, max(1, (int)($_GET['limit'] ?? LEADERBOARD_LIMIT)));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $period = $_GET['period'] ?? 'all'; // all, today, week, month
    
    $offset = ($page - 1) * $limit;
    
    $dateFilter = match($period) {
        'today' => "AND t.created_at >= CURDATE()",
        'week' => "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        'month' => "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        default => ""
    };
    
    if ($period === 'all') {
        // Use wallet total_earned for all-time
        $leaderboard = Database::fetchAll(
            "SELECT u.id, u.username, u.avatar, w.total_earned as total_points, w.balance,
                    (SELECT COUNT(*) FROM scores WHERE user_id = u.id) as games_played,
                    (SELECT MAX(score) FROM scores WHERE user_id = u.id) as high_score
             FROM users u
             JOIN wallet w ON u.id = w.user_id
             WHERE u.status = 'active' AND u.role = 'player'
             ORDER BY w.total_earned DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
        
        $total = Database::fetch(
            "SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role = 'player'"
        )['count'];
    } else {
        // Calculate from transactions for specific period
        $leaderboard = Database::fetchAll(
            "SELECT u.id, u.username, u.avatar,
                    COALESCE(SUM(CASE WHEN t.type = 'earn' THEN t.amount ELSE 0 END), 0) as total_points,
                    (SELECT COUNT(*) FROM scores WHERE user_id = u.id $dateFilter) as games_played,
                    (SELECT MAX(score) FROM scores WHERE user_id = u.id $dateFilter) as high_score
             FROM users u
             JOIN wallet w ON u.id = w.user_id
             LEFT JOIN transactions t ON w.id = t.wallet_id $dateFilter
             WHERE u.status = 'active' AND u.role = 'player'
             GROUP BY u.id, u.username, u.avatar
             HAVING total_points > 0 OR games_played > 0
             ORDER BY total_points DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
        
        $total = count($leaderboard); // Approximate for filtered results
    }
    
    // Add rank and avatar URLs
    foreach ($leaderboard as $index => &$entry) {
        $entry['rank'] = $offset + $index + 1;
        $entry['avatar_url'] = asset('images/avatars/' . ($entry['avatar'] ?? 'default-avatar.png'));
        $entry['total_points'] = (int)$entry['total_points'];
        $entry['games_played'] = (int)$entry['games_played'];
        $entry['high_score'] = (int)($entry['high_score'] ?? 0);
    }
    
    jsonSuccess([
        'leaderboard' => $leaderboard,
        'period' => $period,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get game-specific leaderboard
 */
function getGameLeaderboard(): void {
    $gameId = (int)($_GET['game_id'] ?? 0);
    $gameSlug = $_GET['slug'] ?? '';
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $period = $_GET['period'] ?? 'all';
    
    // Get game
    if ($gameId) {
        $game = Database::fetch("SELECT id, title, slug FROM games WHERE id = ? AND status = 'active'", [$gameId]);
    } elseif ($gameSlug) {
        $game = Database::fetch("SELECT id, title, slug FROM games WHERE slug = ? AND status = 'active'", [$gameSlug]);
    } else {
        jsonError('Game ID or slug is required');
    }
    
    if (!$game) {
        jsonError('Game not found', 404);
    }
    
    $gameId = $game['id'];
    $offset = ($page - 1) * $limit;
    
    $dateFilter = match($period) {
        'today' => "AND s.created_at >= CURDATE()",
        'week' => "AND s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        'month' => "AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        default => ""
    };
    
    $leaderboard = Database::fetchAll(
        "SELECT u.id, u.username, u.avatar,
                MAX(s.score) as high_score,
                COUNT(s.id) as plays,
                SUM(s.points_earned) as total_points,
                MAX(s.created_at) as last_played
         FROM scores s
         JOIN users u ON s.user_id = u.id
         WHERE s.game_id = ? AND u.status = 'active' $dateFilter
         GROUP BY u.id, u.username, u.avatar
         ORDER BY high_score DESC
         LIMIT ? OFFSET ?",
        [$gameId, $limit, $offset]
    );
    
    // Get total unique players
    $total = Database::fetch(
        "SELECT COUNT(DISTINCT user_id) as count FROM scores s 
         JOIN users u ON s.user_id = u.id 
         WHERE s.game_id = ? AND u.status = 'active' $dateFilter",
        [$gameId]
    )['count'];
    
    // Add rank and format data
    foreach ($leaderboard as $index => &$entry) {
        $entry['rank'] = $offset + $index + 1;
        $entry['avatar_url'] = asset('images/avatars/' . ($entry['avatar'] ?? 'default-avatar.png'));
        $entry['high_score'] = (int)$entry['high_score'];
        $entry['plays'] = (int)$entry['plays'];
        $entry['total_points'] = (int)$entry['total_points'];
        $entry['last_played_ago'] = timeAgo($entry['last_played']);
    }
    
    jsonSuccess([
        'game' => $game,
        'leaderboard' => $leaderboard,
        'period' => $period,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get user's rank
 */
function getUserRank(): void {
    $userId = (int)($_GET['user_id'] ?? 0);
    $gameId = (int)($_GET['game_id'] ?? 0);
    $period = $_GET['period'] ?? 'all';
    
    // If no user_id provided, use current logged in user
    if (!$userId && Auth::check()) {
        $userId = Auth::id();
    }
    
    if (!$userId) {
        jsonError('User ID is required');
    }
    
    // Get user info
    $user = Database::fetch(
        "SELECT u.id, u.username, u.avatar, w.total_earned, w.balance
         FROM users u
         LEFT JOIN wallet w ON u.id = w.user_id
         WHERE u.id = ? AND u.status = 'active'",
        [$userId]
    );
    
    if (!$user) {
        jsonError('User not found', 404);
    }
    
    if ($gameId) {
        // Game-specific rank
        $rank = Leaderboard::getUserGameRank($userId, $gameId);
        $userStats = Database::fetch(
            "SELECT MAX(score) as high_score, COUNT(*) as plays, SUM(points_earned) as points
             FROM scores WHERE user_id = ? AND game_id = ?",
            [$userId, $gameId]
        );
    } else {
        // Global rank
        $rank = Leaderboard::getUserRank($userId, $period);
        $userStats = Database::fetch(
            "SELECT MAX(score) as high_score, COUNT(*) as plays, 
                    (SELECT total_earned FROM wallet WHERE user_id = ?) as points
             FROM scores WHERE user_id = ?",
            [$userId, $userId]
        );
    }
    
    jsonSuccess([
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'avatar_url' => asset('images/avatars/' . ($user['avatar'] ?? 'default-avatar.png')),
        ],
        'rank' => $rank,
        'stats' => [
            'high_score' => (int)($userStats['high_score'] ?? 0),
            'games_played' => (int)($userStats['plays'] ?? 0),
            'total_points' => (int)($userStats['points'] ?? $user['total_earned'] ?? 0),
        ],
        'game_id' => $gameId ?: null,
        'period' => $period
    ]);
}

/**
 * Get user statistics
 */
function getUserStats(): void {
    $userId = (int)($_GET['user_id'] ?? 0);
    
    if (!$userId && Auth::check()) {
        $userId = Auth::id();
    }
    
    if (!$userId) {
        jsonError('User ID is required');
    }
    
    // Get basic user info
    $user = Database::fetch(
        "SELECT u.id, u.username, u.avatar, u.created_at,
                w.balance, w.total_earned, w.total_spent
         FROM users u
         LEFT JOIN wallet w ON u.id = w.user_id
         WHERE u.id = ? AND u.status = 'active'",
        [$userId]
    );
    
    if (!$user) {
        jsonError('User not found', 404);
    }
    
    // Get game statistics
    $gameStats = Database::fetch(
        "SELECT 
            COUNT(*) as total_games,
            COUNT(DISTINCT game_id) as unique_games,
            COALESCE(SUM(score), 0) as total_score,
            COALESCE(AVG(score), 0) as avg_score,
            COALESCE(MAX(score), 0) as best_score,
            COALESCE(SUM(points_earned), 0) as points_from_games,
            COALESCE(SUM(play_time), 0) as total_play_time
         FROM scores WHERE user_id = ?",
        [$userId]
    );
    
    // Get favorite game
    $favoriteGame = Database::fetch(
        "SELECT g.title, g.slug, COUNT(*) as plays
         FROM scores s
         JOIN games g ON s.game_id = g.id
         WHERE s.user_id = ?
         GROUP BY g.id, g.title, g.slug
         ORDER BY plays DESC
         LIMIT 1",
        [$userId]
    );
    
    // Get achievements count
    $achievementsCount = Database::fetch(
        "SELECT COUNT(*) as count FROM user_achievements WHERE user_id = ?",
        [$userId]
    )['count'];
    
    // Get recent activity
    $recentGames = Database::fetchAll(
        "SELECT g.title, g.slug, s.score, s.points_earned, s.created_at
         FROM scores s
         JOIN games g ON s.game_id = g.id
         WHERE s.user_id = ?
         ORDER BY s.created_at DESC
         LIMIT 5",
        [$userId]
    );
    
    // Get global rank
    $globalRank = Leaderboard::getUserRank($userId);
    
    jsonSuccess([
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'avatar_url' => asset('images/avatars/' . ($user['avatar'] ?? 'default-avatar.png')),
            'member_since' => $user['created_at'],
        ],
        'wallet' => [
            'balance' => (int)$user['balance'],
            'total_earned' => (int)$user['total_earned'],
            'total_spent' => (int)$user['total_spent'],
        ],
        'gaming' => [
            'total_games_played' => (int)$gameStats['total_games'],
            'unique_games' => (int)$gameStats['unique_games'],
            'total_score' => (int)$gameStats['total_score'],
            'average_score' => round($gameStats['avg_score'], 0),
            'best_score' => (int)$gameStats['best_score'],
            'total_play_time' => (int)$gameStats['total_play_time'],
            'total_play_time_formatted' => formatDuration($gameStats['total_play_time']),
        ],
        'favorite_game' => $favoriteGame,
        'achievements_count' => (int)$achievementsCount,
        'global_rank' => $globalRank,
        'recent_games' => $recentGames
    ]);
}

/**
 * Get top players
 */
function getTopPlayers(): void {
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    $period = $_GET['period'] ?? 'week';
    
    $players = Leaderboard::getGlobalByPoints($limit, $period);
    
    foreach ($players as &$player) {
        $player['avatar_url'] = asset('images/avatars/' . ($player['avatar'] ?? 'default-avatar.png'));
    }
    
    jsonSuccess([
        'players' => $players,
        'period' => $period
    ]);
}

/**
 * Get recent high scores
 */
function getRecentHighScores(): void {
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    $gameId = (int)($_GET['game_id'] ?? 0);
    
    $where = "u.status = 'active'";
    $params = [];
    
    if ($gameId) {
        $where .= " AND s.game_id = ?";
        $params[] = $gameId;
    }
    
    $params[] = $limit;
    
    $scores = Database::fetchAll(
        "SELECT s.id, s.score, s.points_earned, s.created_at,
                u.id as user_id, u.username, u.avatar,
                g.id as game_id, g.title as game_title, g.slug as game_slug
         FROM scores s
         JOIN users u ON s.user_id = u.id
         JOIN games g ON s.game_id = g.id
         WHERE $where
         ORDER BY s.score DESC, s.created_at DESC
         LIMIT ?",
        $params
    );
    
    foreach ($scores as &$score) {
        $score['avatar_url'] = asset('images/avatars/' . ($score['avatar'] ?? 'default-avatar.png'));
        $score['created_at_ago'] = timeAgo($score['created_at']);
    }
    
    jsonSuccess(['scores' => $scores]);
}

/**
 * Get leaderboard statistics
 */
function getLeaderboardStats(): void {
    $stats = Leaderboard::getStats();
    
    // Additional stats
    $todayStats = Database::fetch(
        "SELECT COUNT(*) as games_today, COUNT(DISTINCT user_id) as active_players_today
         FROM scores WHERE DATE(created_at) = CURDATE()"
    );
    
    $weeklyStats = Database::fetch(
        "SELECT COUNT(*) as games_this_week, COALESCE(SUM(points_earned), 0) as points_this_week
         FROM scores WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    
    jsonSuccess([
        'stats' => array_merge($stats, [
            'games_today' => (int)$todayStats['games_today'],
            'active_players_today' => (int)$todayStats['active_players_today'],
            'games_this_week' => (int)$weeklyStats['games_this_week'],
            'points_this_week' => (int)$weeklyStats['points_this_week'],
        ])
    ]);
}

/**
 * Get weekly leaderboard
 */
function getWeeklyLeaderboard(): void {
    $_GET['period'] = 'week';
    getGlobalLeaderboard();
}

/**
 * Get monthly leaderboard
 */
function getMonthlyLeaderboard(): void {
    $_GET['period'] = 'month';
    getGlobalLeaderboard();
}

/**
 * Get leaderboard entries around a specific user
 */
function getLeaderboardAroundUser(): void {
    $userId = (int)($_GET['user_id'] ?? 0);
    $range = min(25, max(1, (int)($_GET['range'] ?? 5))); // Entries above and below
    
    if (!$userId && Auth::check()) {
        $userId = Auth::id();
    }
    
    if (!$userId) {
        jsonError('User ID is required');
    }
    
    // Get user's rank first
    $rank = Leaderboard::getUserRank($userId);
    
    if (!$rank) {
        jsonError('User not found in leaderboard', 404);
    }
    
    // Calculate offset
    $offset = max(0, $rank - $range - 1);
    $limit = ($range * 2) + 1;
    
    $leaderboard = Database::fetchAll(
        "SELECT u.id, u.username, u.avatar, w.total_earned as total_points,
                (SELECT COUNT(*) FROM scores WHERE user_id = u.id) as games_played
         FROM users u
         JOIN wallet w ON u.id = w.user_id
         WHERE u.status = 'active' AND u.role = 'player'
         ORDER BY w.total_earned DESC
         LIMIT ? OFFSET ?",
        [$limit, $offset]
    );
    
    foreach ($leaderboard as $index => &$entry) {
        $entry['rank'] = $offset + $index + 1;
        $entry['avatar_url'] = asset('images/avatars/' . ($entry['avatar'] ?? 'default-avatar.png'));
        $entry['is_current_user'] = ($entry['id'] == $userId);
    }
    
    jsonSuccess([
        'leaderboard' => $leaderboard,
        'user_rank' => $rank,
        'user_id' => $userId
    ]);
}

/**
 * Format duration in seconds to human readable
 */
function formatDuration(int $seconds): string {
    if ($seconds < 60) {
        return $seconds . ' seconds';
    } elseif ($seconds < 3600) {
        return round($seconds / 60) . ' minutes';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = round(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm';
    }
}