<?php
/**
 * Redeem Reward Confirmation Page
 * Lets user confirm redeeming a reward if eligible
 */
$pageTitle = 'Redeem Reward';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/middleware.php';
require_once __DIR__ . '/../includes/wallet.php';
requireAuth();

$userId = Auth::id();
$rewardId = (int)($_GET['id'] ?? 0);

$reward = Database::fetch(
    "SELECT * FROM rewards WHERE id=? AND status='active' AND (valid_from IS NULL OR valid_from <= NOW()) AND (valid_until IS NULL OR valid_until >= NOW())",
    [$rewardId]
);

$userBalance = Wallet::getBalance($userId);

$error = '';
if (!$reward) $error = 'Reward not found or unavailable.';
elseif ($userBalance < $reward['points_cost']) $error = 'Not enough points to redeem this reward.';
elseif ($reward['quantity'] !== null && $reward['quantity'] < 1) $error = 'Sorry, this reward is out of stock.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    if (!$error) {
        $notes = trim($_POST['user_notes'] ?? '');
        // Do API redemption logic here or direct DB queries:
        $deduct = Wallet::deductPoints($userId, $reward['points_cost'], "Redeemed: {$reward['name']}", 'redemption');
        Database::insert(
            "INSERT INTO redemptions (user_id, reward_id, points_spent, status, user_notes, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())",
            [$userId, $reward['id'], $reward['points_cost'],
                $reward['requires_approval'] ? 'pending' : 'approved',
                $notes]
        );
        if ($reward['quantity'] !== null) {
            Database::update(
                "UPDATE rewards SET quantity=quantity-1 WHERE id=? AND quantity>0",
                [$reward['id']]
            );
        }
        logActivity('redemption_created', "User #{$userId} redeemed reward #{$reward['id']}", $userId);
        redirect(baseUrl('/rewards/history.php?success=1'));
    }
}
?>
<div class="min-h-screen py-12 px-4 bg-gray-100">
<div class="max-w-md mx-auto bg-white rounded-xl shadow-xl p-8">
    <h1 class="font-game text-2xl gradient-text mb-4">🎁 Redeem Reward</h1>

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 p-3 mb-5 rounded-lg"><?= $error ?></div>
        <a href="<?= baseUrl('/rewards/') ?>" class="bg-friv-blue text-white px-4 py-2 rounded hover:bg-blue-700 transition">Back to Rewards</a>
    <?php else: ?>
        <div class="flex gap-5 items-center mb-5">
            <img src="<?= asset('images/rewards/' . ($reward['image'] ?? 'default-reward.png')) ?>" alt="<?= e($reward['name']) ?>"
                class="w-16 h-16 object-contain rounded">
            <div>
              <h2 class="font-bold text-lg mb-1"><?= e($reward['name']) ?></h2>
              <div class="text-sm text-gray-700"><?= e($reward['description']) ?></div>
            </div>
        </div>
        <div class="mb-3 font-semibold text-friv-purple">Cost: <?= number_format($reward['points_cost']) ?> pts</div>
        <div class="mb-4 text-sm">Your Balance: <span class="font-bold text-friv-blue"><?= number_format($userBalance) ?></span> pts</div>
        <form method="POST" class="space-y-5">
            <?= csrfField() ?>
            <div>
                <label class="block font-semibold text-gray-700 mb-1">Note (optional)</label>
                <input type="text" name="user_notes" maxlength="120"
                    class="w-full px-3 py-2 border rounded-lg focus:border-blue-500 outline-none"
                    placeholder="Any notes / message for admin?">
            </div>
            <button type="submit"
                class="w-full bg-friv-blue text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors">
                Confirm Redemption
            </button>
            <a href="<?= baseUrl('/rewards/') ?>" class="w-full block text-center mt-3 text-blue-500 hover:underline">← Cancel</a>
        </form>
    <?php endif; ?>
</div>
</div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>