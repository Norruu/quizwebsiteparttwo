<?php
/**
 * Leaderboard Functions
 * Handles leaderboard queries and rankings
 */

class Leaderboard {
    
    /**
     * Get global leaderboard by total points
     */
    public static function getGlobalByPoints(int $limit = 100, string $period = 'all'): array {
        $dateFilter = self::getDateFilter($period);
        
        if ($period === 'all') {
            // Use wallet total_earned for all-time
            return Database::fetchAll(
                "SELECT u.id, u.username, u.avatar, w.total_earned as total_points,
                        (SELECT COUNT(*) FROM scores WHERE user_id = u.id) as games_played
                 FROM users u
                 JOIN wallet w ON u.id = w.user_id
                 WHERE u.status = 'active' AND u.role = 'player'
                 ORDER BY w.total_earned DESC
                 LIMIT ?",
                [$limit]
            );
        } else {
            // Calculate from transactions for specific period
            return Database::fetchAll(
                "SELECT u.id, u.username, u.avatar,
                        COALESCE(SUM(t.amount), 0) as total_points,
                        (SELECT COUNT(*) FROM scores WHERE user_id = u.id AND created_at >= ?) as games_played
                 FROM users u
                 JOIN wallet w ON u.id = w.user_id
                 LEFT JOIN transactions t ON w.id = t.wallet_id 
                    AND t.type = 'earn' 
                    AND t.created_at >= ?
                 WHERE u.status = 'active' AND u.role = 'player'
                 GROUP BY u.id, u.username, u.avatar
                 HAVING total_points > 0
                 ORDER BY total_points DESC
                 LIMIT ?",
                [$dateFilter, $dateFilter, $limit]
            );
        }
    }
    
    /**
     * Get leaderboard for a specific game
     */
    public static function getByGame(int $gameId, int $limit = 100, string $period = 'all'): array {
        $dateFilter = self::getDateFilter($period);
        
        $sql = "SELECT u.id, u.username, u.avatar, 
                       MAX(s.score) as high_score,
                       COUNT(s.id) as plays,
                       SUM(s.points_earned) as total_points
                FROM scores s
                JOIN users u ON s.user_id = u.id
                WHERE s.game_id = ? AND u.status = 'active'";
        
        $params = [$gameId];
        
        if ($period !== 'all') {
            $sql .= " AND s.created_at >= ?";
            $params[] = $dateFilter;
        }
        
        $sql .= " GROUP BY u.id, u.username, u.avatar
                  ORDER BY high_score DESC
                  LIMIT ?";
        $params[] = $limit;
        
        return Database::fetchAll($sql, $params);
    }
    
    /**
     * Get user's rank
     */
    public static function getUserRank(int $userId, string $period = 'all'): ?int {
        $leaderboard = self::getGlobalByPoints(1000, $period);
        
        foreach ($leaderboard as $index => $entry) {
            if ($entry['id'] == $userId) {
                return $index + 1;
            }
        }
        
        return null;
    }
    
    /**
     * Get user's rank for a specific game
     */
    public static function getUserGameRank(int $userId, int $gameId): ?int {
        $leaderboard = self::getByGame($gameId, 1000);
        
        foreach ($leaderboard as $index => $entry) {
            if ($entry['id'] == $userId) {
                return $index + 1;
            }
        }
        
        return null;
    }
    
    /**
     * Get top players this week
     */
    public static function getTopPlayersThisWeek(int $limit = 10): array {
        return self::getGlobalByPoints($limit, 'week');
    }
    
    /**
     * Get recent high scores
     */
    public static function getRecentHighScores(int $limit = 10): array {
        return Database::fetchAll(
            "SELECT s.*, u.username, u.avatar, g.title as game_title, g.slug as game_slug
             FROM scores s
             JOIN users u ON s.user_id = u.id
             JOIN games g ON s.game_id = g.id
             WHERE u.status = 'active'
             ORDER BY s.score DESC, s.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }
    
    /**
     * Get date filter based on period
     */
    private static function getDateFilter(string $period): string {
        return match($period) {
            'today' => date('Y-m-d 00:00:00'),
            'week' => date('Y-m-d 00:00:00', strtotime('-7 days')),
            'month' => date('Y-m-d 00:00:00', strtotime('-30 days')),
            default => '1970-01-01 00:00:00'
        };
    }
    
    /**
     * Get leaderboard statistics
     */
    public static function getStats(): array {
        $totalPlayers = Database::fetch(
            "SELECT COUNT(*) as count FROM users WHERE role = 'player' AND status = 'active'"
        )['count'];
        
        $totalGamesPlayed = Database::fetch(
            "SELECT COUNT(*) as count FROM scores"
        )['count'];
        
        $totalPointsAwarded = Database::fetch(
            "SELECT SUM(total_earned) as total FROM wallet"
        )['total'] ?? 0;
        
        return [
            'total_players' => $totalPlayers,
            'total_games_played' => $totalGamesPlayed,
            'total_points_awarded' => $totalPointsAwarded
        ];
    }
}