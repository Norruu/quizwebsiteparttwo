<?php
/**
 * Games API
 * Handles game listing, details, and game session management
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

// Set JSON response header
header('Content-Type: application/json');

// Get request method and input
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_REQUEST;

// Rate limiting
Security::rateLimit('games_api', 60, 60); // 60 requests per minute

// Determine action
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        handleGetRequest($input);
        break;
        
    case 'POST':
        handlePostRequest($input);
        break;
        
    default:
        jsonError('Method not allowed', 405);
}

/**
 * Handle GET requests
 */
function handleGetRequest(array $input): void {
    $action = $input['action'] ?? $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            getGamesList($input);
            break;
            
        case 'details':
            getGameDetails($input);
            break;
            
        case 'categories':
            getCategories();
            break;
            
        case 'featured':
            getFeaturedGames();
            break;
            
        case 'popular':
            getPopularGames($input);
            break;
            
        case 'recent':
            getRecentlyPlayed($input);
            break;
            
        case 'search':
            searchGames($input);
            break;
            
        default:
            jsonError('Invalid action');
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest(array $input): void {
    // Verify CSRF for state-changing operations
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
    if (!verifyCsrf($csrfToken)) {
        jsonError('Invalid CSRF token', 403);
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'start_session':
            startGameSession($input);
            break;
            
        case 'end_session':
            endGameSession($input);
            break;
            
        case 'check_play_limit':
            checkPlayLimit($input);
            break;
            
        case 'record_play':
            recordPlay($input);
            break;
            
        default:
            jsonError('Invalid action');
    }
}

/**
 * Get list of games
 */
function getGamesList(array $input): void {
    $page = max(1, (int)($input['page'] ?? $_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int)($input['per_page'] ?? $_GET['per_page'] ?? GAMES_PER_PAGE)));
    $category = $input['category'] ?? $_GET['category'] ?? '';
    $difficulty = $input['difficulty'] ?? $_GET['difficulty'] ?? '';
    $sort = $input['sort'] ?? $_GET['sort'] ?? 'featured';
    
    $where = ["status = 'active'"];
    $params = [];
    
    if ($category && $category !== 'all') {
        $where[] = "category = ?";
        $params[] = $category;
    }
    
    if ($difficulty && $difficulty !== 'all') {
        $where[] = "difficulty = ?";
        $params[] = $difficulty;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Get total count
    $total = Database::fetch(
        "SELECT COUNT(*) as count FROM games WHERE $whereClause",
        $params
    )['count'];
    
    // Sort options
    $orderBy = match($sort) {
        'popular' => 'play_count DESC',
        'newest' => 'created_at DESC',
        'title' => 'title ASC',
        'points' => 'points_reward DESC',
        'difficulty_asc' => "FIELD(difficulty, 'easy', 'medium', 'hard')",
        'difficulty_desc' => "FIELD(difficulty, 'hard', 'medium', 'easy')",
        default => 'featured DESC, sort_order ASC, title ASC'
    };
    
    $offset = ($page - 1) * $perPage;
    $params[] = $perPage;
    $params[] = $offset;
    
    $games = Database::fetchAll(
        "SELECT id, title, slug, description, thumbnail, category, 
                points_reward, difficulty, play_count, featured, min_score_for_points, max_plays_per_day
         FROM games 
         WHERE $whereClause 
         ORDER BY $orderBy 
         LIMIT ? OFFSET ?",
        $params
    );
    
    // Add thumbnail URLs
    foreach ($games as &$game) {
        $game['thumbnail_url'] = asset('images/games/' . $game['thumbnail']);
        $game['play_url'] = baseUrl('/games/play.php?game=' . $game['slug']);
    }
    
    jsonSuccess([
        'games' => $games,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage),
            'has_prev' => $page > 1,
            'has_next' => ($page * $perPage) < $total
        ]
    ]);
}

/**
 * Get game details
 */
function getGameDetails(array $input): void {
    $gameId = (int)($input['game_id'] ?? $_GET['game_id'] ?? 0);
    $gameSlug = $input['slug'] ?? $_GET['slug'] ?? '';
    
    if (!$gameId && !$gameSlug) {
        jsonError('Game ID or slug is required');
    }
    
    $where = $gameId ? "id = ?" : "slug = ?";
    $param = $gameId ?: $gameSlug;
    
    $game = Database::fetch(
        "SELECT * FROM games WHERE $where AND status = 'active'",
        [$param]
    );
    
    if (!$game) {
        jsonError('Game not found', 404);
    }
    
    // Get game statistics
    $stats = Database::fetch(
        "SELECT 
            COUNT(*) as total_plays,
            COUNT(DISTINCT user_id) as unique_players,
            COALESCE(AVG(score), 0) as avg_score,
            COALESCE(MAX(score), 0) as high_score,
            COALESCE(SUM(points_earned), 0) as total_points_awarded
         FROM scores WHERE game_id = ?",
        [$game['id']]
    );
    
    // Get top 5 scores for this game
    $topScores = Database::fetchAll(
        "SELECT s.score, s.created_at, u.username, u.avatar
         FROM scores s
         JOIN users u ON s.user_id = u.id
         WHERE s.game_id = ? AND u.status = 'active'
         ORDER BY s.score DESC
         LIMIT 5",
        [$game['id']]
    );
    
    // Check user's play limit if logged in
    $playLimit = null;
    $userHighScore = null;
    
    if (Auth::check()) {
        $playLimit = checkDailyPlayLimit(Auth::id(), $game['id'], $game['max_plays_per_day']);
        
        $userHighScore = Database::fetch(
            "SELECT MAX(score) as high_score FROM scores WHERE user_id = ? AND game_id = ?",
            [Auth::id(), $game['id']]
        )['high_score'];
    }
    
    $game['thumbnail_url'] = asset('images/games/' . $game['thumbnail']);
    $game['play_url'] = baseUrl('/games/play.php?game=' . $game['slug']);
    
    jsonSuccess([
        'game' => $game,
        'stats' => $stats,
        'top_scores' => $topScores,
        'user_high_score' => $userHighScore,
        'play_limit' => $playLimit
    ]);
}

