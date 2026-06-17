<?php
/**
 * ============================================================
 *  دانش‌یار - قفل همزمان پردازش تصویر (File-based Semaphore)
 *
 *  هاست فعلی: ۳ هسته CPU | ۳ گیگ RAM
 *  - حداکثر ۱۵ پردازش تصویر همزمان برای روز امتحان
 *  - timeout کوتاه‌تر برای اینکه دانش‌آموز معطل صف طولانی نشود
 *  - شستشوی خودکار قفل‌های مرده و lockهای جاماندهٔ PHP-FPM
 * ============================================================
 */

// سرور فعلی: ۳ هسته / ۳ گیگ رم و حدود ۱۰ تا ۱۵ کاربر همزمان.
// پردازش بیشتر عکس‌ها سبک است (معمولاً ۳۰۰ تا ۴۰۰ کیلوبایت)، پس صف را بازتر می‌گذاریم.
// اگر هاست ضعیف‌تر شد، این عدد را به ۷ تا ۱۰ برگردان.
define('MAX_CONCURRENT_IMAGE_PROC', 15);      // حداکثر ۱۵ پردازش تصویر همزمان
define('IMAGE_PROC_WAIT_TIMEOUT', 25);        // حداکثر ۲۵ ثانیه صبر؛ برای UX امتحانی سریع‌تر
define('IMAGE_PROC_LOCK_DIR', '');

function _lock_dir() {
    if (defined('IMAGE_PROC_LOCK_DIR') && IMAGE_PROC_LOCK_DIR !== '') {
        return IMAGE_PROC_LOCK_DIR;
    }
    return defined('UPLOADS_PATH') ? UPLOADS_PATH : (defined('ROOT_PATH') ? ROOT_PATH . '/uploads' : sys_get_temp_dir());
}

function acquire_image_proc_lock($waitTimeout = IMAGE_PROC_WAIT_TIMEOUT) {
    $lockDir = rtrim(_lock_dir(), '/\\');
    $locksDir = $lockDir . '/.proc_locks';
    if (!is_dir($locksDir)) @mkdir($locksDir, 0755, true);

    $pid = getmypid();
    $start = microtime(true);

    while (true) {
        $activeLocks = 0;
        $myLock = null;
        $now = time();

        if (is_dir($locksDir)) {
            foreach (scandir($locksDir) as $f) {
                if (!preg_match('/^proc_(\d+)\.lock$/', $f, $m)) continue;
                $lockPid = (int)$m[1];
                $lockPath = $locksDir . '/' . $f;
                $lockTime = @filemtime($lockPath) ?: 0;

                // اگر از نسخه قبلی lock جامانده باشد، ممکن است PID هنوز زنده باشد (PHP-FPM idle)
                // ولی پردازشی در کار نباشد؛ پس lockهای قدیمی را با سن فایل پاک می‌کنیم.
                if ($lockTime > 0 && ($now - $lockTime) > 180) {
                    @unlink($lockPath);
                    continue;
                }

                if ($lockPid === $pid) {
                    $myLock = $lockPath;
                    continue;
                }

                $isAlive = false;
                if (function_exists('posix_getpgid')) {
                    $isAlive = @posix_getpgid($lockPid) !== false;
                } else {
                    $isAlive = ($now - $lockTime) < 60;
                }

                if ($isAlive) {
                    $activeLocks++;
                } else {
                    @unlink($lockPath);
                }
            }
        }

        if ($myLock) return true;

        if ($activeLocks < MAX_CONCURRENT_IMAGE_PROC) {
            $lockFile = $locksDir . '/proc_' . $pid . '.lock';
            $fp = @fopen($lockFile, 'x');
            if ($fp !== false) {
                fwrite($fp, (string)time());
                fclose($fp);
                return true;
            }
        }

        if (microtime(true) - $start >= $waitTimeout) {
            return false;
        }

        usleep(100000); // 100ms - سریع‌تر برای صف کوتاهِ روز امتحان
    }
}

function release_image_proc_lock() {
    $lockDir = rtrim(_lock_dir(), '/\\');
    $locksDir = $lockDir . '/.proc_locks';
    $pid = getmypid();
    @unlink($locksDir . '/proc_' . $pid . '.lock');
}

function cleanup_dead_locks() {
    $lockDir = rtrim(_lock_dir(), '/\\');
    $locksDir = $lockDir . '/.proc_locks';
    if (!is_dir($locksDir)) return;

    $markerFile = $locksDir . '/.last_cleanup';
    $lastCleanup = @filemtime($markerFile) ?: 0;
    if (time() - $lastCleanup < 120) return;

    @touch($markerFile);
    $now = time();

    foreach (scandir($locksDir) as $f) {
        if (!preg_match('/^proc_(\d+)\.lock$/', $f, $m)) continue;
        $lockPid = (int)$m[1];
        $isDead = true;

        $lockPath = $locksDir . '/' . $f;
        $lockTime = @filemtime($lockPath) ?: 0;
        if ($lockTime > 0 && ($now - $lockTime) > 180) {
            $isDead = true;
        } elseif (function_exists('posix_getpgid')) {
            $isDead = @posix_getpgid($lockPid) === false;
        } else {
            $isDead = ($now - $lockTime) >= 60;
        }

        if ($isDead) @unlink($lockPath);
    }
}

cleanup_dead_locks();
