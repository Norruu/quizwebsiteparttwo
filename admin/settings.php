    <?php
/**
 * Admin Settings Page
 * Configure application settings, game defaults, and system options
 */

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';

Auth::requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_general':
            $settings = [
                'site_name' => sanitize($_POST['site_name'] ?? 'Game Library'),
                'site_description' => sanitize($_POST['site_description'] ?? ''),
                'contact_email' => sanitize($_POST['contact_email'] ?? ''),
                'maintenance_mode' => isset($_POST['maintenance_mode']) ? 'true' : 'false',
                'registration_enabled' => isset($_POST['registration_enabled']) ? 'true' : 'false',
            ];
            
            foreach ($settings as $key => $value) {
                Database::query(
                    "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = ?",
                    [$key, $value, $value]
                );
            }
            
            flash('success', 'General settings saved successfully.');
            logActivity('settings_updated', 'Updated general settings', Auth::id());
            break;
            
        case 'save_game':
            $settings = [
                'welcome_bonus' => (int)($_POST['welcome_bonus'] ?? 100),
                'max_daily_plays_default' => (int)($_POST['max_daily_plays_default'] ?? 10),
                'min_password_length' => (int)($_POST['min_password_length'] ?? 8),
                'leaderboard_limit' => (int)($_POST['leaderboard_limit'] ?? 100),
                'points_expiry_days' => (int)($_POST['points_expiry_days'] ?? 0),
            ];
            
            foreach ($settings as $key => $value) {
                Database::query(
                    "INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, 'integer')
                     ON DUPLICATE KEY UPDATE setting_value = ?",
                    [$key, $value, $value]
                );
            }
            
            flash('success', 'Game settings saved successfully.');
            logActivity('settings_updated', 'Updated game settings', Auth::id());
            break;
            
        case 'clear_cache':
            // Clear any cached data (sessions, temp files, etc.)
            // For this simple app, we'll just clear old sessions
            Database::query("DELETE FROM sessions WHERE last_activity < ?", [time() - 86400]);
            
            flash('success', 'Cache cleared successfully.');
            logActivity('cache_cleared', 'Cleared application cache', Auth::id());
            break;
            
        case 'clear_logs':
            $days = (int)($_POST['log_days'] ?? 30);
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            $deleted = Database::update(
                "DELETE FROM activity_log WHERE created_at < ?",
                [$cutoff]
            );
            
            flash('success', "Deleted $deleted log entries older than $days days.");
            logActivity('logs_cleared', "Cleared logs older than $days days", Auth::id());
            break;
            
        case 'reset_daily_limits':
            Database::query("DELETE FROM daily_play_limits WHERE play_date < CURDATE()");
            flash('success', 'Old daily play limits cleared.');
            logActivity('daily_limits_reset', 'Reset daily play limits', Auth::id());
            break;
            
        case 'backup_database':
            // Generate a simple database backup info (actual backup would need shell access)
            $tables = Database::fetchAll("SHOW TABLES");
            $tableCount = count($tables);
            
            $stats = [
                'tables' => $tableCount,
                'users' => Database::fetch("SELECT COUNT(*) as c FROM users")['c'],
                'scores' => Database::fetch("SELECT COUNT(*) as c FROM scores")['c'],
                'transactions' => Database::fetch("SELECT COUNT(*) as c FROM transactions")['c'],
            ];
            
            flash('info', "Database has $tableCount tables, {$stats['users']} users, {$stats['scores']} scores, {$stats['transactions']} transactions. For full backup, use phpMyAdmin or mysqldump.");
            break;
    }
    
    redirect(baseUrl('/admin/settings.php'));
}

