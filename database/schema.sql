-- =========================================================================
-- BEASTz BATTLE - TiDB CLOUD PRODUCTION DATABASE SCHEMA
-- Fully Optimized for Low-Latency Cloud RDBMS & Esports Tournaments
-- Monetary Values Enforced via DECIMAL(12,2)
-- Duo / Squad Dedicated Multi-Player Roster Columns
-- =========================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. USERS TABLE (Players & Gamers)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(64) NOT NULL UNIQUE,
    `name` VARCHAR(128) DEFAULT NULL,
    `email` VARCHAR(128) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(32) NOT NULL DEFAULT 'user',
    `status` VARCHAR(32) NOT NULL DEFAULT 'active',
    `wallet_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `winning_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `avatar` TEXT DEFAULT NULL,
    `game_name` VARCHAR(64) DEFAULT NULL,
    `game_id` VARCHAR(64) DEFAULT NULL,
    `upi_id` VARCHAR(128) DEFAULT NULL,
    `player_id` VARCHAR(191) DEFAULT NULL,
    `remember_token` VARCHAR(128) DEFAULT NULL,
    `referral_code` VARCHAR(32) DEFAULT NULL UNIQUE,
    `referred_by` VARCHAR(32) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_users_role` (`role`),
    KEY `idx_users_status` (`status`),
    KEY `idx_users_remember` (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ADMIN TABLE (Master Admin & Match Hosts/Workers)
CREATE TABLE IF NOT EXISTS `admin` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(64) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(32) NOT NULL DEFAULT 'host_admin',
    `status` VARCHAR(32) NOT NULL DEFAULT 'active',
    `wallet_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `rooms_hosted` INT NOT NULL DEFAULT 0,
    `upi_id` VARCHAR(128) DEFAULT NULL,
    `player_id` VARCHAR(191) DEFAULT NULL,
    `remember_token` VARCHAR(128) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_admin_role` (`role`),
    KEY `idx_admin_remember` (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TOURNAMENTS TABLE (Matches & Custom Rooms)
CREATE TABLE IF NOT EXISTS `tournaments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `game` VARCHAR(64) NOT NULL DEFAULT 'Free Fire',
    `game_name` VARCHAR(64) NOT NULL DEFAULT 'Free Fire',
    `map` VARCHAR(64) NOT NULL DEFAULT 'BERMUDA',
    `match_type` VARCHAR(64) NOT NULL DEFAULT 'SOLO BR',
    `entry_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `prize_pool` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `per_kill` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_slots` INT NOT NULL DEFAULT 48,
    `match_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `room_id` VARCHAR(64) DEFAULT NULL,
    `room_password` VARCHAR(64) DEFAULT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'Upcoming',
    `created_by` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_tourneys_status` (`status`),
    KEY `idx_tourneys_match_time` (`match_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. PARTICIPANTS TABLE (Room Slot Bookings with Duo & Squad Multi-Player Columns)
CREATE TABLE IF NOT EXISTS `participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tournament_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `slot_no` INT NOT NULL DEFAULT 1,
    `in_game_name` VARCHAR(255) NOT NULL,
    `game_id` VARCHAR(255) NOT NULL,
    `game_name` VARCHAR(64) NOT NULL DEFAULT 'Free Fire',
    -- Dedicated Duo & Squad Columns
    `player_1_name` VARCHAR(128) DEFAULT NULL,
    `player_1_uid` VARCHAR(64) DEFAULT NULL,
    `player_2_name` VARCHAR(128) DEFAULT NULL,
    `player_2_uid` VARCHAR(64) DEFAULT NULL,
    `player_3_name` VARCHAR(128) DEFAULT NULL,
    `player_3_uid` VARCHAR(64) DEFAULT NULL,
    `player_4_name` VARCHAR(128) DEFAULT NULL,
    `player_4_uid` VARCHAR(64) DEFAULT NULL,
    `kills` INT NOT NULL DEFAULT 0,
    `rank_position` INT NOT NULL DEFAULT 0,
    `payout_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `u_tourney_slot` (`tournament_id`, `slot_no`),
    UNIQUE KEY `u_tourney_user` (`tournament_id`, `user_id`),
    KEY `idx_parts_user` (`user_id`),
    KEY `idx_parts_slot` (`slot_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. USER_TOURNAMENTS TABLE (Matches Enrolled)
CREATE TABLE IF NOT EXISTS `user_tournaments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `tournament_id` INT NOT NULL,
    `game_name` VARCHAR(128) DEFAULT NULL,
    `slot_no` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ut_user` (`user_id`),
    KEY `idx_ut_tourney` (`tournament_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TRANSACTIONS TABLE (User Passbook Ledger)
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `type` ENUM('credit','debit') NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_tx_user` (`user_id`),
    KEY `idx_tx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. DEPOSITS TABLE (Manual UPI UTR Requests)
CREATE TABLE IF NOT EXISTS `deposits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `transaction_id` VARCHAR(128) NOT NULL UNIQUE,
    `status` VARCHAR(32) NOT NULL DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_dep_user` (`user_id`),
    KEY `idx_dep_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. WITHDRAWALS TABLE (Payout Requests)
CREATE TABLE IF NOT EXISTS `withdrawals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `upi_id` VARCHAR(128) NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'Pending',
    `is_admin_request` TINYINT(1) NOT NULL DEFAULT 0,
    `admin_id` INT DEFAULT NULL,
    `player_id` VARCHAR(191) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_wdr_user` (`user_id`),
    KEY `idx_wdr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. CASHFREE_ORDERS TABLE (Automated PG Verification)
CREATE TABLE IF NOT EXISTS `cashfree_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` VARCHAR(64) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `order_amount` DECIMAL(12,2) NOT NULL,
    `order_currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
    `order_status` VARCHAR(32) NOT NULL DEFAULT 'PENDING',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_cf_user` (`user_id`),
    KEY `idx_cf_status` (`order_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. SETTINGS TABLE (Platform Configurations, Maintenance & Force Update)
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(64) NOT NULL UNIQUE,
    `value` TEXT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. USER_DEVICES TABLE (OneSignal Push Tokens)
CREATE TABLE IF NOT EXISTS `user_devices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `player_id` VARCHAR(191) NOT NULL,
    `platform` VARCHAR(50) DEFAULT 'android',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `u_user_player` (`user_id`, `player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. APP_NOTIFICATIONS TABLE (Broadcast & In-App Alerts)
CREATE TABLE IF NOT EXISTS `app_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `target_url` VARCHAR(255) DEFAULT '/?tab=home',
    `badge_type` VARCHAR(64) DEFAULT 'ALERT',
    `user_id` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. ANTI_CHEAT_LOGS TABLE
CREATE TABLE IF NOT EXISTS `anti_cheat_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tournament_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `event_type` VARCHAR(64) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ac_tourney` (`tournament_id`),
    KEY `idx_ac_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Settings
INSERT INTO `settings` (`key`, `value`) VALUES
('maintenance_mode', '0'),
('apk_force_update', '0'),
('apk_min_version', '1.0.0'),
('apk_download_url', 'https://github.com/bnoorshaikh99887-alt/beast-battle/releases/latest'),
('min_deposit', '10.00'),
('min_withdrawal', '50.00'),
('whatsapp_support', '9878385810'),
('upi_name', 'BEASTz BATTLE')
ON DUPLICATE KEY UPDATE `key` = `key`;

SET FOREIGN_KEY_CHECKS = 1;
