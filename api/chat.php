<?php
/**
 * ============================================================
 *  دانش‌یار - endpoint استریم چت AI (SSE)
 *
 *  ویژگی‌های مهم این نسخه:
 *   1. تصویر همیشه قبل از ارسال به AI، normalize می‌شه (HEIC→JPG، resize).
 *   2. PDF به‌جای استخراج متن regex-based (که برای PDF فارسی شکست می‌خورد)،
 *      مستقیماً به‌عنوان image_url base64 به مدل multimodal پاس می‌شه.
 *   3. تاریخچه چت با truncate درست UTF-8.
 *   4. تشخیص garbage tokens و قطع استریم با پیام مناسب.
 *   5. در صورت شکست استریم یا abort کاربر، quota refund می‌شه.
 *   6. وقتی quota پر باشه، یه پاسخ SSE فارسی داخل bubble نشون می‌ده
 *      (نه یه alert خشک).
 *   7. سیستم RAG: chunk‌های مرتبط کتاب بر اساس سوال انتخاب و به prompt اضافه می‌شن.
 *   8. book_id روی چت ثابت می‌مونه: وقتی کاربر کتاب عوض کنه، book_id چت آپدیت می‌شه.
 * ============================================================
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai.php';
require_once __DIR__ . '/../includes/image_helper.php';
require_once __DIR__ . '/../includes/book_chunker.php';

@set_time_limit(300);
@ini_set('memory_limit', '256M');
@ignore_user_abort(false); // اگه کاربر تب رو بست، می‌خوایم بفهمیم تا refund کنیم

$user = current_user();
if (!$user) {
    sse_error_and_exit('برای استفاده از چت ابتدا وارد حساب کاربری خود شو.', 401);
}
if (is_banned($user)) {
    sse_error_and_exit('حساب شما مسدود شده است.', 403);
}
if (!is_profile_complete($user)) {
    sse_error_and_exit('برای ادامه استفاده، لطفاً از بخش پروفایل نام واقعی و نام مدرسه را تکمیل کن.', 403);
}

$csrfToken = $_POST['csrf'] ?? '';
if (!csrf_check($csrfToken)) {
    // Same tolerant CSRF check as upload.php to completely eliminate "security code expired" errors
    $currentUserForCsrf = current_user();
    if (!$currentUserForCsrf) {
        sse_error_and_exit('توکن امنیتی نامعتبر است. صفحه را رفرش کن و دوباره تلاش کن.', 403);
    }
    @error_log("CSRF token mismatch tolerated for authenticated user ID: " . $currentUserForCsrf['id'] . " (chat)");
}

$message = trim((string)($_POST['message'] ?? ''));
$book_id = (int)($_POST['book_id'] ?? 0);
$chat_id = (int)($_POST['chat_id'] ?? 0);

if ($message === '' && empty($_POST['attachment_path'])) {
    sse_error_and_exit('پیام خالی است.', 400);
}

// ============== چک سهمیه قبل از پردازش ==============
$check = can_send_message($user);
if (!$check['ok']) {
    sse_quota_block_message($check, $user);
    exit;
}

// ============== مدیریت کتاب و چت ==============
$book = null;

// اگر کاربر book_id فرستاده، اون رو استفاده کن
if ($book_id > 0) {
    $book = get_book_for_user($book_id, $user);
}

// چت رو پیدا یا بساز
$chat = null;
if ($chat_id > 0) {
    $chat = get_chat($chat_id, $user['id']);
}

if (!$chat) {
    // چت جدید بساز
    $title   = $message !== '' ? smart_chat_title($message) : 'گفت‌وگوی جدید';
    $chat_id = create_chat($user['id'], $title, $book ? (int)$book['id'] : null);
    $chat    = get_chat($chat_id, $user['id']);
} else {
    // چت موجود: مدیریت book_id
    if ($book) {
        // کاربر کتاب جدید انتخاب کرده → آپدیت book_id چت
        if ((int)($chat['book_id'] ?? 0) !== (int)$book['id']) {
            update_chat_book($chat_id, (int)$book['id']);
            $chat['book_id'] = (int)$book['id'];
        }
    } elseif ($book_id === 0 && !empty($chat['book_id'])) {
        // کاربر book_id نفرستاده ولی چت book_id داره → از book_id چت استفاده کن
        $book = get_book_for_user((int)$chat['book_id'], $user);
    }
    // اگر book_id === -1 فرستاده (یعنی عمومی انتخاب کرده)، book null می‌مونه و book_id چت هم آپدیت می‌شه
    if ($book_id === -1 && !empty($chat['book_id'])) {
        update_chat_book($chat_id, null);
        $book = null;
    }
}

$imageBase64         = null;
$imageMime           = null;
$attachmentSavedPath = null;
$attachmentIsPdf     = false;

// ============== آماده‌سازی ضمیمه ==============
if (!empty($_POST['attachment_path'])) {
    $rel = ltrim((string)$_POST['attachment_path'], '/\\');
    $rel = str_replace(['..', "\0"], '', $rel);
    if (strpos($rel, 'uploads/') !== 0) {
        sse_error_and_exit('مسیر ضمیمه نامعتبر است.', 400);
    }
    $attachmentSavedPath = $rel;
    $fullPath = ROOT_PATH . '/' . $rel;

    if (!is_file($fullPath)) {
        sse_error_and_exit('فایل ضمیمه یافت نشد.', 400);
    }

    $mime = upload_mime($fullPath);
    if (is_heic_file($fullPath)) $mime = 'image/heic';

    if (is_string($mime) && strpos($mime, 'image/') === 0) {
        // عکس: normalize کن (HEIC→JPG، resize)
        $prep = prepare_image_for_ai($fullPath, $mime);
        if (!$prep['ok']) {
            sse_error_and_exit($prep['error'], 400);
        }
        $fullPath  = $prep['path'];
        $imageMime = $prep['mime'];

        $newRel = 'uploads/' . basename($fullPath);
        if ($newRel !== $attachmentSavedPath) {
            $attachmentSavedPath = $newRel;
        }

        $raw = @file_get_contents($fullPath);
        if ($raw === false || strlen($raw) === 0) {
            sse_error_and_exit('خواندن تصویر ناموفق بود.', 500);
        }
        $imageBase64 = base64_encode($raw);
        unset($raw);

    } elseif ($mime === 'application/pdf' || $mime === 'application/x-pdf') {
        // PDF: مستقیماً base64 می‌کنیم و به AI می‌فرستیم
        $attachmentIsPdf = true;
        $fileSize = filesize($fullPath);

        // محدودیت اندازه برای multimodal: 20MB
        if ($fileSize > 20 * 1024 * 1024) {
            sse_error_and_exit('حجم PDF بیش از حد است (حداکثر ۲۰ مگابایت). لطفاً فایل کوچک‌تری بفرست.', 400);
        }

        $raw = @file_get_contents($fullPath);
        if ($raw === false || strlen($raw) === 0) {
            sse_error_and_exit('خواندن PDF ناموفق بود.', 500);
        }
        $imageBase64 = base64_encode($raw);
        $imageMime   = 'application/pdf';
        unset($raw);
    } else {
        sse_error_and_exit('فرمت ضمیمه پشتیبانی نمی‌شود.', 400);
    }
}

// متن نهایی کاربر برای ارسال به AI
$userContent = $message;
if ($userContent === '' && $imageBase64) {
    $userContent = $attachmentIsPdf
        ? 'این فایل PDF امتحانی را بررسی کن و همهٔ سؤال‌های داخلش را به ترتیب، پاسخنامه‌ای، دقیق و کوتاه جواب بده. اگر سؤال حل‌کردنی بود فقط راه‌حل لازم برای نمره کامل را بنویس.'
        : 'این تصویر امتحانی را تحلیل کن و همهٔ سؤال‌های داخلش را به ترتیب، پاسخنامه‌ای، دقیق و کوتاه جواب بده. اگر سؤال حل‌کردنی بود فقط راه‌حل لازم برای نمره کامل را بنویس.';
}
if ($userContent === '') {
    sse_error_and_exit('پیام خالی است.', 400);
}

// رزرو سهمیه (atomic UPDATE)
if (!reserve_message_quota($user, $check['mode'])) {
    $userFresh = current_user_fresh();
    $checkAgain = can_send_message($userFresh);
    sse_quota_block_message($checkAgain['ok'] ? ['ok'=>false, 'reason'=>'سقف پیام شما پر شده است.', 'mode'=>$check['mode']] : $checkAgain, $userFresh);
    exit;
}

// از این لحظه به بعد، اگه چیزی شکست خورد، باید refund کنیم
$quotaConsumed = true;

// ذخیره پیام کاربر
db()->prepare("INSERT INTO chat_history (user_id, chat_id, book_id, role, content, attachment) VALUES (?,?,?, 'user', ?, ?)")
    ->execute([$user['id'], $chat_id, $book ? (int)$book['id'] : null, $message ?: '(فایل)', $attachmentSavedPath]);
update_chat_timestamp($chat_id);

// ============== تشخیص حالت امتحانی هندسه/زیست ==============
$userForPrompt = $user;
$hasAttachment = $imageBase64 !== null;

// اگر دانش‌آموز کتاب انتخاب نکرده ولی عکس امتحانی فرستاده، برای فردای امتحان از رشته‌اش کمک می‌گیریم.
// ریاضی => هندسه، تجربی => زیست. اگر کتاب انتخاب شده باشد، عنوان/درس کتاب معیار اصلی است.
if (!$book && $hasAttachment) {
    $majorCode = (string)($user['major'] ?? '');
    if ($majorCode === 'math') {
        $userForPrompt['_exam_focus'] = 'geometry هندسه';
    } elseif ($majorCode === 'experimental') {
        $userForPrompt['_exam_focus'] = 'biology زیست زیست‌شناسی';
    }
}

$examFocus = DaneshyarAI::examFocus($book, $userForPrompt, $userContent);
$isBiology  = !empty($examFocus['biology']);
$isGeometry = !empty($examFocus['geometry']);

// ============== سیستم RAG: پیدا کردن chunk‌های مرتبط ==============
$bookContext = '';
if ($book) {
    try {
        // وقتی سؤال از روی تصویر/PDF است، متن سؤال را برای جستجو نداریم؛ پس برای زیست/هندسه
        // کانتکست گسترده و متوازن می‌فرستیم تا مدل به همه فصل‌ها دسترسی داشته باشد.
        if ($hasAttachment && $isBiology) {
            $chunks = exam_context_chunks((int)$book['id'], 42000, 90);
        } elseif ($hasAttachment && $isGeometry) {
            $chunks = exam_context_chunks((int)$book['id'], 24000, 60);
        } elseif ($isBiology) {
            $chunks = find_relevant_chunks((int)$book['id'], $userContent, 10, 24000);
        } elseif ($isGeometry) {
            $chunks = find_relevant_chunks((int)$book['id'], $userContent, 8, 18000);
        } else {
            $chunks = find_relevant_chunks((int)$book['id'], $userContent, 6, 12000);
        }

        if (!empty($chunks)) {
            $bookContext = build_book_context($chunks, $book);
        }
    } catch (Throwable $e) {
        // اگر RAG شکست خورد، بدون context ادامه بده
    }
}

// ============== ساخت لیست پیام‌ها برای AI ==============
$messagesArr = [
    ['role'=>'system', 'content'=>DaneshyarAI::systemPrompt($book, $userForPrompt, $bookContext, $userContent)],
];

$h = db()->prepare("
    SELECT role, content FROM (
        SELECT id, role, content FROM chat_history
        WHERE chat_id=? ORDER BY id DESC LIMIT 21
    ) x ORDER BY id ASC
");
$h->execute([$chat_id]);
$hist = $h->fetchAll();

// حذف آخرین پیام تاریخچه (که همین الان ذخیره شد) - بعداً با محتوای کامل می‌فرستیم
if (!empty($hist) && end($hist)['role'] === 'user') {
    array_pop($hist);
}

foreach ($hist as $p) {
    $content = sanitize_utf8((string)$p['content']);
    if (mb_strlen($content, 'UTF-8') > 3000) {
        $content = mb_substr($content, 0, 3000, 'UTF-8') . '…';
    }
    $messagesArr[] = ['role' => $p['role'], 'content' => $content];
}

$messagesArr[] = ['role' => 'user', 'content' => $userContent];

// ============== شروع استریم SSE ==============
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('X-Content-Type-Options: nosniff');
echo ": ping\n\n";
@flush();

$fullReplyText = '';
$buffer        = '';
$gotError      = false;
$emittedAny    = false;

DaneshyarAI::streamChat(
    $messagesArr,
    $imageBase64,
    $imageMime,
    null,
    function ($chunk) use (&$fullReplyText, &$buffer, &$gotError, &$emittedAny) {
        $buffer .= $chunk;

        // اگر کلاینت قطع شد، استریم رو متوقف کن
        if (connection_aborted()) {
            return;
        }

        // اگر اولین بایت‌ها JSON بود (یعنی API به‌جای SSE خطا برگردونده)
        $trimmed = ltrim($buffer);
        if (!$emittedAny && $trimmed !== '' && $trimmed[0] === '{' && strpos($buffer, "data:") === false) {
            if (substr_count($trimmed, '{') === substr_count($trimmed, '}')) {
                $errData = json_decode($trimmed, true);
                if (is_array($errData)) {
                    $errMsg = 'خطای سرویس AI';
                    if (isset($errData['error']['message'])) $errMsg = (string)$errData['error']['message'];
                    elseif (isset($errData['error'])) $errMsg = is_string($errData['error']) ? $errData['error'] : json_encode($errData['error'], JSON_UNESCAPED_UNICODE);
                    echo "data: " . json_encode(['error' => $errMsg], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                    $buffer = '';
                    $gotError = true;
                    return;
                }
            }
            return;
        }

        // پردازش خطوط SSE
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = rtrim(substr($buffer, 0, $pos), "\r");
            $buffer = substr($buffer, $pos + 1);

            if ($line === '' || $line[0] === ':') continue;
            if (strpos($line, 'data: ') !== 0)   continue;

            $dataStr = substr($line, 6);
            if ($dataStr === '[DONE]') return;

            $json = json_decode($dataStr, true);
            if (!is_array($json)) continue;

            if (!empty($json['error'])) {
                $errMsg = is_array($json['error']) ? ($json['error']['message'] ?? 'خطای AI') : (string)$json['error'];
                echo "data: " . json_encode(['error' => $errMsg], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
                $gotError = true;
                return;
            }

            $text = $json['choices'][0]['delta']['content'] ?? null;
            if ($text === null || $text === '') continue;

            $text = sanitize_utf8($text);
            if ($text === '') continue;

            $fullReplyText .= $text;
            $emittedAny = true;
            echo "data: " . json_encode(['chunk' => $text], JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            @flush();

            // محافظت در برابر loop بی‌نهایت مدل
            if (mb_strlen($fullReplyText, 'UTF-8') > 30000) {
                echo "data: " . json_encode(['chunk' => "\n\n…"], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
                return;
            }

            // تشخیص garbage repetition
            if (looks_like_garbage_tail($fullReplyText)) {
                echo "data: " . json_encode(['error' => 'پاسخ هوش مصنوعی نامعتبر دریافت شد. لطفاً دوباره تلاش کن یا سوال را واضح‌تر بپرس.'], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
                $gotError = true;
                return;
            }
        }
    }
);

// ============== پایان استریم ==============
$clientAborted = connection_aborted();

// اگه پاسخ مفید نگرفتیم یا کلاینت قطع شد، quota رو برگردون
if ($quotaConsumed && (!$emittedAny || $gotError || $clientAborted)) {
    refund_message_quota($user, $check['mode']);
    $quotaConsumed = false;
}

// تأیید پایان (فقط اگر کلاینت متصل است)
if (!$clientAborted) {
    echo "data: " . json_encode(['ok' => true, 'chat_id' => $chat_id, 'mode' => $check['mode']]) . "\n\n";
    @flush();
}

// ذخیره پاسخ کامل
if (!empty($fullReplyText) && !$gotError && !$clientAborted) {
    db()->prepare("INSERT INTO chat_history (user_id, chat_id, book_id, role, content) VALUES (?,?,?, 'assistant', ?)")
        ->execute([$user['id'], $chat_id, $book ? (int)$book['id'] : null, $fullReplyText]);
}

/* ============================================================
 *  helpers
 * ============================================================ */

