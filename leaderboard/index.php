<?php
/**
 * Public Leaderboard Page
 */

$pageTitle = 'Leaderboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/leaderboard.php';

// Get filter parameters
$period = $_GET['period'] ?? 'all';
$gameSlug = $_GET['game'] ?? '';
$validPeriods = ['today', 'week', 'month', 'all'];

if (!in_array($period, $validPeriods)) {
    $period = 'all';
}

// Get game if specified
$selectedGame = null;
if ($gameSlug) {
    $selectedGame = Database::fetch(
        "SELECT * FROM games WHERE slug = ? AND status = 'active'",
        [$gameSlug]
    );
}

// Get leaderboard data
if ($selectedGame) {
    $leaderboard = Leaderboard::getByGame($selectedGame['id'], LEADERBOARD_LIMIT, $period);
} else {
    $leaderboard = Leaderboard::getGlobalByPoints(LEADERBOARD_LIMIT, $period);
}

// Get all games for filter
$games = Database::fetchAll(
    "SELECT id, title, slug FROM games WHERE status = 'active' ORDER BY title"
);

// Get current user's rank
$userRank = null;
if (Auth::check()) {
    if ($selectedGame) {
        $userRank = Leaderboard::getUserGameRank(Auth::id(), $selectedGame['id']);
    } else {
        $userRank = Leaderboard::getUserRank(Auth::id(), $period);
    }
}

// Get stats
$stats = Leaderboard::getStats();

// Period labels
$periodLabels = [
    'today' => '📅 Today',
    'week' => '📆 This Week',
    'month' => '🗓️ This Month',
    'all' => '🏆 All Time'
];
?>

