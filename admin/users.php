<?php
/**
 * Admin User Management
 */

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/wallet.php';

Auth::requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($userId && $userId !== Auth::id()) {
        switch ($action) {
            case 'ban':
                Database::update("UPDATE users SET status = 'banned' WHERE id = ?", [$userId]);
                logActivity('user_banned', "Banned user #$userId", Auth::id());
                flash('success', 'User has been banned.');
                break;
                
            case 'activate':
                Database::update("UPDATE users SET status = 'active' WHERE id = ?", [$userId]);
                logActivity('user_activated', "Activated user #$userId", Auth::id());
                flash('success', 'User has been activated.');
                break;
                
            case 'make_admin':
                Database::update("UPDATE users SET role = 'admin' WHERE id = ?", [$userId]);
                logActivity('user_promoted', "Promoted user #$userId to admin", Auth::id());
                flash('success', 'User has been promoted to admin.');
                break;
                
            case 'make_player':
                Database::update("UPDATE users SET role = 'player' WHERE id = ?", [$userId]);
                logActivity('user_demoted', "Demoted user #$userId to player", Auth::id());
                flash('success', 'User has been demoted to player.');
                break;
                
            case 'reset_points':
                Wallet::adminAdjustment($userId, -Wallet::getBalance($userId), 'Admin reset points', Auth::id());
                flash('success', 'User points have been reset.');
                break;
                
            case 'add_points':
                $amount = (int)($_POST['amount'] ?? 0);
                $reason = $_POST['reason'] ?? 'Admin bonus';
                if ($amount > 0) {
                    Wallet::adminAdjustment($userId, $amount, $reason, Auth::id());
                    flash('success', "Added $amount points to user.");
                }
                break;
        }
    }
    
    redirect(baseUrl('/admin/users.php'));
}

// Pagination and filters
$page = max(1, (int)($_GET['page'] ?? 1));
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role) {
    $where[] = "role = ?";
    $params[] = $role;
}

if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

$total = Database::fetch(
    "SELECT COUNT(*) as count FROM users WHERE $whereClause",
    $params
)['count'];

$pagination = paginate($total, USERS_PER_PAGE, $page);

$users = Database::fetchAll(
    "SELECT u.*, w.balance as wallet_balance, w.total_earned,
            (SELECT COUNT(*) FROM scores WHERE user_id = u.id) as games_played
     FROM users u
     LEFT JOIN wallet w ON u.id = w.user_id
     WHERE $whereClause
     ORDER BY u.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$pagination['per_page'], $pagination['offset']])
);
?>

