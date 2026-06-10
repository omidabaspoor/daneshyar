<?php
/**
 * ============================================================
 *  دانش‌یار - استخراج دقیقِ تکه‌تکه کتاب (حالت دقیق)
 *
 *  این endpoint توسط جاوااسکریپت صفحهٔ کتاب‌ها به‌صورت گام‌به‌گام
 *  صدا زده می‌شود تا:
 *    1) فهرست درس‌های PDF را بگیرد (action=start)
 *    2) هر بار فقط چند درس را دقیق استخراج کند (action=step)
 *    3) در پایان، متن کامل را chunk و ذخیره کند (action=finish)
 *
 *  چرا این‌طوری؟ روی هاست ۱ هسته/۱ گیگ، هر درخواست فقط یک تماس API
 *  دارد؛ پس نه تایم‌اوت می‌خوریم، نه حافظه پر می‌شود، و هیچ درسی هم
 *  به‌خاطر محدودیت توکن خلاصه نمی‌شود.
 * ============================================================
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/book_chunker.php';
require_admin();
ensure_book_chunks_schema();
ensure_book_extract_jobs_schema();

@set_time_limit(290);
@ini_set('memory_limit', '256M');

header('Content-Type: application/json; charset=utf-8');

if (!csrf_check($_POST['csrf'] ?? ($_GET['csrf'] ?? ''))) {
    echo json_encode(['ok' => false, 'error' => 'توکن امنیتی نامعتبر است. صفحه را رفرش کن.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action  = $_POST['action'] ?? '';
$book_id = (int)($_POST['book_id'] ?? 0);
if ($book_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'شناسه کتاب نامعتبر است.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$book = db()->prepare("SELECT * FROM books WHERE id=?");
$book->execute([$book_id]);
$book = $book->fetch();
if (!$book) {
    echo json_encode(['ok' => false, 'error' => 'کتاب یافت نشد.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// لیست فایل‌های PDF کتاب
$files = array_values(array_filter(array_map('trim', explode(',', $book['file_names'] ?? $book['file_name']))));
if (empty($files)) {
    echo json_encode(['ok' => false, 'error' => 'فایلی برای این کتاب ثبت نشده.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * PDF را می‌خواند و base64 می‌کند (با محدودیت حجم).
 */
function _read_pdf_b64($relName) {
    $path = BOOKS_PATH . '/' . $relName;
    if (!is_file($path)) return [null, 'فایل ' . $relName . ' یافت نشد.'];
    $size = filesize($path);
    if ($size > 20 * 1024 * 1024) return [null, 'حجم ' . $relName . ' بیش از ۲۰ مگابایت است.'];
    $raw = @file_get_contents($path);
    if ($raw === false || strlen($raw) === 0) return [null, 'خواندن ' . $relName . ' ناموفق.'];
    return [base64_encode($raw), null];
}

