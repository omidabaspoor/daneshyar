<?php
require_once __DIR__ . '/db.php';

/* ============================================================
 *  مدیریت Session طولانی‌مدت (Remember Me - 30 روز)
 * ============================================================ */
function request_is_secure() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') return true;
    return false;
}

function cookie_delete($name) {
    setcookie($name, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => request_is_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function start_session_persistent() {
    if (session_status() !== PHP_SESSION_NONE) return;

    $lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 30 * 24 * 3600;

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'secure'   => request_is_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.use_strict_mode', 1);
    session_start();

    // بررسی اعتبار session با remember_token
    if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember_token'])) {
        $token = (string)$_COOKIE['remember_token'];
        try {
            $stmt = db()->prepare("SELECT user_id FROM user_sessions WHERE id=? AND last_active > DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute([$token]);
            $row = $stmt->fetch();
            if ($row) {
                $_SESSION['user_id'] = $row['user_id'];
                db()->prepare("UPDATE user_sessions SET last_active=NOW() WHERE id=?")->execute([$token]);
            } else {
                cookie_delete('remember_token');
            }
        } catch (Throwable $e) {}
    }
}

start_session_persistent();

/* ============================================================
 *  توابع کمکی عمومی
 * ============================================================ */

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check($token) {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token);
}

/* ----------- اعتبارسنجی فارسی ----------- */

function is_valid_mobile($m) {
    return (bool)preg_match('/^09\d{9}$/', $m);
}

function normalize_human_text($s) {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}

function is_valid_person_name($name) {
    $name = normalize_human_text($name);
    if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 40) return false;
    // فقط حروف فارسی/عربی، فاصله، نیم‌فاصله و خط تیره؛ عدد و نمادهای عجیب ممنوع
    if (!preg_match('/^[\p{Arabic}\x{200c}\s\-]+$/u', $name)) return false;
    $lettersOnly = preg_replace('/[\s\x{200c}\-]/u', '', $name);
    if (mb_strlen($lettersOnly, 'UTF-8') < 2) return false;
    // جلوگیری از نام‌های تک‌حرفی/تکراری مثل «م م»، «ن ن»، «ااا»
    $chars = preg_split('//u', $lettersOnly, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars && count(array_unique($chars)) === 1) return false;
    $bad = ['تست','نام','کاربر','مهمان','ناشناس','فیک','الکی'];
    return !in_array(mb_strtolower($name, 'UTF-8'), $bad, true);
}

function is_valid_school_name($school) {
    $school = normalize_human_text($school);
    if (mb_strlen($school, 'UTF-8') < 2 || mb_strlen($school, 'UTF-8') > 120) return false;
    if (!preg_match('/[\p{Arabic}A-Za-z]/u', $school)) return false;
    $plain = preg_replace('/[\s\x{200c}\-_.]/u', '', $school);
    $chars = preg_split('//u', $plain, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars && count($chars) >= 2 && count(array_unique($chars)) === 1) return false;
    return true;
}

function is_profile_complete($user) {
    if (!$user) return false;
    return is_valid_person_name($user['first_name'] ?? '')
        && is_valid_person_name($user['last_name'] ?? '')
        && is_valid_school_name($user['school'] ?? '');
}

function verify_user_password($password, $storedHash, $userId = null) {
    $password   = (string)$password;
    $storedHash = (string)$storedHash;
    if ($storedHash === '') return false;

    if (password_verify($password, $storedHash)) {
        if ($userId && password_needs_rehash($storedHash, PASSWORD_BCRYPT)) {
            try {
                db()->prepare("UPDATE users SET password=? WHERE id=?")
                    ->execute([password_hash($password, PASSWORD_BCRYPT), (int)$userId]);
            } catch (Throwable $e) {}
        }
        return true;
    }

    // سازگاری با کاربرانی که از نسخه‌های قدیمی/انتقالی رمز ناامن دارند.
    // اگر درست بود، همان لحظه به هش امن تبدیل می‌شود.
    $isKnownHash = (password_get_info($storedHash)['algo'] ?? 0) !== 0;
    $legacyOk = false;
    if (!$isKnownHash && hash_equals($storedHash, $password)) $legacyOk = true;
    if (!$legacyOk && preg_match('/^[a-f0-9]{32}$/i', $storedHash) && hash_equals(strtolower($storedHash), md5($password))) $legacyOk = true;
    if (!$legacyOk && preg_match('/^[a-f0-9]{40}$/i', $storedHash) && hash_equals(strtolower($storedHash), sha1($password))) $legacyOk = true;

    if ($legacyOk && $userId) {
        try {
            db()->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($password, PASSWORD_BCRYPT), (int)$userId]);
        } catch (Throwable $e) {}
    }
    return $legacyOk;
}

function fa_to_en_digits($s) {
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
    return str_replace($fa, $en, (string)$s);
}

function num_fa($n) {
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return str_replace($en, $fa, (string)$n);
}

/* ----------- تبدیل تاریخ شمسی به میلادی ----------- */

