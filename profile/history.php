<?php
/**
 * Play History Page
 * User can view their past played games and scores
 */
$pageTitle = 'Play History';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/middleware.php';
requireAuth();

$userId = Auth::id();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$total = Database::fetch(
    "SELECT COUNT(*) AS c FROM scores WHERE user_id = ?",
    [$userId]
)['c'];

$history = Database::fetchAll(
    "SELECT s.*, g.title as game_title, g.slug as game_slug
     FROM scores s
     JOIN games g ON s.game_id = g.id
     WHERE s.user_id = ?
     ORDER BY s.created_at DESC
     LIMIT ? OFFSET ?",
    [$userId, $perPage, $offset]
);

?>
<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-3xl mx-auto">
        <h1 class="font-game text-4xl mb-6 gradient-text">📜 Play History</h1>
        <?php if (!$history): ?>
            <div class="bg-yellow-50 text-yellow-800 p-5 rounded-xl text-center">
                <span class="text-4xl">🕹️</span>
                <h3 class="mt-2 font-bold">No games played yet</h3>
                <p>Try a game to start building your play history!</p>
            </div>
        <?php else: ?>
            <table class="w-full bg-white rounded-xl shadow-xl mt-2 mb-8">
                <thead>
                    <tr class="bg-gray-50 font-bold text-gray-700">
                        <th class="px-4 py-3 text-left">Game</th>
                        <th class="px-4 py-3 text-center">Score</th>
                        <th class="px-4 py-3 text-center">Points</th>
                        <th class="px-4 py-3 text-center">Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $row): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><?= e($row['game_title']) ?></td>
                        <td class="px-4 py-3 text-center"><?= number_format($row['score']) ?></td>
                        <td class="px-4 py-3 text-center text-green-600 font-bold"><?= number_format($row['points_earned']) ?></td>
                        <td class="px-4 py-3 text-center text-gray-500"><?= date('M d, Y g:ia', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($total > $perPage): ?>
                <div class="my-6 flex items-center justify-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">← Prev</a>
                <?php endif; ?>
                <span class="px-2 text-gray-600">Page <?= $page ?> of <?= ceil($total / $perPage) ?></span>
                <?php if ($offset+$perPage < $total): ?>
                    <a href="?page=<?= $page+1 ?>" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">Next →</a>
                <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>