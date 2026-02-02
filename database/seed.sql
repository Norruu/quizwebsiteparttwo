-- ============================================
-- GAME LIBRARY SEED DATA
-- Version: 1.0
--
-- Run this file AFTER schema.sql
-- This inserts initial data for the application
-- ============================================

-- USE game_library;

-- ============================================
-- Insert default admin user
-- Password: Admin@123 (bcrypt hashed)
-- ============================================
INSERT INTO users (username, email, password, role, status, email_verified_at, created_at) VALUES 
('admin', 'admin@gamelibrary.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4z/VBL8M8WvLqKSe', 'admin', 'active', NOW(), NOW());

-- ============================================
-- Insert demo players
-- Password for all: Player@123
-- Hash: $2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ============================================
INSERT INTO users (username, email, password, role, status, email_verified_at, created_at) VALUES 
('player1', 'player1@gamelibrary.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'active', NOW(), NOW()),
('player2', 'player2@gamelibrary.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'active', NOW(), NOW()),
('gamer_pro', 'gamer@gamelibrary.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'active', NOW(), NOW()),
('newbie', 'newbie@gamelibrary.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'active', NOW(), NOW());

-- ============================================
-- Create wallets for all users
-- ============================================
INSERT INTO wallet (user_id, balance, total_earned, total_spent, created_at) VALUES 
(1, 10000, 10000, 0, NOW()),  -- Admin with lots of points for testing
(2, 500, 750, 250, NOW()),    -- player1
(3, 1200, 1500, 300, NOW()),  -- player2
(4, 3500, 4000, 500, NOW()),  -- gamer_pro
(5, 100, 100, 0, NOW());      -- newbie (just welcome bonus)

-- ============================================
-- Record initial transactions
-- ============================================
INSERT INTO transactions (wallet_id, type, amount, balance_after, description, reference_type, created_at) VALUES
-- Admin
(1, 'bonus', 10000, 10000, 'Admin initial balance', 'admin', NOW()),
-- player1
(2, 'bonus', 100, 100, 'Welcome bonus for new registration', 'bonus', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 'earn', 400, 500, 'Earned from playing Fruit Catch (Score: 850)', 'game', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(2, 'earn', 250, 750, 'Earned from playing Quiz Master (Score: 420)', 'game', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(2, 'spend', -250, 500, 'Redeemed: Bronze Badge', 'redemption', DATE_SUB(NOW(), INTERVAL 5 DAY)),
-- player2
(3, 'bonus', 100, 100, 'Welcome bonus for new registration', 'bonus', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(3, 'earn', 600, 700, 'Earned from playing Memory Match (Score: 1200)', 'game', DATE_SUB(NOW(), INTERVAL 14 DAY)),
(3, 'earn', 500, 1200, 'Earned from playing Sliding Puzzle (Score: 980)', 'game', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(3, 'earn', 300, 1500, 'Earned from playing Word Scramble (Score: 650)', 'game', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(3, 'spend', -300, 1200, 'Redeemed: Silver Badge', 'redemption', DATE_SUB(NOW(), INTERVAL 7 DAY)),
-- gamer_pro
(4, 'bonus', 100, 100, 'Welcome bonus for new registration', 'bonus', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(4, 'earn', 900, 1000, 'Earned from playing Fruit Catch (Score: 2500)', 'game', DATE_SUB(NOW(), INTERVAL 28 DAY)),
(4, 'earn', 800, 1800, 'Earned from playing Quiz Master (Score: 950)', 'game', DATE_SUB(NOW(), INTERVAL 25 DAY)),
(4, 'earn', 700, 2500, 'Earned from playing Memory Match (Score: 1800)', 'game', DATE_SUB(NOW(), INTERVAL 22 DAY)),
(4, 'earn', 1000, 3500, 'Earned from playing Sliding Puzzle (Score: 3200)', 'game', DATE_SUB(NOW(), INTERVAL 18 DAY)),
(4, 'earn', 500, 4000, 'Earned from playing Word Scramble (Score: 890)', 'game', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(4, 'spend', -500, 3500, 'Redeemed: Premium Avatar Pack', 'redemption', DATE_SUB(NOW(), INTERVAL 10 DAY)),
-- newbie
(5, 'bonus', 100, 100, 'Welcome bonus for new registration', 'bonus', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ============================================
-- Insert games
-- ============================================
INSERT INTO games (
    title, slug, description, instructions, thumbnail, game_file, 
    category, points_reward, difficulty, min_score_for_points, 
    max_plays_per_day, play_count, status, featured, sort_order, created_at
) VALUES 

-- Game 1: Fruit Catch
(
    'Fruit Catch', 
    'fruit-catch', 
    'Catch falling fruits in your basket! The more you catch, the higher your score. Watch out for rotten fruits - they will cost you lives! Collect special golden fruits for bonus points.',
    'Use LEFT and RIGHT arrow keys or move your mouse to control the basket. On mobile, touch and drag to move. Catch fresh fruits to earn points. Avoid the skull (💀) rotten fruits - catching them costs a life! Catch the diamond (💎) for bonus points. Game ends when you lose all 3 lives.',
    'fruit-catch.png', 
    'fruit-catch.php', 
    'arcade', 
    15,      -- base points reward
    'easy', 
    50,      -- minimum score for points
    20,      -- max plays per day
    1250,    -- initial play count
    'active', 
    1,       -- featured
    1,       -- sort order
    NOW()
),

-- Game 2: Word Scramble
(
    'Word Scramble', 
    'word-scramble', 
    'Unscramble the letters to form words before time runs out! Test your vocabulary and quick thinking skills. The faster you solve, the more points you earn!',
    'Look at the scrambled letters displayed on screen. Type the correct word using your keyboard. Press ENTER to submit your answer. Complete as many words as possible before the 60-second timer runs out. Correct answers add bonus time. Build streaks for multiplier bonuses!',
    'word-scramble.png', 
    'word-scramble.php', 
    'word', 
    20, 
    'medium', 
    100, 
    15, 
    890,
    'active', 
    1, 
    2,
    NOW()
),

-- Game 3: Quiz Master
(
    'Quiz Master', 
    'quiz-master', 
    'Test your general knowledge with our trivia quiz! Answer multiple choice questions correctly to score points. Challenge yourself across various categories including science, history, geography, and more.',
    'Read each question carefully. Click on one of the four answer options or press 1-4 on your keyboard. You have 15 seconds per question. Correct answers earn points - faster answers earn bonus points! Complete all 10 questions to finish the quiz.',
    'quiz-master.png', 
    'quiz-master.php', 
    'quiz', 
    25, 
    'medium', 
    60, 
    10, 
    2100,
    'active', 
    1, 
    3,
    NOW()
),

-- Game 4: Sliding Puzzle
(
    'Sliding Puzzle', 
    'sliding-puzzle', 
    'Arrange the tiles in the correct order by sliding them! A classic 15-puzzle game that tests your problem-solving skills and spatial reasoning. Can you solve it in the fewest moves?',
    'Click on a tile adjacent to the empty space to slide it. Alternatively, use arrow keys to move tiles. Arrange all tiles in numerical order (1-15) with the empty space in the bottom-right corner. Fewer moves and faster completion = higher score!',
    'sliding-puzzle.png', 
    'sliding-puzzle.php', 
    'puzzle', 
    30, 
    'hard', 
    1,       -- any completion earns points
    10, 
    650,
    'active', 
    1, 
    4,
    NOW()
),

-- Game 5: Memory Match
(
    'Memory Match', 
    'memory-match', 
    'Find matching pairs of cards! Test your memory and concentration in this classic card matching game. Flip cards to reveal hidden symbols and remember their positions to find matches.',
    'Click on cards to flip them over. Find all matching pairs by remembering card positions. Match all 8 pairs to complete the game. Fewer moves = higher score! Time bonus awarded for quick completion.',
    'memory-match.png', 
    'memory-match.php', 
    'memory', 
    20, 
    'easy', 
    1, 
    15, 
    1800,
    'active', 
    1, 
    5,
    NOW()
),

-- Additional games (inactive by default - for future use)
(
    'Snake Classic', 
    'snake-classic', 
    'The classic snake game! Eat food to grow longer, but don''t hit the walls or yourself. How long can you survive?',
    'Use arrow keys to control the snake direction. Eat the food to grow and earn points. Avoid hitting walls and your own tail!',
    'snake-classic.png', 
    'snake-classic.php', 
    'arcade', 
    15, 
    'medium', 
    100, 
    20, 
    0,
    'inactive', 
    0, 
    10,
    NOW()
),

(
    'Math Challenge', 
    'math-challenge', 
    'Test your arithmetic skills! Solve math problems as fast as you can. Addition, subtraction, multiplication, and division await!',
    'Solve the math problem displayed on screen. Type your answer and press ENTER. Answer as many questions as you can before time runs out!',
    'math-challenge.png', 
    'math-challenge.php', 
    'quiz', 
    25, 
    'medium', 
    80, 
    15, 
    0,
    'inactive', 
    0, 
    11,
    NOW()
);

-- ============================================
-- Insert sample scores (for demo leaderboard)
-- ============================================
INSERT INTO scores (user_id, game_id, score, points_earned, play_time, completed, created_at) VALUES
-- player1 scores
(2, 1, 850, 40, 125, 1, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(2, 3, 420, 25, 180, 1, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(2, 1, 620, 30, 98, 1, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(2, 5, 1100, 45, 145, 1, DATE_SUB(NOW(), INTERVAL 6 DAY)),

-- player2 scores
(3, 5, 1200, 50, 132, 1, DATE_SUB(NOW(), INTERVAL 14 DAY)),
(3, 4, 980, 55, 245, 1, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(3, 2, 650, 35, 60, 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(3, 1, 1100, 45, 156, 1, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(3, 3, 580, 30, 195, 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),

-- gamer_pro scores (high scores)
(4, 1, 2500, 75, 180, 1, DATE_SUB(NOW(), INTERVAL 28 DAY)),
(4, 3, 950, 60, 200, 1, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(4, 5, 1800, 65, 95, 1, DATE_SUB(NOW(), INTERVAL 22 DAY)),
(4, 4, 3200, 80, 180, 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(4, 2, 890, 45, 60, 1, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(4, 1, 3100, 85, 210, 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(4, 3, 880, 55, 190, 1, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(4, 5, 2200, 70, 88, 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),

-- newbie scores (just started)
(5, 1, 320, 15, 65, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 5, 450, 20, 180, 1, NOW());

-- ============================================
-- Insert rewards
-- ============================================
INSERT INTO rewards (
    name, description, points_cost, category, image, 
    quantity, max_per_user, requires_approval, status, created_at
) VALUES 

-- Badge Rewards
(
    'Bronze Badge', 
    'A shiny bronze badge for your profile! Shows you''re getting started on your gaming journey.',
    100, 
    'badge', 
    'badge-bronze.png', 
    NULL,    -- unlimited quantity
    1,       -- max 1 per user
    0,       -- no approval needed
    'active',
    NOW()
),
(
    'Silver Badge', 
    'A prestigious silver badge showing your dedication to gaming! Displayed proudly on your profile.',
    500, 
    'badge', 
    'badge-silver.png', 
    NULL, 
    1, 
    0, 
    'active',
    NOW()
),
(
    'Gold Badge', 
    'The ultimate gold badge for true champions! Only the most dedicated players earn this honor.',
    1000, 
    'badge', 
    'badge-gold.png', 
    NULL, 
    1, 
    0, 
    'active',
    NOW()
),
(
    'Diamond Badge', 
    'The legendary diamond badge! Extremely rare and prestigious. A symbol of gaming excellence.',
    5000, 
    'badge', 
    'badge-diamond.png', 
    NULL, 
    1, 
    0, 
    'active',
    NOW()
),

-- Digital Rewards
(
    'Premium Avatar Pack', 
    'Unlock 10 exclusive avatar designs! Stand out from the crowd with unique profile pictures.',
    2000, 
    'digital', 
    'avatar-pack.png', 
    NULL, 
    1, 
    0, 
    'active',
    NOW()
),
(
    'Custom Username Color', 
    'Stand out with a colored username on leaderboards! Choose from rainbow, gold, or neon effects.',
    3000, 
    'digital', 
    'username-color.png', 
    NULL, 
    1, 
    0, 
    'active',
    NOW()
),
(
    'Profile Banner', 
    'Customize your profile with an exclusive animated banner! Multiple designs available.',
    1500, 
    'digital', 
    'profile-banner.png', 
    NULL, 
    1, 
    0, 
    'active',
    NOW()
),
(
    'Double Points Boost (24hr)', 
    'Earn double points on all games for 24 hours! Perfect for climbing the leaderboard fast.',
    2500, 
    'digital', 
    'double-points.png', 
    NULL, 
    10,      -- can redeem up to 10 times
    0, 
    'active',
    NOW()
),

-- Voucher Rewards
(
    '$5 Gift Card', 
    'A $5 digital gift card for popular online stores. Valid for 1 year from redemption.',
    5000, 
    'voucher', 
    'giftcard-5.png', 
    100,     -- limited quantity
    3,       -- max 3 per user
    1,       -- requires admin approval
    'active',
    NOW()
),
(
    '$10 Gift Card', 
    'A $10 digital gift card for popular online stores. Valid for 1 year from redemption.',
    9000, 
    'voucher', 
    'giftcard-10.png', 
    50, 
    2, 
    1, 
    'active',
    NOW()
),
(
    '$25 Gift Card', 
    'A $25 digital gift card for popular online stores. Valid for 1 year from redemption.',
    20000, 
    'voucher', 
    'giftcard-25.png', 
    20, 
    1, 
    1, 
    'active',
    NOW()
),

-- Physical Rewards (example, requires approval)
(
    'Game Library T-Shirt', 
    'An exclusive Game Library branded t-shirt! Available in S, M, L, XL sizes. Shipping included.',
    15000, 
    'physical', 
    'tshirt.png', 
    50, 
    2, 
    1,       -- requires approval
    'active',
    NOW()
),
(
    'Gaming Mouse Pad', 
    'Large gaming mouse pad with Game Library design. Perfect for your gaming setup!',
    8000, 
    'physical', 
    'mousepad.png', 
    100, 
    2, 
    1, 
    'active',
    NOW()
);

-- ============================================
-- Insert sample redemptions
-- ============================================
INSERT INTO redemptions (user_id, reward_id, points_spent, status, created_at, processed_at) VALUES
(2, 1, 100, 'fulfilled', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),  -- player1 got Bronze Badge
(3, 2, 500, 'fulfilled', DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY)),  -- player2 got Silver Badge
(4, 5, 2000, 'fulfilled', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY)); -- gamer_pro got Avatar Pack

-- ============================================
-- Insert achievements
-- ============================================
INSERT INTO achievements (
    name, description, badge_image, criteria_type, 
    criteria_value, criteria_game_id, points_bonus, status, created_at
) VALUES 
-- General achievements
(
    'First Steps', 
    'Play your first game! Everyone starts somewhere.', 
    'ach-first-game.png', 
    'games_played', 
    1, 
    NULL, 
    10, 
    'active',
    NOW()
),
(
    'Getting Started', 
    'Play 10 games total. You''re getting the hang of this!', 
    'ach-10-games.png', 
    'games_played', 
    10, 
    NULL, 
    25, 
    'active',
    NOW()
),
(
    'Dedicated Player', 
    'Play 50 games total. Gaming is in your blood!', 
    'ach-50-games.png', 
    'games_played', 
    50, 
    NULL, 
    100, 
    'active',
    NOW()
),
(
    'Gaming Legend', 
    'Play 100 games total. You''re a true gaming legend!', 
    'ach-100-games.png', 
    'games_played', 
    100, 
    NULL, 
    250, 
    'active',
    NOW()
),
(
    'Point Collector', 
    'Earn 500 total points. Building your fortune!', 
    'ach-500-points.png', 
    'points_earned', 
    500, 
    NULL, 
    50, 
    'active',
    NOW()
),
(
    'Point Master', 
    'Earn 2,000 total points. You''re rich in points!', 
    'ach-2000-points.png', 
    'points_earned', 
    2000, 
    NULL, 
    100, 
    'active',
    NOW()
),
(
    'Point Tycoon', 
    'Earn 10,000 total points. A true points mogul!', 
    'ach-10000-points.png', 
    'points_earned', 
    10000, 
    NULL, 
    500, 
    'active',
    NOW()
),
(
    'High Scorer', 
    'Get a score of 1,000 or more in any single game.', 
    'ach-high-score.png', 
    'total_score', 
    1000, 
    NULL, 
    75, 
    'active',
    NOW()
),
(
    'Score Champion', 
    'Get a score of 2,500 or more in any single game.', 
    'ach-score-champion.png', 
    'total_score', 
    2500, 
    NULL, 
    150, 
    'active',
    NOW()
),
(
    'Score Legend', 
    'Get a score of 5,000 or more in any single game. Incredible!', 
    'ach-score-legend.png', 
    'total_score', 
    5000, 
    NULL, 
    300, 
    'active',
    NOW()
),

-- Game-specific achievements
(
    'Fruit Frenzy', 
    'Score 2,000+ in Fruit Catch. Fruit catching master!', 
    'ach-fruit-frenzy.png', 
    'specific_game', 
    2000, 
    1,       -- game_id for Fruit Catch
    100, 
    'active',
    NOW()
),
(
    'Word Wizard', 
    'Score 800+ in Word Scramble. Your vocabulary is impressive!', 
    'ach-word-wizard.png', 
    'specific_game', 
    800, 
    2,       -- game_id for Word Scramble
    100, 
    'active',
    NOW()
),
(
    'Quiz Genius', 
    'Score 900+ in Quiz Master (90%+ accuracy). Brilliant!', 
    'ach-quiz-genius.png', 
    'specific_game', 
    900, 
    3,       -- game_id for Quiz Master
    125, 
    'active',
    NOW()
),
(
    'Puzzle Prodigy', 
    'Score 5,000+ in Sliding Puzzle (fast completion). Amazing!', 
    'ach-puzzle-prodigy.png', 
    'specific_game', 
    5000, 
    4,       -- game_id for Sliding Puzzle
    150, 
    'active',
    NOW()
),
(
    'Memory Master', 
    'Score 2,000+ in Memory Match. Perfect recall!', 
    'ach-memory-master.png', 
    'specific_game', 
    2000, 
    5,       -- game_id for Memory Match
    100, 
    'active',
    NOW()
);

-- ============================================
-- Award some achievements to demo users
-- ============================================
INSERT INTO user_achievements (user_id, achievement_id, earned_at) VALUES
-- player1 achievements
(2, 1, DATE_SUB(NOW(), INTERVAL 9 DAY)),  -- First Steps
(2, 5, DATE_SUB(NOW(), INTERVAL 7 DAY)),  -- Point Collector

-- player2 achievements
(3, 1, DATE_SUB(NOW(), INTERVAL 14 DAY)), -- First Steps
(3, 2, DATE_SUB(NOW(), INTERVAL 10 DAY)), -- Getting Started
(3, 5, DATE_SUB(NOW(), INTERVAL 8 DAY)),  -- Point Collector
(3, 8, DATE_SUB(NOW(), INTERVAL 12 DAY)), -- High Scorer

-- gamer_pro achievements (lots!)
(4, 1, DATE_SUB(NOW(), INTERVAL 30 DAY)), -- First Steps
(4, 2, DATE_SUB(NOW(), INTERVAL 25 DAY)), -- Getting Started
(4, 5, DATE_SUB(NOW(), INTERVAL 22 DAY)), -- Point Collector
(4, 6, DATE_SUB(NOW(), INTERVAL 15 DAY)), -- Point Master
(4, 8, DATE_SUB(NOW(), INTERVAL 28 DAY)), -- High Scorer
(4, 9, DATE_SUB(NOW(), INTERVAL 18 DAY)), -- Score Champion
(4, 11, DATE_SUB(NOW(), INTERVAL 10 DAY)), -- Fruit Frenzy
(4, 15, DATE_SUB(NOW(), INTERVAL 22 DAY)), -- Memory Master

-- newbie achievements
(5, 1, DATE_SUB(NOW(), INTERVAL 1 DAY));  -- First Steps

-- ============================================
-- Insert default settings
-- ============================================
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'Game Library', 'string', 'The name of the website'),
('site_description', 'Play games, earn points, win rewards!', 'string', 'Site meta description'),
('welcome_bonus', '100', 'integer', 'Points given to new users'),
('max_daily_plays_default', '10', 'integer', 'Default max plays per game per day'),
('maintenance_mode', 'false', 'boolean', 'Enable maintenance mode'),
('registration_enabled', 'true', 'boolean', 'Allow new user registration'),
('leaderboard_limit', '100', 'integer', 'Maximum entries shown on leaderboard'),
('min_password_length', '8', 'integer', 'Minimum password length for registration'),
('points_expiry_days', '0', 'integer', '0 = never expire, otherwise days until points expire'),
('contact_email', 'support@gamelibrary.com', 'string', 'Support contact email');

-- ============================================
-- Insert activity log samples
-- ============================================
INSERT INTO activity_log (user_id, action, description, ip_address, created_at) VALUES
(1, 'login', 'Admin logged in', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 'login', 'User logged in', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 'game_played', 'Played Fruit Catch - Score: 850', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(3, 'login', 'User logged in', '192.168.1.101', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(4, 'register', 'New user registered', '192.168.1.102', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(4, 'login', 'User logged in', '192.168.1.102', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(4, 'redemption', 'Redeemed: Premium Avatar Pack', '192.168.1.102', DATE_SUB(NOW(), INTERVAL 10 DAY));

-- ============================================
-- Summary of test accounts created:
-- ============================================
-- 
-- ADMIN ACCOUNT:
-- Email: admin@gamelibrary.com
-- Password: Admin@123
-- Points: 10,000
--
-- PLAYER ACCOUNTS:
-- Email: player1@gamelibrary.com | Password: Player@123 | Points: 500
-- Email: player2@gamelibrary.com | Password: Player@123 | Points: 1,200
-- Email: gamer@gamelibrary.com   | Password: Player@123 | Points: 3,500
-- Email: newbie@gamelibrary.com  | Password: Player@123 | Points: 100
--
-- ============================================
-- Seed data inserted successfully!
-- ============================================