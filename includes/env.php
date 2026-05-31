<?php
/**
 * ============================================================
 *  دانش‌یار - بارگذار ساده‌ی متغیرهای محیطی (.env)
 *  بدون هیچ وابستگی خارجی.
 * ============================================================
 */

if (!function_exists('load_env')) {
    /**
     * فایل .env را می‌خواند و در $_ENV / getenv قرار می‌دهد.
     * فقط یک‌بار اجرا می‌شود.
     */
    function load_env(string $path): void {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;

        if (!is_file($path) || !is_readable($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;

            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);

            // حذف کوتیشن‌های احتمالی
            if (strlen($val) >= 2) {
                $first = $val[0];
                $last  = $val[strlen($val) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $val = substr($val, 1, -1);
                }
            }

            if ($key === '') continue;
            // اگر از قبل (در محیط واقعی سرور) ست شده، بازنویسی نکن
            if (getenv($key) === false) {
                putenv("$key=$val");
            }
            $_ENV[$key]    = $val;
            $_SERVER[$key] = $val;
        }
    }
}

if (!function_exists('env')) {
    /**
     * مقدار یک متغیر محیطی را با fallback برمی‌گرداند.
     */
    function env(string $key, $default = null) {
        $val = getenv($key);
        if ($val === false) {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }
        if ($val === null) return $default;

        // تبدیل مقادیر بولی متنی
        switch (strtolower((string)$val)) {
            case 'true':  return true;
            case 'false': return false;
            case 'null':  return null;
            case '':      return $default;
        }
        return $val;
    }
}

// بارگذاری خودکار فایل .env از ریشه‌ی پروژه
load_env(dirname(__DIR__) . '/.env');
