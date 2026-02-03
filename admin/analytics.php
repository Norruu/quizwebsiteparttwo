<?php
/**
 * Admin Analytics Dashboard
 * View detailed statistics, charts, and platform insights
 */

$pageTitle = 'Analytics';
require_once __DIR__ . '/../includes/header.php';

Auth::requireAdmin();

// Get date range filter
$range = $_GET['range'] ?? '30'; // days
$startDate = date('Y-m-d', strtotime("-{$range} days"));
$endDate = date('Y-m-d');

// ============================================
// Gather Analytics Data
// ============================================

// Overview Stats
$overviewStats = [
    'total_users' => Database::fetch("SELECT COUNT(*) as count FROM users WHERE role = 'player'")['count'],
    'new_users' => Database::fetch("SELECT COUNT(*) as count FROM users WHERE role = 'player' AND created_at >= ?", [$startDate])['count'],
    'total_games_played' => Database::fetch("SELECT COUNT(*) as count FROM scores WHERE created_at >= ?", [$startDate])['count'],
    'total_points_earned' => Database::fetch("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'earn' AND created_at >= ?", [$startDate])['total'],
    'total_points_spent' => Database::fetch("SELECT COALESCE(ABS(SUM(amount)), 0) as total FROM transactions WHERE type = 'spend' AND created_at >= ?", [$startDate])['total'],
    'active_users' => Database::fetch("SELECT COUNT(DISTINCT user_id) as count FROM scores WHERE created_at >= ?", [$startDate])['count'],
    'avg_score' => Database::fetch("SELECT COALESCE(ROUND(AVG(score), 0), 0) as avg FROM scores WHERE created_at >= ?", [$startDate])['avg'],
    'total_redemptions' => Database::fetch("SELECT COUNT(*) as count FROM redemptions WHERE created_at >= ?", [$startDate])['count'],
];

// Daily Stats for Chart (last N days)
$dailyStats = Database::fetchAll(
    "SELECT 
        DATE(created_at) as date,
        COUNT(*) as plays,
        COUNT(DISTINCT user_id) as unique_players,
        COALESCE(SUM(points_earned), 0) as points_earned,
        COALESCE(ROUND(AVG(score), 0), 0) as avg_score
     FROM scores 
     WHERE created_at >= ?
     GROUP BY DATE(created_at)
     ORDER BY date ASC",
    [$startDate]
);

// New Users per Day
$newUsersDaily = Database::fetchAll(
    "SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
     FROM users 
     WHERE role = 'player' AND created_at >= ?
     GROUP BY DATE(created_at)
     ORDER BY date ASC",
    [$startDate]
);

// Game Performance
$gameStats = Database::fetchAll(
    "SELECT 
        g.id,
        g.title,
        g.category,
        COUNT(s.id) as total_plays,
        COUNT(DISTINCT s.user_id) as unique_players,
        COALESCE(ROUND(AVG(s.score), 0), 0) as avg_score,
        COALESCE(MAX(s.score), 0) as high_score,
        COALESCE(SUM(s.points_earned), 0) as total_points_awarded
     FROM games g
     LEFT JOIN scores s ON g.id = s.game_id AND s.created_at >= ?
     WHERE g.status = 'active'
     GROUP BY g.id, g.title, g.category
     ORDER BY total_plays DESC",
    [$startDate]
);

// Category Distribution
$categoryStats = Database::fetchAll(
    "SELECT 
        g.category,
        COUNT(s.id) as plays
     FROM scores s
     JOIN games g ON s.game_id = g.id
     WHERE s.created_at >= ?
     GROUP BY g.category
     ORDER BY plays DESC",
    [$startDate]
);

// Top Players
$topPlayers = Database::fetchAll(
    "SELECT 
        u.id,
        u.username,
        u.avatar,
        COUNT(s.id) as games_played,
        COALESCE(SUM(s.points_earned), 0) as points_earned,
        COALESCE(MAX(s.score), 0) as high_score
     FROM users u
     JOIN scores s ON u.id = s.user_id
     WHERE s.created_at >= ? AND u.status = 'active'
     GROUP BY u.id, u.username, u.avatar
     ORDER BY points_earned DESC
     LIMIT 10",
    [$startDate]
);

// Hourly Activity (for today)
$hourlyActivity = Database::fetchAll(
    "SELECT 
        HOUR(created_at) as hour,
        COUNT(*) as plays
     FROM scores 
     WHERE DATE(created_at) = CURDATE()
     GROUP BY HOUR(created_at)
     ORDER BY hour ASC"
);

