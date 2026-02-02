<?php
/**
 * Wallet Management Functions
 * Handles all wallet and transaction operations
 */

class Wallet {
    
    /**
     * Get user's wallet
     */
    public static function getByUserId(int $userId): ?array {
        return Database::fetch(
            "SELECT * FROM wallet WHERE user_id = ?",
            [$userId]
        );
    }
    
    /**
     * Get wallet balance
     */
    public static function getBalance(int $userId): int {
        $wallet = self::getByUserId($userId);
        return $wallet['balance'] ?? 0;
    }
    
    /**
     * Add points to wallet (earn)
     */
    public static function addPoints(int $userId, int $amount, string $description, string $referenceType = 'other', ?int $referenceId = null): array {
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be positive'];
        }
        
        try {
            Database::beginTransaction();
            
            $wallet = self::getByUserId($userId);
            
            if (!$wallet) {
                return ['success' => false, 'message' => 'Wallet not found'];
            }
            
            $newBalance = $wallet['balance'] + $amount;
            
            // Update wallet
            Database::update(
                "UPDATE wallet SET 
                    balance = ?, 
                    total_earned = total_earned + ?, 
                    last_transaction_at = NOW(),
                    updated_at = NOW()
                 WHERE id = ?",
                [$newBalance, $amount, $wallet['id']]
            );
            
            // Record transaction
            $transactionId = Database::insert(
                "INSERT INTO transactions (wallet_id, type, amount, balance_after, description, reference_type, reference_id) 
                 VALUES (?, 'earn', ?, ?, ?, ?, ?)",
                [$wallet['id'], $amount, $newBalance, $description, $referenceType, $referenceId]
            );
            
            Database::commit();
            
            return [
                'success' => true,
                'message' => "Added $amount points",
                'new_balance' => $newBalance,
                'transaction_id' => $transactionId
            ];
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Wallet addPoints error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Transaction failed'];
        }
    }
    
    /**
     * Deduct points from wallet (spend)
     */
    public static function deductPoints(int $userId, int $amount, string $description, string $referenceType = 'other', ?int $referenceId = null): array {
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be positive'];
        }
        
        try {
            Database::beginTransaction();
            
            $wallet = self::getByUserId($userId);
            
            if (!$wallet) {
                return ['success' => false, 'message' => 'Wallet not found'];
            }
            
            // Check sufficient balance
            if ($wallet['balance'] < $amount) {
                Database::rollback();
                return ['success' => false, 'message' => 'Insufficient balance'];
            }
            
            $newBalance = $wallet['balance'] - $amount;
            
            // Update wallet
            Database::update(
                "UPDATE wallet SET 
                    balance = ?, 
                    total_spent = total_spent + ?, 
                    last_transaction_at = NOW(),
                    updated_at = NOW()
                 WHERE id = ?",
                [$newBalance, $amount, $wallet['id']]
            );
            
            // Record transaction
            $transactionId = Database::insert(
                "INSERT INTO transactions (wallet_id, type, amount, balance_after, description, reference_type, reference_id) 
                 VALUES (?, 'spend', ?, ?, ?, ?, ?)",
                [$wallet['id'], -$amount, $newBalance, $description, $referenceType, $referenceId]
            );
            
            Database::commit();
            
            return [
                'success' => true,
                'message' => "Deducted $amount points",
                'new_balance' => $newBalance,
                'transaction_id' => $transactionId
            ];
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Wallet deductPoints error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Transaction failed'];
        }
    }
    
    /**
     * Admin adjustment (can be positive or negative)
     */
    public static function adminAdjustment(int $userId, int $amount, string $reason, int $adminId): array {
        try {
            Database::beginTransaction();
            
            $wallet = self::getByUserId($userId);
            
            if (!$wallet) {
                return ['success' => false, 'message' => 'Wallet not found'];
            }
            
            $newBalance = $wallet['balance'] + $amount;
            
            // Prevent negative balance
            if ($newBalance < MIN_BALANCE) {
                Database::rollback();
                return ['success' => false, 'message' => 'Adjustment would result in negative balance'];
            }
            
            $type = $amount >= 0 ? 'bonus' : 'penalty';
            
            // Update wallet
            Database::update(
                "UPDATE wallet SET 
                    balance = ?, 
                    total_earned = total_earned + ?,
                    last_transaction_at = NOW(),
                    updated_at = NOW()
                 WHERE id = ?",
                [$newBalance, max(0, $amount), $wallet['id']]
            );
            
            // Record transaction
            $description = "Admin adjustment by user #$adminId: $reason";
            $transactionId = Database::insert(
                "INSERT INTO transactions (wallet_id, type, amount, balance_after, description, reference_type, reference_id) 
                 VALUES (?, ?, ?, ?, ?, 'admin', ?)",
                [$wallet['id'], $type, $amount, $newBalance, $description, $adminId]
            );
            
            Database::commit();
            
            // Log activity
            logActivity('wallet_adjustment', "Adjusted user #$userId wallet by $amount points: $reason", $adminId);
            
            return [
                'success' => true,
                'message' => "Adjusted by $amount points",
                'new_balance' => $newBalance,
                'transaction_id' => $transactionId
            ];
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Wallet adminAdjustment error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Adjustment failed'];
        }
    }
    
    /**
     * Get transaction history
     */
    public static function getTransactions(int $userId, int $limit = 20, int $offset = 0): array {
        $wallet = self::getByUserId($userId);
        
        if (!$wallet) {
            return [];
        }
        
        return Database::fetchAll(
            "SELECT * FROM transactions 
             WHERE wallet_id = ? 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            [$wallet['id'], $limit, $offset]
        );
    }
    
    /**
     * Get transaction count
     */
    public static function getTransactionCount(int $userId): int {
        $wallet = self::getByUserId($userId);
        
        if (!$wallet) {
            return 0;
        }
        
        return Database::fetch(
            "SELECT COUNT(*) as count FROM transactions WHERE wallet_id = ?",
            [$wallet['id']]
        )['count'] ?? 0;
    }
    
    /**
     * Get wallet statistics
     */
    public static function getStats(int $userId): array {
        $wallet = self::getByUserId($userId);
        
        if (!$wallet) {
            return [
                'balance' => 0,
                'total_earned' => 0,
                'total_spent' => 0,
                'transactions_count' => 0
            ];
        }
        
        $transactionCount = self::getTransactionCount($userId);
        
        // Get earnings by source
        $earningsBySource = Database::fetchAll(
            "SELECT reference_type, SUM(amount) as total 
             FROM transactions 
             WHERE wallet_id = ? AND type = 'earn' 
             GROUP BY reference_type",
            [$wallet['id']]
        );
        
        return [
            'balance' => $wallet['balance'],
            'total_earned' => $wallet['total_earned'],
            'total_spent' => $wallet['total_spent'],
            'transactions_count' => $transactionCount,
            'earnings_by_source' => $earningsBySource,
            'last_transaction' => $wallet['last_transaction_at']
        ];
    }
}