<div class="min-h-screen py-8 px-4">
    <div class="max-w-6xl mx-auto">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="font-game text-5xl gradient-text mb-4">🏆 Leaderboard</h1>
            <p class="text-xl text-gray-600">See who's at the top!</p>
        </div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-2xl p-4 text-center shadow-lg">
                <p class="text-3xl font-bold text-friv-blue"><?= formatNumber($stats['total_players']) ?></p>
                <p class="text-gray-500 text-sm">Players</p>
            </div>
            <div class="bg-white rounded-2xl p-4 text-center shadow-lg">
                <p class="text-3xl font-bold text-friv-green"><?= formatNumber($stats['total_games_played']) ?></p>
                <p class="text-gray-500 text-sm">Games Played</p>
            </div>
            <div class="bg-white rounded-2xl p-4 text-center shadow-lg">
                <p class="text-3xl font-bold text-friv-orange"><?= formatNumber($stats['total_points_awarded']) ?></p>
                <p class="text-gray-500 text-sm">Points Awarded</p>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
            <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                
                <!-- Period Filter -->
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($periodLabels as $key => $label): ?>
                        <a href="?period=<?= $key ?><?= $gameSlug ? "&game=$gameSlug" : '' ?>" 
                           class="px-4 py-2 rounded-xl font-semibold transition-all <?= $period === $key ? 'bg-friv-blue text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Game Filter -->
                <div class="flex items-center gap-2">
                    <span class="text-gray-600">Game:</span>
                    <select onchange="window.location.href='?period=<?= $period ?>&game=' + this.value"
                            class="px-4 py-2 border-2 border-gray-200 rounded-xl bg-white focus:border-friv-blue outline-none">
                        <option value="">All Games</option>
                        <?php foreach ($games as $game): ?>
                            <option value="<?= $game['slug'] ?>" <?= $gameSlug === $game['slug'] ? 'selected' : '' ?>>
                                <?= e($game['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- User's Rank (if logged in) -->
        <?php if ($userRank): ?>
            <div class="bg-gradient-to-r from-friv-blue to-friv-purple rounded-2xl p-6 mb-8 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <img src="<?= asset('images/avatars/' . (Auth::user()['avatar'] ?? 'default-avatar.png')) ?>" 
                             alt="Your Avatar" 
                             class="w-16 h-16 rounded-full border-4 border-white/30">
                        <div>
                            <p class="text-white/80">Your Rank</p>
                            <p class="font-game text-3xl">#<?= $userRank ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-white/80">Your Points</p>
                        <p class="font-game text-3xl"><?= formatNumber(Auth::user()['wallet_balance'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Leaderboard Table -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            
            <?php if ($selectedGame): ?>
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h2 class="font-bold text-lg flex items-center gap-2">
                        <span>🎮</span>
                        <?= e($selectedGame['title']) ?> Leaderboard
                    </h2>
                </div>
            <?php endif; ?>
            
            <?php if (empty($leaderboard)): ?>
                <div class="text-center py-16">
                    <div class="text-6xl mb-4">🏆</div>
                    <h3 class="text-xl font-bold text-gray-700 mb-2">No rankings yet</h3>
                    <p class="text-gray-500">Be the first to play and claim the top spot!</p>
                    <a href="<?= baseUrl('/games/') ?>" class="inline-block mt-4 bg-friv-blue text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-600 transition-colors">
                        Start Playing
                    </a>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($leaderboard as $index => $player): 
                        $rank = $index + 1;
                        $isCurrentUser = Auth::check() && $player['id'] == Auth::id();
                        
                        // Rank styling
                        $rankClass = match($rank) {
                            1 => 'bg-gradient-to-r from-yellow-400 to-yellow-500 text-white',
                            2 => 'bg-gradient-to-r from-gray-300 to-gray-400 text-white',
                            3 => 'bg-gradient-to-r from-orange-400 to-orange-500 text-white',
                            default => 'bg-gray-100 text-gray-700'
                        };
                        
                        $rankIcon = match($rank) {
                            1 => '🥇',
                            2 => '🥈',
                            3 => '🥉',
                            default => '#' . $rank
                        };
                    ?>
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition-colors <?= $isCurrentUser ? 'bg-friv-blue/5' : '' ?>">
                            <div class="flex items-center gap-4">
                                <!-- Rank -->
                                <div class="w-12 h-12 <?= $rankClass ?> rounded-xl flex items-center justify-center font-bold text-lg">
                                    <?= $rankIcon ?>
                                </div>
                                
                                <!-- Avatar -->
                                <img src="<?= asset('images/avatars/' . ($player['avatar'] ?? 'default-avatar.png')) ?>" 
                                     alt="<?= e($player['username']) ?>" 
                                     class="w-12 h-12 rounded-full object-cover border-2 <?= $isCurrentUser ? 'border-friv-blue' : 'border-gray-200' ?>">
                                
                                <!-- Name -->
                                <div>
                                    <p class="font-bold text-gray-800 <?= $isCurrentUser ? 'text-friv-blue' : '' ?>">
                                        <?= e($player['username']) ?>
                                        <?= $isCurrentUser ? '(You)' : '' ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?= formatNumber($player['games_played'] ?? 0) ?> games played
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Score -->
                            <div class="text-right">
                                <?php if ($selectedGame): ?>
                                    <p class="font-bold text-2xl text-friv-purple"><?= formatNumber($player['high_score']) ?></p>
                                    <p class="text-sm text-gray-500">High Score</p>
                                <?php else: ?>
                                    <p class="font-bold text-2xl text-friv-orange"><?= formatNumber($player['total_points']) ?></p>
                                    <p class="text-sm text-gray-500">Total Points</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Call to Action -->
        <?php if (!Auth::check()): ?>
            <div class="mt-8 bg-gradient-to-r from-friv-purple to-friv-pink rounded-2xl p-8 text-center text-white">
                <h2 class="font-game text-3xl mb-4">Ready to Compete?</h2>
                <p class="text-white/80 mb-6">Create an account and start climbing the leaderboard!</p>
                <a href="<?= baseUrl('/auth/register.php') ?>" class="inline-block bg-white text-friv-purple font-bold px-8 py-3 rounded-xl hover:shadow-lg transition-all">
                    Sign Up Now
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>