function jalali_to_gregorian($jy, $jm, $jd) {
    $jy = (int)$jy; $jm = (int)$jm; $jd = (int)$jd;

    if ($jy > 979) { $gy = 1600; $jy -= 979; }
    else            { $gy = 621; }

    $j_d_no = 365 * $jy + (int)($jy / 33) * 8 + (int)(($jy % 33 + 3) / 4);
    $month_lengths = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    for ($i = 0; $i < $jm - 1; $i++) $j_d_no += $month_lengths[$i];
    $j_d_no += $jd - 1;

    $g_d_no = $j_d_no + 79;

    $gy += 400 * (int)($g_d_no / 146097);
    $g_d_no %= 146097;

    if ($g_d_no >= 36525) {
        $g_d_no--;
        $gy += 100 * (int)($g_d_no / 36524);
        $g_d_no %= 36524;
        if ($g_d_no >= 365) $g_d_no++;
    }

    $gy += 4 * (int)($g_d_no / 1461);
    $g_d_no %= 1461;

    if ($g_d_no >= 366) {
        $gy += (int)(($g_d_no - 1) / 365);
        $g_d_no = ($g_d_no - 1) % 365;
    }

    $leap = (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0);
    $g_dim = [31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    $gm = 1; $gd = $g_d_no;
    foreach ($g_dim as $i => $dim) {
        if ($gd < $dim) { $gm = $i + 1; $gd = $gd + 1; break; }
        $gd -= $dim;
    }
    return [$gy, $gm, $gd];
}

/**
 * تبدیل رشته تاریخ شمسی "1403/12/15 14:30" به datetime میلادی
 */
function jalali_datetime_to_gregorian_str($jalali_str) {
    $jalali_str = trim(fa_to_en_digits($jalali_str));
    if (empty($jalali_str)) return null;
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:\s+(\d{1,2}):(\d{2}))?$/', $jalali_str, $m)) {
        [$gy, $gm, $gd] = jalali_to_gregorian((int)$m[1], (int)$m[2], (int)$m[3]);
        $h = isset($m[4]) ? (int)$m[4] : 0;
        $min = isset($m[5]) ? (int)$m[5] : 0;
        return sprintf('%04d-%02d-%02d %02d:%02d:00', $gy, $gm, $gd, $h, $min);
    }
    return null;
}

/* ----------- رشته/شاخه تحصیلی ----------- */

function major_options() {
    return [
        'math'         => 'ریاضی فیزیک',
        'experimental' => 'علوم تجربی',
        'humanities'   => 'علوم انسانی',
        'other'        => 'سایر رشته‌ها',
    ];
}

function book_major_options() {
    return ['all' => 'مشترک همه رشته‌ها'] + major_options();
}

function normalize_major($major, $allowAll = false) {
    $major = trim((string)$major);
    $allowed = $allowAll ? array_keys(book_major_options()) : array_keys(major_options());
    if (in_array($major, $allowed, true)) return $major;
    return $allowAll ? 'all' : 'math';
}

function major_label($major) {
    $all = book_major_options();
    return $all[$major] ?? 'نامشخص';
}

/* ----------- migrate schema ----------- */

function ensure_payment_schema() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `card_receipts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `plan_code` VARCHAR(50) NOT NULL,
            `plan_title` VARCHAR(80) NOT NULL DEFAULT \'\',
            `amount` INT NOT NULL,
            `receipt_image` VARCHAR(255) NOT NULL,
            `activate_at` DATETIME DEFAULT NULL,
            `status` ENUM(\'pending\',\'approved_pending\',\'approved\',\'rejected\') DEFAULT \'pending\',
            `admin_note` TEXT DEFAULT NULL,
            `reviewed_at` DATETIME DEFAULT NULL,
            `reviewed_by` INT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (`user_id`),
            INDEX (`status`),
            INDEX (`activate_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->exec("CREATE TABLE IF NOT EXISTS `user_sessions` (
            `id` VARCHAR(128) PRIMARY KEY,
            `user_id` INT NOT NULL,
            `ip` VARCHAR(45) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `last_active` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // subscription_type به VARCHAR
        try { db()->exec("ALTER TABLE `users` MODIFY COLUMN `subscription_type` VARCHAR(50) NOT NULL DEFAULT \'none\'"); } catch (Throwable $e) {}
        // ستون status و ban_reason برای مسدودسازی
        try { db()->exec("ALTER TABLE `users` ADD COLUMN `status` ENUM(\'active\',\'banned\') NOT NULL DEFAULT \'active\' AFTER `role`"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE `users` ADD COLUMN `ban_reason` VARCHAR(255) DEFAULT NULL AFTER `status`"); } catch (Throwable $e) {}
        // ستون‌های جدید transactions
        try { db()->exec("ALTER TABLE `transactions` ADD COLUMN `activate_at` DATETIME DEFAULT NULL"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE `transactions` ADD COLUMN `payment_method` VARCHAR(20) DEFAULT \'manual\'"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE `transactions` ADD COLUMN `note` TEXT DEFAULT NULL"); } catch (Throwable $e) {}
        // ارتقاء ENUM در card_receipts برای approved_pending
        try { db()->exec("ALTER TABLE `card_receipts` MODIFY COLUMN `status` ENUM(\'pending\',\'approved_pending\',\'approved\',\'rejected\') DEFAULT \'pending\'"); } catch (Throwable $e) {}
    } catch (Throwable $e) {}
}

function ensure_discounts_schema() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS user_discounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_code VARCHAR(50) NOT NULL,
            discount_percent TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_plan (user_id, plan_code),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}
}

/* ============================================================
 *  تنظیمات عمومی برنامه (key/value) – تخفیف همگانی، اعلان و ...
 * ============================================================ */
function ensure_settings_schema() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS app_settings (
            k VARCHAR(64) PRIMARY KEY,
            v TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}
}

function get_setting($key, $default = null) {
    ensure_settings_schema();
    $cache = &_settings_cache();
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $s = db()->prepare("SELECT v FROM app_settings WHERE k=?");
        $s->execute([$key]);
        $r = $s->fetch();
        $val = $r ? $r['v'] : $default;
    } catch (Throwable $e) {
        $val = $default;
    }
    $cache[$key] = $val;
    return $val;
}

/**
 * کش مشترک تنظیمات. با reference برمی‌گردد تا get/set هر دو
 * روی همان آرایه کار کنند و نوشتن، مقدار قدیمی را باطل کند.
 */
function &_settings_cache() {
    static $cache = [];
    return $cache;
}

