<?php
/**
 * Admin Dashboard
 * Overview of platform statistics and recent activity
 */

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';

Auth::requireAdmin();

// Get statistics
$stats = [
    'total_users' => Database::fetch("SELECT COUNT(*) as count FROM users")['count'],
    'total_players' => Database::fetch("SELECT COUNT(*) as count FROM users WHERE role = 'player'")['count'],
    'active_users' => Database::fetch("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'],
    'banned_users' => Database::fetch("SELECT COUNT(*) as count FROM users WHERE status = 'banned'")['count'],
    'total_games' => Database::fetch("SELECT COUNT(*) as count FROM games")['count'],
    'active_games' => Database::fetch("SELECT COUNT(*) as count FROM games WHERE status = 'active'")['count'],
    'total_plays' => Database::fetch("SELECT COUNT(*) as count FROM scores")['count'],
    'plays_today' => Database::fetch("SELECT COUNT(*) as count FROM scores WHERE DATE(created_at) = CURDATE()")['count'],
    'total_points' => Database::fetch("SELECT SUM(total_earned) as total FROM wallet")['total'] ?? 0,
    'points_today' => Database::fetch("SELECT SUM(amount) as total FROM transactions WHERE type = 'earn' AND DATE(created_at) = CURDATE()")['total'] ?? 0,
    'pending_redemptions' => Database::fetch("SELECT COUNT(*) as count FROM redemptions WHERE status = 'pending'")['count'],
    'total_rewards' => Database::fetch("SELECT COUNT(*) as count FROM rewards WHERE status = 'active'")['count'],
];

// Recent users
$recentUsers = Database::fetchAll(
    "SELECT * FROM users ORDER BY created_at DESC LIMIT 5"
);

// Recent scores
$recentScores = Database::fetchAll(
    "SELECT s.*, u.username, g.title as game_title 
     FROM scores s 
     JOIN users u ON s.user_id = u.id 
     JOIN games g ON s.game_id = g.id 
     ORDER BY s.created_at DESC LIMIT 10"
);

// Recent redemptions
$recentRedemptions = Database::fetchAll(
    "SELECT r.*, u.username, rw.name as reward_name 
     FROM redemptions r 
     JOIN users u ON r.user_id = u.id 
     JOIN rewards rw ON r.reward_id = rw.id 
     ORDER BY r.created_at DESC LIMIT 5"
);

// Top games today
$topGamesToday = Database::fetchAll(
    "SELECT g.title, g.slug, COUNT(s.id) as plays 
     FROM scores s 
     JOIN games g ON s.game_id = g.id 
     WHERE DATE(s.created_at) = CURDATE() 
     GROUP BY g.id, g.title, g.slug 
     ORDER BY plays DESC LIMIT 5"
);
?>

<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="font-game text-4xl text-gray-800">⚙️ Admin Dashboard</h1>
                <p class="text-gray-600">Welcome back, <?= e(Auth::user()['username']) ?>!</p>
            </div>
            <div class="text-right text-sm text-gray-500">
                <p>Server Time: <?= date('Y-m-d H:i:s') ?></p>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            
            <!-- Users -->
            <div class="bg-white rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Users</p>
                        <p class="text-3xl font-bold text-gray-800"><?= formatNumber($stats['total_users']) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-2xl">👥</div>
                </div>
                <p class="text-xs text-gray-400 mt-2"><?= $stats['active_users'] ?> active • <?= $stats['banned_users'] ?> banned</p>
            </div>
            
            <!-- Games -->
            <div class="bg-white rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Games</p>
                        <p class="text-3xl font-bold text-gray-800"><?= formatNumber($stats['total_games']) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center text-2xl">🎮</div>
                </div>
                <p class="text-xs text-gray-400 mt-2"><?= $stats['active_games'] ?> active</p>
            </div>
            
            <!-- Plays Today -->
            <div class="bg-white rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Plays Today</p>
                        <p class="text-3xl font-bold text-gray-800"><?= formatNumber($stats['plays_today']) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center text-2xl">🎯</div>
                </div>
                <p class="text-xs text-gray-400 mt-2"><?= formatNumber($stats['total_plays']) ?> total</p>
            </div>
            
            <!-- Points Today -->
            <div class="bg-white rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Points Today</p>
                        <p class="text-3xl font-bold text-gray-800"><?= formatNumber($stats['points_today']) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center text-2xl">💰</div>
                </div>
                <p class="text-xs text-gray-400 mt-2"><?= formatNumber($stats['total_points']) ?> total awarded</p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-xl p-6 shadow-lg mb-8">
            <h2 class="font-bold text-lg mb-4">Quick Actions</h2>
            <div class="flex flex-wrap gap-3">
                <a href="<?= baseUrl('/admin/users.php') ?>" class="bg-blue-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-600 transition-colors">
                    👥 Manage Users
                </a>
                <a href="<?= baseUrl('/admin/games.php') ?>" class="bg-green-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-600 transition-colors">
                    🎮 Manage Games
                </a>
                <a href="<?= baseUrl('/admin/rewards.php') ?>" class="bg-purple-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-purple-600 transition-colors">
                    🎁 Manage Rewards
                </a>
                <a href="<?= baseUrl('/admin/redemptions.php') ?>" class="bg-orange-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-orange-600 transition-colors relative">
                    📦 Redemptions
                    <?php if ($stats['pending_redemptions'] > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs w-6 h-6 rounded-full flex items-center justify-center">
                            <?= $stats['pending_redemptions'] ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="<?= baseUrl('/admin/leaderboard.php') ?>" class="bg-yellow-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-yellow-600 transition-colors">
                    🏆 Leaderboard
                </a>
            </div>
        </div>
        
        <!-- Two Column Layout -->
        <div class="grid lg:grid-cols-2 gap-8">
            
            <!-- Recent Users -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h2 class="font-bold text-lg">Recent Users</h2>
                    <a href="<?= baseUrl('/admin/users.php') ?>" class="text-blue-500 text-sm hover:underline">View All</a>
                </div>
                <div class="divide-y">
                    <?php foreach ($recentUsers as $user): ?>
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50">
                            <div class="flex items-center gap-3">
                                <img src="<?= asset('images/avatars/' . ($user['avatar'] ?? 'default-avatar.png')) ?>" 
                                     alt="<?= e($user['username']) ?>" 
                                     class="w-10 h-10 rounded-full">
                                <div>
                                    <p class="font-semibold"><?= e($user['username']) ?></p>
                                    <p class="text-xs text-gray-500"><?= e($user['email']) ?></p>
                                </div>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full <?= statusColor($user['status']) ?>">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Recent Scores -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h2 class="font-bold text-lg">Recent Game Activity</h2>
                </div>
                <div class="divide-y max-h-80 overflow-y-auto">
                    <?php foreach ($recentScores as $score): ?>
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50">
                            <div>
                                <p class="font-semibold"><?= e($score['username']) ?></p>
                                <p class="text-xs text-gray-500"><?= e($score['game_title']) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-friv-blue"><?= formatNumber($score['score']) ?></p>
                                <p class="text-xs text-gray-400"><?= timeAgo($score['created_at']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Top Games Today -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h2 class="font-bold text-lg">Top Games Today</h2>
                </div>
                <div class="p-4">
                    <?php if (empty($topGamesToday)): ?>
                        <p class="text-gray-500 text-center py-4">No games played today</p>
                    <?php else: ?>
                        <?php foreach ($topGamesToday as $index => $game): ?>
                            <div class="flex items-center justify-between py-2">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center font-bold text-gray-600">
                                        <?= $index + 1 ?>
                                    </span>
                                    <span class="font-semibold"><?= e($game['title']) ?></span>
                                </div>
                                <span class="text-gray-600"><?= $game['plays'] ?> plays</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pending Redemptions -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h2 class="font-bold text-lg">Recent Redemptions</h2>
                    <a href="<?= baseUrl('/admin/redemptions.php') ?>" class="text-blue-500 text-sm hover:underline">View All</a>
                </div>
                <div class="divide-y">
                    <?php if (empty($recentRedemptions)): ?>
                        <p class="text-gray-500 text-center py-8">No redemptions yet</p>
                    <?php else: ?>
                        <?php foreach ($recentRedemptions as $redemption): ?>
                            <div class="flex items-center justify-between p-4 hover:bg-gray-50">
                                <div>
                                    <p class="font-semibold"><?= e($redemption['username']) ?></p>
                                    <p class="text-xs text-gray-500"><?= e($redemption['reward_name']) ?></p>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full <?= statusColor($redemption['status']) ?>">
                                    <?= ucfirst($redemption['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>