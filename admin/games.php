<?php
/**
 * Admin Game Management
 */

$pageTitle = 'Manage Games';
require_once __DIR__ . '/../includes/header.php';

Auth::requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
        case 'update':
            $gameData = [
                'title' => sanitize($_POST['title'] ?? ''),
                'slug' => slugify($_POST['title'] ?? ''),
                'description' => sanitize($_POST['description'] ?? ''),
                'instructions' => sanitize($_POST['instructions'] ?? ''),
                'category' => $_POST['category'] ?? 'arcade',
                'difficulty' => $_POST['difficulty'] ?? 'medium',
                'points_reward' => (int)($_POST['points_reward'] ?? 10),
                'min_score_for_points' => (int)($_POST['min_score_for_points'] ?? 0),
                'max_plays_per_day' => (int)($_POST['max_plays_per_day'] ?? 10),
                'status' => $_POST['status'] ?? 'active',
                'featured' => isset($_POST['featured']) ? 1 : 0,
            ];
            
            // Handle thumbnail upload
            if (!empty($_FILES['thumbnail']['name'])) {
                $thumbnail = uploadImage($_FILES['thumbnail'], GAME_THUMBNAIL_PATH, 'game_');
                if ($thumbnail) {
                    $gameData['thumbnail'] = $thumbnail;
                }
            }
            
            if ($action === 'create') {
                // Set default thumbnail if not uploaded
                if (empty($gameData['thumbnail'])) {
                    $gameData['thumbnail'] = 'default-game.png';
                }
                $gameData['game_file'] = $gameData['slug'] . '.php';
                
                Database::insert(
                    "INSERT INTO games (title, slug, description, instructions, thumbnail, game_file, category, 
                     points_reward, difficulty, min_score_for_points, max_plays_per_day, status, featured, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    array_values($gameData)
                );
                flash('success', 'Game created successfully!');
            } else {
                $gameId = (int)$_POST['game_id'];
                $setClauses = [];
                $params = [];
                
                foreach ($gameData as $key => $value) {
                    if ($key !== 'thumbnail' || !empty($value)) {
                        $setClauses[] = "$key = ?";
                        $params[] = $value;
                    }
                }
                
                $params[] = $gameId;
                Database::update(
                    "UPDATE games SET " . implode(', ', $setClauses) . ", updated_at = NOW() WHERE id = ?",
                    $params
                );
                flash('success', 'Game updated successfully!');
            }
            break;
            
        case 'delete':
            $gameId = (int)$_POST['game_id'];
            Database::update("DELETE FROM games WHERE id = ?", [$gameId]);
            flash('success', 'Game deleted successfully!');
            break;
            
        case 'toggle_status':
            $gameId = (int)$_POST['game_id'];
            $newStatus = $_POST['new_status'];
            Database::update("UPDATE games SET status = ? WHERE id = ?", [$newStatus, $gameId]);
            flash('success', 'Game status updated!');
            break;
    }
    
    redirect(baseUrl('/admin/games.php'));
}

// Get games
$games = Database::fetchAll(
    "SELECT g.*, 
            (SELECT COUNT(*) FROM scores WHERE game_id = g.id) as total_plays,
            (SELECT SUM(points_earned) FROM scores WHERE game_id = g.id) as total_points_awarded
     FROM games g 
     ORDER BY g.sort_order ASC, g.created_at DESC"
);

$categories = ['action', 'puzzle', 'word', 'memory', 'quiz', 'arcade'];
$difficulties = ['easy', 'medium', 'hard'];
$statuses = ['active', 'inactive', 'maintenance'];
?>

