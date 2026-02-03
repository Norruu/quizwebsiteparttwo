<?php
/**
 * Reward Card Template
 * Expected variables:
 *  - $reward (array with keys id, name, description, image, points_cost, quantity, category, etc.)
 *  - $userBalance (int, optional)
 */
$canAfford = isset($userBalance) ? ($userBalance >= $reward['points_cost']) : true;
$inStock = (!isset($reward['quantity']) || $reward['quantity'] === null || $reward['quantity'] > 0);
?>

<div class="bg-white rounded-xl shadow-xl p-5 flex flex-col items-center">
    <img src="<?= asset('images/rewards/' . ($reward['image'] ?? 'default-reward.png')) ?>"
        alt="<?= e($reward['name']) ?>" class="w-20 h-20 object-contain rounded mb-3">
    <h3 class="font-bold mb-1 text-center"><?= e($reward['name']) ?></h3>
    <div class="mb-1 text-xs px-2 py-1 rounded bg-gray-50 text-gray-600"><?= ucfirst($reward['category']) ?></div>
    <div class="font-bold text-xl mb-2 text-friv-purple"><?= number_format($reward['points_cost']) ?> pts</div>
    <div class="text-sm text-center mb-2"><?= e($reward['description']) ?></div>
    <form action="<?= baseUrl('/rewards/redeem.php') ?>" method="GET">
        <input type="hidden" name="id" value="<?= $reward['id'] ?>">
        <button type="submit"
            <?= (!$canAfford || !$inStock) ? 'disabled style="opacity:0.6;cursor:not-allowed;"' : '' ?>
            class="mt-2 px-6 py-2 rounded-xl bg-friv-blue text-white font-bold hover:bg-blue-700 transition-colors">
            <?= !$canAfford ? "Not enough points" : (!$inStock ? "Out of stock" : "Redeem") ?>
        </button>
    </form>
</div>