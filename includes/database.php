<?php
/**
 * Database Connection Handler
 * Provides PDO connection with singleton pattern
 */

class Database {
    private static ?PDO $instance = null;
    
    /**
     * Get database connection (singleton)
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    "mysql:host=%s;dbname=%s;charset=%s",
                    DB_HOST,
                    DB_NAME,
                    DB_CHARSET
                );
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];
                
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    die("Database Connection Error: " . $e->getMessage());
                } else {
                    error_log("Database Connection Error: " . $e->getMessage());
                    die("A database error occurred. Please try again later.");
                }
            }
        }
        
        return self::$instance;
    }
    
    /**
     * Shortcut method to get connection
     */
    public static function db(): PDO {
        return self::getConnection();
    }
    
    /**
     * Execute a prepared statement and return results
     */
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Fetch single row
     */
    public static function fetch(string $sql, array $params = []): ?array {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }
    
    /**
     * Fetch all rows
     */
    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }
    
    /**
     * Insert and return last insert ID
     */
    public static function insert(string $sql, array $params = []): int {
        self::query($sql, $params);
        return (int) self::db()->lastInsertId();
    }
    
    /**
     * Update and return affected rows
     */
    public static function update(string $sql, array $params = []): int {
        return self::query($sql, $params)->rowCount();
    }
    
    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool {
        return self::db()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public static function commit(): bool {
        return self::db()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public static function rollback(): bool {
        return self::db()->rollBack();
    }
    
    /**
     * Check if in transaction
     */
    public static function inTransaction(): bool {
        return self::db()->inTransaction();
    }
}

// Create global shortcut function
function db(): PDO {
    return Database::getConnection();
}