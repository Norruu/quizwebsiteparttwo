<?php
/**
 * Wallet API
 * Handle wallet queries: balance, transactions, add/deduct points, history
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

// Rate limiting
Security::rateLimit('wallet_api', 30, 60); // 30 requests per minute

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_REQUEST;

switch ($method) {
    case 'GET':
        handleGetRequest($input);
        break;
    case 'POST':
        handlePostRequest($input);
        break;
    default:
        jsonError('Method not allowed', 405);
}

// GET Actions
function handleGetRequest($input) {
    $action = $input['action'] ?? $_GET['action'] ?? 'balance';
    switch ($action) {
        case 'balance':
            getWalletBalance($input);
            break;
        case 'transactions':
            getTransactions($input);
            break;
        case 'stats':
            getWalletStats($input);
            break;
        default:
            jsonError('Invalid action');
    }
}

function getWalletBalance($input) {
    $userId = $input['user_id'] ?? (Auth::check() ? Auth::id() : 0);
    if (!$userId) jsonError('User ID required');
    $balance = Wallet::getBalance($userId);
    jsonSuccess(['user_id' => $userId, 'balance' => (int)$balance]);
}

function getTransactions($input) {
    $userId = $input['user_id'] ?? (Auth::check() ? Auth::id() : 0);
    if (!$userId) jsonError('User ID required');
    $page = max(1, (int)($input['page'] ?? 1));
    $perPage = min(50, max(1, (int)($input['per_page'] ?? TRANSACTIONS_PER_PAGE)));
    $offset = ($page - 1) * $perPage;
    $transactions = Wallet::getTransactions($userId, $perPage, $offset);
    jsonSuccess(['transactions' => $transactions, 'pagination' => [
        'current_page' => $page,
        'per_page' => $perPage,
        'offset' => $offset,
    ]]);
}

function getWalletStats($input) {
    $userId = $input['user_id'] ?? (Auth::check() ? Auth::id() : 0);
    if (!$userId) jsonError('User ID required');
    $stats = Wallet::getStats($userId);
    jsonSuccess(['stats' => $stats]);
}

// POST Actions
function handlePostRequest($input) {
    if (!Auth::check()) jsonError('Authentication required', 401);
    $action = $input['action'] ?? '';
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
    if (!verifyCsrf($csrfToken)) jsonError('Invalid CSRF token', 403);

    $userId = Auth::id();

    switch ($action) {
        case 'add_points':
            $amount = (int)($input['amount'] ?? 0);
            $desc = sanitize($input['description'] ?? 'Admin bonus');
            $res = Wallet::addPoints($userId, $amount, $desc);
            if ($res['success']) {
                logActivity('wallet_add_points', "Added $amount points: $desc", $userId);
                jsonSuccess($res, 'Points added');
            } else {
                jsonError($res['message']);
            }
            break;
        case 'deduct_points':
            $amount = (int)($input['amount'] ?? 0);
            $desc = sanitize($input['description'] ?? 'Manual deduction');
            $res = Wallet::deductPoints($userId, $amount, $desc);
            if ($res['success']) {
                logActivity('wallet_deduct_points', "Deducted $amount points: $desc", $userId);
                jsonSuccess($res, 'Points deducted');
            } else {
                jsonError($res['message']);
            }
            break;
        default:
            jsonError('Invalid action');
    }
}