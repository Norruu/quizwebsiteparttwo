<?php
/**
 * Admin Rewards Management
 */

$pageTitle = 'Manage Rewards';
require_once __DIR__ . '/../includes/header.php';

Auth::requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
        case 'update':
            $rewardData = [
                'name' => sanitize($_POST['name'] ?? ''),
                'description' => sanitize($_POST['description'] ?? ''),
                'points_cost' => (int)($_POST['points_cost'] ?? 0),
                'category' => $_POST['category'] ?? 'digital',
                'quantity' => !empty($_POST['quantity']) ? (int)$_POST['quantity'] : null,
                'max_per_user' => (int)($_POST['max_per_user'] ?? 1),
                'status' => $_POST['status'] ?? 'active',
            ];
            
            // Handle image upload
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Define upload path
                $uploadDir = __DIR__ . '/../assets/images/rewards/';
                
                // Create directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($extension, $allowedExtensions)) {
                    $filename = 'reward_' . time() . '_' . uniqid() . '.' . $extension;
                    $targetPath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                        $rewardData['image'] = $filename;
                    } else {
                        flash('error', 'Failed to upload image');
                    }
                } else {
                    flash('error', 'Invalid image format. Allowed: JPG, PNG, GIF, WebP');
                }
            }

            
            if ($action === 'create') {
                if (empty($rewardData['image'])) {
                    $rewardData['image'] = 'default-reward.png';
                }
                
                Database::insert(
                    "INSERT INTO rewards (name, description, points_cost, category, image, quantity, max_per_user, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $rewardData['name'],
                        $rewardData['description'],
                        $rewardData['points_cost'],
                        $rewardData['category'],
                        $rewardData['image'],
                        $rewardData['quantity'],
                        $rewardData['max_per_user'],
                        $rewardData['status']
                    ]
                );
                flash('success', 'Reward created successfully!');
            } else {
                $rewardId = (int)$_POST['reward_id'];
                $setClauses = [];
                $params = [];
                
                foreach ($rewardData as $key => $value) {
                    if ($key !== 'image' || !empty($value)) {
                        $setClauses[] = "$key = ?";
                        $params[] = $value;
                    }
                }
                
                $params[] = $rewardId;
                Database::update(
                    "UPDATE rewards SET " . implode(', ', $setClauses) . ", updated_at = NOW() WHERE id = ?",
                    $params
                );
                flash('success', 'Reward updated successfully!');
            }
            break;
            
        case 'delete':
            $rewardId = (int)$_POST['reward_id'];
            Database::update("DELETE FROM rewards WHERE id = ?", [$rewardId]);
            flash('success', 'Reward deleted successfully!');
            break;
    }
    
    redirect(baseUrl('/admin/rewards.php'));
}

// Get rewards
$rewards = Database::fetchAll(
    "SELECT r.*, 
            (SELECT COUNT(*) FROM redemptions WHERE reward_id = r.id) as total_redemptions,
            (SELECT COUNT(*) FROM redemptions WHERE reward_id = r.id AND status = 'pending') as pending_redemptions
     FROM rewards r 
     ORDER BY r.created_at DESC"
);

$categories = ['digital', 'physical', 'voucher', 'badge', 'other'];
$statuses = ['active', 'inactive', 'out_of_stock'];
?>

