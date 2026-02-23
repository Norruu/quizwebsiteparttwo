<?php
/**
 * Achievements Page
 * Shows user's unlocked achievements and badges
 */
$pageTitle = 'My Achievements';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/middleware.php';
requireAuth();

// Get current user info
$userId = Auth::id();

// Get achievements
$rows = Database::fetchAll(
    "SELECT ua.earned_at, a.name, a.description, a.badge_image, a.points_bonus, a.criteria_type, a.criteria_value, g.title as game_title
     FROM user_achievements ua
     JOIN achievements a ON ua.achievement_id = a.id
     LEFT JOIN games g ON a.criteria_game_id = g.id
     WHERE ua.user_id = ?
     ORDER BY ua.earned_at DESC",
    [$userId]
);

$totalAchieved = count($rows);
$totalBadges = Database::fetch("SELECT COUNT(*) as c FROM achievements WHERE status='active'")['c'];
?>
<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-3xl mx-auto">
        <h1 class="font-game text-4xl mb-6 gradient-text">🏅 My Achievements</h1>
        <div class="mb-6 text-gray-700 font-semibold">You've unlocked <?=$totalAchieved?> out of <?=$totalBadges?> badges!</div>
        <?php if (!$rows): ?>
            <div class="bg-yellow-50 text-yellow-800 p-5 rounded-xl text-center">
                <span class="text-4xl">🐾</span>
                <h3 class="mt-2 font-bold">No achievements yet</h3>
                <p>Play games, earn points, and unlock your first badge!</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($rows as $ach): ?>
                <div class="bg-white rounded-xl shadow-xl p-4 flex gap-4 items-center">
                    <div class="w-16 h-16 flex items-center justify-center">
                        <img src="<?= asset('images/badges/' . ($ach['badge_image'] ?? 'default-badge.png')) ?>"
                            alt="<?= e($ach['name']) ?>" class="w-14 h-14 rounded-full">
                    </div>
                    <div>
                        <div class="font-bold text-lg"><?= e($ach['name']) ?></div>
                        <div class="text-sm text-gray-600"><?= e($ach['description']) ?></div>
                        <?php if ($ach['game_title']): ?>
                            <div class="text-xs text-friv-blue mt-1">Game: <?= e($ach['game_title']) ?></div>
                        <?php endif; ?>
                        <div class="text-xs text-gray-400 mt-2">Unlocked: <?= date('M d, Y', strtotime($ach['earned_at'])) ?></div>
                        <?php if ($ach['points_bonus']): ?>
                            <span class="text-xs text-green-600 bg-green-50 px-2 py-0.5 rounded font-bold ml-2">+<?= $ach['points_bonus'] ?> pts</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>