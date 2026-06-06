<?php
/**
 * ============================================================
 *  دانش‌یار - قفل همزمان پردازش تصویر (File-based Semaphore)
 *
 *  هاست: ۱ هسته CPU | ۱ گیگ RAM
 *  - حداکثر ۳ پردازش تصویر همزمان (بقیه صف می‌شن)
 *  - ۴۵ ثانیه timeout = ۱۲ نفر × ~۳-۴ ثانیه پردازش = همه رد می‌شن
 *  - شستشوی خودکار قفل‌های مرده
 * ============================================================
 */

// ⚠ ۱ هسته = فقط ۱ پردازش واقعاً همزمان روی CPU
// ۳ تا می‌ذاریم چون GD resize سریع تموم می‌شه
define('MAX_CONCURRENT_IMAGE_PROC', 3);
define('IMAGE_PROC_WAIT_TIMEOUT', 45);
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

                if ($lockPid === $pid) {
                    $myLock = $locksDir . '/' . $f;
                    continue;
                }

                $lockPath = $locksDir . '/' . $f;
                $isAlive = false;
                if (function_exists('posix_getpgid')) {
                    $isAlive = @posix_getpgid($lockPid) !== false;
                } else {
                    $lockTime = @filemtime($lockPath) ?: 0;
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

        usleep(300000); // 300ms
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

        if (function_exists('posix_getpgid')) {
            $isDead = @posix_getpgid($lockPid) === false;
        } else {
            $lockPath = $locksDir . '/' . $f;
            $lockTime = @filemtime($lockPath) ?: 0;
            $isDead = ($now - $lockTime) >= 60;
        }

        if ($isDead) @unlink($locksDir . '/' . $f);
    }
}

cleanup_dead_locks();
