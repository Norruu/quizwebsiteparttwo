<?php
/**
 * Admin Leaderboard Management
 * View and manage leaderboard data, reset scores, identify suspicious activity
 */

$pageTitle = 'Leaderboard Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/leaderboard.php';

Auth::requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'reset_user_scores':
            $userId = (int)($_POST['user_id'] ?? 0);
            $gameId = (int)($_POST['game_id'] ?? 0);
            
            if ($userId) {
                if ($gameId) {
                    // Reset scores for specific game
                    Database::update(
                        "DELETE FROM scores WHERE user_id = ? AND game_id = ?",
                        [$userId, $gameId]
                    );
                    flash('success', 'User scores for this game have been reset.');
                } else {
                    // Reset all scores
                    Database::update("DELETE FROM scores WHERE user_id = ?", [$userId]);
                    flash('success', 'All user scores have been reset.');
                }
                logActivity('scores_reset', "Reset scores for user #$userId" . ($gameId ? " game #$gameId" : ""), Auth::id());
            }
            break;
            
        case 'delete_score':
            $scoreId = (int)($_POST['score_id'] ?? 0);
            if ($scoreId) {
                Database::update("DELETE FROM scores WHERE id = ?", [$scoreId]);
                flash('success', 'Score entry deleted.');
                logActivity('score_deleted', "Deleted score #$scoreId", Auth::id());
            }
            break;
            
        case 'flag_suspicious':
            $userId = (int)($_POST['user_id'] ?? 0);
            $reason = sanitize($_POST['reason'] ?? 'Suspicious leaderboard activity');
            if ($userId) {
                logActivity('suspicious_flagged', "User #$userId flagged: $reason", Auth::id());
                flash('success', 'User flagged for review.');
            }
            break;
            
        case 'reset_game_leaderboard':
            $gameId = (int)($_POST['game_id'] ?? 0);
            if ($gameId) {
                Database::update("DELETE FROM scores WHERE game_id = ?", [$gameId]);
                flash('success', 'Game leaderboard has been reset.');
                logActivity('game_leaderboard_reset', "Reset leaderboard for game #$gameId", Auth::id());
            }
            break;
    }
    
    redirect(baseUrl('/admin/leaderboard.php'));
}