/**
 * یه پاسخ AI-style فارسی به کاربر می‌فرستیم وقتی quota پر شده.
 * این به‌جای 403 JSON، یه bubble کامل با پیام واضح می‌سازه.
 */
function sse_quota_block_message($check, $user) {
    while (ob_get_level() > 0) { ob_end_clean(); }

    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    echo ": ping\n\n";

    $mode  = $check['mode']  ?? 'blocked';
    $plan  = $check['plan']  ?? null;
    $limitHit = $check['limit_hit'] ?? null;
    $expired  = !empty($check['expired']);

    // پیام سفارشی بر اساس وضعیت
    if ($mode === 'free_blocked') {
        $msg = "**سهمیه پیام رایگان امروز شما تکمیل شد** 😔\n\n";
        $msg .= "برای ادامه یادگیری، می‌تونی یکی از کارها رو انجام بدی:\n";
        $msg .= "- ⏰ تا فردا صبح صبر کن (سهمیه رایگان ریست می‌شه)\n";
        $msg .= "- 💎 یک پلن اشتراک تهیه کن تا بدون محدودیت سوال بپرسی\n\n";
        $msg .= "👈 [مشاهده پلن‌های اشتراک](" . BASE_URL . "/pricing.php)";
    }
    elseif ($mode === 'subscription_blocked' && $expired) {
        $planTitle = $plan['title'] ?? 'اشتراک شما';
        $msg = "**⏱ اشتراک {$planTitle} به پایان رسیده است**\n\n";
        $msg .= "برای ادامه استفاده از دانش‌یار، اشتراکت رو تمدید کن.\n\n";
        $msg .= "👈 [تمدید اشتراک](" . BASE_URL . "/pricing.php)";
    }
    elseif ($mode === 'subscription_blocked' && $limitHit === 'daily') {
        $planTitle = $plan['title'] ?? 'پلن';
        $dailyLimit = (int)($plan['daily_limit'] ?? 0);
        $msg = "**📊 سقف پیام‌های امروز شما پر شد**\n\n";
        $msg .= "شما امروز " . num_fa($dailyLimit) . " پیام از پلن «{$planTitle}» مصرف کردی.\n";
        $msg .= "🌅 سهمیه روزانه شما فردا ساعت ۰۰:۰۰ ریست می‌شه.\n\n";
        $msg .= "اگه می‌خوای همین الان ادامه بدی، می‌تونی پلن بالاتر بگیری:\n";
        $msg .= "👈 [ارتقاء اشتراک](" . BASE_URL . "/pricing.php)";
    }
    elseif ($mode === 'subscription_blocked' && $limitHit === 'total') {
        $planTitle = $plan['title'] ?? 'پلن';
        $totalLimit = (int)($plan['total_limit'] ?? 0);
        $msg = "**📊 سقف کل پیام‌های پلن «{$planTitle}» تکمیل شد**\n\n";
        $msg .= "شما تمام " . num_fa($totalLimit) . " پیام پلن خود را مصرف کردی.\n";
        $msg .= "برای ادامه، یک پلن جدید تهیه کن:\n\n";
        $msg .= "👈 [خرید پلن جدید](" . BASE_URL . "/pricing.php)";
    }
    elseif ($mode === 'subscription_blocked' && !empty($check['sub']['starts_in'])) {
        $hours = (int)ceil($check['sub']['starts_in'] / 3600);
        $msg = "**⏰ اشتراک شما هنوز فعال نشده است**\n\n";
        $msg .= "اشتراکت تا حدود " . num_fa($hours) . " ساعت دیگر شروع می‌شه.\n";
        $msg .= "تا اون موقع می‌تونی از سهمیه رایگان (۳ پیام در روز) استفاده کنی.";
    }
    else {
        $msg = "⚠ " . ($check['reason'] ?? 'دسترسی به چت در حال حاضر امکان‌پذیر نیست.');
    }

    // به‌صورت chunk‌های کوچک بفرستیم تا حس استریم بده
    foreach (preg_split('//u', $msg) as $ch) {
        if ($ch === '') continue;
        echo "data: " . json_encode(['chunk' => $ch], JSON_UNESCAPED_UNICODE) . "\n\n";
        @flush();
        usleep(8000);
    }
    echo "data: " . json_encode(['ok' => true, 'blocked' => true, 'mode' => $mode]) . "\n\n";
    @flush();
}