/**
 * Get game categories
 */
function getCategories(): void {
    $categories = Database::fetchAll(
        "SELECT category, COUNT(*) as game_count 
         FROM games 
         WHERE status = 'active' 
         GROUP BY category 
         ORDER BY game_count DESC"
    );
    
    $categoryInfo = [
        'action' => ['label' => 'Action', 'icon' => '🎯', 'color' => '#FF6B35'],
        'puzzle' => ['label' => 'Puzzle', 'icon' => '🧩', 'color' => '#4D96FF'],
        'word' => ['label' => 'Word', 'icon' => '📝', 'color' => '#6BCB77'],
        'memory' => ['label' => 'Memory', 'icon' => '🧠', 'color' => '#9B59B6'],
        'quiz' => ['label' => 'Quiz', 'icon' => '❓', 'color' => '#FFD93D'],
        'arcade' => ['label' => 'Arcade', 'icon' => '👾', 'color' => '#FF4757'],
    ];
    
    foreach ($categories as &$cat) {
        $info = $categoryInfo[$cat['category']] ?? ['label' => ucfirst($cat['category']), 'icon' => '🎮', 'color' => '#666'];
        $cat = array_merge($cat, $info);
    }
    
    jsonSuccess(['categories' => $categories]);
}

/**
 * Get featured games
 */
function getFeaturedGames(): void {
    $games = Database::fetchAll(
        "SELECT id, title, slug, description, thumbnail, category, points_reward, difficulty, play_count
         FROM games 
         WHERE status = 'active' AND featured = 1 
         ORDER BY sort_order ASC, play_count DESC 
         LIMIT 10"
    );
    
    foreach ($games as &$game) {
        $game['thumbnail_url'] = asset('images/games/' . $game['thumbnail']);
        $game['play_url'] = baseUrl('/games/play.php?game=' . $game['slug']);
    }
    
    jsonSuccess(['games' => $games]);
}

/**
 * Get popular games
 */
function getPopularGames(array $input): void {
    $limit = min(20, max(1, (int)($input['limit'] ?? $_GET['limit'] ?? 10)));
    $period = $input['period'] ?? $_GET['period'] ?? 'all'; // all, week, month
    
    $dateFilter = match($period) {
        'week' => "AND s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        'month' => "AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        default => ""
    };
    
    if ($period !== 'all') {
        $games = Database::fetchAll(
            "SELECT g.id, g.title, g.slug, g.thumbnail, g.category, g.points_reward, g.difficulty,
                    COUNT(s.id) as recent_plays
             FROM games g
             LEFT JOIN scores s ON g.id = s.game_id $dateFilter
             WHERE g.status = 'active'
             GROUP BY g.id
             ORDER BY recent_plays DESC
             LIMIT ?",
            [$limit]
        );
    } else {
        $games = Database::fetchAll(
            "SELECT id, title, slug, thumbnail, category, points_reward, difficulty, play_count
             FROM games 
             WHERE status = 'active' 
             ORDER BY play_count DESC 
             LIMIT ?",
            [$limit]
        );
    }
    
    foreach ($games as &$game) {
        $game['thumbnail_url'] = asset('images/games/' . $game['thumbnail']);
        $game['play_url'] = baseUrl('/games/play.php?game=' . $game['slug']);
    }
    
    jsonSuccess(['games' => $games, 'period' => $period]);
}

/**
 * Get recently played games (for logged in user)
 */
