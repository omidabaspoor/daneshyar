<?php
/**
 * ============================================================
 *  دانش‌یار - فایل پیکربندی اصلی
 * ------------------------------------------------------------
 *  ⚠ توجه امنیتی مهم:
 *  مقادیر حساس (کلید API، رمز دیتابیس، رمز ادمین، شماره کارت)
 *  باید از فایل .env خوانده شوند، نه اینجا.
 *  یک کپی از .env.example بساز:  cp .env.example .env
 *  سپس مقادیر واقعی را داخل .env بگذار.
 * ============================================================
 */

require_once __DIR__ . '/env.php';

// -------- تنظیمات دیتابیس --------
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'daneshyar'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));           // در XAMPP پیش‌فرض خالی است
define('DB_CHARSET', 'utf8mb4');

// -------- تنظیمات کلی سایت --------
define('SITE_NAME', 'دانش‌یار');
define('SITE_SLOGAN', 'هم‌کلاسی هوشمند تو، همیشه کنارت');

// آدرس پایه به‌صورت خودکار محاسبه می‌شود
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    // پشتیبانی از پروکسی معکوس (Nginx) که X-Forwarded-Proto می‌فرستد
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
    }
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script = preg_replace('#/(admin|user|api)$#', '', $script);
    if ($script === '/' || $script === '.') $script = '';
    define('BASE_URL', $scheme . '://' . $host . $script);
}

// -------- مسیر فیزیکی فایل‌ها --------
define('ROOT_PATH',    realpath(__DIR__ . '/..'));
define('BOOKS_PATH',   ROOT_PATH . '/books');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('RECEIPTS_PATH', ROOT_PATH . '/uploads/receipts');

// -------- AI / GapGPT --------
define('AI_API_URL',  env('AI_API_URL', 'https://api.gapgpt.app/v1/chat/completions'));
define('AI_MODEL',    env('AI_MODEL', 'gemini-3-flash-preview'));
define('AI_API_KEY',  env('AI_API_KEY', ''));

// -------- ادمین --------
define('ADMIN_USER', env('ADMIN_USER', 'webmania'));
define('ADMIN_PASS', env('ADMIN_PASS', ''));

// -------- محدودیت‌ها --------
define('FREE_DAILY_LIMIT', (int)env('FREE_DAILY_LIMIT', 3));
define('MAX_UPLOAD_MB',    (int)env('MAX_UPLOAD_MB', 10));

// -------- اطلاعات کارت بانکی برای پرداخت کارت به کارت --------
define('CARD_NUMBER',    env('CARD_NUMBER', ''));
define('CARD_HOLDER',    env('CARD_HOLDER', ''));
define('CARD_BANK',      env('CARD_BANK', ''));

// -------- اطلاعات تماس --------
define('CONTACT_PHONE',    env('CONTACT_PHONE', ''));
define('CONTACT_TELEGRAM', env('CONTACT_TELEGRAM', ''));

// -------- منطقه زمانی --------
date_default_timezone_set('Asia/Tehran');

// -------- Session طولانی‌مدت --------
define('SESSION_LIFETIME', 30 * 24 * 3600); // 30 روز

// -------- نمایش خطا (فقط در حالت توسعه) --------
define('DEV_MODE', (bool)env('DEV_MODE', false));
if (DEV_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
    // در production خطاها را در فایل لاگ کن
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/uploads/php-error.log');
}
