<?php
/**
 * User Profile Page (Dashboard)
 * Shows summary, quick links, recent plays.
 */
$pageTitle = 'My Profile';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/middleware.php';
requireAuth();

$user = Auth::user();
$recentPlays = Database::fetchAll(
    "SELECT s.*, g.title as game_title FROM scores s JOIN games g ON s.game_id=g.id WHERE s.user_id=? ORDER BY s.created_at DESC LIMIT 5",
    [$user['id']]
);

$achievements = Database::fetchAll(
    "SELECT a.name, a.badge_image FROM user_achievements ua JOIN achievements a ON ua.achievement_id=a.id WHERE ua.user_id=? ORDER BY ua.earned_at DESC LIMIT 4",
    [$user['id']]
);
?>

<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-3xl mx-auto">
        <div class="flex flex-col md:flex-row gap-8 items-start mb-8">
            <div>
                <img src="<?= asset('images/avatars/' . ($user['avatar'] ?? 'default-avatar.png')) ?>" alt="Avatar"
                    class="w-24 h-24 rounded-full border-4 border-friv-blue shadow-xl mb-3">
                <h1 class="font-game text-3xl gradient-text mb-1"><?= e($user['username']) ?></h1>
                <p class="text-gray-500"><?= e($user['email']) ?></p>
            </div>
            <div class="grid grid-cols-2 gap-4 w-full">
                <a href="<?= baseUrl('/profile/edit.php') ?>" class="bg-friv-blue text-white rounded-xl shadow-xl p-4 text-center font-bold hover:bg-blue-600 transition-colors">Edit Profile</a>
                <a href="<?= baseUrl('/profile/wallet.php') ?>" class="bg-friv-green text-white rounded-xl shadow-xl p-4 text-center font-bold">View Wallet</a>
                <a href="<?= baseUrl('/profile/history.php') ?>" class="bg-friv-yellow text-gray-700 rounded-xl shadow-xl p-4 text-center font-bold">Play History</a>
                <a href="<?= baseUrl('/profile/achievements.php') ?>" class="bg-friv-purple text-white rounded-xl shadow-xl p-4 text-center font-bold">Achievements</a>
            </div>
        </div>
        <div class="mb-8">
            <h2 class="font-bold text-xl mb-3">Recent Plays</h2>
            <?php if (!$recentPlays): ?>
                <div class="text-gray-500 p-3">No games played yet.</div>
            <?php else: ?>
                <ul>
                <?php foreach ($recentPlays as $play): ?>
                    <li class="p-3 bg-white rounded-xl shadow mb-3 flex justify-between items-center">
                        <strong><?= e($play['game_title']) ?></strong>
                        <span class="text-gray-500 text-sm"><?= date('M d, g:ia', strtotime($play['created_at'])) ?></span>
                        <span class="text-friv-blue font-bold">Score: <?= $play['score'] ?></span>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php if ($achievements): ?>
            <div class="mb-8">
                <h2 class="font-bold text-xl mb-3">Recent Achievements</h2>
                <div class="flex gap-3">
                    <?php foreach ($achievements as $ach): ?>
                        <div class="w-12 h-12 flex items-center justify-center">
                            <img src="<?= asset('images/badges/' . ($ach['badge_image'] ?? 'default-badge.png')) ?>" alt="<?= e($ach['name']) ?>"
                                class="w-11 h-11 rounded-full" title="<?= e($ach['name']) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>