function getRecentlyPlayed(array $input): void {
    if (!Auth::check()) {
        jsonError('Authentication required', 401);
    }
    
    $limit = min(20, max(1, (int)($input['limit'] ?? $_GET['limit'] ?? 10)));
    $userId = Auth::id();
    
    $games = Database::fetchAll(
        "SELECT g.id, g.title, g.slug, g.thumbnail, g.category, g.points_reward, g.difficulty,
                MAX(s.score) as high_score,
                MAX(s.created_at) as last_played,
                COUNT(s.id) as times_played
         FROM games g
         JOIN scores s ON g.id = s.game_id
         WHERE s.user_id = ? AND g.status = 'active'
         GROUP BY g.id
         ORDER BY last_played DESC
         LIMIT ?",
        [$userId, $limit]
    );
    
    foreach ($games as &$game) {
        $game['thumbnail_url'] = asset('images/games/' . $game['thumbnail']);
        $game['play_url'] = baseUrl('/games/play.php?game=' . $game['slug']);
        $game['last_played_ago'] = timeAgo($game['last_played']);
    }
    
    jsonSuccess(['games' => $games]);
}

/**
 * Search games
 */
function searchGames(array $input): void {
    $query = trim($input['query'] ?? $_GET['query'] ?? '');
    $limit = min(20, max(1, (int)($input['limit'] ?? $_GET['limit'] ?? 10)));
    
    if (strlen($query) < 2) {
        jsonError('Search query must be at least 2 characters');
    }
    
    $games = Database::fetchAll(
        "SELECT id, title, slug, thumbnail, category, points_reward, difficulty, play_count
         FROM games 
         WHERE status = 'active' 
           AND (title LIKE ? OR description LIKE ? OR category LIKE ?)
         ORDER BY 
           CASE 
             WHEN title LIKE ? THEN 1
             WHEN title LIKE ? THEN 2
             ELSE 3
           END,
           play_count DESC
         LIMIT ?",
        ["%$query%", "%$query%", "%$query%", "$query%", "%$query%", $limit]
    );
    
    foreach ($games as &$game) {
        $game['thumbnail_url'] = asset('images/games/' . $game['thumbnail']);
        $game['play_url'] = baseUrl('/games/play.php?game=' . $game['slug']);
    }
    
    jsonSuccess([
        'games' => $games,
        'query' => $query,
        'count' => count($games)
    ]);
}

/**
 * Start a game session
 */
function startGameSession(array $input): void {
    if (!Auth::check()) {
        jsonError('Authentication required', 401);
    }
    
    $gameId = (int)($input['game_id'] ?? 0);
    
    if (!$gameId) {
        jsonError('Game ID is required');
    }
    
    $userId = Auth::id();
    
    // Get game details
    $game = Database::fetch(
        "SELECT * FROM games WHERE id = ? AND status = 'active'",
        [$gameId]
    );
    
    if (!$game) {
        jsonError('Game not found', 404);
    }
    
    // Check play limit
    $playLimit = checkDailyPlayLimit($userId, $gameId, $game['max_plays_per_day']);
    
    if (!$playLimit['allowed']) {
        jsonError("Daily play limit reached. You've played this game {$game['max_plays_per_day']} times today.", 429);
    }
    
    // Generate session token
    $sessionToken = generateGameSession($userId, $gameId);
    
    jsonSuccess([
        'session_token' => $sessionToken,
        'game' => [
            'id' => $game['id'],
            'title' => $game['title'],
            'slug' => $game['slug'],
            'difficulty' => $game['difficulty'],
            'points_reward' => $game['points_reward'],
            'min_score_for_points' => $game['min_score_for_points'],
        ],
        'plays_remaining' => $playLimit['plays_remaining'],
        'can_earn_points' => $playLimit['can_earn_points']
    ]);
}

/**
 * End a game session (record play without score)
 */
function endGameSession(array $input): void {
    if (!Auth::check()) {
        jsonError('Authentication required', 401);
    }
    
    $sessionToken = $input['session_token'] ?? '';
    
    if (!$sessionToken) {
        jsonError('Session token is required');
    }
    
    // Invalidate session
    invalidateGameSession($sessionToken);
    
    jsonSuccess([], 'Game session ended');
}

/**
 * Check play limit for a game
 */
function checkPlayLimit(array $input): void {
    if (!Auth::check()) {
        jsonError('Authentication required', 401);
    }
    
    $gameId = (int)($input['game_id'] ?? 0);
    
    if (!$gameId) {
        jsonError('Game ID is required');
    }
    
    $game = Database::fetch(
        "SELECT max_plays_per_day FROM games WHERE id = ? AND status = 'active'",
        [$gameId]
    );
    
    if (!$game) {
        jsonError('Game not found', 404);
    }
    
    $playLimit = checkDailyPlayLimit(Auth::id(), $gameId, $game['max_plays_per_day']);
    
    jsonSuccess($playLimit);
}

/**
 * Record a play (increments play count, used for analytics)
 */
function recordPlay(array $input): void {
    if (!Auth::check()) {
        jsonError('Authentication required', 401);
    }
    
    $gameId = (int)($input['game_id'] ?? 0);
    $playTime = (int)($input['play_time'] ?? 0);
    
    if (!$gameId) {
        jsonError('Game ID is required');
    }
    
    // Update game play count
    Database::update(
        "UPDATE games SET play_count = play_count + 1 WHERE id = ?",
        [$gameId]
    );
    
    jsonSuccess([], 'Play recorded');
}