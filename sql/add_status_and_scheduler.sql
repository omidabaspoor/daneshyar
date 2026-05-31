-- =========================================================
-- دانش‌یار - ستون‌های جدید برای مسدود کردن و scheduler
-- =========================================================

-- ستون وضعیت کاربر (فعال / مسدود)
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `status` ENUM('active','banned') NOT NULL DEFAULT 'active' AFTER `role`,
  ADD COLUMN IF NOT EXISTS `ban_reason` VARCHAR(255) DEFAULT NULL AFTER `status`;

-- ستون وضعیت تایید در card_receipts (approved_pending = تایید شده ولی زمانش نرسیده)
-- status: pending | approved_pending | approved | rejected
ALTER TABLE `card_receipts`
  MODIFY COLUMN `status` ENUM('pending','approved_pending','approved','rejected') DEFAULT 'pending';

-- ایندکس برای جستجوی سریع‌تر
ALTER TABLE `card_receipts`
  ADD INDEX IF NOT EXISTS `idx_activate_at` (`status`, `activate_at`);
