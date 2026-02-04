<?php
/**
 * User Wallet Page
 * Display wallet balance and transaction history
 */

$pageTitle = 'My Wallet';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/wallet.php';

Auth::requireLogin();

$userId = Auth::id();
$user = Auth::user();

// Get wallet stats
$stats = Wallet::getStats($userId);

// Pagination for transactions
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = TRANSACTIONS_PER_PAGE;
$totalTransactions = Wallet::getTransactionCount($userId);
$pagination = paginate($totalTransactions, $perPage, $page);

// Get transactions
$transactions = Wallet::getTransactions($userId, $perPage, $pagination['offset']);
?>

<div class="min-h-screen py-8 px-4">
    <div class="max-w-4xl mx-auto">
        
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="font-game text-4xl gradient-text mb-2">💰 My Wallet</h1>
            <p class="text-gray-600">Track your points and transaction history</p>
        </div>
        
        <!-- Wallet Overview Cards -->
        <div class="grid md:grid-cols-3 gap-6 mb-8">
            
            <!-- Current Balance -->
            <div class="bg-gradient-to-br from-friv-yellow to-friv-orange rounded-2xl p-6 text-white shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-4xl">💰</span>
                    <span class="text-white/60 text-sm">Current Balance</span>
                </div>
                <p class="font-game text-4xl"><?= number_format($stats['balance']) ?></p>
                <p class="text-white/80 text-sm mt-1">points available</p>
            </div>
            
            <!-- Total Earned -->
            <div class="bg-gradient-to-br from-friv-green to-emerald-500 rounded-2xl p-6 text-white shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-4xl">📈</span>
                    <span class="text-white/60 text-sm">Total Earned</span>
                </div>
                <p class="font-game text-4xl"><?= number_format($stats['total_earned']) ?></p>
                <p class="text-white/80 text-sm mt-1">lifetime points</p>
            </div>
            
            <!-- Total Spent -->
            <div class="bg-gradient-to-br from-friv-purple to-pink-500 rounded-2xl p-6 text-white shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-4xl">🛒</span>
                    <span class="text-white/60 text-sm">Total Spent</span>
                </div>
                <p class="font-game text-4xl"><?= number_format($stats['total_spent']) ?></p>
                <p class="text-white/80 text-sm mt-1">on rewards</p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
            <h2 class="font-bold text-xl mb-4">Quick Actions</h2>
            <div class="flex flex-wrap gap-4">
                <a href="<?= baseUrl('/games/') ?>" class="bg-friv-blue text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-600 transition-colors flex items-center gap-2">
                    <span>🎮</span> Play Games
                </a>
                <a href="<?= baseUrl('/rewards/') ?>" class="bg-friv-purple text-white px-6 py-3 rounded-xl font-bold hover:bg-purple-600 transition-colors flex items-center gap-2">
                    <span>🎁</span> Redeem Rewards
                </a>
                <a href="<?= baseUrl('/leaderboard/') ?>" class="bg-friv-green text-white px-6 py-3 rounded-xl font-bold hover:bg-green-600 transition-colors flex items-center gap-2">
                    <span>🏆</span> View Leaderboard
                </a>
            </div>
        </div>
        
        <!-- Transaction History -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="font-bold text-xl">Transaction History</h2>
                <span class="text-gray-500 text-sm"><?= $totalTransactions ?> transactions</span>
            </div>
            
            <?php if (empty($transactions)): ?>
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">📭</div>
                    <h3 class="text-xl font-bold text-gray-700 mb-2">No transactions yet</h3>
                    <p class="text-gray-500">Play games to start earning points!</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($transactions as $tx): 
                        $isPositive = $tx['amount'] > 0;
                        $icon = match($tx['type']) {
                            'earn' => '🎮',
                            'spend' => '🛒',
                            'bonus' => '🎁',
                            'penalty' => '⚠️',
                            'refund' => '↩️',
                            default => '💫'
                        };
                        $amountClass = $isPositive ? 'text-green-500' : 'text-red-500';
                        $amountPrefix = $isPositive ? '+' : '';
                    ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center text-2xl shadow">
                                    <?= $icon ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?= e($tx['description']) ?></p>
                                    <p class="text-sm text-gray-500"><?= timeAgo($tx['created_at']) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-lg <?= $amountClass ?>">
                                    <?= $amountPrefix ?><?= number_format($tx['amount']) ?>
                                </p>
                                <p class="text-xs text-gray-400">
                                    Balance: <?= number_format($tx['balance_after']) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="flex items-center justify-center gap-2 mt-8">
                        <?php if ($pagination['has_prev']): ?>
                            <a href="?page=<?= $pagination['current_page'] - 1 ?>" 
                               class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                ← Previous
                            </a>
                        <?php endif; ?>
                        
                        <span class="px-4 py-2 text-gray-600">
                            Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
                        </span>
                        
                        <?php if ($pagination['has_next']): ?>
                            <a href="?page=<?= $pagination['current_page'] + 1 ?>" 
                               class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                Next →
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>