// Prepare hourly data (fill in missing hours)
$hourlyData = array_fill(0, 24, 0);
foreach ($hourlyActivity as $row) {
    $hourlyData[(int)$row['hour']] = (int)$row['plays'];
}

// Recent Transactions Summary
$transactionSummary = Database::fetchAll(
    "SELECT 
        type,
        COUNT(*) as count,
        COALESCE(SUM(ABS(amount)), 0) as total_amount
     FROM transactions
     WHERE created_at >= ?
     GROUP BY type
     ORDER BY total_amount DESC",
    [$startDate]
);

// Redemption Stats
$redemptionStats = Database::fetchAll(
    "SELECT 
        r.name as reward_name,
        COUNT(rd.id) as redemption_count,
        COALESCE(SUM(rd.points_spent), 0) as total_points
     FROM redemptions rd
     JOIN rewards r ON rd.reward_id = r.id
     WHERE rd.created_at >= ?
     GROUP BY r.id, r.name
     ORDER BY redemption_count DESC
     LIMIT 10",
    [$startDate]
);

// Prepare chart data
$chartLabels = [];
$chartPlays = [];
$chartPlayers = [];
$chartPoints = [];

foreach ($dailyStats as $stat) {
    $chartLabels[] = date('M j', strtotime($stat['date']));
    $chartPlays[] = (int)$stat['plays'];
    $chartPlayers[] = (int)$stat['unique_players'];
    $chartPoints[] = (int)$stat['points_earned'];
}

// Fill in missing dates
$currentDate = new DateTime($startDate);
$endDateTime = new DateTime($endDate);
$allDates = [];
$playsMap = [];
$playersMap = [];
$pointsMap = [];

foreach ($dailyStats as $stat) {
    $playsMap[$stat['date']] = (int)$stat['plays'];
    $playersMap[$stat['date']] = (int)$stat['unique_players'];
    $pointsMap[$stat['date']] = (int)$stat['points_earned'];
}

while ($currentDate <= $endDateTime) {
    $dateStr = $currentDate->format('Y-m-d');
    $allDates[] = $currentDate->format('M j');
    $chartPlays[] = $playsMap[$dateStr] ?? 0;
    $chartPlayers[] = $playersMap[$dateStr] ?? 0;
    $chartPoints[] = $pointsMap[$dateStr] ?? 0;
    $currentDate->modify('+1 day');
}

// Prepare category chart data
$categoryLabels = [];
$categoryData = [];
$categoryColors = [
    'arcade' => '#FF6B35',
    'puzzle' => '#4D96FF',
    'word' => '#6BCB77',
    'memory' => '#9B59B6',
    'quiz' => '#FFD93D',
    'action' => '#FF4757',
];

foreach ($categoryStats as $cat) {
    $categoryLabels[] = ucfirst($cat['category']);
    $categoryData[] = (int)$cat['plays'];
}
?>

