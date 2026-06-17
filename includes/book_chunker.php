<?php
/**
 * ============================================================
 *  دانش‌یار – سیستم مدیریت محتوای کتاب v3
 *  ─────────────────────────────────────────
 *  استراتژی: خلاصه ساختاریافته + RAG
 *
 *  چرا خلاصه و نه متن کامل؟
 *  1. Gemini Flash خودش کتاب‌های درسی ایران رو می‌شناسه
 *  2. خلاصه = فهرست + سرفصل + نکات کلیدی + آیات/احادیث
 *  3. خلاصه به AI یادآوری می‌کنه که «از همین کتاب» جواب بده
 *  4. حجم context کم = سریع‌تر + ارزان‌تر + روی هاست اشتراکی کار می‌کنه
 *
 *  استخراج: PDF رو base64 به AI می‌فرستیم (یه‌بار هنگام آپلود)
 *  ذخیره: خلاصه ساختاریافته → chunk → ذخیره در DB
 *  سوال: RAG بهترین chunk‌ها رو پیدا و به prompt اضافه می‌کنه
 *
 *  سازگار با cPanel (فقط PHP، بدون Python)
 * ============================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ============================================================
//  Schema
// ============================================================

function ensure_book_chunks_schema() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `book_chunks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `book_id` INT NOT NULL,
            `chunk_index` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `title` VARCHAR(200) NOT NULL DEFAULT '',
            `content` MEDIUMTEXT NOT NULL,
            `keywords` TEXT DEFAULT NULL,
            `page_start` SMALLINT UNSIGNED DEFAULT NULL,
            `page_end` SMALLINT UNSIGNED DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_chunk_book` (`book_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try { db()->exec("ALTER TABLE `books` ADD COLUMN `chunks_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE `books` ADD COLUMN `extract_mode` VARCHAR(12) NOT NULL DEFAULT 'summary'"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE `book_chunks` ADD COLUMN `page_start` SMALLINT UNSIGNED DEFAULT NULL"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE `book_chunks` ADD COLUMN `page_end` SMALLINT UNSIGNED DEFAULT NULL"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE `book_chunks` ADD FULLTEXT INDEX `ft_chunk_content` (`content`, `keywords`)"); } catch (Throwable $e) {}
    } catch (Throwable $e) {}
}


// ============================================================
//  استخراج خلاصه ساختاریافته با AI
// ============================================================

/**
 * PDF رو به AI می‌فرسته و یه خلاصه ساختاریافته درخواست می‌کنه.
 * این فقط یه‌بار هنگام آپلود اجرا می‌شه (نه هر سوال).
 *
 * @param string $pdfPath مسیر فایل PDF
 * @return array ['ok'=>bool, 'text'=>string, 'error'=>?string]
 */
