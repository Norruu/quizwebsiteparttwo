<?php
/**
 * Database Setup Script
 *
 * - Run this once to create and seed the database for the Game Library app.
 * - Place this file (setup.php) inside the /database directory.
 * - Make sure schema.sql and seed.sql exist in this directory.
 * - Visit http://yourdomain/database/setup.php in your browser to run.
 * - **DELETE THIS FILE after running for security!**
 */

// ========================
// CONFIGURE YOUR DATABASE
// ========================
$host     = 'localhost';             // MySQL host (often 'localhost')
$username = 'root';                  // MySQL username (set to your db user)
$password = '';                      // MySQL password
$database = 'game_library';          // MySQL database to create/use

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='font-family:monospace;background:#2a2e3f;color:#fff;padding:22px 36px;border-radius:12px;max-width:700px;margin:40px auto;'>";

function bold($msg) { return "<span style='font-weight:bold;color:#FFD93D;'>$msg</span>"; }
function ok($msg)   { return "<span style='color:#79d770;'>✓ $msg</span>"; }
function fail($msg) { return "<span style='color:#e74c3c;'>✗ $msg</span>"; }

echo bold("◼️ GAME LIBRARY DATABASE SETUP ◼️") . "\n";
echo str_repeat('-', 60) . "\n";

try {
    // 1: Database connection (no database yet!)
    $pdo = new PDO("mysql:host=$host", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo ok("Connected to MySQL host <b>$host</b> as <b>$username</b>\n");

    // 2: Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo ok("Verified database: <b>$database</b>\n");

    // 3: Select/Create database
    $pdo->exec("USE `$database`");
    echo ok("Selected database: <b>$database</b>\n\n");

    // 4: Read and execute schema.sql
    $schemaFile = __DIR__.'/schema.sql';
    if (!file_exists($schemaFile))
        throw new Exception("schema.sql not found in database/"
        );
    $schema = file_get_contents($schemaFile);

    // Remove SQL comments for splitting
    $schema = preg_replace('/--.*$/m', '', $schema);
    $schema = preg_replace('#/\*.*?\*/#s', '', $schema);

    // Remove delimiter blocks for triggers (import triggers manually if needed)
    $schema = preg_replace('/DELIMITER\s+\S+.*?DELIMITER\s+;/s', '', $schema);

    $statements = array_filter(array_map('trim', explode(';', $schema)), function($s) {
        return strlen($s) > 5;
    });

    $count = 0;
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
            $count++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo fail("Table: " . $e->getMessage())."\n";
            }
        }
    }
    echo ok("Schema imported (" . bold("$count") . " statements)\n");

    // 5: Seed data
    $seedFile = __DIR__.'/seed.sql';
    if (!file_exists($seedFile)) throw new Exception("seed.sql not found in database/");
    $seed = file_get_contents($seedFile);
    $seed = preg_replace('/--.*$/m', '', $seed);

    $seedStatements = array_filter(array_map('trim', explode(';', $seed)), function($s) {
        return strlen($s) > 5;
    });

    $seedCount = 0;
    foreach ($seedStatements as $stmt) {
        try {
            $pdo->exec($stmt);
            $seedCount++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                echo fail("Seed: " . substr($e->getMessage(), 0, 90))."\n";
            }
        }
    }
    echo ok("Seed data imported (" . bold("$seedCount") . " statements)\n");

    // 6: Show a summary
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "\n";
    echo ok("Tables created: " . implode(', ', $tables)) . "\n";

    $users = $pdo->query("SELECT username, email, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
    echo "\n<b>✔️ Test accounts created:</b>\n";
    foreach ($users as $u) {
        echo "  • <b>{$u['role']}:</b> {$u['username']} ({$u['email']})\n";
    }

    echo "\n";
    echo "<span style='color:#FFD93D;'>🎉 Setup complete! You can now login to your Game Library.</span>\n";
    echo "<b>Admin email:</b> admin@gamelibrary.com  <b>Password:</b> Admin@123\n";
    echo "\n";
    echo "<span style='color:#fa5555;'>⚠️ Please delete <b>database/setup.php</b> for security.</span>";
    echo "\n\n";

} catch (PDOException $ex) {
    echo "\n" . fail("MySQL Error: " . $ex->getMessage()) . "\n";
    exit;
} catch (Exception $ex) {
    echo "\n" . fail("Error: " . $ex->getMessage()) . "\n";
    exit;
}
echo "</pre>";