<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
                <h1 class="font-game text-3xl text-gray-800">📊 Analytics Dashboard</h1>
                <p class="text-gray-600">Platform insights and statistics</p>
            </div>
            
            <div class="flex items-center gap-3">
                <a href="<?= baseUrl('/admin/') ?>" class="text-blue-500 hover:underline">← Back</a>
                
                <!-- Date Range Filter -->
                <form method="GET" class="flex items-center gap-2">
                    <select name="range" onchange="this.form.submit()" 
                            class="px-4 py-2 border rounded-lg bg-white focus:border-blue-500 outline-none">
                        <option value="7" <?= $range == '7' ? 'selected' : '' ?>>Last 7 days</option>
                        <option value="14" <?= $range == '14' ? 'selected' : '' ?>>Last 14 days</option>
                        <option value="30" <?= $range == '30' ? 'selected' : '' ?>>Last 30 days</option>
                        <option value="60" <?= $range == '60' ? 'selected' : '' ?>>Last 60 days</option>
                        <option value="90" <?= $range == '90' ? 'selected' : '' ?>>Last 90 days</option>
                        <option value="365" <?= $range == '365' ? 'selected' : '' ?>>Last year</option>
                    </select>
                </form>
                
                <!-- Export Button -->
                <button onclick="exportData()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                    📥 Export
                </button>
            </div>
        </div>
        
        <!-- Overview Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl p-5 shadow-lg">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-3xl">👥</span>
                    <span class="text-xs text-green-500 font-semibold">+<?= $overviewStats['new_users'] ?> new</span>
                </div>
                <p class="text-2xl font-bold text-gray-800"><?= number_format($overviewStats['total_users']) ?></p>
                <p class="text-sm text-gray-500">Total Players</p>
            </div>
            
            <div class="bg-white rounded-xl p-5 shadow-lg">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-3xl">🎮</span>
                    <span class="text-xs text-blue-500 font-semibold"><?= $overviewStats['active_users'] ?> active</span>
                </div>
                <p class="text-2xl font-bold text-gray-800"><?= number_format($overviewStats['total_games_played']) ?></p>
                <p class="text-sm text-gray-500">Games Played</p>
            </div>
            
            <div class="bg-white rounded-xl p-5 shadow-lg">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-3xl">💰</span>
                    <span class="text-xs text-purple-500 font-semibold">earned</span>
                </div>
                <p class="text-2xl font-bold text-gray-800"><?= number_format($overviewStats['total_points_earned']) ?></p>
                <p class="text-sm text-gray-500">Points Earned</p>
            </div>
            
            <div class="bg-white rounded-xl p-5 shadow-lg">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-3xl">🎁</span>
                    <span class="text-xs text-orange-500 font-semibold"><?= number_format($overviewStats['total_points_spent']) ?> spent</span>
                </div>
                <p class="text-2xl font-bold text-gray-800"><?= number_format($overviewStats['total_redemptions']) ?></p>
                <p class="text-sm text-gray-500">Redemptions</p>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="grid lg:grid-cols-2 gap-6 mb-8">
            
            <!-- Activity Chart -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="font-bold text-lg mb-4">📈 Daily Activity</h3>
                <canvas id="activityChart" height="250"></canvas>
            </div>
            
            <!-- Category Distribution -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="font-bold text-lg mb-4">🎯 Games by Category</h3>
                <canvas id="categoryChart" height="250"></canvas>
            </div>
        </div>
        
        <!-- Second Charts Row -->
        <div class="grid lg:grid-cols-2 gap-6 mb-8">
            
            <!-- Hourly Activity (Today) -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="font-bold text-lg mb-4">⏰ Today's Hourly Activity</h3>
                <canvas id="hourlyChart" height="200"></canvas>
            </div>
            
            <!-- Points Flow -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="font-bold text-lg mb-4">💰 Points Flow</h3>
                <canvas id="pointsChart" height="200"></canvas>
            </div>
        </div>
        
        <!-- Data Tables Row -->
        <div class="grid lg:grid-cols-2 gap-6 mb-8">
            
            <!-- Game Performance Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h3 class="font-bold text-lg">🎮 Game Performance</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 text-sm">
                            <tr>
                                <th class="px-4 py-3 text-left">Game</th>
                                <th class="px-4 py-3 text-center">Plays</th>
                                <th class="px-4 py-3 text-center">Players</th>
                                <th class="px-4 py-3 text-center">Avg Score</th>
                                <th class="px-4 py-3 text-right">Points</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($gameStats as $game): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div>
                                            <p class="font-semibold text-gray-800"><?= e($game['title']) ?></p>
                                            <p class="text-xs text-gray-500"><?= ucfirst($game['category']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center font-semibold text-blue-600">
                                        <?= number_format($game['total_plays']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-600">
                                        <?= number_format($game['unique_players']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-600">
                                        <?= number_format($game['avg_score']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-green-600">
                                        <?= number_format($game['total_points_awarded']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($gameStats)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        No game data for this period
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Top Players Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h3 class="font-bold text-lg">🏆 Top Players (This Period)</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 text-sm">
                            <tr>
                                <th class="px-4 py-3 text-left">Rank</th>
                                <th class="px-4 py-3 text-left">Player</th>
                                <th class="px-4 py-3 text-center">Games</th>
                                <th class="px-4 py-3 text-center">High Score</th>
                                <th class="px-4 py-3 text-right">Points</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($topPlayers as $index => $player): 
                                $rank = $index + 1;
                                $rankBadge = match($rank) {
                                    1 => '🥇',
                                    2 => '🥈',
                                    3 => '🥉',
                                    default => '#' . $rank
                                };
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-bold text-lg">
                                        <?= $rankBadge ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <img src="<?= asset('images/avatars/' . ($player['avatar'] ?? 'default-avatar.png')) ?>" 
                                                 alt="" class="w-8 h-8 rounded-full">
                                            <span class="font-semibold"><?= e($player['username']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-600">
                                        <?= number_format($player['games_played']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center text-purple-600 font-semibold">
                                        <?= number_format($player['high_score']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-green-600">
                                        <?= number_format($player['points_earned']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($topPlayers)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        No player data for this period
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Transaction & Redemption Summary -->
        <div class="grid lg:grid-cols-2 gap-6">
            
            <!-- Transaction Summary -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="font-bold text-lg mb-4">💳 Transaction Summary</h3>
                <div class="space-y-3">
                    <?php foreach ($transactionSummary as $tx): 
                        $icon = match($tx['type']) {
                            'earn' => '📈',
                            'spend' => '📉',
                            'bonus' => '🎁',
                            'penalty' => '⚠️',
                            'refund' => '↩️',
                            default => '💫'
                        };
                        $color = match($tx['type']) {
                            'earn', 'bonus', 'refund' => 'text-green-600 bg-green-50',
                            'spend', 'penalty' => 'text-red-600 bg-red-50',
                            default => 'text-gray-600 bg-gray-50'
                        };
                    ?>
                        <div class="flex items-center justify-between p-3 rounded-lg <?= $color ?>">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl"><?= $icon ?></span>
                                <div>
                                    <p class="font-semibold capitalize"><?= $tx['type'] ?></p>
                                    <p class="text-sm opacity-75"><?= number_format($tx['count']) ?> transactions</p>
                                </div>
                            </div>
                            <p class="font-bold text-xl"><?= number_format($tx['total_amount']) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($transactionSummary)): ?>
                        <p class="text-center text-gray-500 py-4">No transactions for this period</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Popular Redemptions -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="font-bold text-lg mb-4">🎁 Popular Redemptions</h3>
                <div class="space-y-3">
                    <?php foreach ($redemptionStats as $index => $redemption): ?>
                        <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 bg-purple-200 rounded-full flex items-center justify-center font-bold text-purple-700">
                                    <?= $index + 1 ?>
                                </span>
                                <div>
                                    <p class="font-semibold text-gray-800"><?= e($redemption['reward_name']) ?></p>
                                    <p class="text-sm text-gray-500"><?= number_format($redemption['total_points']) ?> points spent</p>
                                </div>
                            </div>
                            <p class="font-bold text-purple-600"><?= $redemption['redemption_count'] ?>×</p>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($redemptionStats)): ?>
                        <p class="text-center text-gray-500 py-4">No redemptions for this period</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Chart.js Configuration
Chart.defaults.font.family = 'Nunito, sans-serif';

// Activity Chart (Line)
const activityCtx = document.getElementById('activityChart').getContext('2d');
new Chart(activityCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($allDates) ?>,
        datasets: [
            {
                label: 'Games Played',
                data: <?= json_encode($chartPlays) ?>,
                borderColor: '#4D96FF',
                backgroundColor: 'rgba(77, 150, 255, 0.1)',
                fill: true,
                tension: 0.3
            },
            {
                label: 'Unique Players',
                data: <?= json_encode($chartPlayers) ?>,
                borderColor: '#6BCB77',
                backgroundColor: 'rgba(107, 203, 119, 0.1)',
                fill: true,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Category Chart (Doughnut)
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($categoryLabels) ?>,
        datasets: [{
            data: <?= json_encode($categoryData) ?>,
            backgroundColor: ['#FF6B35', '#4D96FF', '#6BCB77', '#9B59B6', '#FFD93D', '#FF4757'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
            }
        }
    }
});

// Hourly Chart (Bar)
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: Array.from({length: 24}, (_, i) => i + ':00'),
        datasets: [{
            label: 'Games Played',
            data: <?= json_encode($hourlyData) ?>,
            backgroundColor: '#4D96FF',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Points Flow Chart (Bar)
const pointsCtx = document.getElementById('pointsChart').getContext('2d');
new Chart(pointsCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($allDates) ?>,
        datasets: [{
            label: 'Points Earned',
            data: <?= json_encode($chartPoints) ?>,
            backgroundColor: '#6BCB77',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Export function
function exportData() {
    const data = {
        period: '<?= $startDate ?> to <?= $endDate ?>',
        overview: <?= json_encode($overviewStats) ?>,
        games: <?= json_encode($gameStats) ?>,
        topPlayers: <?= json_encode($topPlayers) ?>
    };
    
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'analytics_<?= date('Y-m-d') ?>.json';
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>s