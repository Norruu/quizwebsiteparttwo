-- ============================================
-- GAME LIBRARY DATABASE SCHEMA
-- Version: 1.0
-- Database: MySQL 8.x / MariaDB 10.x
-- 
-- Run this file first to create all tables
-- ============================================

-- Create database (uncomment if running manually)
-- CREATE DATABASE IF NOT EXISTS game_library 
-- CHARACTER SET utf8mb4 
-- COLLATE utf8mb4_unicode_ci;

-- USE game_library;

-- ============================================
-- Drop existing tables (for fresh install)
-- ============================================
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS user_achievements;
DROP TABLE IF EXISTS achievements;
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS daily_play_limits;
DROP TABLE IF EXISTS redemptions;
DROP TABLE IF EXISTS rewards;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS wallet;
DROP TABLE IF EXISTS scores;
DROP TABLE IF EXISTS games;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- TABLE: users
-- Stores all user accounts (players and admins)
-- ============================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('player', 'admin') DEFAULT 'player',
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    status ENUM('active', 'banned', 'suspended') DEFAULT 'active',
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    last_login TIMESTAMP NULL,
    login_attempts INT UNSIGNED DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Unique constraints
    UNIQUE KEY unique_username (username),
    UNIQUE KEY unique_email (email),
    
    -- Indexes for faster queries
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: games
-- Stores all available games
-- ============================================
CREATE TABLE games (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    instructions TEXT NULL,
    thumbnail VARCHAR(255) NOT NULL DEFAULT 'default-game.png',
    game_file VARCHAR(255) NOT NULL,
    category ENUM('action', 'puzzle', 'word', 'memory', 'quiz', 'arcade') DEFAULT 'arcade',
    points_reward INT UNSIGNED DEFAULT 10,
    points_multiplier DECIMAL(3,2) DEFAULT 1.00,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    min_score_for_points INT UNSIGNED DEFAULT 0,
    max_plays_per_day INT UNSIGNED DEFAULT 10,
    play_count INT UNSIGNED DEFAULT 0,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    featured TINYINT(1) DEFAULT 0,
    sort_order INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Unique constraint
    UNIQUE KEY unique_slug (slug),
    
    -- Indexes
    INDEX idx_slug (slug),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_featured (featured),
    INDEX idx_sort_order (sort_order),
    INDEX idx_difficulty (difficulty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: scores
-- Records every game play session
-- ============================================
CREATE TABLE scores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    game_id INT UNSIGNED NOT NULL,
    score INT UNSIGNED NOT NULL DEFAULT 0,
    points_earned INT UNSIGNED DEFAULT 0,
    play_time INT UNSIGNED DEFAULT 0,
    completed TINYINT(1) DEFAULT 0,
    game_data JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    session_token VARCHAR(64) NULL,
    validated TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys
    CONSTRAINT fk_scores_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_scores_game FOREIGN KEY (game_id) 
        REFERENCES games(id) ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_game_id (game_id),
    INDEX idx_score (score DESC),
    INDEX idx_created_at (created_at),
    INDEX idx_user_game (user_id, game_id),
    INDEX idx_user_game_date (user_id, game_id, created_at),
    INDEX idx_points_earned (points_earned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: wallet
-- User point balances
-- ============================================
CREATE TABLE wallet (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    balance INT UNSIGNED DEFAULT 0,
    total_earned INT UNSIGNED DEFAULT 0,
    total_spent INT UNSIGNED DEFAULT 0,
    last_transaction_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key
    CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Unique constraint (one wallet per user)
    UNIQUE KEY unique_user_wallet (user_id),
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_balance (balance DESC),
    INDEX idx_total_earned (total_earned DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: transactions
-- All point transactions (earn/spend)
-- ============================================
CREATE TABLE transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT UNSIGNED NOT NULL,
    type ENUM('earn', 'spend', 'bonus', 'penalty', 'refund', 'adjustment') NOT NULL,
    amount INT NOT NULL,
    balance_after INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    reference_type ENUM('game', 'redemption', 'admin', 'bonus', 'other') NULL,
    reference_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key
    CONSTRAINT fk_transactions_wallet FOREIGN KEY (wallet_id) 
        REFERENCES wallet(id) ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes
    INDEX idx_wallet_id (wallet_id),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at),
    INDEX idx_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: rewards
-- Redeemable rewards/prizes
-- ============================================
CREATE TABLE rewards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    points_cost INT UNSIGNED NOT NULL,
    category ENUM('digital', 'physical', 'voucher', 'badge', 'other') DEFAULT 'digital',
    image VARCHAR(255) NULL DEFAULT 'default-reward.png',
    quantity INT NULL,
    max_per_user INT UNSIGNED DEFAULT 1,
    requires_approval TINYINT(1) DEFAULT 0,
    status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
    valid_from TIMESTAMP NULL,
    valid_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_status (status),
    INDEX idx_points_cost (points_cost),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: redemptions
-- User reward redemption history
-- ============================================
CREATE TABLE redemptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    reward_id INT UNSIGNED NOT NULL,
    points_spent INT UNSIGNED NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'fulfilled', 'cancelled') DEFAULT 'pending',
    admin_notes TEXT NULL,
    user_notes TEXT NULL,
    processed_by INT UNSIGNED NULL,
    processed_at TIMESTAMP NULL,
    fulfilled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign keys
    CONSTRAINT fk_redemptions_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_redemptions_reward FOREIGN KEY (reward_id) 
        REFERENCES rewards(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_redemptions_admin FOREIGN KEY (processed_by) 
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_reward_id (reward_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: daily_play_limits
-- Track daily plays per user per game
-- ============================================
CREATE TABLE daily_play_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    game_id INT UNSIGNED NOT NULL,
    play_date DATE NOT NULL,
    play_count INT UNSIGNED DEFAULT 1,
    points_earned INT UNSIGNED DEFAULT 0,
    
    -- Foreign keys
    CONSTRAINT fk_daily_limits_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_daily_limits_game FOREIGN KEY (game_id) 
        REFERENCES games(id) ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Unique constraint
    UNIQUE KEY unique_user_game_date (user_id, game_id, play_date),
    
    -- Indexes
    INDEX idx_play_date (play_date),
    INDEX idx_user_game (user_id, game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: achievements
-- Achievement/badge definitions
-- ============================================
CREATE TABLE achievements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    badge_image VARCHAR(255) NULL DEFAULT 'default-badge.png',
    criteria_type ENUM('total_score', 'games_played', 'points_earned', 'streak', 'specific_game', 'custom') NOT NULL,
    criteria_value INT UNSIGNED NOT NULL,
    criteria_game_id INT UNSIGNED NULL,
    points_bonus INT UNSIGNED DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key (optional, for game-specific achievements)
    CONSTRAINT fk_achievements_game FOREIGN KEY (criteria_game_id) 
        REFERENCES games(id) ON DELETE SET NULL ON UPDATE CASCADE,
    
    -- Indexes
    INDEX idx_status (status),
    INDEX idx_criteria_type (criteria_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: user_achievements
-- Tracks which achievements users have earned
-- ============================================
CREATE TABLE user_achievements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    achievement_id INT UNSIGNED NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys
    CONSTRAINT fk_user_achievements_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_user_achievements_achievement FOREIGN KEY (achievement_id) 
        REFERENCES achievements(id) ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Unique constraint (user can only earn each achievement once)
    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_achievement_id (achievement_id),
    INDEX idx_earned_at (earned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: sessions
-- For secure session handling (optional)
-- ============================================
CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload TEXT NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    
    -- Foreign key
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: activity_log
-- For admin monitoring and security
-- ============================================
CREATE TABLE activity_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    extra_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key
    CONSTRAINT fk_activity_log_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: settings
-- Application settings (key-value store)
-- ============================================
CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Unique constraint
    UNIQUE KEY unique_setting_key (setting_key),
    
    -- Index
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: password_resets
-- For password reset functionality
-- ============================================
CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    
    -- Indexes
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Create triggers for automatic updates
-- ============================================

-- Trigger: Update game play_count when score is added
DELIMITER //
CREATE TRIGGER after_score_insert
AFTER INSERT ON scores
FOR EACH ROW
BEGIN
    UPDATE games SET play_count = play_count + 1 WHERE id = NEW.game_id;
END//
DELIMITER ;

-- ============================================
-- Create views for common queries
-- ============================================

-- View: Leaderboard by total points
CREATE OR REPLACE VIEW v_leaderboard_points AS
SELECT 
    u.id,
    u.username,
    u.avatar,
    w.balance,
    w.total_earned,
    (SELECT COUNT(*) FROM scores WHERE user_id = u.id) as games_played,
    (SELECT MAX(created_at) FROM scores WHERE user_id = u.id) as last_played
FROM users u
JOIN wallet w ON u.id = w.user_id
WHERE u.status = 'active' AND u.role = 'player'
ORDER BY w.total_earned DESC;

-- View: Game statistics
CREATE OR REPLACE VIEW v_game_stats AS
SELECT 
    g.id,
    g.title,
    g.slug,
    g.category,
    g.difficulty,
    g.play_count,
    g.points_reward,
    COALESCE(AVG(s.score), 0) as avg_score,
    COALESCE(MAX(s.score), 0) as high_score,
    COALESCE(SUM(s.points_earned), 0) as total_points_awarded,
    COUNT(DISTINCT s.user_id) as unique_players
FROM games g
LEFT JOIN scores s ON g.id = s.game_id
GROUP BY g.id, g.title, g.slug, g.category, g.difficulty, g.play_count, g.points_reward;

-- ============================================
-- Done! Schema created successfully.
-- ============================================