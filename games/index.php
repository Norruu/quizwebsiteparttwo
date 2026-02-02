<?php
/**
 * Game Library - Main Grid View
 * Friv-style game listing with colorful cards
 */

$pageTitle = 'Game Library';
require_once __DIR__ . '/../includes/header.php';

// Get filter parameters
$category = $_GET['category'] ?? 'all';
$difficulty = $_GET['difficulty'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

// Build query
$where = ["status = 'active'"];
$params = [];

if ($category !== 'all') {
    $where[] = "category = ?";
    $params[] = $category;
}

if ($difficulty !== 'all') {
    $where[] = "difficulty = ?";
    $params[] = $difficulty;
}

if (!empty($search)) {
    $where[] = "(title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Get total count for pagination
$total = Database::fetch(
    "SELECT COUNT(*) as count FROM games WHERE $whereClause",
    $params
)['count'];

$pagination = paginate($total, GAMES_PER_PAGE, $page);

// Get games
$games = Database::fetchAll(
    "SELECT * FROM games WHERE $whereClause ORDER BY featured DESC, sort_order ASC, title ASC LIMIT ? OFFSET ?",
    array_merge($params, [$pagination['per_page'], $pagination['offset']])
);

// Get categories for filter
$categories = ['all' => 'All Games', 'action' => '🎯 Action', 'puzzle' => '🧩 Puzzle', 'word' => '📝 Word', 'memory' => '🧠 Memory', 'quiz' => '❓ Quiz', 'arcade' => '👾 Arcade'];
$difficulties = ['all' => 'All Levels', 'easy' => '🟢 Easy', 'medium' => '🟡 Medium', 'hard' => '🔴 Hard'];

// Color palette for game cards (Friv-style)
$cardColors = [
    'bg-gradient-to-br from-friv-orange to-yellow-400',
    'bg-gradient-to-br from-friv-blue to-cyan-400',
    'bg-gradient-to-br from-friv-green to-emerald-400',
    'bg-gradient-to-br from-friv-purple to-pink-400',
    'bg-gradient-to-br from-friv-pink to-rose-400',
    'bg-gradient-to-br from-friv-cyan to-blue-400',
    'bg-gradient-to-br from-friv-red to-orange-400',
    'bg-gradient-to-br from-friv-yellow to-amber-400',
];
?>

<!-- Hero Section -->
<section class="relative overflow-hidden py-12 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-8">
            <h1 class="font-game text-5xl md:text-6xl gradient-text mb-4 animate-float">
                🎮 Game Library
            </h1>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                Play amazing games, earn points, and climb the leaderboard!
            </p>
        </div>
        
        <!-- Search & Filters -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
            <form method="GET" action="" class="flex flex-col md:flex-row gap-4">
                <!-- Search -->
                <div class="flex-1">
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                        <input type="text" 
                               name="search" 
                               value="<?= e($search) ?>"
                               placeholder="Search games..."
                               class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:border-friv-blue focus:ring-4 focus:ring-friv-blue/20 outline-none transition-all">
                    </div>
                </div>
                
                <!-- Category Filter -->
                <div>
                    <select name="category" 
                            class="w-full md:w-auto px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-friv-blue outline-none bg-white cursor-pointer">
                        <?php foreach ($categories as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $category === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Difficulty Filter -->
                <div>
                    <select name="difficulty" 
                            class="w-full md:w-auto px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-friv-blue outline-none bg-white cursor-pointer">
                        <?php foreach ($difficulties as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $difficulty === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Submit -->
                <button type="submit" 
                        class="bg-gradient-to-r from-friv-blue to-friv-purple text-white font-bold px-8 py-3 rounded-xl hover:shadow-lg transition-all">
                    Filter
                </button>
            </form>
        </div>
        
        <!-- Results Info -->
        <div class="flex items-center justify-between mb-6">
            <p class="text-gray-600">
                Showing <span class="font-bold text-friv-blue"><?= count($games) ?></span> of 
                <span class="font-bold"><?= $total ?></span> games
            </p>
            <?php if (!empty($search) || $category !== 'all' || $difficulty !== 'all'): ?>
                <a href="<?= baseUrl('/games/') ?>" class="text-friv-red hover:underline flex items-center gap-1">
                    <span>✕</span> Clear filters
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Games Grid -->
        <?php if (empty($games)): ?>
            <div class="text-center py-16">
                <div class="text-6xl mb-4">🎮</div>
                <h3 class="text-2xl font-bold text-gray-700 mb-2">No games found</h3>
                <p class="text-gray-500">Try adjusting your search or filters</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-6">
                <?php foreach ($games as $index => $game): 
                    $colorClass = $cardColors[$index % count($cardColors)];
                ?>
                    <a href="<?= baseUrl('/games/play.php?game=' . $game['slug']) ?>" 
                       class="game-card group">
                        <div class="<?= $colorClass ?> rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all">
                            <!-- Thumbnail -->
                            <div class="relative aspect-square overflow-hidden">
                                <img src="<?= asset('images/games/' . $game['thumbnail']) ?>" 
                                     alt="<?= e($game['title']) ?>"
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                     onerror="this.src='<?= asset('images/games/default-game.png') ?>'">
                                
                                <!-- Overlay on hover -->
                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <span class="bg-white text-gray-900 font-bold px-6 py-3 rounded-full transform scale-0 group-hover:scale-100 transition-transform">
                                        ▶ PLAY
                                    </span>
                                </div>
                                
                                <!-- Featured Badge -->
                                <?php if ($game['featured']): ?>
                                    <div class="absolute top-2 left-2 bg-friv-yellow text-gray-900 text-xs font-bold px-2 py-1 rounded-full">
                                        ⭐ Featured
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Points Badge -->
                                <div class="absolute bottom-2 right-2 bg-white/90 backdrop-blur text-gray-900 text-sm font-bold px-3 py-1 rounded-full flex items-center gap-1">
                                    <span>💰</span>
                                    <span><?= $game['points_reward'] ?></span>
                                </div>
                            </div>
                            
                            <!-- Info -->
                            <div class="p-3 bg-white/90 backdrop-blur">
                                <h3 class="font-bold text-gray-800 truncate"><?= e($game['title']) ?></h3>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-xs <?= difficultyColor($game['difficulty']) ?> text-white px-2 py-0.5 rounded-full">
                                        <?= ucfirst($game['difficulty']) ?>
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        <?= formatNumber($game['play_count']) ?> plays
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="flex items-center justify-center gap-2 mt-12">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])) ?>" 
                           class="px-4 py-2 bg-white rounded-xl shadow hover:shadow-lg transition-all">
                            ← Prev
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $pagination['current_page'] - 2);
                    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                        $isActive = $i === $pagination['current_page'];
                    ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="w-10 h-10 flex items-center justify-center rounded-xl transition-all <?= $isActive ? 'bg-friv-blue text-white shadow-lg' : 'bg-white hover:shadow-lg' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($pagination['has_next']): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])) ?>" 
                           class="px-4 py-2 bg-white rounded-xl shadow hover:shadow-lg transition-all">
                            Next →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>