function set_setting($key, $val) {
    ensure_settings_schema();
    try {
        db()->prepare("INSERT INTO app_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v=VALUES(v)")
            ->execute([$key, (string)$val]);
        // کش را همگام کن تا در همین درخواست مقدار جدید دیده شود
        $cache = &_settings_cache();
        $cache[$key] = (string)$val;
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * درصد تخفیف مؤثر برای یک کاربر روی یک پلن.
 * اولویت: تخفیف اختصاصی کاربر > تخفیف همگانی.
 * (سیستم تخفیف اختصاصی دست‌نخورده باقی می‌ماند.)
 */
function effective_plan_discount($user_id, $plan_code) {
    $user_id = (int)$user_id;
    $plan_code = (string)$plan_code;

    // 1) تخفیف اختصاصی کاربر (اولویت اول)
    if ($user_id > 0) {
        try {
            $s = db()->prepare("SELECT discount_percent FROM user_discounts WHERE user_id=? AND plan_code=?");
            $s->execute([$user_id, $plan_code]);
            $r = $s->fetch();
            if ($r && (int)$r['discount_percent'] > 0) {
                return ['percent' => (int)$r['discount_percent'], 'type' => 'personal'];
            }
        } catch (Throwable $e) {}
    }

    // 2) تخفیف همگانی
    $g = (int)get_setting('global_discount_percent', '0');
    if ($g > 0 && $g <= 100) {
        return ['percent' => $g, 'type' => 'global'];
    }

    return ['percent' => 0, 'type' => 'none'];
}

/**
 * آیا فروش اشتراک فعال است؟
 * منبع اصلی: تنظیم دیتابیس (sales_enabled). اگر تعریف نشده بود،
 * به ثابت SALES_ENABLED (از .env) برمی‌گردیم.
 * این تابع تضمین می‌کند که بستن فروش از پنل، در فرانت هم اعمال شود.
 */
function sales_enabled() {
    $v = get_setting('sales_enabled', null);
    if ($v === null || $v === '') {
        return defined('SALES_ENABLED') ? (bool)SALES_ENABLED : true;
    }
    return !in_array(strtolower((string)$v), ['0', 'false', 'no', 'off', ''], true);
}

function set_sales_enabled($on) {
    return set_setting('sales_enabled', $on ? '1' : '0');
}

/**
 * اعلان همگانی فعلی (که ادمین برای همه کاربران می‌گذارد).
 * خروجی: ['id'=>..., 'text'=>..., 'title'=>...] یا null اگر غیرفعال باشد.
 */
function active_announcement() {
    $on = get_setting('announcement_enabled', '0');
    if (in_array(strtolower((string)$on), ['0', 'false', 'no', 'off', ''], true)) return null;
    $text = (string)get_setting('announcement_text', '');
    if (trim($text) === '') return null;
    return [
        'id'    => (string)get_setting('announcement_id', '0'),
        'title' => (string)get_setting('announcement_title', 'اطلاعیه'),
        'text'  => $text,
    ];
}

function ensure_education_schema() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo = db();
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'major'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE users ADD COLUMN major VARCHAR(30) NOT NULL DEFAULT 'math' AFTER grade");
            try { $pdo->exec("ALTER TABLE users ADD INDEX idx_users_grade_major (grade, major)"); } catch (Throwable $e) {}
        }
        $col = $pdo->query("SHOW COLUMNS FROM books LIKE 'major'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE books ADD COLUMN major VARCHAR(30) NOT NULL DEFAULT 'all' AFTER subject");
            try { $pdo->exec("ALTER TABLE books ADD INDEX idx_books_grade_major (grade, major)"); } catch (Throwable $e) {}
        }
    } catch (Throwable $e) {}
}

function schema_bootstrap_if_needed() {
    static $booted = false;
    if ($booted) return;
    $booted = true;

    if (!env('AUTO_SCHEMA_BOOTSTRAP', true)) return;

    $interval = max(300, (int)env('AUTO_SCHEMA_BOOTSTRAP_INTERVAL', 86400));
    $baseDir  = defined('UPLOADS_PATH') ? UPLOADS_PATH : (defined('ROOT_PATH') ? ROOT_PATH . '/uploads' : sys_get_temp_dir());
    $marker   = rtrim($baseDir, '/\\') . '/.schema_bootstrap_at';

    $last = @filemtime($marker);
    if ($last && (time() - (int)$last) < $interval) return;

    ensure_education_schema();
    ensure_discounts_schema();
    ensure_payment_schema();
    ensure_settings_schema();

    // تبدیل کدهای قدیمی رشته
    try {
        db()->exec("UPDATE IGNORE users SET major='other' WHERE major IN ('technical','vocational')");
        db()->exec("UPDATE IGNORE books SET major='other' WHERE major IN ('technical','vocational')");
    } catch (Throwable $e) {}

    // ساخت جداول تکمیلی فقط در بازه‌های محدود (نه هر درخواست)
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS book_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            grade TINYINT NOT NULL,
            major VARCHAR(30) NOT NULL DEFAULT 'math',
            subject VARCHAR(80) NOT NULL,
            description TEXT DEFAULT NULL,
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME DEFAULT NULL,
            INDEX (status),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            name VARCHAR(100) NOT NULL,
            subject VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (is_read),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}

    if (!is_dir($baseDir)) @mkdir($baseDir, 0755, true);
    @touch($marker);
}

schema_bootstrap_if_needed();

/* ----------- کتاب‌ها ----------- */