// Get current settings
function getSetting($key, $default = '') {
    static $settings = null;
    
    if ($settings === null) {
        $rows = Database::fetchAll("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return $settings[$key] ?? $default;
}

// Get system info
$systemInfo = [
    'php_version' => PHP_VERSION,
    'mysql_version' => Database::fetch("SELECT VERSION() as v")['v'] ?? 'Unknown',
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time') . 's',
];

// Get database stats
$dbStats = [
    'users' => Database::fetch("SELECT COUNT(*) as c FROM users")['c'],
    'games' => Database::fetch("SELECT COUNT(*) as c FROM games")['c'],
    'scores' => Database::fetch("SELECT COUNT(*) as c FROM scores")['c'],
    'transactions' => Database::fetch("SELECT COUNT(*) as c FROM transactions")['c'],
    'redemptions' => Database::fetch("SELECT COUNT(*) as c FROM redemptions")['c'],
    'activity_logs' => Database::fetch("SELECT COUNT(*) as c FROM activity_log")['c'],
];

// Calculate database size (approximate)
$dbSize = Database::fetch(
    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
     FROM information_schema.tables 
     WHERE table_schema = ?",
    [DB_NAME]
)['size_mb'] ?? 0;
?>

<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-5xl mx-auto">
        
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="font-game text-3xl text-gray-800">⚙️ Settings</h1>
                <p class="text-gray-600">Configure your Game Library application</p>
            </div>
            <a href="<?= baseUrl('/admin/') ?>" class="text-blue-500 hover:underline">← Back to Dashboard</a>
        </div>
        
        <div class="grid lg:grid-cols-3 gap-6">
            
            <!-- Main Settings Column -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- General Settings -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="px-6 py-4 border-b bg-gray-50">
                        <h2 class="font-bold text-lg">🌐 General Settings</h2>
                    </div>
                    <form method="POST" class="p-6">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_general">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Site Name</label>
                                <input type="text" name="site_name" value="<?= e(getSetting('site_name', 'Game Library')) ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Site Description</label>
                                <textarea name="site_description" rows="2"
                                          class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none"><?= e(getSetting('site_description', 'Play games, earn points, win rewards!')) ?></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Contact Email</label>
                                <input type="email" name="contact_email" value="<?= e(getSetting('contact_email', '')) ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none"
                                       placeholder="support@example.com">
                            </div>
                            
                            <div class="flex items-center gap-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="registration_enabled" 
                                           <?= getSetting('registration_enabled', 'true') === 'true' ? 'checked' : '' ?>
                                           class="w-5 h-5 text-blue-500 rounded">
                                    <span>Allow New Registrations</span>
                                </label>
                                
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="maintenance_mode" 
                                           <?= getSetting('maintenance_mode', 'false') === 'true' ? 'checked' : '' ?>
                                           class="w-5 h-5 text-orange-500 rounded">
                                    <span class="text-orange-600">Maintenance Mode</span>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="mt-6 bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                            Save General Settings
                        </button>
                    </form>
                </div>
                
                <!-- Game Settings -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="px-6 py-4 border-b bg-gray-50">
                        <h2 class="font-bold text-lg">🎮 Game & Points Settings</h2>
                    </div>
                    <form method="POST" class="p-6">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_game">
                        
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Welcome Bonus (Points)</label>
                                <input type="number" name="welcome_bonus" min="0" max="10000"
                                       value="<?= e(getSetting('welcome_bonus', '100')) ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                                <p class="text-xs text-gray-500 mt-1">Points given to new users on registration</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Default Max Daily Plays</label>
                                <input type="number" name="max_daily_plays_default" min="1" max="100"
                                       value="<?= e(getSetting('max_daily_plays_default', '10')) ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                                <p class="text-xs text-gray-500 mt-1">Per game per user per day</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Leaderboard Limit</label>
                                <input type="number" name="leaderboard_limit" min="10" max="1000"
                                       value="<?= e(getSetting('leaderboard_limit', '100')) ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                                <p class="text-xs text-gray-500 mt-1">Max entries shown on leaderboard</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Min Password Length</label>
                                <input type="number" name="min_password_length" min="6" max="32"
                                       value="<?= e(getSetting('min_password_length', '8')) ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                                <p class="text-xs text-gray-500 mt-1">Minimum characters for passwords</p>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Points Expiry (Days)</label>
                                <input type="number" name="points_expiry_days" min="0" max="3650"
                                       value="<?= e(getSetting('points_expiry_days', '0')) ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
                                <p class="text-xs text-gray-500 mt-1">0 = Points never expire. Otherwise, days until inactive points expire.</p>
                            </div>
                        </div>
                        
                        <button type="submit" class="mt-6 bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600">
                            Save Game Settings
                        </button>
                    </form>
                </div>
                
                <!-- Maintenance Tools -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="px-6 py-4 border-b bg-gray-50">
                        <h2 class="font-bold text-lg">🔧 Maintenance Tools</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        
                        <!-- Clear Cache -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <h3 class="font-semibold">Clear Cache</h3>
                                <p class="text-sm text-gray-500">Remove old sessions and temporary data</p>
                            </div>
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="clear_cache">
                                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 text-sm">
                                    Clear Cache
                                </button>
                            </form>
                        </div>
                        
                        <!-- Clear Old Logs -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <h3 class="font-semibold">Clear Activity Logs</h3>
                                <p class="text-sm text-gray-500">Remove old activity log entries</p>
                            </div>
                            <form method="POST" class="flex items-center gap-2">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="clear_logs">
                                <select name="log_days" class="px-3 py-2 border rounded-lg text-sm">
                                    <option value="7">Older than 7 days</option>
                                    <option value="30" selected>Older than 30 days</option>
                                    <option value="90">Older than 90 days</option>
                                    <option value="180">Older than 180 days</option>
                                </select>
                                <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 text-sm">
                                    Clear Logs
                                </button>
                            </form>
                        </div>
                        
                        <!-- Reset Daily Limits -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <h3 class="font-semibold">Reset Daily Play Limits</h3>
                                <p class="text-sm text-gray-500">Clear old daily play limit records</p>
                            </div>
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="reset_daily_limits">
                                <button type="submit" class="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 text-sm">
                                    Reset Limits
                                </button>
                            </form>
                        </div>
                        
                        <!-- Database Backup Info -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <h3 class="font-semibold">Database Info</h3>
                                <p class="text-sm text-gray-500">Get database statistics</p>
                            </div>
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="backup_database">
                                <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 text-sm">
                                    View Stats
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="space-y-6">
                
                <!-- System Info -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h3 class="font-bold">💻 System Info</h3>
                    </div>
                    <div class="p-4 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">PHP Version</span>
                            <span class="font-semibold"><?= $systemInfo['php_version'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">MySQL Version</span>
                            <span class="font-semibold"><?= $systemInfo['mysql_version'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Upload Max Size</span>
                            <span class="font-semibold"><?= $systemInfo['upload_max_filesize'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Memory Limit</span>
                            <span class="font-semibold"><?= $systemInfo['memory_limit'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Max Execution</span>
                            <span class="font-semibold"><?= $systemInfo['max_execution_time'] ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Database Stats -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h3 class="font-bold">📊 Database Stats</h3>
                    </div>
                    <div class="p-4 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Database Size</span>
                            <span class="font-semibold"><?= $dbSize ?> MB</span>
                        </div>
                        <hr>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Users</span>
                            <span class="font-semibold"><?= number_format($dbStats['users']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Games</span>
                            <span class="font-semibold"><?= number_format($dbStats['games']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Score Records</span>
                            <span class="font-semibold"><?= number_format($dbStats['scores']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Transactions</span>
                            <span class="font-semibold"><?= number_format($dbStats['transactions']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Redemptions</span>
                            <span class="font-semibold"><?= number_format($dbStats['redemptions']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Activity Logs</span>
                            <span class="font-semibold"><?= number_format($dbStats['activity_logs']) ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h3 class="font-bold">🔗 Quick Links</h3>
                    </div>
                    <div class="p-4 space-y-2">
                        <a href="<?= baseUrl('/admin/users.php') ?>" class="block p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            👥 Manage Users
                        </a>
                        <a href="<?= baseUrl('/admin/games.php') ?>" class="block p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            🎮 Manage Games
                        </a>
                        <a href="<?= baseUrl('/admin/rewards.php') ?>" class="block p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            🎁 Manage Rewards
                        </a>
                        <a href="<?= baseUrl('/admin/analytics.php') ?>" class="block p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            📊 View Analytics
                        </a>
                    </div>
                </div>
                
                <!-- App Info -->
                <div class="bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl shadow-lg p-4 text-white">
                    <h3 class="font-bold mb-2">🎮 <?= e(getSetting('site_name', 'Game Library')) ?></h3>
                    <p class="text-sm text-white/80 mb-3">Version <?= APP_VERSION ?? '1.0.0' ?></p>
                    <div class="text-xs text-white/60">
                        <p>Environment: <?= APP_ENV ?></p>
                        <p>Debug: <?= APP_DEBUG ? 'Enabled' : 'Disabled' ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Activity Log Preview -->
        <div class="mt-8 bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                <h2 class="font-bold text-lg">📜 Recent Activity Log</h2>
                <span class="text-sm text-gray-500">Last 20 entries</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 text-sm">
                        <tr>
                            <th class="px-4 py-3 text-left">Time</th>
                            <th class="px-4 py-3 text-left">User</th>
                            <th class="px-4 py-3 text-left">Action</th>
                            <th class="px-4 py-3 text-left">Description</th>
                            <th class="px-4 py-3 text-left">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm">
                        <?php
                        $recentLogs = Database::fetchAll(
                            "SELECT l.*, u.username 
                             FROM activity_log l 
                             LEFT JOIN users u ON l.user_id = u.id 
                             ORDER BY l.created_at DESC 
                             LIMIT 20"
                        );
                        
                        foreach ($recentLogs as $log):
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                    <?= date('M j, g:i A', strtotime($log['created_at'])) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?= $log['username'] ? e($log['username']) : '<span class="text-gray-400">System</span>' ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 bg-gray-100 rounded text-xs font-mono">
                                        <?= e($log['action']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 max-w-md truncate">
                                    <?= e($log['description'] ?? '-') ?>
                                </td>
                                <td class="px-4 py-3 text-gray-400 font-mono text-xs">
                                    <?= e($log['ip_address'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recentLogs)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    No activity logs found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>