<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="font-game text-3xl text-gray-800">🎁 Manage Rewards</h1>
                <p class="text-gray-600"><?= count($rewards) ?> rewards</p>
            </div>
            <div class="flex gap-3">
                <a href="<?= baseUrl('/admin/') ?>" class="text-blue-500 hover:underline">← Back to Dashboard</a>
                <button onclick="showRewardModal()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                    + Add Reward
                </button>
            </div>
        </div> 
        
        <!-- Rewards Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($rewards as $reward): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <!-- Image -->
                    <div class="relative h-40 bg-gradient-to-br from-purple-100 to-pink-100 flex items-center justify-center">
                        <?php if (!empty($reward['image']) && $reward['image'] !== 'default-reward.png'): ?>
                            <img src="<?= baseUrl('/assets/images/rewards/' . $reward['image']) ?>" 
                                 alt="<?= e($reward['name']) ?>"
                                 class="max-h-32 object-contain"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <span class="text-6xl hidden">🎁</span>
                        <?php else: ?>
                            <span class="text-6xl">🎁</span>
                        <?php endif; ?>
                        
                        <!-- Status Badge -->
                        <span class="absolute top-2 right-2 px-2 py-1 rounded-full text-xs font-bold <?= statusColor($reward['status']) ?>">
                            <?= ucfirst(str_replace('_', ' ', $reward['status'])) ?>
                        </span>
                    </div>
                    
                    <!-- Info -->
                    <div class="p-4">
                        <h3 class="font-bold text-lg text-gray-800"><?= e($reward['name']) ?></h3>
                        <p class="text-sm text-gray-500 mb-3"><?= ucfirst($reward['category']) ?></p>
                        
                        <!-- Cost & Stock -->
                        <div class="flex items-center justify-between mb-4">
                            <span class="font-bold text-xl text-friv-purple">
                                💰 <?= formatNumber($reward['points_cost']) ?>
                            </span>
                            <span class="text-sm text-gray-500">
                                Stock: <?= $reward['quantity'] ?? '∞' ?>
                            </span>
                        </div>
                        
                        <!-- Stats -->
                        <div class="flex items-center justify-between text-sm text-gray-600 mb-4">
                            <span><?= $reward['total_redemptions'] ?> redeemed</span>
                            <?php if ($reward['pending_redemptions'] > 0): ?>
                                <span class="text-orange-500 font-semibold">
                                    <?= $reward['pending_redemptions'] ?> pending
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex gap-2">
                            <button onclick="showRewardModal(<?= htmlspecialchars(json_encode($reward)) ?>)" 
                                    class="flex-1 bg-blue-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-600">
                                Edit
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this reward?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="reward_id" value="<?= $reward['id'] ?>">
                                <button type="submit" class="px-3 py-2 bg-red-100 text-red-600 rounded-lg text-sm hover:bg-red-200">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Reward Modal -->
<div id="rewardModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center overflow-y-auto">
    <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4 my-8">
        <h3 id="rewardModalTitle" class="text-xl font-bold mb-4">Add New Reward</h3>
        
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" id="rewardFormAction" value="create">
            <input type="hidden" name="reward_id" id="rewardId">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-1">Name *</label>
                <input type="text" name="name" id="rewardName" required
                       class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-1">Description</label>
                <textarea name="description" id="rewardDescription" rows="2"
                          class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none"></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Points Cost *</label>
                    <input type="number" name="points_cost" id="rewardCost" min="1" required
                           class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Category</label>
                    <select name="category" id="rewardCategory" class="w-full px-4 py-2 border rounded-lg">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Quantity (empty = unlimited)</label>
                    <input type="number" name="quantity" id="rewardQuantity" min="0"
                           class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Max Per User</label>
                    <input type="number" name="max_per_user" id="rewardMaxPerUser" min="1" value="1"
                           class="w-full px-4 py-2 border rounded-lg">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Status</label>
                    <select name="status" id="rewardStatus" class="w-full px-4 py-2 border rounded-lg">
                        <?php foreach ($statuses as $stat): ?>
                            <option value="<?= $stat ?>"><?= ucfirst(str_replace('_', ' ', $stat)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Image</label>
                    <input type="file" name="image" accept="image/*"
                           class="w-full px-4 py-2 border rounded-lg text-sm">
                </div>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closeRewardModal()"
                        class="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    Save Reward
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showRewardModal(reward = null) {
    const modal = document.getElementById('rewardModal');
    const title = document.getElementById('rewardModalTitle');
    const action = document.getElementById('rewardFormAction');
    
    if (reward) {
        title.textContent = 'Edit Reward';
        action.value = 'update';
        document.getElementById('rewardId').value = reward.id;
        document.getElementById('rewardName').value = reward.name;
        document.getElementById('rewardDescription').value = reward.description || '';
        document.getElementById('rewardCost').value = reward.points_cost;
        document.getElementById('rewardCategory').value = reward.category;
        document.getElementById('rewardQuantity').value = reward.quantity || '';
        document.getElementById('rewardMaxPerUser').value = reward.max_per_user;
        document.getElementById('rewardStatus').value = reward.status;
    } else {
        title.textContent = 'Add New Reward';
        action.value = 'create';
        document.querySelector('#rewardModal form').reset();
    }
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeRewardModal() {
    document.getElementById('rewardModal').classList.add('hidden');
    document.getElementById('rewardModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>