function extract_book_content_with_ai($pdfPath, $mode = 'summary') {
    if (!is_file($pdfPath)) {
        return ['ok' => false, 'text' => '', 'error' => 'فایل PDF یافت نشد.'];
    }

    $fileSize = filesize($pdfPath);
    if ($fileSize > 20 * 1024 * 1024) {
        return ['ok' => false, 'text' => '', 'error' => 'حجم PDF بیشتر از ۲۰ مگابایت.'];
    }
    if ($fileSize < 1000) {
        return ['ok' => false, 'text' => '', 'error' => 'فایل PDF خیلی کوچک است.'];
    }

    $raw = @file_get_contents($pdfPath);
    if ($raw === false || strlen($raw) === 0) {
        return ['ok' => false, 'text' => '', 'error' => 'خواندن فایل ناموفق.'];
    }

    $pdfBase64 = base64_encode($raw);
    unset($raw);

    $mode = ($mode === 'detailed') ? 'detailed' : 'summary';

    // ──────────────────────────────────────────────
    //  دو حالت استخراج:
    //  - detailed: متن کامل و دقیق، خیلی نزدیک به متنِ خودِ کتاب
    //  - summary : خلاصه ساختاریافته (پیش‌فرض، سبک‌تر برای هاست ضعیف)
    // ──────────────────────────────────────────────
    if ($mode === 'detailed') {
        $prompt = book_extract_prompt_detailed();
    } else {
        $prompt = book_extract_prompt_summary();
    }

    $messages = [
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => [
                    'url' => 'data:application/pdf;base64,' . $pdfBase64,
                ]],
            ],
        ],
    ];

    $payload = [
        'model'       => AI_MODEL,
        'messages'    => $messages,
        'temperature' => $mode === 'detailed' ? 0.0 : 0.1,
        // حالت دقیق متن بیشتری تولید می‌کند، پس سقف توکن بالاتر
        'max_tokens'  => $mode === 'detailed' ? 32000 : 16000,
        'stream'      => false,
    ];

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    unset($pdfBase64, $messages, $payload);

    $ch = curl_init(AI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AI_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => $payloadJson,
        CURLOPT_SSL_VERIFYPEER => env('CURL_INSECURE', false) ? false : true,
        CURLOPT_SSL_VERIFYHOST => env('CURL_INSECURE', false) ? 0 : 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    $curlNo   = curl_errno($ch);
    curl_close($ch);
    unset($payloadJson);

    if ($response === false) {
        return ['ok' => false, 'text' => '', 'error' => 'خطای شبکه: ' . $curlErr . ' (#' . $curlNo . ')'];
    }

    if ($httpCode !== 200) {
        $errMsg = 'خطای API (HTTP ' . $httpCode . ')';
        $d = @json_decode($response, true);
        if (isset($d['error']['message'])) {
            $errMsg .= ': ' . $d['error']['message'];
        } elseif (isset($d['error']) && is_string($d['error'])) {
            $errMsg .= ': ' . $d['error'];
        }
        return ['ok' => false, 'text' => '', 'error' => $errMsg];
    }

    $d = @json_decode($response, true);
    if (!is_array($d)) {
        return ['ok' => false, 'text' => '', 'error' => 'پاسخ API قابل پردازش نبود.'];
    }

    $text = trim($d['choices'][0]['message']['content'] ?? '');

    if (mb_strlen($text, 'UTF-8') < 200) {
        return ['ok' => false, 'text' => $text, 'error' => 'محتوای استخراج‌شده خیلی کم بود (' . mb_strlen($text, 'UTF-8') . ' کاراکتر). PDF ممکنه اسکنی باشه.'];
    }

    // پاکسازی
    $text = preg_replace('/\r\n?/', "\n", $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

    return ['ok' => true, 'text' => $text, 'error' => null];
}


// ============================================================
//  استخراج دقیقِ تکه‌تکه (حالت دقیق، سبک برای هاست ضعیف)
//  هر تماس = یک درخواست API، تا نه تایم‌اوت بخوریم نه حافظه پر شود.
// ============================================================

/**
 * تماس پایه با مدل، با ارسال PDF base64 + یک prompt متنی.
 * فقط یک درخواست API می‌زند و متن خروجی را برمی‌گرداند.
 *
 * @return array ['ok'=>bool,'text'=>string,'error'=>?string]
 */
function _book_ai_pdf_call($pdfBase64, $promptText, $maxTokens = 8000, $temperature = 0.0, $timeout = 240) {
    $messages = [[
        'role' => 'user',
        'content' => [
            ['type' => 'text', 'text' => $promptText],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:application/pdf;base64,' . $pdfBase64]],
        ],
    ]];

    $payload = [
        'model'       => AI_MODEL,
        'messages'    => $messages,
        'temperature' => $temperature,
        'max_tokens'  => (int)$maxTokens,
        'stream'      => false,
    ];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    unset($messages, $payload);

    $ch = curl_init(AI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => (int)$timeout,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AI_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => $payloadJson,
        CURLOPT_SSL_VERIFYPEER => env('CURL_INSECURE', false) ? false : true,
        CURLOPT_SSL_VERIFYHOST => env('CURL_INSECURE', false) ? 0 : 2,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    $curlNo   = curl_errno($ch);
    curl_close($ch);
    unset($payloadJson);

    if ($response === false) {
        return ['ok' => false, 'text' => '', 'error' => 'خطای شبکه: ' . $curlErr . ' (#' . $curlNo . ')'];
    }
    if ($httpCode !== 200) {
        $errMsg = 'خطای API (HTTP ' . $httpCode . ')';
        $d = @json_decode($response, true);
        if (isset($d['error']['message'])) $errMsg .= ': ' . $d['error']['message'];
        elseif (isset($d['error']) && is_string($d['error'])) $errMsg .= ': ' . $d['error'];
        return ['ok' => false, 'text' => '', 'error' => $errMsg];
    }
    $d = @json_decode($response, true);
    if (!is_array($d)) return ['ok' => false, 'text' => '', 'error' => 'پاسخ API قابل پردازش نبود.'];
    $text = trim($d['choices'][0]['message']['content'] ?? '');
    return ['ok' => true, 'text' => $text, 'error' => null];
}

/**
 * مرحله ۱: فهرست درس‌ها/بخش‌های کتاب را از روی PDF می‌گیرد.
 * خروجی: آرایه‌ای از عنوان‌ها (به ترتیب کتاب).
 */
function book_detect_sections($pdfBase64) {
    $prompt = <<<'PROMPT'
این یک کتاب درسی ایرانی (PDF) است.
فقط «فهرست درس‌ها / فصل‌ها / گفتارهای اصلی» این فایل را به ترتیب از اول تا آخر بده.
قوانین:
- هر عنوان را دقیقاً همان‌طور که در کتاب آمده بنویس (با شماره درس/فصل اگر دارد).
- هر عنوان در یک خط جداگانه.
- فقط عنوان‌ها؛ هیچ توضیح، شماره صفحه یا متن اضافه ننویس.
- مقدمه/سخن ناشر/فهرست/واژه‌نامه را هم اگر بخش مستقلی هستند بیاور.
- خروجی فقط فهرست خام خطی باشد، بدون شماره‌گذاری مجدد و بدون علامت اضافه.
PROMPT;

    $res = _book_ai_pdf_call($pdfBase64, $prompt, 2000, 0.0, 120);
    if (!$res['ok']) return ['ok' => false, 'sections' => [], 'error' => $res['error']];

    $lines = preg_split('/\r\n|\r|\n/', $res['text']);
    $sections = [];
    foreach ($lines as $l) {
        $l = trim($l);
        // حذف بولت/شماره‌گذاری ابتدای خط
        $l = preg_replace('/^\s*([-*•]|\d+[\.\)ـ-])\s*/u', '', $l);
        $l = trim($l);
        if ($l === '') continue;
        if (mb_strlen($l, 'UTF-8') < 2) continue;
        $sections[] = $l;
        if (count($sections) >= 60) break; // محافظت
    }
    $sections = array_values(array_unique($sections));
    if (empty($sections)) return ['ok' => false, 'sections' => [], 'error' => 'فهرستی تشخیص داده نشد.'];
    return ['ok' => true, 'sections' => $sections, 'error' => null];
}

/**
 * مرحله ۲: فقط درس‌های مشخص‌شده را به‌صورت دقیق و کامل استخراج می‌کند.
 * چون فقط چند درس خواسته می‌شود، مدل بودجهٔ توکن کافی برای «عینِ کتاب» دارد.
 *
 * @param array $sectionTitles عنوان درس‌هایی که در این تکه باید استخراج شوند
 */
function book_extract_sections($pdfBase64, $sectionTitles, $maxTokens = 8000) {
    $list = '';
    foreach ($sectionTitles as $i => $t) $list .= ($i+1) . '. ' . $t . "\n";

    $base = book_extract_prompt_detailed();
    $prompt = $base . "\n\n# محدودهٔ این مرحله (خیلی مهم)\n"
        . "از کلِ این PDF، فقط و فقط این درس‌ها/بخش‌ها را استخراج کن (به همین ترتیب):\n"
        . $list
        . "\nقوانین این مرحله:\n"
        . "- فقط همین درس‌های بالا را بیاور؛ درس‌های دیگر کتاب را در این خروجی نیاور.\n"
        . "- چون فقط همین درس(ها) خواسته شده، باید بسیار کامل و عیناً مثل متن کتاب بنویسی و هیچ چیزی را خلاصه نکنی. تو فضای کافی داری، پس از آن استفاده کن.\n"
        . "- هر درس را با سرتیتر «## [عنوان دقیق درس]» شروع کن.\n"
        . "- متنِ کاملِ این درس را پاراگراف‌به‌پاراگراف از ابتدا تا انتها بیاور؛ هیچ پاراگراف، جمله، تعریف یا نکته‌ای را حذف نکن.\n"
        . "- همهٔ فهرست‌ها را به‌صورت کامل و عینِ کتاب بنویس: هر جا کتاب «فایده‌ها»، «دلایل»، «عوامل»، «ویژگی‌ها»، «کاربردها»، «منابع تأمین» و مانند این‌ها را شمرده، همهٔ موارد را یک‌به‌یک بیاور و حتی یک مورد را جا نینداز.\n"
        . "- جعبه‌های «بیشتر بدانید»، «فکر کنید»، «فعالیت»، آیات/احادیث، اعداد، آمار، شماره صفحه (مثل «ص ۵۸») و جدول‌های همین درس را هم کامل بیاور.\n"
        . "- اگر دربارهٔ یک پدیده هم «فایده/کاربرد» و هم «مشکل/دلیل» در کتاب آمده، هر دو را جدا و کامل بنویس؛ یکی را به‌جای دیگری ننویس.\n";

    $res = _book_ai_pdf_call($pdfBase64, $prompt, $maxTokens, 0.0, 240);
    if (!$res['ok']) return $res;

    $text = $res['text'];
    if (mb_strlen($text, 'UTF-8') < 80) {
        return ['ok' => false, 'text' => $text, 'error' => 'محتوای این تکه خیلی کم بود.'];
    }
    $text = preg_replace('/\r\n?/', "\n", $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    return ['ok' => true, 'text' => trim($text), 'error' => null];
}

/**
 * تعداد درس در هر تکه (batch) را تعیین می‌کند.
 * هرچه کتاب درس بیشتری دارد، تکه‌ها کوچک‌تر تا خلاصه نشوند.
 */
function book_batch_size($sectionCount) {
    // هر درس را در یک تماس جدا استخراج می‌کنیم تا مدل بودجهٔ توکن کامل
    // برای همان درس داشته باشد و هیچ جزئیاتی (فهرست‌ها، فواید، دلایل) خلاصه نشود.
    // فقط برای کتاب‌های خیلی کوچک (۲ بخش یا کمتر) یکجا می‌گیریم.
    if ($sectionCount <= 2) return $sectionCount;
    return 1;
}

/**
 * استخراج «کل یک فایل» در حالت دقیق، با ورودی base64 (بدون خواندن دوبارهٔ دیسک).
 * فقط برای فایل‌هایی که فهرستشان تشخیص داده نشد به‌کار می‌رود.
 */
function extract_book_content_with_ai_b64($pdfBase64, $mode = 'detailed') {
    $prompt = ($mode === 'detailed') ? book_extract_prompt_detailed() : book_extract_prompt_summary();
    $maxTok = ($mode === 'detailed') ? 16000 : 16000;
    $res = _book_ai_pdf_call($pdfBase64, $prompt, $maxTok, $mode === 'detailed' ? 0.0 : 0.1, 280);
    if (!$res['ok']) return $res;
    $text = trim($res['text']);
    if (mb_strlen($text, 'UTF-8') < 100) {
        return ['ok' => false, 'text' => $text, 'error' => 'محتوای استخراج‌شده خیلی کم بود.'];
    }
    $text = preg_replace('/\r\n?/', "\n", $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    return ['ok' => true, 'text' => $text, 'error' => null];
}

/**
 * جدول وضعیت کار استخراج تکه‌تکه (برای ادامه دادن بین درخواست‌ها).
 */
function ensure_book_extract_jobs_schema() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `book_extract_jobs` (
            `book_id` INT PRIMARY KEY,
            `plan_json` MEDIUMTEXT,
            `accumulated` LONGTEXT,
            `current_step` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `total_steps` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(12) NOT NULL DEFAULT 'running',
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}
}


// ============================================================
//  Chunking — تقسیم خلاصه به بخش‌های مستقل
// ============================================================

/**
 * خلاصه ساختاریافته رو به chunk‌ها تقسیم می‌کنه.
 * هر chunk = یک درس یا بخش مستقل.
 * اگه headingها نبودن، بر اساس تعداد کلمات تقسیم می‌کنه.
 */
function chunk_book_text($fullText, $chunkSize = 1200, $overlap = 150) {
    $fullText = trim((string)$fullText);
    if ($fullText === '') return [];

    // تلاش برای تقسیم بر اساس سرفصل ## (هر درس = یه chunk)
    $sections = split_by_headings($fullText);
    if (!empty($sections) && count($sections) >= 2) {
        return $sections;
    }

    // Fallback: تقسیم بر اساس تعداد کلمات
    return chunk_by_words($fullText, $chunkSize, $overlap);
}

/**
 * متن رو بر اساس headingهای ## به بخش‌ها تقسیم می‌کنه.
 */
function split_by_headings($text) {
    // پیدا کردن headingهای سطح 2
    $parts = preg_split('/^(##\s+.+)$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

    if (count($parts) < 3) return []; // حداقل یه heading + content

    $chunks = [];
    $currentTitle = '';
    $currentContent = '';

    // اگه قبل از اولین heading متنی هست
    $preamble = '';

    foreach ($parts as $part) {
        if (preg_match('/^##\s+(.+)$/mu', trim($part), $m)) {
            // heading جدید پیدا شد
            if ($currentTitle !== '' && mb_strlen(trim($currentContent), 'UTF-8') > 50) {
                $content = trim($currentContent);
                if ($preamble !== '' && empty($chunks)) {
                    $content = $preamble . "\n\n" . $content;
                    $preamble = '';
                }
                $chunks[] = [
                    'title'      => mb_substr(trim($currentTitle), 0, 150, 'UTF-8'),
                    'content'    => $content,
                    'keywords'   => extract_kw($content),
                    'page_start' => null,
                    'page_end'   => null,
                ];
            }
            $currentTitle = trim($m[1]);
            $currentContent = '';
        } else {
            if ($currentTitle === '') {
                $preamble .= $part;
            } else {
                $currentContent .= $part;
            }
        }
    }

    // آخرین بخش
    if ($currentTitle !== '' && mb_strlen(trim($currentContent), 'UTF-8') > 50) {
        $chunks[] = [
            'title'      => mb_substr(trim($currentTitle), 0, 150, 'UTF-8'),
            'content'    => trim($currentContent),
            'keywords'   => extract_kw(trim($currentContent)),
            'page_start' => null,
            'page_end'   => null,
        ];
    }

    // اگه preamble مونده و chunks خالیه
    if (!empty($preamble) && empty($chunks)) {
        $chunks[] = [
            'title'      => 'مقدمه',
            'content'    => trim($preamble),
            'keywords'   => extract_kw(trim($preamble)),
            'page_start' => null,
            'page_end'   => null,
        ];
    }

    // chunk‌های خیلی بزرگ رو تقسیم کن
    $final = [];
    foreach ($chunks as $c) {
        $words = count(preg_split('/\s+/u', $c['content'], -1, PREG_SPLIT_NO_EMPTY));
        if ($words > 2000) {
            // تقسیم به ۲
            $subChunks = chunk_by_words($c['content'], 1200, 100);
            foreach ($subChunks as $j => $sc) {
                $sc['title'] = $c['title'] . ($j > 0 ? ' (ادامه)' : '');
                $final[] = $sc;
            }
        } else {
            $final[] = $c;
        }
    }

    return $final;
}

/**
 * Fallback: تقسیم بر اساس تعداد کلمات
 */
function chunk_by_words($fullText, $chunkSize = 1200, $overlap = 150) {
    $words = preg_split('/\s+/u', $fullText, -1, PREG_SPLIT_NO_EMPTY);
    $total = count($words);
    if ($total === 0) return [];

    if ($total <= $chunkSize + $overlap) {
        $c = implode(' ', $words);
        return [['title' => make_chunk_title($c), 'content' => $c, 'keywords' => extract_kw($c), 'page_start' => null, 'page_end' => null]];
    }

    $chunks = [];
    $pos = 0;
    while ($pos < $total) {
        $end = min($pos + $chunkSize, $total);
        $c = implode(' ', array_slice($words, $pos, $end - $pos));
        $c = trim_sentence($c);
        if (mb_strlen($c, 'UTF-8') > 50) {
            $chunks[] = ['title' => make_chunk_title($c), 'content' => $c, 'keywords' => extract_kw($c), 'page_start' => null, 'page_end' => null];
        }
        $next = $end - $overlap;
        if ($next <= $pos) $next = $pos + 1;
        $pos = $next;
        if ($pos >= $total) break;
    }
    return $chunks;
}


function trim_sentence($t) {
    $l = mb_strlen($t, 'UTF-8');
    if ($l < 200) return $t;
    $tail = mb_substr($t, $l - 150, 150, 'UTF-8');
    $best = 0;
    foreach (['.', '؟', '!', '؛', ':', "\n\n"] as $s) {
        $p = mb_strrpos($tail, $s);
        if ($p !== false && $p > $best) $best = $p;
    }
    return $best > 10 ? mb_substr($t, 0, $l - 150 + $best + 1, 'UTF-8') : $t;
}

function make_chunk_title($c) {
    if (preg_match('/^##\s*(.{5,})/mu', $c, $m)) return mb_substr(trim($m[1]), 0, 150, 'UTF-8');
    if (preg_match('/^###\s*(.{5,})/mu', $c, $m)) return mb_substr(trim($m[1]), 0, 150, 'UTF-8');
    $w = preg_split('/\s+/u', preg_replace('/[\$\\\\{}[\]#]/u', '', trim($c)), 12, PREG_SPLIT_NO_EMPTY);
    return mb_substr(implode(' ', array_slice($w, 0, 10)) ?: 'بخش کتاب', 0, 150, 'UTF-8');
}

function extract_kw($c) {
    $kw = [];
    if (preg_match_all('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]{3,}/u', $c, $m)) $kw = array_merge($kw, $m[0]);
    if (preg_match_all('/[a-zA-Z]{2,}/u', $c, $m)) $kw = array_merge($kw, $m[0]);
    if (preg_match_all('/\\\\([a-zA-Z]{3,})/u', $c, $m)) $kw = array_merge($kw, $m[1]);
    $stop = ['از','به','در','با','که','این','آن','است','بود','شد','می','و','یا','هم','را','تا','برای','یک','هر','شده','های','ها','ای','کنید','باشد','دارد','کند','خود','اگر','همه','شود',
             'the','and','for','is','are','was','not','text','frac','cdot','left','right','begin','end'];
    $kw = array_filter(array_unique($kw), fn($w) => !in_array(mb_strtolower($w,'UTF-8'), $stop, true) && mb_strlen($w,'UTF-8') >= 2);
    return implode(' ', array_slice(array_values($kw), 0, 150));
}


// ============================================================
//  ذخیره chunk‌ها
// ============================================================

function save_book_chunks($bookId, $fullText) {
    ensure_book_chunks_schema();
    $bookId = (int)$bookId;
    if ($bookId <= 0) return 0;

    try { db()->prepare("DELETE FROM book_chunks WHERE book_id=?")->execute([$bookId]); } catch (Throwable $e) {}

    $chunks = chunk_book_text($fullText);
    if (empty($chunks)) {
        try { db()->prepare("UPDATE books SET chunks_count=0 WHERE id=?")->execute([$bookId]); } catch (Throwable $e) {}
        return 0;
    }

    $stmt = db()->prepare("INSERT INTO book_chunks (book_id, chunk_index, title, content, keywords, page_start, page_end) VALUES (?,?,?,?,?,?,?)");
    foreach ($chunks as $i => $c) {
        $stmt->execute([
            $bookId,
            $i,
            cln($c['title']),
            cln($c['content']),
            cln($c['keywords']),
            $c['page_start'] ?? null,
            $c['page_end'] ?? null,
        ]);
    }

    $n = count($chunks);
    db()->prepare("UPDATE books SET chunks_count=? WHERE id=?")->execute([$n, $bookId]);
    return $n;
}

/**
 * افزودن محتوای جدید به کتاب موجود (append)
 * برای وقتی که کتاب چند فایل PDF داره
 */
function append_book_content($bookId, $newText) {
    $bookId = (int)$bookId;
    $stmt = db()->prepare("SELECT cached_text FROM books WHERE id=?");
    $stmt->execute([$bookId]);
    $row = $stmt->fetch();
    if (!$row) return false;

    $existing = trim($row['cached_text'] ?? '');
    $combined = $existing !== '' ? $existing . "\n\n---\n\n" . $newText : $newText;

    db()->prepare("UPDATE books SET cached_text=? WHERE id=?")->execute([$combined, $bookId]);
    return save_book_chunks($bookId, $combined);
}

function cln($t) {
    if (!is_string($t)) return '';
    $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $t);
    if (function_exists('mb_convert_encoding')) $t = mb_convert_encoding($t, 'UTF-8', 'UTF-8');
    return $t;
}


// ============================================================
//  جستجوی chunk‌ها (RAG)
// ============================================================

/**
 * chunk‌های مرتبط با سوال رو پیدا می‌کنه.
 */
function find_relevant_chunks($bookId, $question, $maxChunks = 4, $maxChars = 12000) {
    ensure_book_chunks_schema();
    $bookId = (int)$bookId;
    if ($bookId <= 0 || trim($question) === '') return [];

    $stmt = db()->prepare("SELECT COUNT(*) FROM book_chunks WHERE book_id=?");
    $stmt->execute([$bookId]);
    $cnt = (int)$stmt->fetchColumn();
    if ($cnt === 0) return [];

    // اگه chunk‌ها کم هستن، همه رو برگردون
    if ($cnt <= $maxChunks) {
        $stmt = db()->prepare("SELECT * FROM book_chunks WHERE book_id=? ORDER BY chunk_index ASC");
        $stmt->execute([$bookId]);
        return cap($stmt->fetchAll(), $maxChars);
    }

    $terms = search_terms($question);
    if (empty($terms)) {
        $stmt = db()->prepare("SELECT * FROM book_chunks WHERE book_id=? ORDER BY chunk_index ASC LIMIT ?");
        $stmt->execute([$bookId, $maxChunks]);
        return $stmt->fetchAll();
    }

    // 1) FULLTEXT
    $r = ft_search($bookId, $terms, $maxChunks);
    if (!empty($r)) return cap($r, $maxChars);

    // 2) Keyword scoring
    $r = kw_search($bookId, $terms, $maxChunks);
    if (!empty($r)) return cap($r, $maxChars);

    // 3) LIKE search
    $r = like_search($bookId, $terms, $maxChunks);
    if (!empty($r)) return cap($r, $maxChars);

    // 4) Fallback
    $stmt = db()->prepare("SELECT * FROM book_chunks WHERE book_id=? ORDER BY chunk_index ASC LIMIT 2");
    $stmt->execute([$bookId]);
    return $stmt->fetchAll();
}

function search_terms($q) {
    $t = [];
    if (preg_match_all('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}]{2,}/u', $q, $m)) $t = array_merge($t, $m[0]);
    if (preg_match_all('/[a-zA-Z]{2,}/', $q, $m)) $t = array_merge($t, $m[0]);
    if (preg_match_all('/\d+/', $q, $m)) $t = array_merge($t, $m[0]);
    $stop = ['از','به','در','با','که','این','آن','است','بود','شد','می','و','یا','هم','را','تا','برای','یک','هر',
             'چرا','چی','چه','سوال','جواب','حل','توضیح','بده','بگو','لطفا','مسئله','تمرین','صفحه','کتاب','درس',
             'کن','کنم','من','تو','ما','شما','آیا','بله','خیر','نه','لطفاً'];
    return array_values(array_unique(array_filter($t, fn($w) => !in_array(mb_strtolower($w,'UTF-8'), $stop, true) && mb_strlen($w,'UTF-8') >= 2)));
}

function ft_search($bid, $terms, $lim) {
    try {
        $s = implode(' ', array_slice($terms, 0, 15));
        $st = db()->prepare("SELECT *, MATCH(content,keywords) AGAINST(? IN NATURAL LANGUAGE MODE) AS sc FROM book_chunks WHERE book_id=? AND MATCH(content,keywords) AGAINST(? IN NATURAL LANGUAGE MODE) ORDER BY sc DESC LIMIT ?");
        $st->execute([$s, $bid, $s, $lim]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

function kw_search($bid, $terms, $lim) {
    try {
        $st = db()->prepare("SELECT * FROM book_chunks WHERE book_id=?"); $st->execute([$bid]);
        $all = $st->fetchAll(); $scored = [];
        foreach ($all as $c) {
            $sc = 0;
            $h = mb_strtolower($c['content'] . ' ' . ($c['keywords'] ?? ''), 'UTF-8');
            foreach ($terms as $t) {
                $tl = mb_strtolower($t, 'UTF-8');
                $n = mb_substr_count($h, $tl);
                if ($n > 0) {
                    $sc += $n;
                    if (mb_strpos(mb_strtolower($c['keywords'] ?? '','UTF-8'), $tl) !== false) $sc += 5;
                    if (mb_strpos(mb_strtolower($c['title'] ?? '','UTF-8'), $tl) !== false) $sc += 3;
                }
            }
            if ($sc > 0) { $c['_s'] = $sc; $scored[] = $c; }
        }
        usort($scored, fn($a,$b) => $b['_s'] <=> $a['_s']);
        return array_slice($scored, 0, $lim);
    } catch (Throwable $e) { return []; }
}

function like_search($bid, $terms, $lim) {
    try {
        $wheres = [];
        $params = [$bid];
        foreach (array_slice($terms, 0, 5) as $t) {
            $wheres[] = "(content LIKE ? OR keywords LIKE ?)";
            $params[] = '%' . $t . '%';
            $params[] = '%' . $t . '%';
        }
        if (empty($wheres)) return [];
        $sql = "SELECT * FROM book_chunks WHERE book_id=? AND (" . implode(' OR ', $wheres) . ") ORDER BY chunk_index ASC LIMIT ?";
        $params[] = $lim;
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

function cap($chunks, $max = 12000) {
    $r = []; $t = 0;
    foreach ($chunks as $c) {
        $l = mb_strlen($c['content'], 'UTF-8');
        if ($t + $l > $max && !empty($r)) break;
        $r[] = $c; $t += $l;
    }
    return $r;
}

/**
 * کانتکست گسترده و متوازن برای روز امتحان.
 * وقتی سؤال از روی عکس/PDF می‌آید، متن سؤال را قبل از ارسال به مدل نداریم؛
 * پس جستجوی کلمه‌ای RAG ممکن است chunk درست را پیدا نکند. این تابع از همهٔ
 * فصل‌ها/درس‌ها یک سهم کوتاه و مرتب می‌فرستد تا مدل برای آزمون‌های چندسؤالی
 * (مثل زیست ۴۰ سؤالی) به کل کتاب دسترسی سریع داشته باشد.
 */
function exam_context_chunks($bookId, $maxChars = 35000, $maxChunks = 80) {
    ensure_book_chunks_schema();
    $bookId = (int)$bookId;
    if ($bookId <= 0) return [];

    $chunks = [];
    try {
        $st = db()->prepare("SELECT * FROM book_chunks WHERE book_id=? ORDER BY chunk_index ASC LIMIT ?");
        $st->execute([$bookId, (int)$maxChunks]);
        $chunks = $st->fetchAll();
    } catch (Throwable $e) { $chunks = []; }

    // اگر chunk ساخته نشده بود ولی cached_text داریم، همان را سریع chunk کن.
    if (empty($chunks)) {
        try {
            $st = db()->prepare("SELECT cached_text FROM books WHERE id=?");
            $st->execute([$bookId]);
            $txt = trim((string)($st->fetch()['cached_text'] ?? ''));
            if (mb_strlen($txt, 'UTF-8') > 100) $chunks = chunk_book_text($txt);
        } catch (Throwable $e) {}
    }

    if (empty($chunks)) return [];
    return balanced_cap_chunks($chunks, (int)$maxChars);
}

function balanced_cap_chunks($chunks, $maxChars = 35000) {
    $maxChars = max(4000, (int)$maxChars);
    $total = 0;
    foreach ($chunks as $c) $total += mb_strlen((string)($c['content'] ?? ''), 'UTF-8');
    if ($total <= $maxChars) return $chunks;

    $n = max(1, count($chunks));
    $perChunk = max(350, (int)floor(($maxChars - ($n * 60)) / $n));
    $out = [];
    $used = 0;

    foreach ($chunks as $c) {
        $content = trim((string)($c['content'] ?? ''));
        if ($content === '') continue;

        $slice = mb_substr($content, 0, $perChunk, 'UTF-8');
        if (mb_strlen($content, 'UTF-8') > $perChunk) {
            $slice = rtrim(trim_sentence($slice));
            $slice .= "\n… [ادامهٔ این بخش برای سرعت کوتاه شد]";
        }

        $c['content'] = $slice;
        $len = mb_strlen($slice, 'UTF-8');
        if ($used + $len > $maxChars && !empty($out)) break;
        $out[] = $c;
        $used += $len;
    }

    return $out ?: array_slice($chunks, 0, 1);
}


// ============================================================
//  ساخت context برای prompt
// ============================================================

function build_book_context($chunks, $book) {
    if (empty($chunks)) return '';
    $title = $book['title'] ?? '';

    $ctx  = "\n═══ محتوای کتاب «{$title}» ═══\n";
    $ctx .= "بخش‌های زیر متنِ خودِ کتاب درسی‌اند و معتبرترین منبع پاسخ هستند.\n";
    $ctx .= "قانون: جواب را تا حد ممکن از همین متن بردار؛ همان جمله‌ها، تعریف‌ها، واژگان و ترتیب کتاب را به کار ببر و فقط در حدّ لازم برای روان‌بودن آن‌ها را به هم وصل کن. از خودت بازنویسی آزاد، ساده‌سازی یا افزودن اطلاعاتِ بیرون از این متن خودداری کن.\n\n";

    foreach ($chunks as $i => $c) {
        $n = $i + 1;
        $t = trim($c['title'] ?? '');
        $ctx .= "── بخش {$n}" . ($t ? ": {$t}" : '') . " ──\n";
        $ctx .= trim($c['content']) . "\n\n";
    }

    $ctx .= "═══ پایان محتوای کتاب ═══\n";
    return $ctx;
}


// ============================================================
//  پرامپت‌های استخراج محتوای کتاب (دو حالت)
// ============================================================

function book_extract_prompt_summary() {
    return <<<'PROMPT'
تو یک سیستم خلاصه‌ساز محتوای آموزشی هستی. این PDF بخشی از یک کتاب درسی ایرانی است.

وظیفه تو:
یک خلاصه جامع، ساختاریافته و دقیق از محتوای کتاب بنویس، به‌طوری‌که:
- همه درس‌ها / فصل‌ها / گفتارها / بخش‌ها را به ترتیب اصلی کتاب پوشش بدهی
- برای هر بخش، نکات مهم، مفاهیم اصلی، فرمول‌ها، قواعد، مثال‌ها و محتوای کلیدی را استخراج کنی
- هیچ مطلب مهمی از قلم نیفتد
- هیچ اطلاعاتی از خودت اضافه نکنی

---

## هدف اصلی خروجی:

خروجی باید طوری باشد که اگر کسی کتاب را کامل نخوانده، فقط با خواندن خلاصه تو:
- ترتیب درس‌ها و محتوای هر درس را بفهمد
- مفاهیم اصلی هر درس را بداند
- فرمول‌ها، قواعد، واژه‌ها، تعریف‌ها و نکات مهم را یکجا داشته باشد
- برای مرور امتحانی بتواند از آن استفاده کند

---

## تشخیص نوع درس (خیلی مهم):

ابتدا محتوای کتاب را تشخیص بده و متناسب با نوع درس، اطلاعات مهم همان درس را استخراج کن.

### اگر درس مفهومی/حفظی بود:
مثل دینی، تاریخ، جغرافیا، اجتماعی، فلسفه، منطق، علوم انسانی:
- مفاهیم اصلی
- تعریف‌ها
- اسامی اشخاص، مکان‌ها، رویدادها
- آیات، احادیث، شعرها، متن‌های مهم
- پیام‌ها و نکات مهم
- جدول‌ها و دسته‌بندی‌ها

### اگر درس محاسباتی بود:
مثل ریاضی، فیزیک، شیمی، حسابان، آمار، هندسه:
- تعریف مفاهیم
- فرمول‌ها و روابط
- واحدها و نمادها
- قضیه‌ها، قانون‌ها، اصل‌ها
- مراحل حل و روش‌ها
- مثال‌های مهم
- جدول‌ها، نمودارها و جمع‌بندی‌ها

### اگر درس زبانی بود:
مثل عربی، زبان انگلیسی، فارسی، نگارش:
- واژگان مهم
- معنی و ترجمه
- قواعد دستوری
- ساختارها
- نکات نگارشی و زبانی
- متن‌های مهم
- مثال‌های آموزشی
- تمرین‌ها و الگوهای پرتکرار

### اگر درس علمی-توصیفی بود:
مثل زیست‌شناسی، زمین‌شناسی، علوم:
- تعریف‌ها
- فرایندها
- طبقه‌بندی‌ها
- شکل‌ها، نمودارها، جدول‌ها
- نکات مقایسه‌ای
- اصطلاحات علمی
- چرخه‌ها، مراحل و ارتباط مفاهیم

---

## قالب خروجی (الزامی):

# خلاصه ساختاریافته کتاب

اگر PDF شامل چند درس / فصل / گفتار است، همه را دقیقاً به همان ترتیب کتاب بنویس.

---

## [شماره و عنوان درس / فصل / گفتار]

### موضوعات اصلی:
- [فهرست موضوعات مهم این بخش]

### مفاهیم کلیدی:
- [تعریف‌ها، اصطلاحات و نکات اصلی — فقط بر اساس کتاب]

### فرمول‌ها و روابط:
- [اگر وجود داشت، همه فرمول‌ها و رابطه‌های مهم را دقیق بنویس]
- [فرمول‌ها را با LaTeX بنویس: $...$]

### قواعد / ساختارها:
- [برای عربی، زبان، فارسی، نگارش یا هر درس قاعده‌محور]
- [اگر وجود نداشت، این بخش را حذف کن]

### واژگان / اصطلاحات / نمادها:
- [واژه‌های مهم، نمادهای علمی، اصطلاحات تخصصی، معنی آن‌ها]
- [در زبان‌ها: واژگان + معنی]
- [در درس‌های علمی: نمادها و اصطلاحات تخصصی]

### آیات، احادیث، اشعار یا متن‌های مهم:
- [هر آیه، حدیث، شعر، متن عربی/فارسی/انگلیسی مهم را عیناً بنویس]
- [ترجمه یا معنی آن را هم بنویس]
- [اگر چنین بخشی در این درس نبود، این بخش را حذف کن]

### جدول‌ها / نمودارها / دسته‌بندی‌ها:
- [هر جدول یا دسته‌بندی مهم را حفظ کن]
- [اگر در کتاب آمده، ساختار آن را خلاصه ولی دقیق منتقل کن]

### نکات مهم:
- [نکته‌های کلیدی، جعبه‌های «توجه»، «بیشتر بدانیم»، «نکته»، «یادآوری»، «پیام‌ها»]

### مثال‌ها / فعالیت‌ها / تمرین‌ها:
- [خلاصه مثال‌های مهم]
- [خلاصه فعالیت‌ها، کار در کلاس، خودارزیابی، تمرین‌ها و پرسش‌ها]
- [فقط آن‌قدر که محتوای آموزشی بخش حفظ شود]

### خلاصه محتوا:
[یک پاراگراف روان و دقیق از کل این درس / فصل]

---

## قوانین مهم:

1. همه درس‌ها / فصل‌ها / گفتارها را به ترتیب اصلی PDF استخراج کن
2. هیچ درس یا بخش مهمی را جا نینداز
3. فقط از محتوای خود کتاب استفاده کن؛ هیچ چیز اضافه نکن
4. اگر عنوان دقیق بخش مشخص بود، همان را حفظ کن
5. اگر شماره صفحه مهم بود و دیده شد، ذکر کن
6. تعریف‌ها و اصطلاحات کلیدی را دقیق بنویس
7. اسامی خاص (اشخاص، مکان‌ها، رویدادها، پیامبران، امامان، دانشمندان، آثار، مفاهیم علمی) را دقیق حفظ کن
8. آیات، احادیث، اشعار و متن‌های مهم را عیناً بنویس
9. ترجمه یا معنی آن‌ها را هم بنویس
10. فرمول‌ها، قانون‌ها، قضیه‌ها و روابط را کامل استخراج کن
11. فرمول‌ها را با LaTeX بنویس: $...$
12. جدول‌ها و دسته‌بندی‌های مهم را حذف نکن
13. در درس‌های ریاضی/فیزیک/شیمی، حتماً روابط و روش‌ها را جداگانه استخراج کن
14. در درس‌های عربی/انگلیسی/فارسی، حتماً واژگان و قواعد را جداگانه استخراج کن
15. در درس‌های زیست/علوم، فرایندها، مراحل، طبقه‌بندی‌ها و مقایسه‌ها را دقیق بیاور
16. اگر یک بخش در یک درس وجود نداشت، آن بخش را حذف کن؛ چیزی الکی تولید نکن
17. خروجی فقط فارسی باشد؛ اصطلاحات غیرفارسی در صورت نیاز داخل پرانتز
18. متن نهایی باید جامع، تمیز، منظم و مناسب مرور درسی باشد
19. خروجی را کوتاه و ناقص نکن؛ تا حد ممکن کامل و پوشش‌دهنده بنویس
20. اگر کتاب چند درس دارد، برای هر درس حتماً یک «خلاصه محتوا» جداگانه بنویس
21. **فهرست‌ها را کامل و عینِ کتاب بیاور:** هر جا کتاب چند مورد را فهرست کرده (مثل «دلایل»، «عوامل»، «ویژگی‌ها»، «کاربردها»، «فواید»)، همهٔ آن موارد را دقیق و کامل بنویس؛ چون سؤال‌های امتحانی معمولاً «چند مورد بنویسید» هستند. هیچ موردی را حذف نکن.
22. **عبارت‌های کلیدیِ قابل‌نقل را عیناً نگه دار:** برای جمله‌هایی که احتمالاً عینِ آن‌ها در پاسخنامه می‌آید (تعریف‌ها، علت‌ها، فواید)، همان واژگانِ کتاب را به کار ببر، نه معادلِ خودت. مثلاً اگر کتاب گفته «املاح برای درمان امراض پوستی» همان را بنویس، نه «استخراج مواد معدنی».
23. شماره صفحهٔ هر بخش را اگر در PDF دیده شد ذکر کن (مثلاً «ص ۵۸»)، چون پاسخنامه‌ها به صفحه ارجاع می‌دهند.

---

## تأکید نهایی:

خروجی باید شبیه یک «جزوه خلاصه کامل کتاب» باشد:
- مرتب
- بخش‌بندی‌شده
- دقیق و وفادار به واژگانِ خودِ کتاب (نه بازنویسیِ آزاد)
- کامل از نظر فهرست‌ها و موارد قابل‌سؤال (هیچ مورد حذف نشود)
- مناسب همه درس‌ها، از دینی تا ریاضی و فیزیک و عربی و زبان
PROMPT;
}

function book_extract_prompt_detailed() {
    return <<<'PROMPT'
تو یک سیستم استخراج متن دقیق از کتاب درسی هستی. این PDF بخشی از یک کتاب درسی ایرانی است.

# هدف (خیلی مهم)
متن این کتاب را **کامل، دقیق و وفادار به خودِ کتاب** بازنویسی کن؛ به‌طوری‌که عبارت‌ها، تعریف‌ها و جمله‌ها **تا حد امکان عینِ متن کتاب** باشند. این خروجی قرار است منبعِ پاسخ‌دهی به دانش‌آموز باشد، پس باید بتوان جمله‌ها را تقریباً کلمه‌به‌کلمه از آن نقل کرد.

# قوانین استخراج دقیق
1. همه درس‌ها / فصل‌ها / گفتارها را به ترتیب اصلی کتاب و کامل بیاور؛ هیچ بخشی را جا ننداز.
2. متن اصلی هر درس را تا جای ممکن **عیناً** بنویس؛ خلاصه و بازنویسی آزاد نکن. فقط عناصر تزئینی و تکراری (شماره صفحه پراکنده، سرصفحه/پاصفحه ناشر، تصاویر بدون متن) را حذف کن.
3. تعریف‌ها، قانون‌ها، قاعده‌ها و جمله‌های کلیدی را **دقیقاً با همان واژگان کتاب** بنویس.
4. آیات، احادیث، اشعار، متن‌های عربی/انگلیسی و نقل‌قول‌ها را **عیناً** و کامل بیاور؛ ترجمه/معنی کتاب را هم اگر بود بنویس.
5. فرمول‌ها، روابط، واحدها و نمادها را دقیق و کامل با LaTeX بنویس: $...$ .
6. جدول‌ها، فهرست‌ها و دسته‌بندی‌ها را با همان محتوا و ترتیب کتاب حفظ کن.
7. اسامی خاص (اشخاص، مکان‌ها، رویدادها، آثار، اصطلاحات علمی) را دقیق و بدون تغییر بنویس.
8. اعداد، تاریخ‌ها، درصدها و آمار را عیناً از کتاب بیاور؛ از خودت چیزی اضافه یا گرد نکن.
9. هیچ اطلاعاتی از دانشِ بیرونِ کتاب اضافه نکن. فقط محتوای همین PDF.
10. خروجی فقط فارسی و خوانا باشد؛ اصطلاح غیرفارسی در صورت نیاز داخل پرانتز.

# قالب خروجی (الزامی)
برای هر درس/فصل، از سرتیتر سطح ۲ استفاده کن تا بعداً درست تکه‌بندی (chunk) شود:

## [شماره و عنوان دقیق درس/فصل/گفتار، عیناً مثل کتاب]

[متنِ کاملِ همان درس، نزدیک به عینِ کتاب، شامل همه پاراگراف‌ها، تعریف‌ها، نکته‌ها، آیات/احادیث/اشعار، فرمول‌ها، جدول‌ها و فعالیت‌ها به ترتیب کتاب.]

# جلوگیری از خلاصه‌شدن درس‌های پایانی (خیلی مهم)
- همهٔ درس‌ها باید **با یک سطحِ دقتِ یکسان** بازنویسی شوند. اجازه نده هرچه به آخر کتاب نزدیک می‌شوی، درس‌ها کوتاه و خلاصه شوند.
- اگر حس کردی متن طولانی می‌شود، **هیچ درسی را فدا نکن**؛ به‌جای کوتاه‌کردن درس‌های آخر، فقط جمله‌های تزئینی و تکراری را حذف کن.
- مخصوصاً «فهرست‌ها» و «موارد قابل‌سؤال» را در همهٔ درس‌ها کامل بیاور: هر جا کتاب چند مورد را شمرده (فواید، دلایل، عوامل، ویژگی‌ها، کاربردها)، همهٔ موارد را با همان واژگان کتاب بنویس. مثلاً اگر دربارهٔ یک دریاچه چند فایده/کاربرد آمده، همهٔ آن‌ها را بیاور و حتی یکی را حذف نکن.

# تأکید نهایی
این یک «خلاصه» نیست؛ یک «بازنویسی دقیق و کامل متن کتاب» است. هرچه به متن اصلی کتاب نزدیک‌تر باشی، بهتر است. فقط مطمئن شو که هر درس با یک سرتیتر «## » شروع می‌شود و هیچ درسی (به‌ویژه درس‌های آخر) خلاصه نشده باشد.
PROMPT;
}
