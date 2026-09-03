    -- Unfiltered Challenge App Database Schema
    -- Database: u389024941_challenge
    -- Run this script to create all required tables

    SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
    START TRANSACTION;
    SET time_zone = "+00:00";

    -- --------------------------------------------------------
    -- Table: users
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `email` VARCHAR(255) NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `first_name` VARCHAR(100) DEFAULT NULL,
        `last_name` VARCHAR(100) DEFAULT NULL,
        `dob` DATE DEFAULT NULL,
        `profile_pic` VARCHAR(255) DEFAULT NULL,
        `profile_bio` TEXT DEFAULT NULL,
        `profile_prompt_key` VARCHAR(50) DEFAULT NULL,
        `profile_prompt_answer` TEXT DEFAULT NULL,
        `profile_visible` TINYINT(1) NOT NULL DEFAULT 0,
        `profile_banner_x` TINYINT UNSIGNED NOT NULL DEFAULT 50,
        `profile_banner_y` TINYINT UNSIGNED NOT NULL DEFAULT 50,
        `profile_banner_zoom` DECIMAL(4,2) NOT NULL DEFAULT 1.00,
        `profile_banner_text_color` VARCHAR(7) DEFAULT NULL,
        `public_profile_slug` VARCHAR(48) DEFAULT NULL,
        `last_active_at` DATETIME DEFAULT NULL,
        `timezone` VARCHAR(64) NOT NULL DEFAULT 'America/New_York',
        `weight_lbs` DECIMAL(5,1) DEFAULT NULL,
        `height_inches` INT DEFAULT NULL,
        `age` INT DEFAULT NULL,
        `bmi` DECIMAL(4,1) DEFAULT NULL,
        `daily_water_oz` INT DEFAULT NULL,
        `water_bottle_oz` INT NOT NULL DEFAULT 24,
        `journal_in_app` TINYINT(1) NOT NULL DEFAULT 1,
        `chat_bubble_color` VARCHAR(7) DEFAULT '#6366f1',
        `daily_reminder_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `daily_reminder_time` TIME NOT NULL DEFAULT '18:00:00',
        `streak_risk_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `streak_repairs` INT NOT NULL DEFAULT 3,
        `challenge_mode` VARCHAR(20) NOT NULL DEFAULT 'intermediate',
        `calm_points` INT NOT NULL DEFAULT 0,
        `total_calm_points` INT NOT NULL DEFAULT 0,
        `equipped_background` VARCHAR(64) DEFAULT NULL,
        `equipped_banner_pattern` VARCHAR(64) DEFAULT NULL,
        `equipped_frame` VARCHAR(64) DEFAULT NULL,
        `equipped_stickers` TEXT DEFAULT NULL,
        `equipped_avatar` TEXT DEFAULT NULL,
        `avatar_public_face` TINYINT(1) NOT NULL DEFAULT 0,
        `onboarding_completed` TINYINT(1) NOT NULL DEFAULT 0,
        `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
        `admin_role` VARCHAR(20) NOT NULL DEFAULT 'user',
        `auth_version` INT UNSIGNED NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email_unique` (`email`),
        UNIQUE KEY `idx_public_profile_slug` (`public_profile_slug`),
        INDEX `idx_email` (`email`),
        INDEX `idx_last_active_at` (`last_active_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: user_streaks
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `user_streaks` (
        `user_id` INT UNSIGNED NOT NULL,
        `current_streak` INT NOT NULL DEFAULT 0,
        `longest_streak` INT NOT NULL DEFAULT 0,
        `last_completed_date` DATE DEFAULT NULL,
        `streak_updated_at_utc` DATETIME DEFAULT NULL,
        `freeze_count` INT NOT NULL DEFAULT 0,
        `freeze_used_on_date` DATE DEFAULT NULL,
        PRIMARY KEY (`user_id`),
        CONSTRAINT `fk_streaks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: daily_checklist_items
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `daily_checklist_items` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `icon` VARCHAR(50) DEFAULT NULL,
        `is_required` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `item_type` VARCHAR(50) DEFAULT 'checkbox',
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Seed: daily_checklist_items (7 items)
    -- Using INSERT IGNORE to skip if items already exist
    -- --------------------------------------------------------
    INSERT IGNORE INTO `daily_checklist_items` (`id`, `name`, `description`, `icon`, `is_required`, `sort_order`, `item_type`) VALUES
    (1, 'Drink Water Goal', 'Drink your recommended daily water intake based on your body weight. Proper hydration improves energy, focus, and overall health.', 'water', 1, 1, 'water_tracker'),
    (2, 'Read Scripture', 'Read a chapter in scripture. Daily spiritual reading provides wisdom, peace, and guidance for life''s challenges.', 'book', 1, 2, 'checkbox'),
    (3, 'Workout 30 Minutes', 'Complete at least 30 minutes of physical exercise. Regular exercise boosts mood, energy, and long-term health.', 'fitness', 1, 3, 'checkbox'),
    (4, 'Journal Entry', 'Write in your journal and track your mood. Journaling helps process emotions and maintain mental clarity.', 'journal', 1, 4, 'mood_tracker'),
    (5, 'Encourage/Reach out to a Friend', 'Reach out and encourage someone today. Building connections strengthens relationships and spreads positivity.', 'heart', 1, 5, 'checkbox'),
    (6, 'No Fast Food', 'Avoid fast food for the day. Choosing whole foods fuels your body better and supports long-term wellness.', 'no-food', 1, 6, 'checkbox'),
    (7, 'No Soda/Pop', 'Avoid sugary sodas and soft drinks. Cutting out empty calories improves energy levels and overall health.', 'no-drink', 1, 7, 'checkbox');

    -- --------------------------------------------------------
    -- Table: daily_checklist_entries
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `daily_checklist_entries` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `item_id` INT UNSIGNED NOT NULL,
        `user_date` DATE NOT NULL,
        `checked_at_utc` DATETIME NOT NULL,
        `value` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_item_day` (`user_id`, `item_id`, `user_date`),
        INDEX `idx_user_date` (`user_id`, `user_date`),
        CONSTRAINT `fk_entries_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_entries_item` FOREIGN KEY (`item_id`) REFERENCES `daily_checklist_items` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: user_daily_completion
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `user_daily_completion` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `user_date` DATE NOT NULL,
        `completed_at_utc` DATETIME NOT NULL,
        `completion_score` INT NOT NULL DEFAULT 7,
        `completion_rule_version` INT NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_day` (`user_id`, `user_date`),
        INDEX `idx_user_date` (`user_id`, `user_date`),
        CONSTRAINT `fk_completion_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: water_log
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `water_log` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `user_date` DATE NOT NULL,
        `oz_amount` INT NOT NULL DEFAULT 8,
        `logged_at_utc` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        INDEX `idx_user_date` (`user_id`, `user_date`),
        CONSTRAINT `fk_water_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: workout_log
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `workout_log` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `user_date` DATE NOT NULL,
        `workout_type` VARCHAR(50) NOT NULL,
        `workout_custom` VARCHAR(100) DEFAULT NULL,
        `duration_minutes` INT NOT NULL DEFAULT 30,
        `logged_at_utc` DATETIME NOT NULL,
        `updated_at_utc` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_workout_day` (`user_id`, `user_date`),
        INDEX `idx_user_date` (`user_id`, `user_date`),
        CONSTRAINT `fk_workout_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: weight_log
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `weight_log` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `user_date` DATE NOT NULL,
        `weight_lbs` DECIMAL(5,1) NOT NULL,
        `bmi` DECIMAL(4,1) NOT NULL,
        `logged_at_utc` DATETIME NOT NULL,
        `updated_at_utc` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_weight_day` (`user_id`, `user_date`),
        INDEX `idx_user_date` (`user_id`, `user_date`),
        CONSTRAINT `fk_weight_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Weight updates now live exclusively in Weight & BMI Insights.
    UPDATE `daily_checklist_items`
    SET `active` = 0
    WHERE `id` = 8 AND `item_type` = 'weight_tracker';

    -- --------------------------------------------------------
    -- Table: mood_entries
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `mood_entries` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `user_date` DATE NOT NULL,
        `mood_level` TINYINT NOT NULL CHECK (`mood_level` BETWEEN 1 AND 10),
        `notes` TEXT,
        `created_at_utc` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_mood_day` (`user_id`, `user_date`),
        INDEX `idx_user_date` (`user_id`, `user_date`),
        CONSTRAINT `fk_mood_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: inner_circles
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `inner_circles` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `created_by` INT UNSIGNED NOT NULL,
        `invite_code` VARCHAR(20) NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `invite_code_unique` (`invite_code`),
        INDEX `idx_invite_code` (`invite_code`),
        CONSTRAINT `fk_circles_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: inner_circle_members
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `inner_circle_members` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `circle_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        `invited_by` INT UNSIGNED DEFAULT NULL,
        `role` ENUM('owner', 'member') NOT NULL DEFAULT 'member',
        `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_circle_user` (`circle_id`, `user_id`),
        INDEX `idx_user_circles` (`user_id`),
        CONSTRAINT `fk_members_circle` FOREIGN KEY (`circle_id`) REFERENCES `inner_circles` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_members_inviter` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: circle_join_requests
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `circle_join_requests` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `circle_id` INT UNSIGNED NOT NULL,
        `requester_id` INT UNSIGNED NOT NULL,
        `status` ENUM('pending', 'approved', 'denied') NOT NULL DEFAULT 'pending',
        `pending_key` CHAR(64) DEFAULT NULL,
        `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `resolved_at` DATETIME DEFAULT NULL,
        `resolved_by` INT UNSIGNED DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_pending_join_request` (`pending_key`),
        INDEX `idx_circle_status` (`circle_id`, `status`),
        INDEX `idx_requester_status` (`requester_id`, `status`),
        CONSTRAINT `fk_join_request_circle` FOREIGN KEY (`circle_id`) REFERENCES `inner_circles` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_join_request_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_join_request_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: circle_messages
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `circle_messages` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `circle_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        `message` TEXT NOT NULL,
        `message_type` ENUM('message', 'system_join', 'system_milestone') NOT NULL DEFAULT 'message',
        `created_at_utc` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_circle_time` (`circle_id`, `created_at_utc`),
        CONSTRAINT `fk_messages_circle` FOREIGN KEY (`circle_id`) REFERENCES `inner_circles` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `circle_message_reactions` (
        `message_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        `reaction` VARCHAR(20) NOT NULL DEFAULT 'heart',
        `created_at_utc` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`message_id`, `user_id`, `reaction`),
        INDEX `idx_reaction_user` (`user_id`),
        CONSTRAINT `fk_reaction_message` FOREIGN KEY (`message_id`) REFERENCES `circle_messages` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_reaction_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: invite_tracking (for streak repair rewards)
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `invite_tracking` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `inviter_id` INT UNSIGNED NOT NULL,
        `invitee_id` INT UNSIGNED NOT NULL,
        `circle_id` INT UNSIGNED DEFAULT NULL,
        `invite_code_used` VARCHAR(20) NOT NULL,
        `reward_granted` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_invitee` (`invitee_id`),
        INDEX `idx_inviter` (`inviter_id`),
        CONSTRAINT `fk_invite_inviter` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_invite_invitee` FOREIGN KEY (`invitee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_invite_circle` FOREIGN KEY (`circle_id`) REFERENCES `inner_circles` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: pending_invites (for onboarding invite codes)
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `pending_invites` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `invite_code` VARCHAR(20) NOT NULL,
        `circle_id` INT UNSIGNED NOT NULL,
        `inviter_id` INT UNSIGNED NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_invite_code` (`invite_code`),
        CONSTRAINT `fk_pending_circle` FOREIGN KEY (`circle_id`) REFERENCES `inner_circles` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_pending_inviter` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: push_subscriptions
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `push_subscriptions` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `endpoint` TEXT NOT NULL,
        `endpoint_hash` CHAR(64) NOT NULL,
        `p256dh_key` VARCHAR(255) NOT NULL,
        `auth_key` VARCHAR(255) NOT NULL,
        `content_encoding` VARCHAR(32) NOT NULL DEFAULT 'aes128gcm',
        `user_agent` VARCHAR(255) DEFAULT NULL,
        `daily_reminder_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `streak_risk_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `feed_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `last_used_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_endpoint_hash` (`endpoint_hash`),
        INDEX `idx_user_id` (`user_id`),
        CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: push_notification_log
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `push_notification_log` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `notification_type` VARCHAR(50) NOT NULL,
        `user_date` DATE NOT NULL,
        `sent_at_utc` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_type_day` (`user_id`, `notification_type`, `user_date`),
        INDEX `idx_sent_at` (`sent_at_utc`),
        CONSTRAINT `fk_push_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Table: push_broadcasts
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `push_broadcasts` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `admin_user_id` INT UNSIGNED NOT NULL,
        `title` VARCHAR(120) NOT NULL,
        `body` VARCHAR(500) NOT NULL,
        `target_url` VARCHAR(255) NOT NULL DEFAULT '/challenge/app/dashboard.php',
        `audience` VARCHAR(50) NOT NULL DEFAULT 'all_onboarded',
        `target_devices` INT NOT NULL DEFAULT 0,
        `sent_count` INT NOT NULL DEFAULT 0,
        `failed_count` INT NOT NULL DEFAULT 0,
        `created_at_utc` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        INDEX `idx_created_at` (`created_at_utc`),
        CONSTRAINT `fk_push_broadcast_admin` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Migration: Add journal_in_app column (for existing databases)
    -- --------------------------------------------------------
    -- ALTER TABLE users ADD COLUMN journal_in_app TINYINT(1) NOT NULL DEFAULT 1 AFTER daily_water_oz;

    -- --------------------------------------------------------
    -- Migration: Change mood_level from 1-5 to 1-10 (for existing databases)
    -- --------------------------------------------------------
    -- ALTER TABLE mood_entries MODIFY COLUMN mood_level TINYINT NOT NULL CHECK (mood_level BETWEEN 1 AND 10);

    -- --------------------------------------------------------
    -- Migration: Add chat_bubble_color column (for existing databases)
    -- --------------------------------------------------------
    -- ALTER TABLE users ADD COLUMN chat_bubble_color VARCHAR(7) DEFAULT '#6366f1' AFTER journal_in_app;

    -- --------------------------------------------------------
    -- Migration: Add message_type column to circle_messages (for existing databases)
    -- --------------------------------------------------------
    -- ALTER TABLE circle_messages ADD COLUMN message_type ENUM('message', 'system_join', 'system_milestone') NOT NULL DEFAULT 'message' AFTER message;

    -- --------------------------------------------------------
    -- Migration: Update streak_repairs default to 3 (for existing databases)
    -- --------------------------------------------------------
    -- ALTER TABLE users MODIFY COLUMN streak_repairs INT NOT NULL DEFAULT 3;
    -- UPDATE users SET streak_repairs = 3 WHERE streak_repairs < 3;

    -- --------------------------------------------------------
    -- Migration: Add water_bottle_oz column (for existing databases)
    -- --------------------------------------------------------
    ALTER TABLE users ADD COLUMN IF NOT EXISTS water_bottle_oz INT NOT NULL DEFAULT 24 AFTER daily_water_oz;

    -- Migration: optional weight tracking (for existing databases)
    CREATE TABLE IF NOT EXISTS `weight_log` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `user_date` DATE NOT NULL,
        `weight_lbs` DECIMAL(5,1) NOT NULL,
        `bmi` DECIMAL(4,1) NOT NULL,
        `logged_at_utc` DATETIME NOT NULL,
        `updated_at_utc` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_weight_day` (`user_id`, `user_date`),
        INDEX `idx_user_date` (`user_id`, `user_date`),
        CONSTRAINT `fk_weight_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Migration: Add public profile fields (for existing databases)
    -- --------------------------------------------------------
    ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_bio TEXT DEFAULT NULL AFTER profile_pic;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_prompt_key VARCHAR(50) DEFAULT NULL AFTER profile_bio;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_prompt_answer TEXT DEFAULT NULL AFTER profile_prompt_key;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_visible TINYINT(1) NOT NULL DEFAULT 0 AFTER profile_prompt_answer;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_banner_x TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER profile_visible;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_banner_y TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER profile_banner_x;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_banner_zoom DECIMAL(4,2) NOT NULL DEFAULT 1.00 AFTER profile_banner_y;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_banner_text_color VARCHAR(7) DEFAULT NULL AFTER profile_banner_zoom;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS public_profile_slug VARCHAR(48) DEFAULT NULL AFTER profile_banner_text_color;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS last_active_at DATETIME DEFAULT NULL AFTER public_profile_slug;

    -- --------------------------------------------------------
    -- Migration: Add admin role flag (for existing databases)
    -- --------------------------------------------------------
    ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER onboarding_completed;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS admin_role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER is_admin;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER admin_role;
    UPDATE users SET admin_role = 'super_admin' WHERE is_admin = 1 AND admin_role = 'user';

    CREATE TABLE IF NOT EXISTS `admin_audit_log` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `admin_user_id` INT UNSIGNED NOT NULL,
        `action` VARCHAR(80) NOT NULL,
        `target_user_id` INT UNSIGNED DEFAULT NULL,
        `details_json` TEXT DEFAULT NULL,
        `ip_hash` CHAR(64) DEFAULT NULL,
        `created_at_utc` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_admin_created` (`admin_user_id`, `created_at_utc`),
        INDEX `idx_target_created` (`target_user_id`, `created_at_utc`),
        CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_audit_target` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `token_hash` CHAR(64) NOT NULL,
        `created_by_admin_id` INT UNSIGNED DEFAULT NULL,
        `requested_ip_hash` CHAR(64) DEFAULT NULL,
        `expires_at_utc` DATETIME NOT NULL,
        `used_at_utc` DATETIME DEFAULT NULL,
        `created_at_utc` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_reset_token_hash` (`token_hash`),
        INDEX `idx_reset_user_created` (`user_id`, `created_at_utc`),
        INDEX `idx_reset_expiry` (`expires_at_utc`),
        CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_reset_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `password_reset_requests` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `email_hash` CHAR(64) NOT NULL,
        `ip_hash` CHAR(64) NOT NULL,
        `created_at_utc` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_reset_request_email` (`email_hash`, `created_at_utc`),
        INDEX `idx_reset_request_ip` (`ip_hash`, `created_at_utc`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `user_notification_status` (
        `user_id` INT UNSIGNED NOT NULL,
        `supported` TINYINT(1) NOT NULL DEFAULT 0,
        `permission_state` VARCHAR(20) NOT NULL DEFAULT 'unknown',
        `last_reported_at_utc` DATETIME NOT NULL,
        `user_agent` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`user_id`),
        INDEX `idx_permission` (`permission_state`),
        CONSTRAINT `fk_notification_status_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `admin_email_campaign_log` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `campaign_key` VARCHAR(80) NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `error_message` VARCHAR(255) DEFAULT NULL,
        `created_at_utc` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `sent_at_utc` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_campaign_user` (`campaign_key`, `user_id`),
        INDEX `idx_campaign_status` (`campaign_key`, `status`),
        CONSTRAINT `fk_email_campaign_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- --------------------------------------------------------
    -- Migration: Reminder prefs, challenge mode, Calm Points
    -- --------------------------------------------------------
    ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_reminder_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER chat_bubble_color;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_reminder_time TIME NOT NULL DEFAULT '18:00:00' AFTER daily_reminder_enabled;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS streak_risk_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER daily_reminder_time;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS challenge_mode VARCHAR(20) NOT NULL DEFAULT 'intermediate' AFTER streak_repairs;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS calm_points INT NOT NULL DEFAULT 0 AFTER challenge_mode;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS total_calm_points INT NOT NULL DEFAULT 0 AFTER calm_points;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS equipped_background VARCHAR(64) DEFAULT NULL AFTER total_calm_points;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS equipped_banner_pattern VARCHAR(64) DEFAULT NULL AFTER equipped_background;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS equipped_frame VARCHAR(64) DEFAULT NULL AFTER equipped_banner_pattern;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS equipped_stickers TEXT DEFAULT NULL AFTER equipped_frame;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS equipped_avatar TEXT DEFAULT NULL AFTER equipped_stickers;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_public_face TINYINT(1) NOT NULL DEFAULT 0 AFTER equipped_avatar;

    CREATE TABLE IF NOT EXISTS `user_xp_events` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `event_type` VARCHAR(50) NOT NULL,
        `points` INT NOT NULL,
        `user_date` DATE NOT NULL,
        `meta` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_type_day` (`user_id`, `event_type`, `user_date`),
        INDEX `idx_user_date` (`user_id`, `user_date`),
        CONSTRAINT `fk_xp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `user_shop_inventory` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `item_id` VARCHAR(64) NOT NULL,
        `purchased_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_item` (`user_id`, `item_id`),
        INDEX `idx_user_shop` (`user_id`),
        CONSTRAINT `fk_shop_inv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `jar_entries` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `owner_user_id` INT UNSIGNED NOT NULL,
        `author_user_id` INT UNSIGNED DEFAULT NULL,
        `source_circle_id` INT UNSIGNED DEFAULT NULL,
        `entry_type` VARCHAR(24) NOT NULL DEFAULT 'general',
        `message` VARCHAR(600) NOT NULL,
        `pull_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `last_pulled_at_utc` DATETIME DEFAULT NULL,
        `owner_seen_at_utc` DATETIME DEFAULT NULL,
        `created_at_utc` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_jar_owner_created` (`owner_user_id`, `created_at_utc`),
        INDEX `idx_jar_owner_pull` (`owner_user_id`, `pull_count`),
        INDEX `idx_jar_author` (`author_user_id`),
        CONSTRAINT `fk_jar_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_jar_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_jar_circle` FOREIGN KEY (`source_circle_id`) REFERENCES `inner_circles` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `user_feature_announcements` (
        `user_id` INT UNSIGNED NOT NULL,
        `announcement_key` VARCHAR(64) NOT NULL,
        `seen_at_utc` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`user_id`, `announcement_key`),
        CONSTRAINT `fk_feature_announcement_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    COMMIT;