function get_accessible_books_for_user($user) {
    $major = normalize_major($user['major'] ?? 'math');
    // پشتیبانی از ستون majors (چندتایی) و major (تکی) برای سازگاری
    $stmt = db()->prepare("
        SELECT id, title, subject, grade, major, COALESCE(majors, major) as majors
        FROM books
        WHERE grade=? AND (
            major='all'
            OR major=?
            OR COALESCE(majors,'') LIKE '%all%'
            OR FIND_IN_SET(?, COALESCE(majors, major))
        )
        ORDER BY subject ASC, title ASC
    ");
    $stmt->execute([(int)$user['grade'], $major, $major]);
    return $stmt->fetchAll();
}

function user_can_access_book($user, $book) {
    if (!$book) return false;
    if (($user['role'] ?? '') === 'admin') return true;
    $userMajor = normalize_major($user['major'] ?? 'math');
    $bookGrade = (int)($book['grade'] ?? 0);
    $userGrade = (int)($user['grade'] ?? 0);
    if ($bookGrade !== $userGrade) return false;

    // چک ستون majors (چندتایی) یا major (تکی)
    $majorsStr = $book['majors'] ?? $book['major'] ?? 'all';
    $bookMajors = array_map('trim', explode(',', $majorsStr));

    return in_array('all', $bookMajors, true) || in_array($userMajor, $bookMajors, true);
}

function get_book_for_user($book_id, $user) {
    $book_id = (int)$book_id;
    if ($book_id <= 0) return null;
    $stmt = db()->prepare("SELECT *, COALESCE(majors, major) as majors FROM books WHERE id=?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch() ?: null;
    return user_can_access_book($user, $book) ? $book : null;
}

/* ----------- کاربر ----------- */

function current_user($fresh = false) {
    if (!isset($_SESSION['user_id'])) return null;
    static $u = null;
    if ($fresh || $u === null) {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch() ?: null;
        if ($u) $u = reset_daily_if_needed($u);
    }
    return $u;
}

/**
 * نسخه تازه از کاربر فعلی - cache رو دور می‌زنه.
 * برای جاهایی که بعد از UPDATE نیاز به مقدار جدید داریم.
 */
function current_user_fresh() {
    return current_user(true);
}

function require_login() {
    $u = current_user();
    if (!$u) redirect(BASE_URL . '/login.php');
    if (is_banned($u)) {
        logout_user();
        redirect(BASE_URL . '/login.php?banned=1');
    }
    // کاربران قدیمی با نام/مدرسه ناقص باید قبل از استفاده، پروفایل را کامل کنند.
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!is_profile_complete($u) && !in_array($script, ['profile.php', 'logout.php'], true)) {
        redirect(BASE_URL . '/profile.php?complete=1');
    }
}

function issue_remember_token($user_id) {
    $token = bin2hex(random_bytes(32));
    try {
        db()->prepare("INSERT INTO user_sessions (id, user_id, ip, user_agent) VALUES (?,?,?,?)")
            ->execute([$token, (int)$user_id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (Throwable $e) {}
    setcookie('remember_token', $token, [
        'expires'  => time() + 30 * 24 * 3600,
        'path'     => '/',
        'secure'   => request_is_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function login_user($user_id, $remember = true) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }
    $_SESSION['user_id'] = (int)$user_id;
    if ($remember) {
        issue_remember_token((int)$user_id);
    }
}

function logout_user() {
    // حذف remember token
    if (!empty($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        try {
            db()->prepare("DELETE FROM user_sessions WHERE id=?")->execute([$token]);
        } catch (Throwable $e) {}
        cookie_delete('remember_token');
    }
    unset($_SESSION['user_id']);
}

/* ----------- ادمین جدا از حساب سایت ----------- */

function current_admin() {
    if (!isset($_SESSION['admin_id'])) return null;
    static $admin = null;
    if ($admin === null) {
        $stmt = db()->prepare("SELECT * FROM users WHERE id=? AND role='admin'");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch() ?: null;
    }
    return $admin;
}

function require_admin() {
    if (!current_admin()) redirect(BASE_URL . '/admin/login.php');
}

function admin_logout() {
    unset($_SESSION['admin_id']);
}

function logout() {
    logout_user();
}

function reset_daily_if_needed($user) {
    $today = date('Y-m-d');
    if ($user['last_reset_date'] !== $today) {
        db()->prepare("UPDATE users SET messages_used_today=0, free_used_today=0, last_reset_date=? WHERE id=? AND (last_reset_date IS NULL OR last_reset_date != ?)")
            ->execute([$today, $user['id'], $today]);
        $stmt = db()->prepare("SELECT messages_used_today, free_used_today, last_reset_date FROM users WHERE id=?");
        $stmt->execute([$user['id']]);
        $updated = $stmt->fetch();
        if ($updated) {
            $user['messages_used_today'] = $updated['messages_used_today'];
            $user['free_used_today']     = $updated['free_used_today'];
            $user['last_reset_date']     = $updated['last_reset_date'];
        }
    }
    return $user;
}

/* ----------- اشتراک ----------- */

function subscription_status($user) {
    if (!$user) return ['active' => false, 'reason' => 'کاربر نامعتبر'];

    $type = $user['subscription_type'] ?? 'none';
    if (empty($type) || $type === 'none' || empty($user['subscription_end'])) {
        return ['active' => false, 'reason' => 'بدون اشتراک', 'has_plan' => false];
    }

    // پلن رو از DB بگیر (حتی اگر منقضی شده)
    $stmt = db()->prepare("SELECT * FROM pricing WHERE plan_code = ?");
    $stmt->execute([$type]);
    $plan = $stmt->fetch() ?: null;
    if (!$plan) {
        return ['active' => false, 'reason' => 'پلن نامعتبر', 'has_plan' => false];
    }

    $now = time();
    $startTs = !empty($user['subscription_start']) ? strtotime($user['subscription_start']) : null;
    $endTs   = strtotime($user['subscription_end']);

    // هنوز شروع نشده
    if ($startTs && $startTs > $now) {
        return ['active' => false, 'reason' => 'اشتراک شما هنوز شروع نشده است', 'plan' => $plan, 'has_plan' => true, 'starts_in' => $startTs - $now];
    }
    // منقضی شده
    if ($endTs && $endTs < $now) {
        return ['active' => false, 'reason' => 'اشتراک شما به پایان رسیده است', 'plan' => $plan, 'has_plan' => true, 'expired' => true];
    }
    // سقف کل
    if ((int)$plan['total_limit'] > 0 && (int)$user['messages_used_total'] >= (int)$plan['total_limit']) {
        return ['active' => false, 'reason' => 'سقف کل پیام‌های پلن شما تکمیل شده است', 'plan' => $plan, 'has_plan' => true, 'limit_hit' => 'total'];
    }
    // سقف روزانه
    if ((int)$plan['daily_limit'] > 0 && (int)$user['messages_used_today'] >= (int)$plan['daily_limit']) {
        return ['active' => false, 'reason' => 'سقف پیام‌های امروز شما تکمیل شده است (فردا ریست می‌شود)', 'plan' => $plan, 'has_plan' => true, 'limit_hit' => 'daily'];
    }

    return ['active' => true, 'plan' => $plan, 'has_plan' => true];
}

function can_send_message($user) {
    $sub = subscription_status($user);

    // 1) اشتراک فعال داره
    if ($sub['active']) {
        $plan = $sub['plan'];
        $remaining = null;
        if ((int)$plan['daily_limit'] > 0) {
            $remaining = max(0, (int)$plan['daily_limit'] - (int)$user['messages_used_today']);
        } elseif ((int)$plan['total_limit'] > 0) {
            $remaining = max(0, (int)$plan['total_limit'] - (int)$user['messages_used_total']);
        }
        return ['ok' => true, 'mode' => 'subscription', 'sub' => $sub, 'plan' => $plan, 'remaining' => $remaining];
    }

    // 2) اشتراک داره ولی منقضی/پر شده → فالبک به رایگان نه!
    //    کاربر صریحاً پیام «اشتراک پر شد» می‌گیره.
    if (!empty($sub['has_plan'])) {
        return [
            'ok'     => false,
            'mode'   => 'subscription_blocked',
            'reason' => $sub['reason'],
            'sub'    => $sub,
            'plan'   => $sub['plan'] ?? null,
            'limit_hit' => $sub['limit_hit'] ?? null,
            'expired'   => !empty($sub['expired']),
        ];
    }

    // 3) بدون اشتراک — از پلن رایگان استفاده کن
    $freeUsed = (int)($user['free_used_today'] ?? 0);
    if ($freeUsed < FREE_DAILY_LIMIT) {
        return [
            'ok'        => true,
            'mode'      => 'free',
            'remaining' => FREE_DAILY_LIMIT - $freeUsed,
        ];
    }

    // 4) پلن رایگان هم پر شد
    return [
        'ok'     => false,
        'mode'   => 'free_blocked',
        'reason' => 'سهمیه پیام رایگان امروز شما تکمیل شده است. برای ادامه، یک پلن اشتراک تهیه کن یا تا فردا صبر کن.',
    ];
}

/**
 * فعال‌سازی اشتراک با پشتیبانی از زمان دلخواه شروع
 * $activate_at: datetime میلادی (مثلاً '2024-03-15 14:00:00') یا null برای همین لحظه
 */
function activate_subscription($user_id, $plan_code, $activate_at = null, $payment_method = 'manual', $ref_id = null) {
    $stmt = db()->prepare("SELECT * FROM pricing WHERE plan_code=?");
    $stmt->execute([$plan_code]);
    $plan = $stmt->fetch();
    if (!$plan) return false;

    // زمان شروع
    if ($activate_at && is_string($activate_at)) {
        $startTs = strtotime($activate_at);
        if (!$startTs || $startTs < time() - 60) {
            $startTs = time(); // اگر تاریخ گذشته بود، همین الان
        }
    } else {
        $startTs = time();
    }

    $start = date('Y-m-d H:i:s', $startTs);
    $end   = date('Y-m-d H:i:s', $startTs + (int)$plan['duration_hours'] * 3600);

    // ⭐ ریست کامل سهمیه‌ها هنگام فعال‌سازی پلن جدید
    //    این مهم‌ترین فیکس است: اگر این‌ها صفر نشن، کاربر بلافاصله block می‌شه
    //    چون شرط UPDATE در reserve_message_quota شکست می‌خوره.
    db()->prepare("
        UPDATE users
           SET subscription_type   = ?,
               subscription_start  = ?,
               subscription_end    = ?,
               messages_used_total = 0,
               messages_used_today = 0,
               free_used_today     = 0,
               last_reset_date     = CURDATE()
         WHERE id = ?
    ")->execute([$plan_code, $start, $end, $user_id]);

    db()->prepare("INSERT INTO transactions (user_id, plan_code, amount, status, ref_id, payment_method, activate_at) VALUES (?,?,?, 'paid', ?, ?, ?)")
        ->execute([$user_id, $plan_code, $plan['price'], $ref_id, $payment_method, $start]);

    return true;
}

/* ============================================================
 *  مدیریت چت‌ها
 * ============================================================ */

function create_chat($user_id, $title = 'گفت‌وگوی جدید', $book_id = null) {
    db()->prepare("INSERT INTO chats (user_id, title, book_id) VALUES (?,?,?)")
        ->execute([$user_id, $title, $book_id ?: null]);
    return (int)db()->lastInsertId();
}

function get_user_chats($user_id) {
    $stmt = db()->prepare("
        SELECT c.*,
               (SELECT content FROM chat_history WHERE chat_id=c.id AND role='user' ORDER BY id ASC LIMIT 1) as first_msg
        FROM chats c
        WHERE c.user_id=?
        ORDER BY c.is_pinned DESC, c.updated_at DESC
        LIMIT 100
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function get_chat($chat_id, $user_id) {
    $stmt = db()->prepare("SELECT * FROM chats WHERE id=? AND user_id=?");
    $stmt->execute([$chat_id, $user_id]);
    return $stmt->fetch() ?: null;
}

function chat_messages($chat_id) {
    $stmt = db()->prepare("SELECT * FROM chat_history WHERE chat_id=? ORDER BY id ASC");
    $stmt->execute([$chat_id]);
    return $stmt->fetchAll();
}

function update_chat_timestamp($chat_id) {
    db()->prepare("UPDATE chats SET updated_at=NOW() WHERE id=?")->execute([$chat_id]);
}

/**
 * آپدیت book_id یک چت.
 * وقتی کاربر وسط چت کتاب عوض می‌کنه.
 */
function update_chat_book($chat_id, $book_id = null) {
    db()->prepare("UPDATE chats SET book_id=? WHERE id=?")->execute([$book_id, (int)$chat_id]);
}

function smart_chat_title($text, $max = 38) {
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if (mb_strlen($text) <= $max) return $text ?: 'گفت‌وگوی جدید';
    return mb_substr($text, 0, $max) . '…';
}

/* ----------- سهمیه پیام ----------- */

function reserve_message_quota($user, $mode) {
    $user = reset_daily_if_needed($user);

    if ($mode === 'free') {
        $stmt = db()->prepare(
            "UPDATE users
               SET free_used_today = free_used_today + 1
             WHERE id = ?
               AND free_used_today < ?
               AND (subscription_type IS NULL OR subscription_type = 'none' OR subscription_end IS NULL OR subscription_end < NOW())"
        );
        $stmt->execute([(int)$user['id'], FREE_DAILY_LIMIT]);
        return $stmt->rowCount() === 1;
    }

    if ($mode !== 'subscription') return false;

    $sub = subscription_status($user);
    if (!$sub['active']) return false;
    $plan = $sub['plan'];

    // شرط atomic: همه چیز با هم در یک UPDATE
    $conds  = ["id = ?", "subscription_type = ?", "subscription_end > NOW()"];
    $params = [(int)$user['id'], (string)$plan['plan_code']];

    if ((int)$plan['daily_limit'] > 0) {
        $conds[] = "messages_used_today < " . (int)$plan['daily_limit'];
    }
    if ((int)$plan['total_limit'] > 0) {
        $conds[] = "messages_used_total < " . (int)$plan['total_limit'];
    }

    $stmt = db()->prepare(
        "UPDATE users
           SET messages_used_today = messages_used_today + 1,
               messages_used_total = messages_used_total + 1
         WHERE " . implode(' AND ', $conds)
    );
    $stmt->execute($params);
    return $stmt->rowCount() === 1;
}

function refund_message_quota($user, $mode) {
    if ($mode === 'free') {
        db()->prepare("UPDATE users SET free_used_today=GREATEST(free_used_today-1,0) WHERE id=?")->execute([(int)$user['id']]);
    } else {
        db()->prepare("UPDATE users SET messages_used_today=GREATEST(messages_used_today-1,0), messages_used_total=GREATEST(messages_used_total-1,0) WHERE id=?")->execute([(int)$user['id']]);
    }
}


/* ----------- آپلود ----------- */

function upload_mime($path) {
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $m  = finfo_file($fi, $path);
        finfo_close($fi);
        if ($m) return $m;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'heic' => 'image/heic',
        'heif' => 'image/heif',
        'pdf' => 'application/pdf'
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

function safe_image_ext($mime) {
    $map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    // HEIC فقط اگر Imagick نصب باشه قابل تبدیل است
    if (extension_loaded('imagick')) {
        $map['image/heic'] = 'heic';
        $map['image/heif'] = 'heic';
    }
    return $map[$mime] ?? null;
}

function save_chat_image_upload($file, $userId) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [false, 'آپلود تصویر ناموفق بود.'];
    if (!is_uploaded_file($file['tmp_name'])) return [false, 'فایل معتبر نیست.'];
    if ((int)$file['size'] <= 0 || (int)$file['size'] > MAX_UPLOAD_MB * 1024 * 1024) return [false, 'حجم تصویر بیش از '.num_fa(MAX_UPLOAD_MB).' مگابایت مجاز نیست.'];
    $mime = upload_mime($file['tmp_name']);
    $ext  = safe_image_ext($mime);
    if (!$ext) {
        $origExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $fb = [
            'jpg' => 'jpg',
            'jpeg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            'gif' => 'gif',
            'heic' => 'heic',
            'heif' => 'heic'
        ];
        if (isset($fb[$origExt])) { 
            $ext = $fb[$origExt]; 
            if ($ext === 'heic') $mime = 'image/heic';
            else $mime = 'image/'.($ext==='jpg'?'jpeg':$ext); 
        }
    }
    if (!$ext) return [false, 'فقط تصویر JPG، PNG، WEBP، HEIC یا GIF مجاز است.'];
    
    // For HEIC, getimagesize might fail if GD doesn't support it, but we can skip dimensions check or handle it.
    $info = @getimagesize($file['tmp_name']);
    if ($info && ($info[0] > 8000 || $info[1] > 8000)) return [false, 'ابعاد تصویر بیش از حد بزرگ است.'];
    
    if (!is_dir(UPLOADS_PATH)) @mkdir(UPLOADS_PATH, 0755, true);
    if (!is_writable(UPLOADS_PATH)) return [false, 'پوشه uploads قابل نوشتن نیست.'];
    $name = 'img_' . (int)$userId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = UPLOADS_PATH . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return [false, 'ذخیره تصویر ناموفق بود.'];
    @chmod($dest, 0644);
    return [true, ['relative'=>'uploads/'.$name, 'path'=>$dest, 'mime'=>$mime, 'name'=>$name]];
}

function validate_chat_pdf_upload($file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [false, 'آپلود PDF ناموفق بود.'];
    if (!is_uploaded_file($file['tmp_name'])) return [false, 'فایل معتبر نیست.'];
    if ((int)$file['size'] <= 0 || (int)$file['size'] > MAX_UPLOAD_MB * 1024 * 1024) return [false, 'حجم PDF مجاز نیست.'];
    $head = @file_get_contents($file['tmp_name'], false, null, 0, 5);
    $ext  = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if ($head !== '%PDF-' && $ext !== 'pdf') return [false, 'فقط PDF معتبر پذیرفته می‌شود.'];
    return [true, ['mime'=>'application/pdf']];
}

function save_receipt_image($file, $userId) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [false, 'آپلود رسید ناموفق بود.'];
    if (!is_uploaded_file($file['tmp_name'])) return [false, 'فایل معتبر نیست.'];
    if ((int)$file['size'] <= 0 || (int)$file['size'] > 10 * 1024 * 1024) return [false, 'حجم رسید نباید بیش از ۱۰ مگابایت باشد.'];
    $mime = upload_mime($file['tmp_name']);
    $ext  = safe_image_ext($mime);
    if (!$ext) {
        $origExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $fb = ['jpg'=>'jpg','jpeg'=>'jpg','png'=>'png','webp'=>'webp'];
        $ext = $fb[$origExt] ?? null;
    }
    if (!$ext) return [false, 'فقط تصویر JPG یا PNG برای رسید مجاز است.'];
    $dir = defined('RECEIPTS_PATH') ? RECEIPTS_PATH : (UPLOADS_PATH . '/receipts');
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!is_writable($dir)) return [false, 'پوشه رسیدها قابل نوشتن نیست.'];
    $name = 'receipt_' . (int)$userId . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return [false, 'ذخیره رسید ناموفق بود.'];
    @chmod($dest, 0644);
    return [true, 'uploads/receipts/' . $name];
}

/* ----------- UTF-8 ----------- */

function sanitize_utf8($text) {
    if (empty($text)) return '';
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $text = str_replace("\0", '', $text);
    if (function_exists('mb_convert_encoding')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    return $text;
}

function extract_pdf_text_simple($path) {
    // محافظت در برابر فایل خیلی بزرگ (هاست‌های ضعیف)
    $maxBytes = 12 * 1024 * 1024; // 12MB
    if (!is_file($path)) return '';
    $size = @filesize($path);
    if ($size === false || $size === 0) return '';
    if ($size > $maxBytes) {
        // فقط بخش ابتدای فایل را می‌خوانیم
        $fp = @fopen($path, 'rb');
        if (!$fp) return '';
        $content = @fread($fp, $maxBytes);
        fclose($fp);
    } else {
        $content = @file_get_contents($path);
    }
    if (!$content) return '';

    $text = '';
    if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $m)) {
        foreach ($m[1] as $stream) {
            $stream  = ltrim($stream, "\r\n");
            $decoded = @gzuncompress($stream);
            if ($decoded === false) $decoded = $stream;
            if (preg_match_all('/\((.*?)\)\s*Tj/s', $decoded, $tt)) {
                foreach ($tt[1] as $t) $text .= $t . ' ';
            }
            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $tt2)) {
                foreach ($tt2[1] as $t) {
                    if (preg_match_all('/\((.*?)\)/', $t, $ts)) {
                        foreach ($ts[1] as $tx) $text .= $tx;
                    }
                    $text .= ' ';
                }
            }
            // محافظت در برابر مصرف حافظه: قطع کن
            if (strlen($text) > 200000) break;
        }
    }
    if (trim($text) === '' && preg_match_all('/\((.*?)\)\s*Tj/s', $content, $m2)) {
        foreach ($m2[1] as $t) $text .= $t . ' ';
    }
    unset($content);

    $text = str_replace(['\\(','\\)','\\\\','\\n','\\r','\\t'], ['(',')','\\',"\n","\r","\t"], $text);
    $text = sanitize_utf8(trim($text));

    // اگر خروجی واقعاً قابل خواندن نیست (مثلاً PDF اسکن‌شده)، یه پیام راهنما برگردون
    $printable = preg_match_all('/[\p{L}\p{N}]/u', $text);
    if ($printable < 30) {
        return ''; // اجازه بده api/chat.php پیام مناسب بفرسته
    }
    return $text;
}

/* ----------- اعلان‌ها ----------- */

function user_notifications($user) {
    $items = [];
    if (!$user) return $items;

    // رسید در انتظار
    try {
        $pr = db()->prepare("SELECT COUNT(*) as cnt FROM card_receipts WHERE user_id=? AND status='pending'");
        $pr->execute([$user['id']]);
        $pc = (int)($pr->fetch()['cnt'] ?? 0);
        if ($pc > 0) {
            $items[] = [
                'type' => 'info',
                'icon' => 'clock',
                'title' => 'رسید در انتظار بررسی',
                'text'  => 'رسید پرداخت شما ارسال شده و در انتظار تایید ادمین است.',
                'action' => BASE_URL . '/profile.php#receipts',
                'action_text' => 'مشاهده وضعیت'
            ];
        }
    } catch (Throwable $e) {}

    $sub = subscription_status($user);
    if ($sub['active']) {
        $plan     = $sub['plan'];
        $endTs    = strtotime($user['subscription_end']);
        $hoursLeft = $endTs ? floor(($endTs - time()) / 3600) : 0;
        if ($hoursLeft <= 24) {
            $items[] = ['type'=>'warning','icon'=>'warning','title'=>'اشتراکت رو به پایان است','text'=>'برای اینکه وسط درس قطع نشی، بهتره تمدیدش کنی.','action'=>BASE_URL.'/pricing.php','action_text'=>'تمدید اشتراک'];
        } else {
            $items[] = ['type'=>'success','icon'=>'crown','title'=>'اشتراک فعال','text'=>'پلن '.($plan['title']??'').' فعال است؛ با خیال راحت سوال بپرس.','action'=>null,'action_text'=>null];
        }
        if (!empty($plan['daily_limit']) && (int)$plan['daily_limit'] > 0) {
            $left = max(0, (int)$plan['daily_limit'] - (int)$user['messages_used_today']);
            if ($left <= 5) {
                $items[] = ['type'=>'warning','icon'=>'warning','title'=>'سقف امروز نزدیکه','text'=>'فقط '.num_fa($left).' پیام از محدودیت امروزت باقی مانده.','action'=>BASE_URL.'/pricing.php','action_text'=>'ارتقا / تمدید'];
            }
        }
    } else {
        $freeLeft = max(0, FREE_DAILY_LIMIT - (int)($user['free_used_today'] ?? 0));
        if ($freeLeft > 0) {
            $items[] = ['type'=>'info','icon'=>'sparkle','title'=>'پلن رایگان','text'=>num_fa($freeLeft).' سوال رایگان امروز باقی مانده.','action'=>BASE_URL.'/pricing.php','action_text'=>'مشاهده پلن‌ها'];
        } else {
            $items[] = ['type'=>'warning','icon'=>'warning','title'=>'سوال رایگان امروز تمام شد','text'=>'برای ادامه یادگیری، یک پلن فعال کن.','action'=>BASE_URL.'/pricing.php','action_text'=>'فعال‌سازی اشتراک'];
        }
    }

    try {
        if (empty(get_accessible_books_for_user($user))) {
            $items[] = ['type'=>'info','icon'=>'book','title'=>'کتاب اختصاصی هنوز اضافه نشده','text'=>'فعلاً می‌تونی از حالت عمومی استفاده کنی.','action'=>null,'action_text'=>null];
        }
    } catch (Throwable $e) {}

    return $items;
}

/* ----------- فرمت ----------- */

function format_price($n) { return num_fa(number_format((int)$n)); }

function time_left($end) {
    $diff = strtotime($end) - time();
    if ($diff <= 0) return 'منقضی شده';
    $d = floor($diff / 86400);
    $h = floor(($diff % 86400) / 3600);
    if ($d > 0) return num_fa($d) . ' روز و ' . num_fa($h) . ' ساعت';
    $m = floor(($diff % 3600) / 60);
    return num_fa($h) . ' ساعت و ' . num_fa($m) . ' دقیقه';
}

/* ----------- درخواست کتاب ----------- */

function submit_book_request($user_id, $data) {
    $title   = trim($data['title'] ?? '');
    $grade   = (int)($data['grade'] ?? 0);
    $major   = normalize_major($data['major'] ?? 'math');
    $subject = trim($data['subject'] ?? '');
    $desc    = trim($data['description'] ?? '');
    if ($title === '' || $grade < 7 || $grade > 12 || $subject === '') {
        return [false, 'لطفاً همه فیلدهای الزامی را پر کنید.'];
    }
    try {
        $stmt = db()->prepare("SELECT id FROM book_requests WHERE title=? AND grade=? AND subject=? AND status='pending'");
        $stmt->execute([$title, $grade, $subject]);
        if ($stmt->fetch()) return [false, 'این کتاب قبلاً درخواست داده شده.'];
    } catch (Throwable $e) {}
    try {
        db()->prepare("INSERT INTO book_requests (user_id, title, grade, major, subject, description) VALUES (?,?,?,?,?,?)")
            ->execute([$user_id, $title, $grade, $major, $subject, $desc]);
    } catch (Throwable $e) {
        return [false, 'خطا در ثبت درخواست.'];
    }
    return [true, 'درخواست شما با موفقیت ثبت شد.'];
}

/* ----------- پیام تماس ----------- */

function submit_message($user_id, $data) {
    $name    = trim($data['name'] ?? '');
    $subject = trim($data['subject'] ?? '');
    $body    = trim($data['body'] ?? '');
    if ($name === '' || $subject === '' || $body === '') return [false, 'لطفاً همه فیلدها را پر کنید.'];
    if (mb_strlen($body) > 2000) return [false, 'پیام شما خیلی طولانی است.'];
    try {
        db()->prepare("INSERT INTO messages (user_id, name, subject, body) VALUES (?,?,?,?)")
            ->execute([$user_id ?: null, $name, $subject, $body]);
    } catch (Throwable $e) {
        return [false, 'خطا در ارسال پیام.'];
    }
    return [true, 'پیام شما با موفقیت ارسال شد.'];
}


/* ============================================================
 *  مسدودسازی کاربر
 * ============================================================ */

function is_banned($user) {
    if (!$user) return false;
    return ($user['status'] ?? 'active') === 'banned';
}

function ban_user($user_id, $reason = '') {
    db()->prepare("UPDATE users SET status='banned', ban_reason=? WHERE id=?")
        ->execute([$reason ?: null, (int)$user_id]);
    try {
        db()->prepare("DELETE FROM user_sessions WHERE user_id=?")->execute([(int)$user_id]);
    } catch (Throwable $e) {}
}

function unban_user($user_id) {
    db()->prepare("UPDATE users SET status='active', ban_reason=NULL WHERE id=?")
        ->execute([(int)$user_id]);
}

/* ============================================================
 *  Scheduler – فعال‌سازی خودکار اشتراک‌های scheduled
 * ============================================================ */

function run_scheduler() {
    static $ran = false;
    if ($ran) return 0;
    $ran = true;
    $activated = 0;
    try {
        $stmt = db()->prepare(
            "SELECT cr.*, p.duration_hours
             FROM card_receipts cr
             JOIN pricing p ON p.plan_code = cr.plan_code
             WHERE cr.status = 'approved_pending'
               AND cr.activate_at IS NOT NULL
               AND cr.activate_at <= NOW()
             LIMIT 50"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            try {
                $startTs = strtotime($r['activate_at']);
                $endTs   = $startTs + (int)$r['duration_hours'] * 3600;
                $start   = date('Y-m-d H:i:s', $startTs);
                $end     = date('Y-m-d H:i:s', $endTs);
                // ⭐ ریست کامل سهمیه‌ها (مثل activate_subscription)
                db()->prepare("
                    UPDATE users
                       SET subscription_type   = ?,
                           subscription_start  = ?,
                           subscription_end    = ?,
                           messages_used_total = 0,
                           messages_used_today = 0,
                           free_used_today     = 0,
                           last_reset_date     = CURDATE()
                     WHERE id = ?
                ")->execute([$r['plan_code'], $start, $end, $r['user_id']]);
                db()->prepare("INSERT INTO transactions (user_id, plan_code, amount, status, ref_id, payment_method, activate_at) VALUES (?,?,?,'paid',?,'card',?)")
                    ->execute([$r['user_id'], $r['plan_code'], $r['amount'], 'receipt_' . $r['id'], $r['activate_at']]);
                db()->prepare("UPDATE card_receipts SET status='approved' WHERE id=?")
                    ->execute([$r['id']]);
                $activated++;
            } catch (Throwable $inner) {}
        }
    } catch (Throwable $e) {}
    return $activated;
}

function maybe_run_scheduler() {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    if (!env('AUTO_SCHEDULER_ON_REQUEST', true)) return;

    $interval = max(60, (int)env('AUTO_SCHEDULER_CHECK_INTERVAL', 300));
    $baseDir  = defined('UPLOADS_PATH') ? UPLOADS_PATH : (defined('ROOT_PATH') ? ROOT_PATH . '/uploads' : sys_get_temp_dir());
    $marker   = rtrim($baseDir, '/\\') . '/.scheduler_checked_at';

    $last = @filemtime($marker);
    if ($last && (time() - (int)$last) < $interval) return;

    if (!is_dir($baseDir)) @mkdir($baseDir, 0755, true);
    @touch($marker);

    try {
        $c = (int)db()->query("SELECT COUNT(*) FROM card_receipts WHERE status='approved_pending' AND activate_at <= NOW()")->fetchColumn();
        if ($c > 0) run_scheduler();
    } catch (Throwable $e) {}
}
