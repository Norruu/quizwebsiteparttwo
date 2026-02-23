<?php
/**
 * Game Player Wrapper
 * Loads and displays individual games with score tracking
 */

$pageTitle = 'Play Game';
require_once __DIR__ . '/../includes/header.php';

// Require login to play
Auth::requireLogin();

$userId = Auth::id();
$gameSlug = $_GET['game'] ?? '';

// Get game details
$game = Database::fetch(
    "SELECT * FROM games WHERE slug = ? AND status = 'active'",
    [$gameSlug]
);

if (!$game) {
    flash('error', 'Game not found or unavailable.');
    redirect(baseUrl('/games/'));
}

// Check daily play limit
$playLimit = checkDailyPlayLimit($userId, $game['id'], $game['max_plays_per_day']);

// Generate game session token for score validation
$gameSessionToken = generateGameSession($userId, $game['id']);

// Get user's high score for this game
$highScore = Database::fetch(
    "SELECT MAX(score) as high_score FROM scores WHERE user_id = ? AND game_id = ?",
    [$userId, $game['id']]
)['high_score'] ?? 0;

// Increment play count
Database::update(
    "UPDATE games SET play_count = play_count + 1 WHERE id = ?",
    [$game['id']]
);

$pageTitle = $game['title'];
?>

