<?php
require_once __DIR__ . '/includes/config.php';

$messages = [];
$success = false;

try {
    // اتصال بدون نام دیتابیس
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $messages[] = ['ok', '✓ اتصال به MySQL موفق بود'];

    // ساخت دیتابیس
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    $messages[] = ['ok', '✓ دیتابیس <b>' . DB_NAME . '</b> ساخته شد'];

    // اجرای SQL
    $sql = file_get_contents(__DIR__ . '/sql/install.sql');
    // حذف خطوط CREATE DATABASE تکراری چون خودمان انجام دادیم
    $sql = preg_replace('/CREATE DATABASE.*?;/is', '', $sql, 1);
    $sql = preg_replace('/USE\s+`?\w+`?\s*;/i', '', $sql);

    // جایگذاری هش پسورد ادمین
    $adminHash = password_hash(ADMIN_PASS, PASSWORD_BCRYPT);
    $sql = str_replace('__ADMIN_HASH__', $adminHash, $sql);

    $pdo->exec($sql);
    $messages[] = ['ok', '✓ جداول ساخته شدند'];

    // ساخت پوشه‌های لازم
    foreach (['/books', '/uploads'] as $d) {
        if (!is_dir(__DIR__ . $d)) { @mkdir(__DIR__ . $d, 0755, true); }
    }
    $messages[] = ['ok', '✓ پوشه‌های books و uploads آماده شدند'];

    // ساخت پوشه receipts
    if (!is_dir(__DIR__ . '/uploads/receipts')) { @mkdir(__DIR__ . '/uploads/receipts', 0755, true); }
    
    // اجرای SQL ارتقاء پرداخت
    try { $pdo->exec(file_get_contents(__DIR__ . '/sql/payment_upgrade.sql')); } catch (Throwable $pu) {}
    
    // ادمین پیش‌فرض
    $messages[] = ['ok', '✓ ادمین پیش‌فرض ساخته شد (ورود از /admin)'];
    $success = true;
} catch (Throwable $e) {
    $messages[] = ['err', '❌ خطا: ' . $e->getMessage()];
}
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>نصب دانش‌یار</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card glass">
    <h1>⚙️ نصب دانش‌یار</h1>
    <p class="sub">گزارش نصب اولیه:</p>
    <?php foreach ($messages as [$type, $msg]): ?>
      <div class="alert alert-<?= $type==='ok' ? 'success' : 'error' ?>"><?= $msg ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
      <div class="alert alert-info">
        <b>پنل ادمین:</b> آدرس <code>/admin</code><br>
        <b>یوزر:</b> <code><?= ADMIN_USER ?></code> – <b>پسورد:</b> <code><?= ADMIN_PASS ?></code><br>
        <br>
        <b>⚠️ نکته امنیتی:</b> بعد از نصب فایل <code>install.php</code> را حذف کن.
      </div>
      <a href="<?= BASE_URL ?>/" class="btn btn-primary btn-block">رفتن به سایت</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
