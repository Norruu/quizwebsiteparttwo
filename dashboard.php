<?php
/**
 * User Dashboard (Home after login)
 * Shows stats, quick links, and personalized details.
 */
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/middleware.php';
require_once __DIR__ . '/includes/wallet.php';
requireAuth();

$user = Auth::user();

$userBalance = Wallet::getBalance($user['id']);
$recentPlays = Database::fetchAll(
    "SELECT s.*, g.title as game_title FROM scores s JOIN games g ON s.game_id=g.id WHERE s.user_id=? ORDER BY s.created_at DESC LIMIT 4",
    [$user['id']]
);
$achievements = Database::fetchAll(
    "SELECT a.name, a.badge_image FROM user_achievements ua JOIN achievements a ON ua.achievement_id=a.id WHERE ua.user_id=? ORDER BY ua.earned_at DESC LIMIT 3",
    [$user['id']]
);
?>

<div class="min-h-screen py-10 px-4 bg-gray-100">
    <div class="max-w-4xl mx-auto">
        <h1 class="font-game text-3xl gradient-text mb-4">👋 Welcome, <?= e($user['username']) ?>!</h1>
        <div class="mb-8 text-gray-600">Let's get playing. Check your stats, earn rewards, and compete for the top spot!</div>

        <!-- Stats row -->
        <div class="grid grid-cols-3 gap-6 mb-8">
            <div class="p-5 bg-friv-blue text-white rounded-xl shadow-xl text-center">
                <div class="text-xl font-bold mb-1"><?= number_format($userBalance) ?></div>
                <div class="text-xs">Points Balance</div>
            </div>
            <a href="<?= baseUrl('/profile/history.php') ?>" class="p-5 bg-friv-yellow text-gray-800 rounded-xl shadow-xl text-center hover:bg-yellow-300 transition">
                <div class="text-xl font-bold mb-1"><?= Database::fetch("SELECT COUNT(*) as c FROM scores WHERE user_id=?",[$user['id']])['c'] ?></div>
                <div class="text-xs">Games Played</div>
            </a>
            <a href="<?= baseUrl('/profile/achievements.php') ?>" class="p-5 bg-friv-purple text-white rounded-xl shadow-xl text-center hover:bg-purple-700 transition">
                <div class="text-xl font-bold mb-1"><?= Database::fetch("SELECT COUNT(*) as c FROM user_achievements WHERE user_id=?",[$user['id']])['c'] ?></div>
                <div class="text-xs">Achievements</div>
            </a>
        </div>

        <!-- Quick Links -->
        <div class="grid md:grid-cols-4 gap-4 mb-10 text-center">
            <a href="<?= baseUrl('/games/') ?>" class="bg-friv-green text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-700 transition shadow">Play Games</a>
            <a href="<?= baseUrl('/rewards/') ?>" class="bg-friv-blue text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition shadow">Rewards</a>
            <a href="<?= baseUrl('/leaderboard/') ?>" class="bg-friv-yellow text-gray-800 px-4 py-2 rounded-lg font-semibold hover:bg-yellow-400 transition shadow">Leaderboard</a>
            <a href="<?= baseUrl('/profile/edit.php') ?>" class="bg-friv-pink text-white px-4 py-2 rounded-lg font-semibold hover:bg-pink-400 transition shadow">Edit Profile</a>
        </div>

        <!-- Recent Plays -->
        <div class="mb-10">
            <h2 class="font-bold mb-2 text-xl">Recent Games</h2>
            <?php if ($recentPlays): ?>
                <ul>
                <?php foreach($recentPlays as $play): ?>
                    <li class="py-2 flex justify-between border-b border-gray-100 text-gray-700">
                        <span><?= e($play['game_title']) ?></span>
                        <span class="font-bold text-friv-blue w-10 text-right "><?= number_format($play['score']) ?></span>
                        <span class="text-sm text-gray-400"><?= date('M d, g:ia', strtotime($play['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="text-gray-500">No recent games played.</div>
            <?php endif; ?>
        </div>

        <!-- Achievements Preview -->
        <?php if ($achievements): ?>
            <h2 class="font-bold mb-2 text-xl">Recent Achievements</h2>
            <div class="flex gap-4 mb-6">
                <?php foreach($achievements as $ach): ?>
                    <img src="<?= asset('images/badges/' . ($ach['badge_image'] ?? 'default-badge.png')) ?>"
                        class="w-14 h-14 rounded-full" title="<?= e($ach['name']) ?>" alt="<?= e($ach['name']) ?>">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>