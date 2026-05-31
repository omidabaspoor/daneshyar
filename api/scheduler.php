<?php
/**
 * ============================================================
 *  دانش‌یار – Scheduler خودکار
 *
 *  برای استفاده با cron هر ۵ دقیقه:
 *  * /5 * * * * curl -s "https://yourdomain.ir/api/scheduler.php?token=TOKEN" >/dev/null 2>&1
 *
 *  TOKEN = dy_sch_ + md5('daneshyar_scheduler_secret_key_2025')
 * ============================================================
 */
require_once dirname(__DIR__) . '/includes/functions.php';

define('SCHEDULER_TOKEN', 'dy_sch_' . md5('daneshyar_scheduler_secret_key_2025'));

// اگه مستقیم call شده، token چک کن
$isCli    = (php_sapi_name() === 'cli');
$isDirect = !$isCli && (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? ''));

if ($isDirect) {
    $token = $_GET['token'] ?? $_SERVER['HTTP_X_SCHEDULER_TOKEN'] ?? '';
    if ($token !== SCHEDULER_TOKEN) {
        http_response_code(403);
        die(json_encode(['ok' => false, 'error' => 'Forbidden']));
    }
}

$activated = run_scheduler();

if ($isDirect || $isCli) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'        => true,
        'activated' => $activated,
        'time'      => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
}
