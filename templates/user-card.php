<?php
/**
 * User Card Template
 * Expected variables:
 *  - $user (array: id, username, avatar, role, etc.)
 *  - $showStats (bool, optional)
 *  - $showLink (bool, optional)
 */
?>
<div class="flex items-center gap-4 p-4 bg-white rounded-xl shadow hover:bg-gray-50 transition">
    <img src="<?= asset('images/avatars/' . ($user['avatar'] ?? 'default-avatar.png')) ?>"
        class="w-10 h-10 rounded-full object-cover" alt="<?= e($user['username']) ?>">
    <div class="flex-1 min-w-0">
        <div class="font-semibold text-gray-800"><?= e($user['username']) ?>
            <?php if (isset($user['role']) && $user['role']==='admin'): ?>
                <span class="px-2 py-0.5 ml-1 rounded bg-purple-100 text-purple-700 text-xs font-bold">Admin</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($user['email'])): ?>
            <div class="text-xs text-gray-500"><?= e($user['email']) ?></div>
        <?php endif; ?>
        <?php if (!empty($showStats) && isset($user['wallet_balance'])): ?>
            <div class="text-xs text-green-700 mt-1">Points: <?= number_format($user['wallet_balance']) ?></div>
        <?php endif; ?>
    </div>
    <?php if (!empty($showLink)): ?>
        <a href="<?= baseUrl('/profile/view.php?id='.$user['id']) ?>"
            class="px-3 py-1 bg-friv-blue text-white text-xs rounded-lg font-bold hover:bg-blue-600 transition">View</a>
    <?php endif; ?>
</div>