<div class="min-h-screen py-8 px-4">
    <div class="max-w-6xl mx-auto">
        <a href="<?= baseUrl('/games/') ?>" 
           class="inline-flex items-center gap-2 text-gray-600 hover:text-friv-blue mb-6 transition-colors">
            <span>←</span>
            <span>Back to Games</span>
        </a>
        
        <div class="grid lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-3xl shadow-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-friv-blue to-friv-purple p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl">🎮</span>
                            <div>
                                <h1 class="font-game text-2xl text-white"><?= e($game['title']) ?></h1>
                                <span class="text-white/80 text-sm"><?= ucfirst($game['category']) ?></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <button id="sound-toggle" class="text-white/80 hover:text-white transition-colors" title="Toggle Sound">
                                <span id="sound-on">🔊</span>
                                <span id="sound-off" class="hidden">🔇</span>
                            </button>
                            <button id="fullscreen-toggle" class="text-white/80 hover:text-white transition-colors" title="Fullscreen">
                                ⛶
                            </button>
                        </div>
                    </div>
                    
                    <div id="game-container" class="relative bg-gray-900 aspect-video flex items-center justify-center">
                        <?php if (!$playLimit['allowed']): ?>
                            <div class="text-center p-8">
                                <div class="text-6xl mb-4">⏰</div>
                                <h2 class="text-2xl font-bold text-white mb-2">Daily Limit Reached</h2>
                                <p class="text-gray-400 mb-4">
                                    You've played this game <?= $game['max_plays_per_day'] ?> times today.
                                    <br>Come back tomorrow for more!
                                </p>
                                <a href="<?= baseUrl('/games/') ?>" 
                                   class="inline-block bg-friv-blue text-white font-bold px-6 py-3 rounded-xl hover:bg-blue-600 transition-colors">
                                    Play Other Games
                                </a>
                            </div>
                        <?php else: ?>
                            <div id="game-loading" class="text-center">
                                <div class="animate-spin w-16 h-16 border-4 border-friv-blue border-t-transparent rounded-full mx-auto mb-4"></div>
                                <p class="text-white">Loading game...</p>
                            </div>
                            
                            <div id="game-frame" class="w-full h-full hidden">
                                </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-gray-100 p-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Score</p>
                                <p id="current-score" class="font-bold text-xl text-friv-blue">0</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">High Score</p>
                                <p class="font-bold text-xl text-friv-purple"><?= formatNumber($highScore) ?></p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Plays Left</p>
                                <p class="font-bold text-xl text-friv-green"><?= $playLimit['plays_remaining'] ?></p>
                            </div>
                        </div>
                        <div>
                            <button id="restart-btn" class="bg-friv-orange text-white font-bold px-6 py-2 rounded-xl hover:bg-orange-600 transition-colors">
                                🔄 Restart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="space-y-6">
                <div class="bg-white rounded-2xl shadow-xl p-6">
                    <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                        <span>ℹ️</span> Game Info
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Difficulty</span>
                            <span class="<?= difficultyColor($game['difficulty']) ?> text-white px-3 py-1 rounded-full text-sm font-bold">
                                <?= ucfirst($game['difficulty']) ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Points Reward</span>
                            <span class="text-friv-orange font-bold flex items-center gap-1">
                                <span>💰</span> Up to <?= $game['points_reward'] ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Min Score for Points</span>
                            <span class="font-bold"><?= $game['min_score_for_points'] ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Total Plays</span>
                            <span class="font-bold"><?= formatNumber($game['play_count']) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-2xl shadow-xl p-6">
                    <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                        <span>📖</span> How to Play
                    </h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        <?= nl2br(e($game['instructions'])) ?>
                    </p>
                </div>
                
                <div class="bg-gradient-to-br from-friv-yellow/20 to-friv-orange/20 rounded-2xl p-6">
                    <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                        <span>💡</span> Earn Points
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-start gap-2">
                            <span>✓</span>
                            <span>Score at least <?= $game['min_score_for_points'] ?> to earn points</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span>✓</span>
                            <span>Higher scores = more points!</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span>✓</span>
                            <span>Play up to <?= $game['max_plays_per_day'] ?> times per day</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const GAME_CONFIG = {
        gameId: <?= $game['id'] ?>,
        gameSlug: '<?= e($game['slug']) ?>',
        sessionToken: '<?= $gameSessionToken ?>',
        userId: <?= $userId ?>,
        pointsReward: <?= $game['points_reward'] ?>,
        minScore: <?= $game['min_score_for_points'] ?>,
        difficulty: '<?= $game['difficulty'] ?>',
        csrfToken: '<?= csrfToken() ?>',
        apiUrl: '<?= baseUrl('/api/scores.php') ?>'
    };
    
    // Load the specific game
    document.addEventListener('DOMContentLoaded', function() {
        loadGame('<?= e($game['slug']) ?>');
        initSoundControl();
    });
    
    function initSoundControl() {
        const soundToggle = document.getElementById('sound-toggle');
        const soundOn = document.getElementById('sound-on');
        const soundOff = document.getElementById('sound-off');
        
        // Load initial state
        let muted = localStorage.getItem('game_muted') === 'true';
        updateUI(muted);
        
        // Sync with Utils
        if (typeof setGameSoundMute === 'function') {
            setGameSoundMute(muted);
        }
        
        soundToggle.addEventListener('click', function() {
            muted = !muted;
            updateUI(muted);
            
            // Sync with Utils
            if (typeof setGameSoundMute === 'function') {
                setGameSoundMute(muted);
            }
        });
        
        function updateUI(isMuted) {
            if (isMuted) {
                soundOn.classList.add('hidden');
                soundOff.classList.remove('hidden');
            } else {
                soundOn.classList.remove('hidden');
                soundOff.classList.add('hidden');
            }
        }
    }
    
    function loadGame(slug) {
        const container = document.getElementById('game-frame');
        const loading = document.getElementById('game-loading');
        
        // Dynamically load game script
        const script = document.createElement('script');
        script.src = '../assets/js/games/' + slug + '.js';
        script.onload = function() {
            loading.classList.add('hidden');
            container.classList.remove('hidden');
            
            // Initialize the game (each game defines its own init function)
            if (typeof initGame === 'function') {
                initGame(container, GAME_CONFIG);
            }
        };
        script.onerror = function() {
            loading.innerHTML = '<p class="text-red-500">Failed to load game. Please refresh the page.</p>';
        };
        document.body.appendChild(script);
    }
    
    // Submit score function (called by games)
    async function submitScore(score, gameData = {}) {
        try {
            const response = await fetch(GAME_CONFIG.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': GAME_CONFIG.csrfToken
                },
                body: JSON.stringify({
                    action: 'submit',
                    game_id: GAME_CONFIG.gameId,
                    session_token: GAME_CONFIG.sessionToken,
                    score: score,
                    game_data: gameData
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Update UI
                document.getElementById('current-score').textContent = score;
                
                // Show points earned
                if (result.data.points_earned > 0) {
                    showPointsNotification(result.data.points_earned);
                    
                    // Update navbar balance
                    const balanceEl = document.getElementById('navbar-balance');
                    if (balanceEl) {
                        balanceEl.textContent = result.data.new_balance;
                    }
                }
                
                return result;
            } else {
                console.error('Score submission failed:', result.message);
                return result;
            }
        } catch (error) {
            console.error('Score submission error:', error);
            return { success: false, message: 'Network error' };
        }
    }
    
    // Show points notification
    function showPointsNotification(points) {
        const notification = document.createElement('div');
        notification.className = 'fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-gradient-to-r from-friv-yellow to-friv-orange text-white px-8 py-6 rounded-2xl shadow-2xl z-50 animate-bounce text-center';
        notification.innerHTML = `
            <div class="text-4xl mb-2">🎉</div>
            <div class="font-game text-3xl">+${points} Points!</div>
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translate(-50%, -50%) scale(0.5)';
            notification.style.transition = 'all 0.5s ease';
            setTimeout(() => notification.remove(), 500);
        }, 2000);
    }
    
    // Restart button
    document.getElementById('restart-btn').addEventListener('click', function() {
        if (typeof restartGame === 'function') {
            restartGame();
        } else {
            location.reload();
        }
    });
    
    // Fullscreen toggle
    document.getElementById('fullscreen-toggle').addEventListener('click', function() {
        const container = document.getElementById('game-container');
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            container.requestFullscreen();
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>