try {
    if ($action === 'start') {
        // ── ساخت نقشهٔ کار: برای هر فایل، فهرست درس‌ها را بگیر ──
        $plan = []; // هر آیتم: ['file'=>idx, 'titles'=>[...]]
        $warnings = [];

        foreach ($files as $fi => $fn) {
            [$b64, $err] = _read_pdf_b64($fn);
            if ($err) { $warnings[] = $err; continue; }

            $det = book_detect_sections($b64);
            unset($b64);

            if (!$det['ok'] || empty($det['sections'])) {
                // اگر فهرست تشخیص داده نشد، کل فایل را یک تکه در نظر بگیر
                $plan[] = ['file' => $fi, 'titles' => null]; // null = کل فایل
                $warnings[] = 'فایل ' . ($fi+1) . ': فهرست تشخیص داده نشد، کل فایل یکجا استخراج می‌شود.';
                continue;
            }

            $sections = $det['sections'];
            $batch = book_batch_size(count($sections));
            for ($i = 0; $i < count($sections); $i += $batch) {
                $plan[] = ['file' => $fi, 'titles' => array_slice($sections, $i, $batch)];
            }
        }

        if (empty($plan)) {
            echo json_encode(['ok' => false, 'error' => 'هیچ محتوایی برای استخراج پیدا نشد. ' . implode(' | ', $warnings)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // پاک‌کردن متن قبلی و ذخیرهٔ نقشه
        db()->prepare("DELETE FROM book_extract_jobs WHERE book_id=?")->execute([$book_id]);
        db()->prepare("INSERT INTO book_extract_jobs (book_id, plan_json, accumulated, current_step, total_steps, status) VALUES (?,?,?,0,?, 'running')")
            ->execute([$book_id, json_encode($plan, JSON_UNESCAPED_UNICODE), '', count($plan)]);

        echo json_encode([
            'ok'    => true,
            'total' => count($plan),
            'warnings' => $warnings,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'step') {
        $job = db()->prepare("SELECT * FROM book_extract_jobs WHERE book_id=?");
        $job->execute([$book_id]);
        $job = $job->fetch();
        if (!$job) { echo json_encode(['ok'=>false,'error'=>'ابتدا مرحله شروع را اجرا کن.'], JSON_UNESCAPED_UNICODE); exit; }

        $plan = json_decode($job['plan_json'], true) ?: [];
        $step = (int)$job['current_step'];
        $total = (int)$job['total_steps'];

        if ($step >= $total) {
            echo json_encode(['ok'=>true, 'done'=>true, 'step'=>$step, 'total'=>$total], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $item = $plan[$step];
        $fi = (int)$item['file'];
        $titles = $item['titles'];

        [$b64, $err] = _read_pdf_b64($files[$fi] ?? '');
        if ($err) {
            // این تکه را رد کن ولی متوقف نشو
            $newStep = $step + 1;
            db()->prepare("UPDATE book_extract_jobs SET current_step=? WHERE book_id=?")->execute([$newStep, $book_id]);
            echo json_encode(['ok'=>true, 'step'=>$newStep, 'total'=>$total, 'skipped'=>true, 'note'=>$err], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($titles === null) {
            // کل فایل یکجا (فهرست نداشت)
            $res = extract_book_content_with_ai_b64($b64, 'detailed');
            $label = 'کل فایل ' . ($fi+1);
        } else {
            $res = book_extract_sections($b64, $titles, 12000);
            $label = implode('، ', array_slice($titles, 0, 3));
        }
        unset($b64);

        $note = '';
        if ($res['ok'] && mb_strlen(trim($res['text']), 'UTF-8') > 50) {
            $acc = (string)$job['accumulated'];
            $acc .= ($acc !== '' ? "\n\n" : '') . trim($res['text']);
            db()->prepare("UPDATE book_extract_jobs SET accumulated=?, current_step=? WHERE book_id=?")
                ->execute([$acc, $step + 1, $book_id]);
        } else {
            $note = $res['error'] ?? 'این تکه خروجی نداد.';
            db()->prepare("UPDATE book_extract_jobs SET current_step=? WHERE book_id=?")->execute([$step + 1, $book_id]);
        }

        $newStep = $step + 1;
        echo json_encode([
            'ok'    => true,
            'step'  => $newStep,
            'total' => $total,
            'done'  => $newStep >= $total,
            'label' => $label,
            'note'  => $note,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'finish') {
        $job = db()->prepare("SELECT * FROM book_extract_jobs WHERE book_id=?");
        $job->execute([$book_id]);
        $job = $job->fetch();
        if (!$job) { echo json_encode(['ok'=>false,'error'=>'کاری برای پایان‌دادن نیست.'], JSON_UNESCAPED_UNICODE); exit; }

        $allText = trim((string)$job['accumulated']);
        if (function_exists('sanitize_utf8') && $allText !== '') $allText = sanitize_utf8($allText);

        if (mb_strlen($allText, 'UTF-8') < 100) {
            db()->prepare("UPDATE book_extract_jobs SET status='failed' WHERE book_id=?")->execute([$book_id]);
            echo json_encode(['ok'=>false,'error'=>'محتوای کافی استخراج نشد.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        db()->prepare("UPDATE books SET cached_text=?, extract_mode='detailed' WHERE id=?")->execute([$allText, $book_id]);
        $cnt = save_book_chunks($book_id, $allText);
        db()->prepare("UPDATE book_extract_jobs SET status='done' WHERE book_id=?")->execute([$book_id]);

        // پاکسازی نقشه برای آزادکردن فضا (متن در books.cached_text هست)
        db()->prepare("UPDATE book_extract_jobs SET accumulated='' WHERE book_id=?")->execute([$book_id]);

        echo json_encode([
            'ok'     => true,
            'chars'  => mb_strlen($allText, 'UTF-8'),
            'chunks' => $cnt,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'اکشن نامعتبر.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'خطای داخلی: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
