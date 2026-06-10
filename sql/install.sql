

-- ---------------- کاربران ----------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `mobile` VARCHAR(15) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `grade` TINYINT NOT NULL DEFAULT 7,
  `major` VARCHAR(30) NOT NULL DEFAULT 'math',
  `school` VARCHAR(120) DEFAULT NULL,
  `role` ENUM('user','admin') DEFAULT 'user',
  `status` ENUM('active','banned') NOT NULL DEFAULT 'active',
  `ban_reason` VARCHAR(255) DEFAULT NULL,
  `subscription_type` VARCHAR(50) NOT NULL DEFAULT 'none',
  `subscription_start` DATETIME DEFAULT NULL,
  `subscription_end` DATETIME DEFAULT NULL,
  `messages_used_total` INT DEFAULT 0,
  `messages_used_today` INT DEFAULT 0,
  `free_used_today` TINYINT DEFAULT 0,
  `last_reset_date` DATE DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`grade`, `major`),
  INDEX (`role`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- کتاب‌ها ----------------
CREATE TABLE IF NOT EXISTS `books` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `grade` TINYINT NOT NULL,
  `subject` VARCHAR(80) NOT NULL,
  `major` VARCHAR(30) NOT NULL DEFAULT 'all',
  `majors` VARCHAR(200) NOT NULL DEFAULT 'all',
  `file_name` VARCHAR(255) NOT NULL,
  `file_names` TEXT DEFAULT NULL,
  `cached_text` LONGTEXT DEFAULT NULL,
  `chunks_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `extract_mode` VARCHAR(12) NOT NULL DEFAULT 'summary',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`grade`, `major`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- چانک‌های کتاب برای RAG ----------------
CREATE TABLE IF NOT EXISTS `book_chunks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `book_id` INT NOT NULL,
  `chunk_index` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `title` VARCHAR(200) NOT NULL DEFAULT '',
  `content` MEDIUMTEXT NOT NULL,
  `keywords` TEXT DEFAULT NULL,
  `page_start` SMALLINT UNSIGNED DEFAULT NULL,
  `page_end` SMALLINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_chunk_book` (`book_id`),
  INDEX `idx_chunk_keywords` (`book_id`, `chunk_index`),
  FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- وضعیت کار استخراج تکه‌تکه کتاب ----------------
CREATE TABLE IF NOT EXISTS `book_extract_jobs` (
  `book_id` INT PRIMARY KEY,
  `plan_json` MEDIUMTEXT,
  `accumulated` LONGTEXT,
  `current_step` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `total_steps` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(12) NOT NULL DEFAULT 'running',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- قیمت‌ها ----------------
CREATE TABLE IF NOT EXISTS `pricing` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_code` VARCHAR(20) UNIQUE NOT NULL,
  `title` VARCHAR(80) NOT NULL,
  `price` INT NOT NULL,
  `daily_limit` INT NOT NULL DEFAULT 0,
  `total_limit` INT NOT NULL DEFAULT 0,
  `duration_hours` INT NOT NULL DEFAULT 0,
  `description` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `pricing` (`plan_code`,`title`,`price`,`daily_limit`,`total_limit`,`duration_hours`,`description`) VALUES
('3h',     '۳ ساعته',   100000,  0, 50, 3,    'مناسب جلسات مرور سریع'),
('weekly', 'هفتگی',     500000, 50,  0, 168,  'یک هفته دسترسی روزانه'),
('monthly','ماهانه',   2000000, 50,  0, 720,  'پیشنهاد ویژه ما');

-- ---------------- چت‌ها (گفت‌وگوها) ----------------
CREATE TABLE IF NOT EXISTS `chats` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(120) NOT NULL DEFAULT 'گفت‌وگوی جدید',
  `book_id` INT DEFAULT NULL,
  `is_pinned` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`is_pinned`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- تاریخچه پیام‌ها ----------------
CREATE TABLE IF NOT EXISTS `chat_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `chat_id` INT DEFAULT NULL,
  `book_id` INT DEFAULT NULL,
  `role` ENUM('user','assistant') NOT NULL,
  `content` LONGTEXT NOT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- تراکنش‌ها ----------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `plan_code` VARCHAR(20) NOT NULL,
  `amount` INT NOT NULL,
  `status` ENUM('pending','paid','failed') DEFAULT 'paid',
  `ref_id` VARCHAR(80) DEFAULT NULL,
  `activate_at` DATETIME DEFAULT NULL,
  `payment_method` ENUM('card','online','manual') DEFAULT 'manual',
  `note` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- رسیدهای کارت به کارت ----------------
CREATE TABLE IF NOT EXISTS `card_receipts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `plan_code` VARCHAR(50) NOT NULL,
  `plan_title` VARCHAR(80) NOT NULL DEFAULT '',
  `amount` INT NOT NULL,
  `receipt_image` VARCHAR(255) NOT NULL,
  `activate_at` DATETIME DEFAULT NULL COMMENT 'زمان دلخواه فعال‌سازی',
  `status` ENUM('pending','approved_pending','approved','rejected') DEFAULT 'pending',
  `admin_note` TEXT DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `reviewed_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`status`),
  INDEX (`created_at`),
  INDEX `idx_activate_at` (`status`, `activate_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- تنظیمات عمومی برنامه (key/value) ----------------
-- استفاده: تخفیف همگانی، وضعیت فروش، اعلان همگانی و ...
CREATE TABLE IF NOT EXISTS `app_settings` (
  `k` VARCHAR(64) PRIMARY KEY,
  `v` TEXT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- نشست‌های کاربران (سشن در دیتابیس) ----------------
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` VARCHAR(128) PRIMARY KEY,
  `user_id` INT NOT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `last_active` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`last_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- ادمین پیش‌فرض ----------------
INSERT IGNORE INTO `users`
  (`first_name`,`last_name`,`mobile`,`password`,`grade`,`major`,`school`,`role`)
VALUES
  ('وب','مانیا','webmania','__ADMIN_HASH__',12,'math','مدیریت','admin');

-- ---------------- درخواست‌های کتاب ----------------
CREATE TABLE IF NOT EXISTS `book_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `grade` TINYINT NOT NULL,
  `major` VARCHAR(30) NOT NULL DEFAULT 'math',
  `subject` VARCHAR(80) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME DEFAULT NULL,
  INDEX (`status`),
  INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- پیام‌های ارتباط با ما ----------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(200) NOT NULL,
  `body` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`is_read`),
  INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تبدیل کدهای قدیمی رشته
UPDATE IGNORE `users` SET `major`='other' WHERE `major` IN ('technical','vocational');
UPDATE IGNORE `books` SET `major`='other' WHERE `major` IN ('technical','vocational');
