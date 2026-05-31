<?php
/**
 * فایل migrate برای آپدیت دیتابیس‌های موجود
 * این فایل رو بعد از install.php اجرا کن اگه از نسخه قبلی استفاده می‌کردی
 */
require_once __DIR__ . '/includes/db.php';

$messages = [];
try {
    // افزودن ستون رشته به users
    $cols = db()->query("SHOW COLUMNS FROM users LIKE 'major'")->fetch();
    if (!$cols) {
        db()->exec("ALTER TABLE users ADD COLUMN major VARCHAR(30) NOT NULL DEFAULT 'math' AFTER grade");
        $messages[] = ['ok', '✓ ستون رشته به کاربران اضافه شد'];
    } else {
        $messages[] = ['info', '• ستون major در users قبلاً موجود است'];
    }

    // افزودن ستون رشته به books
    $cols = db()->query("SHOW COLUMNS FROM books LIKE 'major'")->fetch();
    if (!$cols) {
        db()->exec("ALTER TABLE books ADD COLUMN major VARCHAR(30) NOT NULL DEFAULT 'all' AFTER subject");
        $messages[] = ['ok', '✓ ستون رشته به کتاب‌ها اضافه شد'];
    } else {
        $messages[] = ['info', '• ستون major در books قبلاً موجود است'];
    }

    // افزودن ایندکس‌های کمک‌کننده، اگر از قبل وجود نداشته باشند خطا نادیده گرفته می‌شود
    try { db()->exec("ALTER TABLE users ADD INDEX idx_users_grade_major (grade, major)"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE books ADD INDEX idx_books_grade_major (grade, major)"); } catch (Throwable $e) {}

    // افزودن ستون chat_id به chat_history
    $cols = db()->query("SHOW COLUMNS FROM chat_history LIKE 'chat_id'")->fetch();
    if (!$cols) {
        db()->exec("ALTER TABLE chat_history ADD COLUMN chat_id INT DEFAULT NULL AFTER user_id, ADD INDEX (chat_id)");
        $messages[] = ['ok', '✓ ستون chat_id به chat_history اضافه شد'];
    } else {
        $messages[] = ['info', '• chat_id قبلاً موجود است'];
    }

    // ساخت جدول chats
    db()->exec("CREATE TABLE IF NOT EXISTS `chats` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `title` VARCHAR(120) NOT NULL DEFAULT 'گفت‌وگوی جدید',
      `book_id` INT DEFAULT NULL,
      `is_pinned` TINYINT(1) DEFAULT 0,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (`user_id`),
      INDEX (`is_pinned`, `updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = ['ok', '✓ جدول chats آماده شد'];

    $messages[] = ['ok', '✅ migration کامل شد. این فایل را بعد از اجرا حذف کن.'];
} catch (Throwable $e) {
    $messages[] = ['err', '❌ خطا: ' . $e->getMessage()];
}
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>Migrate</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fonts/vazirmatn.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card glass">
    <h1>🔄 آپدیت دیتابیس</h1>
    <?php foreach ($messages as [$type, $msg]): ?>
      <div class="alert alert-<?= $type==='ok'?'success':($type==='err'?'error':'info') ?>"><?= $msg ?></div>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>/" class="btn btn-primary btn-block">بازگشت به سایت</a>
  </div>
</div>
</body>
</html>
