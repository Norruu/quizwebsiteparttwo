<?php
/**
 * Reward Gallery (Main Page)
 * Browse and redeem available rewards for points
 */
$pageTitle = 'Rewards';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/middleware.php';
require_once __DIR__ . '/../includes/wallet.php';
requireAuth();

$categories = Database::fetchAll("SELECT category, COUNT(*) as count FROM rewards GROUP BY category ORDER BY count DESC");

$selectedCategory = $_GET['category'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$where = ["status='active'", "(valid_from IS NULL OR valid_from <= NOW())","(valid_until IS NULL OR valid_until >= NOW())"];
$params = [];
if ($selectedCategory) {
    $where[] = 'category=?';
    $params[] = $selectedCategory;
}
$whereClause = implode(' AND ', $where);

$total = Database::fetch("SELECT COUNT(*) as c FROM rewards WHERE $whereClause", $params)['c'];
$offset = ($page-1)*$perPage;

$params[] = $perPage;
$params[] = $offset;
$rewards = Database::fetchAll(
    "SELECT * FROM rewards WHERE $whereClause ORDER BY points_cost ASC LIMIT ? OFFSET ?",
    $params
);

$userId = Auth::id();
$userBalance = Wallet::getBalance($userId);

?>
<div class="min-h-screen py-8 px-4 bg-gray-100">
<div class="max-w-5xl mx-auto">
    <div class="mb-8">
        <h1 class="font-game text-4xl gradient-text">🎁 Browse Rewards</h1>
        <div class="text-gray-700 text-lg">Exchange your points for awesome rewards!</div>
    </div>
    <div class="mb-6 flex flex-wrap gap-2">
        <a href="<?= baseUrl('/rewards/') ?>" class="px-4 py-2 <?= !$selectedCategory ? 'bg-friv-blue text-white' : 'bg-white text-gray-700' ?> rounded transition">
            All (<?= array_sum(array_column($categories,'count')) ?>)
        </a>
        <?php foreach ($categories as $cat): ?>
            <a href="?category=<?= urlencode($cat['category']) ?>"
                class="px-4 py-2 <?= $selectedCategory===$cat['category']?'bg-friv-blue text-white':'bg-white text-gray-700' ?> rounded transition">
                <?= ucfirst($cat['category']) ?> (<?= $cat['count'] ?>)
            </a>
        <?php endforeach; ?>
    </div>
    <div class="mb-6 text-right font-bold text-friv-blue">Your Balance: <?= number_format($userBalance) ?> pts</div>
    <?php if (!$rewards): ?>
        <div class="bg-yellow-50 text-yellow-800 p-6 rounded-xl text-center">
            <span class="text-4xl">🎲</span>
            <h3 class="mt-2 font-bold">No rewards available</h3>
            <p>More rewards coming soon!</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-7">
        <?php foreach ($rewards as $r): ?>
            <div class="bg-white rounded-xl shadow-xl p-5 flex flex-col items-center">
                <img src="<?= asset('images/rewards/' . ($r['image'] ?? 'default-reward.png')) ?>" alt="<?= e($r['name']) ?>"
                    class="w-20 h-20 object-contain rounded mb-4">
                <h3 class="font-bold mb-1 text-center"><?= e($r['name']) ?></h3>
                <div class="mb-1 text-xs px-2 py-1 rounded bg-gray-50 text-gray-600"><?= ucfirst($r['category']) ?></div>
                <div class="font-bold text-xl mb-2 text-friv-purple"><?= number_format($r['points_cost']) ?> pts</div>
                <div class="text-sm text-center mb-3"><?= e($r['description']) ?></div>
                <?php
                    $inStock = ($r['quantity'] === null) || ($r['quantity'] > 0);
                    $canAfford = $userBalance >= $r['points_cost'];
                ?>
                <form action="<?= baseUrl('/rewards/redeem.php') ?>" method="GET">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button type="submit" <?= (!$canAfford || !$inStock) ? 'disabled style="opacity:0.6;cursor:not-allowed;"' : '' ?>
                        class="mt-2 px-6 py-2 rounded-xl bg-friv-blue text-white font-bold hover:bg-blue-700 transition-colors">
                        <?= !$canAfford ? "Not enough points" : (!$inStock ? "Out of stock" : "Redeem") ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
        </div>
        <?php if ($total > $perPage): ?>
            <div class="my-7 flex items-center justify-center gap-2">
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
    <div class="mt-9 text-right">
        <a href="<?= baseUrl('/rewards/history.php') ?>" class="text-blue-500 hover:underline">View your redemption history →</a>
    </div>
</div>
</div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>