/**
 * خطای ساده (برای حالاتی که هنوز header SSE نداده‌ایم).
 */
function sse_error_and_exit($message, $code = 400) {
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/**
 * تشخیص خروجی خراب (garbage tokens) از مدل
 */
function looks_like_garbage_tail($text) {
    // افزایش آستانه تشخیص زباله برای جلوگیری از قطع پاسخ‌های طولانی و دقیق
    $tail = mb_substr($text, -500, null, 'UTF-8');
    if ($tail === '') return false;

    // تکرار بسیار زیاد (>100 بار) از یک کاراکتر
    if (preg_match('/(.)\\1{100,}/u', $tail)) return true;

    // یک کلمه لاتین غیرمتعارف بسیار طولانی (بدون فاصله/علامت)
    if (preg_match('/[A-Za-z]{150,}/', $tail)) return true;

    // tail کاملاً ASCII و طولانی در پاسخ فارسی (بدون هیچ کاراکتر فارسی در ۵۰۰ کاراکتر آخر)
    if (mb_strlen($tail, 'UTF-8') >= 400) {
        $nonAscii = preg_match_all('/[^\\x00-\\x7F]/u', $tail);
        if ($nonAscii === 0) {
            $alphaRand = preg_match_all('/[A-Za-z]/', $tail);
            if ($alphaRand > 350) return true;
        }
    }
    return false;
}
