<?php
/**
 * Admin Redemptions Management
 * Process and manage reward redemption requests
 */

$pageTitle = 'Manage Redemptions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/wallet.php';

Auth::requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    $redemptionId = (int)($_POST['redemption_id'] ?? 0);
    
    if ($redemptionId) {
        $redemption = Database::fetch(
            "SELECT r.*, u.username, u.email, rw.name as reward_name, rw.points_cost
             FROM redemptions r
             JOIN users u ON r.user_id = u.id
             JOIN rewards rw ON r.reward_id = rw.id
             WHERE r.id = ?",
            [$redemptionId]
        );
        
        if ($redemption) {
            switch ($action) {
                case 'approve':
                    Database::update(
                        "UPDATE redemptions SET status = 'approved', processed_by = ?, processed_at = NOW() WHERE id = ?",
                        [Auth::id(), $redemptionId]
                    );
                    flash('success', 'Redemption approved successfully.');
                    logActivity('redemption_approved', "Approved redemption #{$redemptionId} for {$redemption['username']}", Auth::id());
                    break;
                    
                case 'fulfill':
                    $adminNotes = sanitize($_POST['admin_notes'] ?? '');
                    Database::update(
                        "UPDATE redemptions SET status = 'fulfilled', admin_notes = ?, fulfilled_at = NOW(), processed_by = ?, processed_at = NOW() WHERE id = ?",
                        [$adminNotes, Auth::id(), $redemptionId]
                    );
                    flash('success', 'Redemption marked as fulfilled.');
                    logActivity('redemption_fulfilled', "Fulfilled redemption #{$redemptionId} for {$redemption['username']}", Auth::id());
                    break;
                    
                case 'reject':
                    $adminNotes = sanitize($_POST['admin_notes'] ?? '');
                    
                    // Refund points
                    $refundResult = Wallet::addPoints(
                        $redemption['user_id'],
                        $redemption['points_spent'],
                        "Refund: Redemption rejected - {$redemption['reward_name']}",
                        'redemption',
                        $redemptionId
                    );
                    
                    if ($refundResult['success']) {
                        Database::update(
                            "UPDATE redemptions SET status = 'rejected', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?",
                            [$adminNotes, Auth::id(), $redemptionId]
                        );
                        flash('success', 'Redemption rejected and points refunded.');
                                                logActivity('redemption_rejected', "Rejected redemption #{$redemptionId} for {$redemption['username']}, points refunded", Auth::id());
                    } else {
                        flash('error', 'Failed to refund points: ' . $refundResult['message']);
                    }
                    break;
                    
                case 'cancel':
                    $adminNotes = sanitize($_POST['admin_notes'] ?? 'Cancelled by admin');
                    
                    // Refund points
                    $refundResult = Wallet::addPoints(
                        $redemption['user_id'],
                        $redemption['points_spent'],
                        "Refund: Redemption cancelled - {$redemption['reward_name']}",
                        'redemption',
                        $redemptionId
                    );
                    
                    if ($refundResult['success']) {
                        Database::update(
                            "UPDATE redemptions SET status = 'cancelled', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?",
                            [$adminNotes, Auth::id(), $redemptionId]
                        );
                        flash('success', 'Redemption cancelled and points refunded.');
                        logActivity('redemption_cancelled', "Cancelled redemption #{$redemptionId} for {$redemption['username']}", Auth::id());
                    } else {
                        flash('error', 'Failed to refund points.');
                    }
                    break;
                    
                case 'add_note':
                    $adminNotes = sanitize($_POST['admin_notes'] ?? '');
                    Database::update(
                        "UPDATE redemptions SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[', NOW(), '] ', ?) WHERE id = ?",
                        [$adminNotes, $redemptionId]
                    );
                    flash('success', 'Note added to redemption.');
                    break;
            }
        }
    }
    
    redirect(baseUrl('/admin/redemptions.php') . (isset($_GET['status']) ? '?status=' . $_GET['status'] : ''));
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$rewardFilter = $_GET['reward'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Build query
$where = ['1=1'];
$params = [];

if ($statusFilter) {
    $where[] = "r.status = ?";
    $params[] = $statusFilter;
}

if ($search) {
    $where[] = "(u.username LIKE ? OR u.email LIKE ? OR rw.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($rewardFilter) {
    $where[] = "r.reward_id = ?";
    $params[] = $rewardFilter;
}

$whereClause = implode(' AND ', $where);

// Get total count
$total = Database::fetch(
    "SELECT COUNT(*) as count 
     FROM redemptions r
     JOIN users u ON r.user_id = u.id
     JOIN rewards rw ON r.reward_id = rw.id
     WHERE $whereClause",
    $params
)['count'];

$pagination = paginate($total, $perPage, $page);

// Get redemptions
$params[] = $pagination['per_page'];
$params[] = $pagination['offset'];

$redemptions = Database::fetchAll(
    "SELECT r.*, 
            u.username, u.email, u.avatar,
            rw.name as reward_name, rw.category as reward_category, rw.image as reward_image, rw.points_cost,
            admin.username as processed_by_name
     FROM redemptions r
     JOIN users u ON r.user_id = u.id
     JOIN rewards rw ON r.reward_id = rw.id
     LEFT JOIN users admin ON r.processed_by = admin.id
     WHERE $whereClause
     ORDER BY 
        CASE r.status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            ELSE 3 
        END,
        r.created_at DESC
     LIMIT ? OFFSET ?",
    $params
);

// Get all rewards for filter
$rewards = Database::fetchAll("SELECT id, name FROM rewards ORDER BY name");

// Get status counts
$statusCounts = Database::fetchAll(
    "SELECT status, COUNT(*) as count FROM redemptions GROUP BY status"
);
$counts = [];
foreach ($statusCounts as $sc) {
    $counts[$sc['status']] = $sc['count'];
}

// Status configuration
$statuses = [
    'pending' => ['label' => 'Pending', 'color' => 'bg-yellow-100 text-yellow-800', 'icon' => '⏳'],
    'approved' => ['label' => 'Approved', 'color' => 'bg-blue-100 text-blue-800', 'icon' => '✓'],
    'fulfilled' => ['label' => 'Fulfilled', 'color' => 'bg-green-100 text-green-800', 'icon' => '✅'],
    'rejected' => ['label' => 'Rejected', 'color' => 'bg-red-100 text-red-800', 'icon' => '❌'],
    'cancelled' => ['label' => 'Cancelled', 'color' => 'bg-gray-100 text-gray-800', 'icon' => '🚫'],
];
?>

<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
                <h1 class="font-game text-3xl text-gray-800">📦 Manage Redemptions</h1>
                <p class="text-gray-600">Process and track reward redemption requests</p>
            </div>
            <a href="<?= baseUrl('/admin/') ?>" class="text-blue-500 hover:underline">← Back to Dashboard</a>
        </div>
        
        <!-- Status Tabs -->
        <div class="flex flex-wrap gap-2 mb-6">
            <a href="<?= baseUrl('/admin/redemptions.php') ?>" 
               class="px-4 py-2 rounded-lg font-semibold transition-all <?= !$statusFilter ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>">
                All (<?= array_sum($counts) ?>)
            </a>
            <?php foreach ($statuses as $key => $status): ?>
                <a href="?status=<?= $key ?>" 
                   class="px-4 py-2 rounded-lg font-semibold transition-all <?= $statusFilter === $key ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>">
                    <?= $status['icon'] ?> <?= $status['label'] ?> (<?= $counts[$key] ?? 0 ?>)
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <?php if ($statusFilter): ?>
                    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <?php endif; ?>
                
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" value="<?= e($search) ?>" 
                           placeholder="Username, email, or reward..."
                           class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Reward</label>
                    <select name="reward" class="px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                        <option value="">All Rewards</option>
                        <?php foreach ($rewards as $reward): ?>
                            <option value="<?= $reward['id'] ?>" <?= $rewardFilter == $reward['id'] ? 'selected' : '' ?>>
                                <?= e($reward['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                    Filter
                </button>
                
                <?php if ($search || $rewardFilter): ?>
                    <a href="<?= baseUrl('/admin/redemptions.php') . ($statusFilter ? '?status=' . $statusFilter : '') ?>" 
                       class="text-gray-500 hover:text-gray-700 px-4 py-2">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Redemptions List -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <?php if (empty($redemptions)): ?>
                <div class="text-center py-16">
                    <div class="text-6xl mb-4">📦</div>
                    <h3 class="text-xl font-bold text-gray-700 mb-2">No redemptions found</h3>
                    <p class="text-gray-500">
                        <?= $statusFilter ? 'No ' . strtolower($statuses[$statusFilter]['label']) . ' redemptions' : 'No redemption requests yet' ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="divide-y">
                    <?php foreach ($redemptions as $redemption): 
                        $status = $statuses[$redemption['status']] ?? $statuses['pending'];
                    ?>
                        <div class="p-6 hover:bg-gray-50 transition-colors">
                            <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                                
                                <!-- Reward Info -->
                                <div class="flex items-center gap-4 flex-1">
                                    <div class="w-16 h-16 bg-gradient-to-br from-purple-100 to-pink-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                        <?php if ($redemption['reward_image']): ?>
                                            <img src="<?= asset('images/rewards/' . $redemption['reward_image']) ?>" 
                                                 alt="" class="max-w-12 max-h-12 object-contain">
                                        <?php else: ?>
                                            <span class="text-3xl">🎁</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="font-bold text-gray-800"><?= e($redemption['reward_name']) ?></h3>
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $status['color'] ?>">
                                                <?= $status['icon'] ?> <?= $status['label'] ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500">
                                            <span class="capitalize"><?= $redemption['reward_category'] ?></span> • 
                                            <span class="font-semibold text-purple-600"><?= number_format($redemption['points_spent']) ?> points</span>
                                        </p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            Redemption #<?= $redemption['id'] ?> • <?= date('M j, Y g:i A', strtotime($redemption['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- User Info -->
                                <div class="flex items-center gap-3 lg:w-64">
                                    <img src="<?= asset('images/avatars/' . ($redemption['avatar'] ?? 'default-avatar.png')) ?>" 
                                         alt="" class="w-10 h-10 rounded-full">
                                    <div>
                                        <p class="font-semibold text-gray-800"><?= e($redemption['username']) ?></p>
                                        <p class="text-xs text-gray-500"><?= e($redemption['email']) ?></p>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex items-center gap-2 lg:w-auto">
                                    <?php if ($redemption['status'] === 'pending'): ?>
                                        <button onclick="showActionModal(<?= $redemption['id'] ?>, 'approve', '<?= e($redemption['reward_name']) ?>', '<?= e($redemption['username']) ?>')"
                                                class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 text-sm font-semibold">
                                            ✓ Approve
                                        </button>
                                        <button onclick="showActionModal(<?= $redemption['id'] ?>, 'reject', '<?= e($redemption['reward_name']) ?>', '<?= e($redemption['username']) ?>')"
                                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 text-sm font-semibold">
                                            ✗ Reject
                                        </button>
                                    <?php elseif ($redemption['status'] === 'approved'): ?>
                                        <button onclick="showActionModal(<?= $redemption['id'] ?>, 'fulfill', '<?= e($redemption['reward_name']) ?>', '<?= e($redemption['username']) ?>')"
                                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 text-sm font-semibold">
                                            📦 Mark Fulfilled
                                        </button>
                                        <button onclick="showActionModal(<?= $redemption['id'] ?>, 'cancel', '<?= e($redemption['reward_name']) ?>', '<?= e($redemption['username']) ?>')"
                                                class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm font-semibold">
                                            Cancel
                                        </button>
                                    <?php else: ?>
                                        <button onclick="showDetailsModal(<?= htmlspecialchars(json_encode($redemption)) ?>)"
                                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-semibold">
                                            View Details
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Additional Info -->
                            <?php if ($redemption['admin_notes'] || $redemption['user_notes'] || $redemption['processed_by_name']): ?>
                                <div class="mt-4 pt-4 border-t border-gray-100">
                                    <?php if ($redemption['user_notes']): ?>
                                        <p class="text-sm text-gray-600 mb-2">
                                            <span class="font-semibold">User Notes:</span> <?= e($redemption['user_notes']) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($redemption['admin_notes']): ?>
                                        <p class="text-sm text-gray-600 mb-2">
                                            <span class="font-semibold">Admin Notes:</span> <?= nl2br(e($redemption['admin_notes'])) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($redemption['processed_by_name']): ?>
                                        <p class="text-xs text-gray-400">
                                            Processed by <?= e($redemption['processed_by_name']) ?> 
                                            on <?= date('M j, Y g:i A', strtotime($redemption['processed_at'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="px-6 py-4 border-t flex items-center justify-center gap-2">
                        <?php if ($pagination['has_prev']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])) ?>" 
                               class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">← Previous</a>
                        <?php endif; ?>
                        
                        <span class="px-4 py-2 text-gray-600">
                            Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
                        </span>
                        
                        <?php if ($pagination['has_next']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])) ?>" 
                               class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Action Modal -->
<div id="actionModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
        <h3 id="modalTitle" class="text-xl font-bold mb-2">Process Redemption</h3>
        <p id="modalSubtitle" class="text-gray-600 mb-4"></p>
        
        <form method="POST" id="actionForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" id="modalAction">
            <input type="hidden" name="redemption_id" id="modalRedemptionId">
            
            <div class="mb-4" id="notesSection">
                <label class="block text-sm font-semibold mb-1">Admin Notes <span class="text-gray-400">(optional)</span></label>
                <textarea name="admin_notes" rows="3" id="modalNotes"
                          class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none"
                          placeholder="Add notes about this action..."></textarea>
            </div>
            
            <div id="refundNotice" class="hidden mb-4 p-3 bg-yellow-50 rounded-lg text-sm text-yellow-800">
                ⚠️ Points will be automatically refunded to the user.
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closeActionModal()"
                        class="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" id="modalSubmitBtn"
                        class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-bold mb-4">📦 Redemption Details</h3>
        
        <div id="detailsContent" class="space-y-4">
            <!-- Content filled by JavaScript -->
        </div>
        
        <button onclick="closeDetailsModal()"
                class="w-full mt-6 px-4 py-2 border rounded-lg hover:bg-gray-50">
            Close
        </button>
    </div>
</div>

<script>
function showActionModal(redemptionId, action, rewardName, username) {
    document.getElementById('modalRedemptionId').value = redemptionId;
    document.getElementById('modalAction').value = action;
    
    const titles = {
        'approve': '✓ Approve Redemption',
        'fulfill': '📦 Mark as Fulfilled',
        'reject': '❌ Reject Redemption',
        'cancel': '🚫 Cancel Redemption'
    };
    
    const buttonColors = {
        'approve': 'bg-green-500 hover:bg-green-600',
        'fulfill': 'bg-blue-500 hover:bg-blue-600',
        'reject': 'bg-red-500 hover:bg-red-600',
        'cancel': 'bg-gray-500 hover:bg-gray-600'
    };
    
    document.getElementById('modalTitle').textContent = titles[action] || 'Process Redemption';
    document.getElementById('modalSubtitle').textContent = `${rewardName} for ${username}`;
    
    const submitBtn = document.getElementById('modalSubmitBtn');
    submitBtn.className = `flex-1 px-4 py-2 text-white rounded-lg ${buttonColors[action]}`;
    submitBtn.textContent = titles[action].split(' ').slice(1).join(' ');
    
    // Show refund notice for reject/cancel
    const refundNotice = document.getElementById('refundNotice');
    if (action === 'reject' || action === 'cancel') {
        refundNotice.classList.remove('hidden');
    } else {
        refundNotice.classList.add('hidden');
    }
    
    document.getElementById('actionModal').classList.remove('hidden');
    document.getElementById('actionModal').classList.add('flex');
}

function closeActionModal() {
    document.getElementById('actionModal').classList.add('hidden');
    document.getElementById('actionModal').classList.remove('flex');
    document.getElementById('modalNotes').value = '';
}

function showDetailsModal(redemption) {
    const statuses = {
        'pending': { label: 'Pending', color: 'bg-yellow-100 text-yellow-800' },
        'approved': { label: 'Approved', color: 'bg-blue-100 text-blue-800' },
        'fulfilled': { label: 'Fulfilled', color: 'bg-green-100 text-green-800' },
        'rejected': { label: 'Rejected', color: 'bg-red-100 text-red-800' },
        'cancelled': { label: 'Cancelled', color: 'bg-gray-100 text-gray-800' }
    };
    
    const status = statuses[redemption.status] || statuses.pending;
    
    let html = `
        <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
            <div class="w-16 h-16 bg-purple-100 rounded-xl flex items-center justify-center text-3xl">🎁</div>
            <div>
                <h4 class="font-bold text-lg">${redemption.reward_name}</h4>
                <p class="text-gray-500 capitalize">${redemption.reward_category}</p>
            </div>
        </div>
        
        <div class="grid grid-cols-2 gap-4">
            <div class="p-3 bg-gray-50 rounded-lg">
                <p class="text-xs text-gray-500">Status</p>
                <p class="font-semibold ${status.color} inline-block px-2 py-0.5 rounded-full text-sm mt-1">${status.label}</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
                <p class="text-xs text-gray-500">Points Spent</p>
                <p class="font-bold text-purple-600">${parseInt(redemption.points_spent).toLocaleString()}</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
                <p class="text-xs text-gray-500">User</p>
                <p class="font-semibold">${redemption.username}</p>
                <p class="text-xs text-gray-400">${redemption.email}</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
                <p class="text-xs text-gray-500">Requested</p>
                <p class="font-semibold">${new Date(redemption.created_at).toLocaleDateString()}</p>
                <p class="text-xs text-gray-400">${new Date(redemption.created_at).toLocaleTimeString()}</p>
            </div>
        </div>
    `;
    
    if (redemption.user_notes) {
        html += `
            <div class="p-3 bg-blue-50 rounded-lg">
                <p class="text-xs text-blue-600 font-semibold mb-1">User Notes</p>
                <p class="text-sm text-blue-800">${redemption.user_notes}</p>
            </div>
        `;
    }
    
    if (redemption.admin_notes) {
        html += `
            <div class="p-3 bg-orange-50 rounded-lg">
                <p class="text-xs text-orange-600 font-semibold mb-1">Admin Notes</p>
                <p class="text-sm text-orange-800 whitespace-pre-line">${redemption.admin_notes}</p>
            </div>
        `;
    }
    
    if (redemption.processed_by_name) {
        html += `
            <div class="text-xs text-gray-400 text-center">
                Processed by ${redemption.processed_by_name} on ${new Date(redemption.processed_at).toLocaleString()}
            </div>
        `;
    }
    
    document.getElementById('detailsContent').innerHTML = html;
    document.getElementById('detailsModal').classList.remove('hidden');
    document.getElementById('detailsModal').classList.add('flex');
}

function closeDetailsModal() {
    document.getElementById('detailsModal').classList.add('hidden');
    document.getElementById('detailsModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>