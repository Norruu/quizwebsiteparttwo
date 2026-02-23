<?php
/**
 * Game Library Home Page
 * Public landing page for all visitors.
 */
require_once __DIR__ . '/includes/header.php';

// Fetch featured and popular games for homepage carousel/previews
$featuredGames = Database::fetchAll(
    "SELECT * FROM games WHERE status='active' AND featured=1 ORDER BY sort_order ASC, created_at DESC LIMIT 4"
);
$popularGames = Database::fetchAll(
    "SELECT * FROM games WHERE status='active' ORDER BY play_count DESC LIMIT 8"
);
?>

<div class="min-h-screen bg-gray-100 py-10 px-3">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-10">
            <h1 class="font-game text-5xl gradient-text mb-3">🎮 Bountiful Harvest</h1>
            <div class="text-lg text-gray-500 mb-2">Play free games. Earn points. Win real rewards and compete on the leaderboard!</div>
            <?php if (!Auth::check()): ?>
                <a href="<?= baseUrl('/auth/register.php') ?>"
                    class="inline-block bg-friv-blue text-white font-bold px-8 py-3 mt-4 rounded-xl text-xl hover:bg-blue-600 shadow-xl transition">
                    Get Started - Join Free
                </a>
            <?php endif; ?>
        </div>

        <!-- Featured Games -->
        <?php if ($featuredGames): ?>
        <div class="mb-12">
            <div class="font-bold text-xl mb-4">🌟 Featured Games</div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($featuredGames as $game): ?>
                    <a href="<?= baseUrl('/games/play.php?game=' . urlencode($game['slug'])) ?>"
                        class="p-4 bg-white rounded-xl shadow-xl hover:shadow-2xl flex flex-col items-center hover:scale-105 transition transform">
                        <img src="<?= asset('images/games/' . ($game['thumbnail'] ?? 'default-game.png')) ?>"
                            alt="<?= e($game['title']) ?>" class="w-24 h-24 object-contain rounded mb-3">
                        <div class="font-bold text-lg mb-1 text-center"><?= e($game['title']) ?></div>
                        <div class="text-xs text-gray-500"><?= ucfirst($game['category']) ?> • <?= ucfirst($game['difficulty']) ?></div>
                        <div class="text-sm text-gray-600 mt-2 text-center"><?= e($game['description'] ?? '') ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Popular Games -->
        <div class="mb-12">
            <div class="font-bold text-xl mb-4">🔥 Most Popular</div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($popularGames as $game): ?>
                    <a href="<?= baseUrl('/games/play.php?game=' . urlencode($game['slug'])) ?>"
                        class="p-4 bg-white rounded-xl shadow flex flex-col items-center hover:scale-102 hover:shadow-2xl transition">
                        <img src="<?= asset('images/games/' . ($game['thumbnail'] ?? 'default-game.png')) ?>"
                            alt="<?= e($game['title']) ?>" class="w-16 h-16 object-contain rounded mb-2">
                        <div class="font-semibold"><?= e($game['title']) ?></div>
                        <div class="text-xs text-gray-500"><?= number_format($game['play_count']) ?> plays</div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- How it works / Benefits -->
        <div class="bg-white rounded-xl shadow-xl p-10 mt-12">
            <h2 class="font-game text-2xl mb-6 text-center">How it Works</h2>
            <div class="grid md:grid-cols-3 gap-8 text-center">
                <div>
                    <div class="text-4xl mb-2">🎮</div>
                    <h3 class="font-bold mb-2">Play Games</h3>
                    <div class="text-gray-600">Choose from a library of fun, original games. Play anytime on any device!</div>
                </div>
                <div>
                    <div class="text-4xl mb-2">💰</div>
                    <h3 class="font-bold mb-2">Earn Points</h3>
                    <div class="text-gray-600">Beat challenges and score high to earn points. Level up as you play more.</div>
                </div>
                <div>
                    <div class="text-4xl mb-2">🎁</div>
                    <h3 class="font-bold mb-2">Redeem Rewards</h3>
                    <div class="text-gray-600">Cash out points for cool digital and real-world rewards. The more you play, the more you win!</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>