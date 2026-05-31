-- =========================================================
-- دانش‌یار - جدول chunk‌های کتاب برای RAG
-- =========================================================

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

-- ستون chunks_count روی books برای tracking
-- (اگر قبلاً نبود اضافه می‌شه)
-- ALTER TABLE books ADD COLUMN chunks_count SMALLINT UNSIGNED NOT NULL DEFAULT 0;
