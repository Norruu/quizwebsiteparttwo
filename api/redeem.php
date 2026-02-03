<?php
/**
 * Redemption API
 * Handles reward redemption, redemption history, and status updates
 *
 * To version this API (v1/v2), place this file in:
 * - /api/v1/redeem.php (for v1)
 * - /api/v2/redeem.php (for v2, with any changes)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/security.php';

// Set JSON response header
header('Content-Type: application/json');

// Rate limiting
Security::rateLimit('redeem_api', 30, 60);

// Request method
$method = $_SERVER['REQUEST_METHOD'];

// Utility: get parsed input (JSON/body/GET)
$input = json_decode(file_get_contents('php://input'), true) ?? $_REQUEST;

// === GET endpoints ===
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list_rewards';

    switch ($action) {
        case 'list_rewards':
            getRewardsList($input);
            break;
        case 'reward_details':
            getRewardDetails($input);
            break;
        case 'my_redemptions':
            getMyRedemptions($input);
            break;
        case 'redemption_status':
            getRedemptionStatus($input);
            break;
        case 'categories':
            getRewardCategories();
            break;
        case 'can_redeem':
            checkCanRedeem($input);
            break;
        default:
            jsonError('Invalid action');
    }
    exit;
}

// === POST endpoints (require authentication + CSRF) ===
if ($method === 'POST') {
    if (!Auth::check()) jsonError('Authentication required', 401);

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
    if (!verifyCsrf($csrfToken)) jsonError('Invalid CSRF token', 403);

    $action = $input['action'] ?? '';

    switch ($action) {
        case 'redeem':
            redeemReward($input);
            break;
        case 'cancel':
            cancelRedemption($input);
            break;
        case 'add_note':
            addRedemptionNote($input);
            break;
        default:
            jsonError('Invalid action');
    }
    exit;
}

jsonError('Method not allowed', 405);


// ==== HANDLERS BELOW ====

// List all active rewards (with pagination)
function getRewardsList($input) {
    $category = $input['category'] ?? '';
    $sort = $input['sort'] ?? 'points_asc';
    $page = max(1, (int)($input['page'] ?? 1));
    $perPage = min(50, max(1, (int)($input['per_page'] ?? 20)));

    $where = ["status = 'active'"];
    $params = [];

    if ($category) {
        $where[] = "category = ?";
        $params[] = $category;
    }
       [] = "(valid_until IS NULL OR valid_until >= NOW())";
    $whereClause = implode(' AND ', $where);

    // Sorting options
    $orderBy = match($sort) {
        'points_desc' => 'points_cost DESC',
        'name' => 'name ASC',
        'newest' => 'created_at DESC',
        'popular' => '(SELECT COUNT(*) FROM redemptions WHERE reward_id = rewards.id) DESC',
        default => 'points_cost ASC'
    };

    $total = Database::fetch(
        "SELECT COUNT(*) as count FROM rewards WHERE $whereClause",
        $params
    )['count'];

    $offset = ($page - 1) * $perPage;
    $params[] = $perPage;
    $params[] = $offset;

    $rewards = Database::fetchAll(
        "SELECT r.*, 
            (SELECT COUNT(*) FROM redemptions WHERE reward_id = r.id) as total_redemptions
        FROM rewards r
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?",
        $params
    );

    // Check redemption eligibility and format images
    $userId = Auth::check() ? Auth::id() : null;
    $userBalance = $userId ? Wallet::getBalance($userId) : 0;

    foreach ($rewards as &$reward) {
        $reward['image_url'] = $reward['image'] 
            ? asset('images/rewards/' . $reward['image']) 
            : asset('images/rewards/default-reward.png');
        $reward['can_afford'] = $userBalance >= $reward['points_cost'];
        $reward['in_stock'] = ($reward['quantity'] === null) || ($reward['quantity'] > 0);

        if ($userId) {
            $userRedemptions = Database::fetch(
                "SELECT COUNT(*) as count FROM redemptions 
                WHERE user_id = ? AND reward_id = ? AND status NOT IN ('rejected', 'cancelled')",
                [$userId, $reward['id']]
            )['count'];
            $reward['user_redemptions'] = (int)$userRedemptions;
            $reward['can_redeem'] = ($reward['max_per_user'] === null) || ($userRedemptions < $reward['max_per_user']);
        } else {
            $reward['user_redemptions'] = 0;
            $reward['can_redeem'] = false;
        }
    }

    jsonSuccess([
        'rewards' => $rewards,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage),
        ]
    ]);
}

// Get details for a specific reward
function getRewardDetails($input) {
    $rewardId = (int)($input['reward_id'] ?? 0);
    if (!$rewardId) jsonError('Reward ID is required');
    $reward = Database::fetch("SELECT * FROM rewards WHERE id = ? AND status = 'active'", [$rewardId]);
    if (!$reward) jsonError('Reward not found', 404);
    $reward['image_url'] = $reward['image'] 
        ? asset('images/rewards/' . $reward['image']) 
        : asset('images/rewards/default-reward.png');
    jsonSuccess(['reward' => $reward]);
}

// Get logged-in user's recent redemptions
function getMyRedemptions($input) {
    if (!Auth::check()) jsonError('Authentication required', 401);
    $userId = Auth::id();
    $status = $input['status'] ?? '';
    $where = ["r.user_id = ?"];
    $params = [$userId];
    if ($status) {
        $where[] = "r.status = ?";
        $params[] = $status;
    }
    $whereClause = implode(' AND ', $where);
    $redemptions = Database::fetchAll(
        "SELECT r.*, rw.name as reward_name, rw.image as reward_image
        FROM redemptions r
        JOIN rewards rw ON r.reward_id = rw.id
        WHERE $whereClause
        ORDER BY r.created_at DESC
        LIMIT 50",
        $params
    );
    foreach ($redemptions as &$r) {
        $r['image_url'] = $r['reward_image'] 
            ? asset('images/rewards/' . $r['reward_image']) 
            : asset('images/rewards/default-reward.png');
    }
    jsonSuccess(['redemptions' => $redemptions]);
}

// Get status and details for a single redemption
function getRedemptionStatus($input) {
    $redemptionId = (int)($input['redemption_id'] ?? 0);
    if (!$redemptionId) jsonError('Redemption ID is required');
    $redemption = Database::fetch(
        "SELECT r.*, rw.name as reward_name, rw.image as reward_image
        FROM redemptions r
        JOIN rewards rw ON r.reward_id = rw.id
        WHERE r.id = ?",
        [$redemptionId]
    );
    if (!$redemption) jsonError('Redemption not found', 404);
    $redemption['image_url'] = $redemption['reward_image'] 
        ? asset('images/rewards/' . $redemption['reward_image']) 
        : asset('images/rewards/default-reward.png');
    jsonSuccess(['redemption' => $redemption]);
}

// List all reward categories with counts
function getRewardCategories() {
    $categories = Database::fetchAll(
        "SELECT category, COUNT(*) as count FROM rewards GROUP BY category"
    );
    $cats = [];
    foreach ($categories as $c) $cats[] = $c['category'];
    jsonSuccess(['categories' => $cats]);
}

// Check if user can redeem a specific reward
function checkCanRedeem($input) {
    if (!Auth::check()) jsonError('Authentication required', 401);
    $userId = Auth::id();
    $rewardId = (int)($input['reward_id'] ?? 0);
    if (!$rewardId) jsonError('Reward ID is required');
    $reward = Database::fetch("SELECT * FROM rewards WHERE id = ? AND status = 'active'", [$rewardId]);
    if (!$reward) jsonError('Reward not found', 404);
    $userBalance = Wallet::getBalance($userId);
    $userRedemptions = Database::fetch(
        "SELECT COUNT(*) as count FROM redemptions WHERE user_id = ? AND reward_id = ? AND status NOT IN ('rejected','cancelled')",
        [$userId, $rewardId]
    )['count'];
    $canAfford = $userBalance >= $reward['points_cost'];
    $canRedeem = ($reward['max_per_user'] === null) || ($userRedemptions < $reward['max_per_user']);
    $inStock = $reward['quantity'] === null || $reward['quantity'] > 0;
    jsonSuccess([
        'can_afford' => $canAfford,
        'can_redeem' => $canRedeem,
        'in_stock' => $inStock,
        'user_balance' => $userBalance,
        'cost' => $reward['points_cost'],
        'already_redeemed' => $userRedemptions,
        'limit' => $reward['max_per_user']
    ]);
}

// Redeem a reward (deduct points, create redemption)
function redeemReward($input) {
    $userId = Auth::id();
    $rewardId = (int)($input['reward_id'] ?? 0);
    $userNotes = sanitize($input['notes'] ?? '');

    if (!$rewardId) jsonError('Reward ID is required');
    $reward = Database::fetch("SELECT * FROM rewards WHERE id = ? AND status = 'active'", [$rewardId]);
    if (!$reward) jsonError('Reward not found', 404);

    $userBalance = Wallet::getBalance($userId);
    $userRedemptions = Database::fetch(
        "SELECT COUNT(*) as count FROM redemptions WHERE user_id = ? AND reward_id = ? AND status NOT IN ('rejected','cancelled')",
        [$userId, $rewardId]
    )['count'];

    $canAfford = $userBalance >= $reward['points_cost'];
    $canRedeem = ($reward['max_per_user'] === null) || ($userRedemptions < $reward['max_per_user']);
    $inStock = $reward['quantity'] === null || $reward['quantity'] > 0;

    if (!$canAfford) jsonError('Not enough points to redeem this reward', 403);
    if (!$canRedeem) jsonError('Redemption limit reached for this reward', 403);
    if (!$inStock) jsonError('This reward is out of stock', 403);

    Database::beginTransaction();
    try {
        // Deduct wallet points
        $deduct = Wallet::deductPoints(
            $userId,
            $reward['points_cost'],
            "Redeemed: {$reward['name']}",
            'redemption'
        );
        if (!$deduct['success']) throw new Exception($deduct['message']);

        // Insert redemption record
        $status = $reward['requires_approval'] ? 'pending' : 'approved';
        $redemptionId = Database::insert(
            "INSERT INTO redemptions (user_id, reward_id, points_spent, status, user_notes, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())",
            [$userId, $rewardId, $reward['points_cost'], $status, $userNotes]
        );

        // Decrement quantity if applicable
        if ($reward['quantity'] !== null) {
            Database::update(
                "UPDATE rewards SET quantity = quantity - 1 WHERE id = ? AND quantity > 0",
                [$rewardId]
            );
        }

        Database::commit();
        logActivity('redemption_created', "User #{$userId} redeemed reward #{$rewardId}", $userId);
        jsonSuccess([
            'redemption_id' => $redemptionId,
            'status' => $status,
            'redirect' => '/profile/redemptions.php'
        ], $status === 'approved' ? 'Redemption successful!' : 'Your request will be reviewed.');
    } catch (Exception $ex) {
        Database::rollback();
        jsonError('Redemption failed: ' . $ex->getMessage());
    }
}

// Cancel a pending redemption, refund points
function cancelRedemption($input) {
    $redemptionId = (int)($input['redemption_id'] ?? 0);
    if (!$redemptionId) jsonError('Redemption ID is required');
    $userId = Auth::id();
    $redemption = Database::fetch(
        "SELECT * FROM redemptions WHERE id = ? AND user_id = ? AND status IN ('pending','approved')",
        [$redemptionId, $userId]
    );
    if (!$redemption) jsonError('Redemption not found or cannot be cancelled', 404);
    // Refund points
    $refund = Wallet::addPoints(
        $userId,
        $redemption['points_spent'],
        "Refund: Redemption cancelled",
        'redemption',
        $redemptionId
    );
    if ($refund['success']) {
        Database::update(
            "UPDATE redemptions SET status = 'cancelled', admin_notes = CONCAT(COALESCE(admin_notes,''), '\n[', NOW(), '] Cancelled by user'), processed_at = NOW() WHERE id = ?",
            [$redemptionId]
        );
        logActivity('redemption_cancelled', "User #{$userId} cancelled redemption #{$redemptionId}", $userId);
        jsonSuccess(['redirect' => '/profile/redemptions.php'], 'Redemption cancelled, points refunded');
    } else {
        jsonError('Failed to refund points:' . $refund['message']);
    }
}

// Allows user or admin to add a note to redemption
function addRedemptionNote($input) {
    $redemptionId = (int)($input['redemption_id'] ?? 0);
    $note = sanitize($input['note'] ?? '');
    if (!$redemptionId || !$note) jsonError('Redemption ID and note required');
    $userId = Auth::id();
    $redemption = Database::fetch(
        "SELECT * FROM redemptions WHERE id = ?",
        [$redemptionId]
    );
    if (!$redemption) jsonError('Redemption not found', 404);
    if ($redemption['user_id'] == $userId || Auth::isAdmin()) {
        Database::update(
            "UPDATE redemptions SET user_notes = CONCAT(COALESCE(user_notes,''), '\n[', NOW(), '] ', ?) WHERE id = ?",
            [$note, $redemptionId]
        );
        logActivity('redemption_note_added', "User #{$userId} added note to redemption #{$redemptionId}", $userId);
        jsonSuccess([], 'Note added');
    } else {
        jsonError('Not authorized', 403);
    }
}