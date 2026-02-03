<?php
/**
 * Reward Redemption History
 * Shows user's own completed/pending redemptions
 */
$pageTitle = 'Rewards - My Redemption History';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/middleware.php';
requireAuth();

$userId = Auth::id();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page-1)*$perPage;

$total = Database::fetch(
    "SELECT COUNT(*) as c FROM redemptions WHERE user_id=?",
    [$userId]
)['c'];

$redemptions = Database::fetchAll(
    "SELECT r.*, rw.name as reward_name, rw.image as reward_image, rw.points_cost
     FROM redemptions r
     JOIN rewards rw ON r.reward_id = rw.id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC
     LIMIT ? OFFSET ?",
    [$userId, $perPage, $offset]
);

$statusColor = [
    'pending'   => 'bg-yellow-50 text-yellow-800',
    'approved'  => 'bg-blue-50 text-blue-800',
    'fulfilled' => 'bg-green-50 text-green-800',
    'rejected'  => 'bg-red-50 text-red-800',
    'cancelled' => 'bg-gray-50 text-gray-800',
];
?>
<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-3xl mx-auto">
        <h1 class="font-game text-3xl gradient-text mb-6">🎁 My Reward History</h1>
        <?php if (!$redemptions): ?>
            <div class="bg-yellow-50 text-yellow-800 rounded-xl p-6 text-center">
                <span class="text-4xl">🎲</span>
                <h3 class="mt-2 font-bold">No redemptions yet</h3>
                <p>Redeem your points for cool rewards!</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
            <?php foreach($redemptions as $r): ?>
                <div class="p-4 rounded-xl shadow bg-white flex gap-4 items-center">
                    <img src="<?= asset('images/rewards/' . ($r['reward_image'] ?? 'default-reward.png')) ?>"
                        alt="<?= e($r['reward_name']) ?>" class="w-14 h-14 object-contain rounded-lg">
                    <div class="flex-1">
                        <div class="font-semibold"><?= e($r['reward_name']) ?>
                            <span class="px-2 py-0.5 rounded text-xs <?= $statusColor[$r['status']] ?? 'bg-gray-100' ?>">
                                <?= ucfirst($r['status']) ?>
                            </span>
                        </div>
                        <div class="text-gray-600 text-sm"><?= number_format($r['points_spent']) ?> points spent</div>
                        <div class="text-xs text-gray-400">
                            <?= date('M d, Y g:ia', strtotime($r['created_at'])) ?>
                        </div>
                        <?php if ($r['admin_notes']): ?>
                            <div class="text-xs text-gray-500 mt-2"><strong>Admin:</strong> <?= nl2br(e($r['admin_notes'])) ?></div>
                        <?php endif; ?>
                        <?php if ($r['user_notes']): ?>
                            <div class="text-xs text-blue-600 mt-1"><?= nl2br(e($r['user_notes'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php if ($total > $perPage): ?>
                <div class="my-6 flex items-center justify-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">← Prev</a>
                    <?php endif; ?>
                    <span class="px-2 text-gray-600">Page <?= $page ?> of <?= ceil($total / $perPage) ?></span>
                    <?php if ($offset + $perPage < $total): ?>
                        <a href="?page=<?= $page+1 ?>" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">Next →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
           <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>