-- ============================================================
--  دانش‌یار — مایگریشن v2 کتاب‌ها
--  اضافه شدن: majors (چندتایی)، file_names (چند فایل)
-- ============================================================

-- ستون majors برای انتخاب چند رشته
ALTER TABLE `books` ADD COLUMN IF NOT EXISTS `majors` VARCHAR(200) NOT NULL DEFAULT 'all' AFTER `major`;

-- ستون file_names برای چند فایل PDF
ALTER TABLE `books` ADD COLUMN IF NOT EXISTS `file_names` TEXT DEFAULT NULL AFTER `file_name`;

-- ستون‌های page_start و page_end برای chunk‌ها
ALTER TABLE `book_chunks` ADD COLUMN IF NOT EXISTS `page_start` SMALLINT UNSIGNED DEFAULT NULL;
ALTER TABLE `book_chunks` ADD COLUMN IF NOT EXISTS `page_end` SMALLINT UNSIGNED DEFAULT NULL;

-- کپی مقادیر قبلی
UPDATE `books` SET `majors` = `major` WHERE `majors` = 'all' AND `major` != 'all';
UPDATE `books` SET `file_names` = `file_name` WHERE `file_names` IS NULL;