<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="font-game text-3xl text-gray-800">👥 Manage Users</h1>
                <p class="text-gray-600"><?= $total ?> total users</p>
            </div>
            <a href="<?= baseUrl('/admin/') ?>" class="text-blue-500 hover:underline">← Back to Dashboard</a>
        </div>
        
        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" value="<?= e($search) ?>" 
                           placeholder="Username or email..."
                           class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Role</label>
                    <select name="role" class="px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                        <option value="">All Roles</option>
                        <option value="player" <?= $role === 'player' ? 'selected' : '' ?>>Player</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                    <select name="status" class="px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                        <option value="">All Status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="banned" <?= $status === 'banned' ? 'selected' : '' ?>>Banned</option>
                        <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                    Filter
                </button>
                <?php if ($search || $role || $status): ?>
                    <a href="<?= baseUrl('/admin/users.php') ?>" class="text-gray-500 hover:text-gray-700 px-4 py-2">
                        Clear
                    </a>
                <?php endif; ?>
                            </form>
        </div>
        
        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">User</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Role</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Status</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Points</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Games</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Joined</th>
                        <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($users as $user): 
                        $isCurrentUser = $user['id'] === Auth::id();
                    ?>
                        <tr class="hover:bg-gray-50 <?= $isCurrentUser ? 'bg-blue-50' : '' ?>">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img src="<?= asset('images/avatars/' . ($user['avatar'] ?? 'default-avatar.png')) ?>" 
                                         alt="<?= e($user['username']) ?>" 
                                         class="w-10 h-10 rounded-full object-cover">
                                    <div>
                                        <p class="font-semibold text-gray-800">
                                            <?= e($user['username']) ?>
                                            <?= $isCurrentUser ? '<span class="text-blue-500 text-xs">(You)</span>' : '' ?>
                                        </p>
                                        <p class="text-sm text-gray-500"><?= e($user['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-sm font-semibold <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700' ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-sm font-semibold <?= statusColor($user['status']) ?>">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-bold text-friv-orange"><?= formatNumber($user['wallet_balance'] ?? 0) ?></p>
                                <p class="text-xs text-gray-500">Earned: <?= formatNumber($user['total_earned'] ?? 0) ?></p>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                <?= formatNumber($user['games_played']) ?>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-sm">
                                <?= date('M j, Y', strtotime($user['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if (!$isCurrentUser): ?>
                                    <div class="relative" x-data="{ open: false }">
                                        <button @click="open = !open" class="p-2 hover:bg-gray-100 rounded-lg">
                                            <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                            </svg>
                                        </button>
                                        
                                        <div x-show="open" @click.away="open = false" 
                                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border z-50">
                                            
                                            <!-- Add Points -->
                                            <button onclick="showAddPointsModal(<?= $user['id'] ?>, '<?= e($user['username']) ?>')"
                                                    class="w-full px-4 py-2 text-left text-sm hover:bg-gray-100 flex items-center gap-2">
                                                <span>💰</span> Add Points
                                            </button>
                                            
                                            <!-- Toggle Status -->
                                            <?php if ($user['status'] === 'active'): ?>
                                                <form method="POST" class="w-full">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="ban">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" onclick="return confirm('Ban this user?')"
                                                            class="w-full px-4 py-2 text-left text-sm hover:bg-red-50 text-red-600 flex items-center gap-2">
                                                        <span>🚫</span> Ban User
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="w-full">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit"
                                                            class="w-full px-4 py-2 text-left text-sm hover:bg-green-50 text-green-600 flex items-center gap-2">
                                                        <span>✓</span> Activate User
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Toggle Role -->
                                            <?php if ($user['role'] === 'player'): ?>
                                                <form method="POST" class="w-full">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="make_admin">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" onclick="return confirm('Make this user an admin?')"
                                                            class="w-full px-4 py-2 text-left text-sm hover:bg-purple-50 text-purple-600 flex items-center gap-2">
                                                        <span>⬆️</span> Make Admin
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="w-full">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="make_player">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" onclick="return confirm('Demote this admin to player?')"
                                                            class="w-full px-4 py-2 text-left text-sm hover:bg-gray-100 flex items-center gap-2">
                                                        <span>⬇️</span> Make Player
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Reset Points -->
                                            <form method="POST" class="w-full border-t">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="reset_points">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" onclick="return confirm('Reset all points for this user? This cannot be undone!')"
                                                        class="w-full px-4 py-2 text-left text-sm hover:bg-red-50 text-red-600 flex items-center gap-2">
                                                    <span>🔄</span> Reset Points
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($users)): ?>
                <div class="text-center py-12">
                    <p class="text-gray-500">No users found</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="flex items-center justify-center gap-2 mt-6">
                <?php if ($pagination['has_prev']): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])) ?>" 
                       class="px-4 py-2 bg-white rounded-lg shadow hover:shadow-md transition-shadow">
                        ← Previous
                    </a>
                <?php endif; ?>
                
                <span class="px-4 py-2 text-gray-600">
                    Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
                </span>
                
                <?php if ($pagination['has_next']): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])) ?>" 
                       class="px-4 py-2 bg-white rounded-lg shadow hover:shadow-md transition-shadow">
                        Next →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Points Modal -->
<div id="addPointsModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
        <h3 class="text-xl font-bold mb-4">💰 Add Points</h3>
        <p class="text-gray-600 mb-4">Adding points to: <strong id="modalUsername"></strong></p>
        
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_points">
            <input type="hidden" name="user_id" id="modalUserId">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-1">Amount</label>
                <input type="number" name="amount" min="1" max="100000" required
                       class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none"
                       placeholder="Enter points amount">
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-semibold mb-1">Reason</label>
                <input type="text" name="reason" required
                       class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none"
                       placeholder="e.g., Contest winner, Bug compensation">
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closeAddPointsModal()"
                        class="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    Add Points
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddPointsModal(userId, username) {
    document.getElementById('modalUserId').value = userId;
    document.getElementById('modalUsername').textContent = username;
    document.getElementById('addPointsModal').classList.remove('hidden');
    document.getElementById('addPointsModal').classList.add('flex');
}

function closeAddPointsModal() {
    document.getElementById('addPointsModal').classList.add('hidden');
    document.getElementById('addPointsModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
            