// Get filter parameters
$gameFilter = $_GET['game'] ?? '';
$period = $_GET['period'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

// Get all games for filter dropdown
$games = Database::fetchAll("SELECT id, title, slug FROM games WHERE status = 'active' ORDER BY title");

// Build query for leaderboard
$where = ["u.status = 'active'", "u.role = 'player'"];
$params = [];

if ($gameFilter) {
    $where[] = "g.slug = ?";
    $params[] = $gameFilter;
}

if ($search) {
    $where[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Date filter
$dateFilter = match($period) {
    'today' => "AND s.created_at >= CURDATE()",
    'week' => "AND s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'month' => "AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    default => ""
};

$whereClause = implode(' AND ', $where);

// Get total count
$countSql = "SELECT COUNT(DISTINCT u.id) as count 
             FROM users u 
             JOIN scores s ON u.id = s.user_id $dateFilter
             JOIN games g ON s.game_id = g.id
             WHERE $whereClause";
$total = Database::fetch($countSql, $params)['count'];

$pagination = paginate($total, $perPage, $page);

// Get leaderboard data
if ($gameFilter) {
    // Game-specific leaderboard
    $leaderboardSql = "SELECT 
                        u.id as user_id,
                        u.username,
                        u.email,
                        u.avatar,
                        u.status as user_status,
                        g.title as game_title,
                        g.slug as game_slug,
                        MAX(s.score) as high_score,
                        COUNT(s.id) as total_plays,
                        SUM(s.points_earned) as total_points,
                        MAX(s.created_at) as last_played,
                        AVG(s.score) as avg_score
                       FROM users u
                       JOIN scores s ON u.id = s.user_id $dateFilter
                       JOIN games g ON s.game_id = g.id
                       WHERE $whereClause
                       GROUP BY u.id, u.username, u.email, u.avatar, u.status, g.title, g.slug
                       ORDER BY high_score DESC
                       LIMIT ? OFFSET ?";
} else {
    // Global leaderboard by total points
    $leaderboardSql = "SELECT 
                        u.id as user_id,
                        u.username,
                        u.email,
                        u.avatar,
                        u.status as user_status,
                        NULL as game_title,
                        NULL as game_slug,
                        MAX(s.score) as high_score,
                        COUNT(s.id) as total_plays,
                        SUM(s.points_earned) as total_points,
                        MAX(s.created_at) as last_played,
                        AVG(s.score) as avg_score
                       FROM users u
                       JOIN scores s ON u.id = s.user_id $dateFilter
                       JOIN games g ON s.game_id = g.id
                       WHERE $whereClause
                       GROUP BY u.id, u.username, u.email, u.avatar, u.status
                       ORDER BY total_points DESC
                       LIMIT ? OFFSET ?";
}

$params[] = $pagination['per_page'];
$params[] = $pagination['offset'];
$leaderboard = Database::fetchAll($leaderboardSql, $params);

// Get suspicious activity (users with unusually high scores)
$suspiciousActivity = Database::fetchAll(
    "SELECT 
        u.id as user_id,
        u.username,
        g.title as game_title,
        s.score,
        s.play_time,
        s.created_at,
        s.ip_address
     FROM scores s
     JOIN users u ON s.user_id = u.id
     JOIN games g ON s.game_id = g.id
     WHERE s.score > (
         SELECT AVG(score) + (3 * STDDEV(score)) 
         FROM scores 
         WHERE game_id = s.game_id
     )
     AND s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY s.created_at DESC
     LIMIT 20"
);

// Get recent high scores
$recentHighScores = Database::fetchAll(
    "SELECT 
        s.*,
        u.username,
        u.avatar,
        g.title as game_title,
        g.slug as game_slug
     FROM scores s
     JOIN users u ON s.user_id = u.id
     JOIN games g ON s.game_id = g.id
     ORDER BY s.score DESC
     LIMIT 10"
);

// Get leaderboard stats
$leaderboardStats = [
    'total_scores' => Database::fetch("SELECT COUNT(*) as count FROM scores")['count'],
    'total_points_awarded' => Database::fetch("SELECT COALESCE(SUM(points_earned), 0) as total FROM scores")['total'],
    'avg_score' => Database::fetch("SELECT COALESCE(ROUND(AVG(score), 0), 0) as avg FROM scores")['avg'],
    'highest_score' => Database::fetch("SELECT COALESCE(MAX(score), 0) as max FROM scores")['max'],
];
?>

<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
                <h1 class="font-game text-3xl text-gray-800">🏆 Leaderboard Management</h1>
                <p class="text-gray-600">View rankings, manage scores, and identify suspicious activity</p>
            </div>
            <a href="<?= baseUrl('/admin/') ?>" class="text-blue-500 hover:underline">← Back to Dashboard</a>
        </div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl p-5 shadow-lg">
                <p class="text-gray-500 text-sm">Total Scores</p>
                <p class="text-2xl font-bold text-gray-800"><?= number_format($leaderboardStats['total_scores']) ?></p>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-lg">
                <p class="text-gray-500 text-sm">Points Awarded</p>
                <p class="text-2xl font-bold text-green-600"><?= number_format($leaderboardStats['total_points_awarded']) ?></p>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-lg">
                <p class="text-gray-500 text-sm">Average Score</p>
                <p class="text-2xl font-bold text-blue-600"><?= number_format($leaderboardStats['avg_score']) ?></p>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-lg">
                <p class="text-gray-500 text-sm">Highest Score</p>
                <p class="text-2xl font-bold text-purple-600"><?= number_format($leaderboardStats['highest_score']) ?></p>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Search Player</label>
                    <input type="text" name="search" value="<?= e($search) ?>" 
                           placeholder="Username or email..."
                           class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Game</label>
                    <select name="game" class="px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                        <option value="">All Games</option>
                        <?php foreach ($games as $game): ?>
                            <option value="<?= $game['slug'] ?>" <?= $gameFilter === $game['slug'] ? 'selected' : '' ?>>
                                <?= e($game['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Period</label>
                    <select name="period" class="px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                        <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>This Week</option>
                        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This Month</option>
                    </select>
                </div>
                <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                    Filter
                </button>
                <?php if ($search || $gameFilter || $period !== 'all'): ?>
                    <a href="<?= baseUrl('/admin/leaderboard.php') ?>" class="text-gray-500 hover:text-gray-700 px-4 py-2">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="grid lg:grid-cols-3 gap-6">
            
            <!-- Main Leaderboard Table -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                    <h3 class="font-bold text-lg">
                        <?= $gameFilter ? 'Game Leaderboard' : 'Global Leaderboard' ?>
                    </h3>
                    <?php if ($gameFilter): ?>
                        <form method="POST" onsubmit="return confirm('Reset ALL scores for this game? This cannot be undone!')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reset_game_leaderboard">
                            <input type="hidden" name="game_id" value="<?= $games[array_search($gameFilter, array_column($games, 'slug'))]['id'] ?? 0 ?>">
                            <button type="submit" class="text-red-500 text-sm hover:underline">
                                Reset Game Leaderboard
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 text-sm">
                            <tr>
                                <th class="px-4 py-3 text-left">Rank</th>
                                <th class="px-4 py-3 text-left">Player</th>
                                <?php if ($gameFilter): ?>
                                    <th class="px-4 py-3 text-center">High Score</th>
                                <?php endif; ?>
                                <th class="px-4 py-3 text-center">Plays</th>
                                <th class="px-4 py-3 text-center">Avg Score</th>
                                <th class="px-4 py-3 text-center">Points</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($leaderboard as $index => $entry): 
                                $rank = $pagination['offset'] + $index + 1;
                                $rankBadge = match($rank) {
                                    1 => '<span class="text-2xl">🥇</span>',
                                    2 => '<span class="text-2xl">🥈</span>',
                                    3 => '<span class="text-2xl">🥉</span>',
                                    default => '<span class="text-gray-500 font-bold">#' . $rank . '</span>'
                                };
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><?= $rankBadge ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= asset('images/avatars/' . ($entry['avatar'] ?? 'default-avatar.png')) ?>" 
                                                 alt="" class="w-10 h-10 rounded-full">
                                            <div>
                                                <p class="font-semibold text-gray-800"><?= e($entry['username']) ?></p>
                                                <p class="text-xs text-gray-500"><?= e($entry['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <?php if ($gameFilter): ?>
                                        <td class="px-4 py-3 text-center font-bold text-purple-600">
                                            <?= number_format($entry['high_score']) ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="px-4 py-3 text-center text-gray-600">
                                        <?= number_format($entry['total_plays']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-600">
                                        <?= number_format($entry['avg_score']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-green-600">
                                        <?= number_format($entry['total_points']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="relative" x-data="{ open: false }">
                                            <button @click="open = !open" class="p-2 hover:bg-gray-100 rounded-lg">
                                                <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                                </svg>
                                            </button>
                                            
                                            <div x-show="open" @click.away="open = false" 
                                                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border z-50">
                                                <a href="<?= baseUrl('/admin/users.php?search=' . urlencode($entry['username'])) ?>"
                                                   class="block px-4 py-2 text-sm hover:bg-gray-100">
                                                    👤 View User
                                                </a>
                                                
                                                <form method="POST">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="reset_user_scores">
                                                    <input type="hidden" name="user_id" value="<?= $entry['user_id'] ?>">
                                                    <?php if ($gameFilter): ?>
                                                        <input type="hidden" name="game_id" value="<?= $games[array_search($gameFilter, array_column($games, 'slug'))]['id'] ?? 0 ?>">
                                                    <?php endif; ?>
                                                    <button type="submit" onclick="return confirm('Reset scores for this user?')"
                                                            class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                        🗑️ Reset Scores
                                                    </button>
                                                </form>
                                                
                                                <button onclick="showFlagModal(<?= $entry['user_id'] ?>, '<?= e($entry['username']) ?>')"
                                                        class="w-full text-left px-4 py-2 text-sm text-orange-600 hover:bg-orange-50">
                                                    🚩 Flag Suspicious
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($leaderboard)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                        No leaderboard data found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="px-6 py-4 border-t flex items-center justify-center gap-2">
                        <?php if ($pagination['has_prev']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])) ?>" 
                               class="px-3 py-1 bg-gray-100 rounded hover:bg-gray-200">←</a>
                        <?php endif; ?>
                        
                        <span class="px-4 py-1 text-gray-600">
                            Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
                        </span>
                        
                        <?php if ($pagination['has_next']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])) ?>" 
                               class="px-3 py-1 bg-gray-100 rounded hover:bg-gray-200">→</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="space-y-6">
                
                <!-- Recent High Scores -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h3 class="font-bold">🎯 Top Scores (All Time)</h3>
                    </div>
                    <div class="divide-y">
                        <?php foreach ($recentHighScores as $index => $score): ?>
                            <div class="px-4 py-3 flex items-center justify-between hover:bg-gray-50">
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center text-xs font-bold">
                                        <?= $index + 1 ?>
                                    </span>
                                    <div>
                                        <p class="font-semibold text-sm"><?= e($score['username']) ?></p>
                                        <p class="text-xs text-gray-500"><?= e($score['game_title']) ?></p>
                                    </div>
                                </div>
                                <span class="font-bold text-purple-600"><?= number_format($score['score']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Suspicious Activity -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="px-4 py-3 border-b bg-red-50">
                        <h3 class="font-bold text-red-700">⚠️ Suspicious Activity</h3>
                        <p class="text-xs text-red-600">Scores 3+ std dev above average</p>
                    </div>
                    <div class="divide-y max-h-80 overflow-y-auto">
                        <?php if (empty($suspiciousActivity)): ?>
                            <div class="px-4 py-6 text-center text-gray-500 text-sm">
                                No suspicious activity detected
                            </div>
                        <?php else: ?>
                            <?php foreach ($suspiciousActivity as $suspicious): ?>
                                <div class="px-4 py-3 hover:bg-red-50">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-semibold text-sm"><?= e($suspicious['username']) ?></span>
                                        <span class="font-bold text-red-600"><?= number_format($suspicious['score']) ?></span>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        <?= e($suspicious['game_title']) ?> • 
                                        <?= $suspicious['play_time'] ?>s • 
                                        <?= timeAgo($suspicious['created_at']) ?>
                                    </p>
                                    <div class="mt-2 flex gap-2">
                                        <form method="POST" class="inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="flag_suspicious">
                                            <input type="hidden" name="user_id" value="<?= $suspicious['user_id'] ?>">
                                            <input type="hidden" name="reason" value="High score anomaly: <?= $suspicious['score'] ?> in <?= $suspicious['game_title'] ?>">
                                            <button type="submit" class="text-xs text-orange-600 hover:underline">Flag</button>
                                        </form>
                                        <span class="text-gray-300">|</span>
                                        <a href="<?= baseUrl('/admin/users.php?search=' . urlencode($suspicious['username'])) ?>" 
                                           class="text-xs text-blue-600 hover:underline">View User</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg p-4">
                    <h3 class="font-bold mb-3">⚡ Quick Actions</h3>
                    <div class="space-y-2">
                        <a href="<?= baseUrl('/leaderboard/') ?>" target="_blank"
                           class="block w-full text-center bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 text-sm">
                            View Public Leaderboard
                        </a>
                        <a href="<?= baseUrl('/admin/analytics.php') ?>"
                           class="block w-full text-center bg-purple-500 text-white py-2 rounded-lg hover:bg-purple-600 text-sm">
                            View Analytics
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Flag Suspicious Modal -->
<div id="flagModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
        <h3 class="text-xl font-bold mb-4">🚩 Flag Suspicious Activity</h3>
        <p class="text-gray-600 mb-4">Flagging user: <strong id="flagUsername"></strong></p>
        
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="flag_suspicious">
            <input type="hidden" name="user_id" id="flagUserId">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-1">Reason</label>
                <textarea name="reason" rows="3" required
                          class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none"
                          placeholder="Describe the suspicious activity..."></textarea>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closeFlagModal()"
                        class="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600">
                    Flag User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showFlagModal(userId, username) {
    document.getElementById('flagUserId').value = userId;
    document.getElementById('flagUsername').textContent = username;
    document.getElementById('flagModal').classList.remove('hidden');
    document.getElementById('flagModal').classList.add('flex');
}

function closeFlagModal() {
    document.getElementById('flagModal').classList.add('hidden');
    document.getElementById('flagModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>