<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="font-game text-3xl text-gray-800">🎮 Manage Games</h1>
                <p class="text-gray-600"><?= count($games) ?> games</p>
            </div>
            <div class="flex gap-3">
                <a href="<?= baseUrl('/admin/') ?>" class="text-blue-500 hover:underline">← Back to Dashboard</a>
                <button onclick="showGameModal()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                    + Add Game
                </button>
            </div>
        </div>
        
        <!-- Games Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($games as $game): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <!-- Thumbnail -->
                    <div class="relative h-40 bg-gray-200">
                        <img src="<?= asset('images/games/' . $game['thumbnail']) ?>" 
                             alt="<?= e($game['title']) ?>"
                             class="w-full h-full object-cover"
                             onerror="this.src='<?= asset('images/games/default-game.png') ?>'">
                        
                        <!-- Status Badge -->
                        <span class="absolute top-2 right-2 px-2 py-1 rounded-full text-xs font-bold <?= statusColor($game['status']) ?>">
                            <?= ucfirst($game['status']) ?>
                        </span>
                        
                        <?php if ($game['featured']): ?>
                            <span class="absolute top-2 left-2 bg-yellow-400 text-gray-900 px-2 py-1 rounded-full text-xs font-bold">
                                ⭐ Featured
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Info -->
                    <div class="p-4">
                        <h3 class="font-bold text-lg text-gray-800"><?= e($game['title']) ?></h3>
                        <p class="text-sm text-gray-500 mb-3">
                            <?= ucfirst($game['category']) ?> • <?= ucfirst($game['difficulty']) ?>
                        </p>
                        
                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-2 text-center text-sm mb-4">
                            <div class="bg-gray-50 rounded-lg p-2">
                                <p class="font-bold text-blue-600"><?= formatNumber($game['total_plays'] ?? 0) ?></p>
                                <p class="text-xs text-gray-500">Plays</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-2">
                                <p class="font-bold text-green-600"><?= $game['points_reward'] ?></p>
                                <p class="text-xs text-gray-500">Points</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-2">
                                <p class="font-bold text-orange-600"><?= formatNumber($game['total_points_awarded'] ?? 0) ?></p>
                                <p class="text-xs text-gray-500">Awarded</p>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex gap-2">
                            <button onclick="showGameModal(<?= htmlspecialchars(json_encode($game)) ?>)" 
                                    class="flex-1 bg-blue-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-600">
                                Edit
                            </button>
                            <a href="<?= baseUrl('/games/play.php?game=' . $game['slug']) ?>" 
                               target="_blank"
                               class="px-3 py-2 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">
                                Preview
                            </a>
                            <form method="POST" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $game['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <button type="submit" class="px-3 py-2 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">
                                    <?= $game['status'] === 'active' ? '⏸️' : '▶️' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Game Modal -->
<div id="gameModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center overflow-y-auto">
    <div class="bg-white rounded-2xl p-6 w-full max-w-2xl mx-4 my-8">
        <h3 id="modalTitle" class="text-xl font-bold mb-4">Add New Game</h3>
        
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="game_id" id="gameId">
            
            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Title *</label>
                    <input type="text" name="title" id="gameTitle" required
                           class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Category</label>
                    <select name="category" id="gameCategory" class="w-full px-4 py-2 border rounded-lg">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-1">Description</label>
                <textarea name="description" id="gameDescription" rows="2"
                          class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none"></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-1">Instructions</label>
                <textarea name="instructions" id="gameInstructions" rows="2"
                          class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none"></textarea>
            </div>
            
            <div class="grid md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Difficulty</label>
                    <select name="difficulty" id="gameDifficulty" class="w-full px-4 py-2 border rounded-lg">
                        <?php foreach ($difficulties as $diff): ?>
                            <option value="<?= $diff ?>"><?= ucfirst($diff) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Points Reward</label>
                    <input type="number" name="points_reward" id="gamePoints" min="1" max="1000" value="10"
                           class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Status</label>
                    <select name="status" id="gameStatus" class="w-full px-4 py-2 border rounded-lg">
                        <?php foreach ($statuses as $stat): ?>
                            <option value="<?= $stat ?>"><?= ucfirst($stat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Min Score for Points</label>
                    <input type="number" name="min_score_for_points" id="gameMinScore" min="0" value="0"
                           class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Max Plays Per Day</label>
                    <input type="number" name="max_plays_per_day" id="gameMaxPlays" min="1" value="10"
                           class="w-full px-4 py-2 border rounded-lg">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-1">Thumbnail</label>
                <input type="file" name="thumbnail" accept="image/*"
                       class="w-full px-4 py-2 border rounded-lg">
            </div>
            
            <div class="mb-6">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="featured" id="gameFeatured" class="w-5 h-5">
                    <span>Featured Game</span>
                </label>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closeGameModal()"
                        class="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    Save Game
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showGameModal(game = null) {
    const modal = document.getElementById('gameModal');
    const title = document.getElementById('modalTitle');
    const action = document.getElementById('formAction');
    
    if (game) {
        title.textContent = 'Edit Game';
        action.value = 'update';
        document.getElementById('gameId').value = game.id;
        document.getElementById('gameTitle').value = game.title;
        document.getElementById('gameCategory').value = game.category;
        document.getElementById('gameDescription').value = game.description || '';
        document.getElementById('gameInstructions').value = game.instructions || '';
        document.getElementById('gameDifficulty').value = game.difficulty;
        document.getElementById('gamePoints').value = game.points_reward;
        document.getElementById('gameStatus').value = game.status;
        document.getElementById('gameMinScore').value = game.min_score_for_points;
        document.getElementById('gameMaxPlays').value = game.max_plays_per_day;
        document.getElementById('gameFeatured').checked = game.featured == 1;
    } else {
        title.textContent = 'Add New Game';
        action.value = 'create';
        document.getElementById('gameId').value = '';
        document.querySelector('#gameModal form').reset();
    }
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeGameModal() {
    document.getElementById('gameModal').classList.add('hidden');
    document.getElementById('gameModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>