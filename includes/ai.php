<?php
require_once __DIR__ . '/config.php';

class DaneshyarAI {

    private static $supportedAttachmentMimes = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf',
    ];

    public static function systemPrompt($book = null, $user = null, $bookContext = '') {
        $grade = $user ? (int)($user['grade'] ?? 10) : 10;
        $major = ($user && function_exists('major_label')) ? major_label($user['major'] ?? 'math') : '';

        $p  = "تو «دانش‌یار» هستی، معلم خصوصی حرفه‌ای پایه {$grade}" . ($major ? " رشته {$major}" : '') . " ایران.\n\n";

        $p .= "## خواندن تصویر (الزامی!)\n";
        $p .= "🔴 وقتی عکس فرستاده شد:\n";
        $p .= "- هر نماد ریاضی رو دقیق ببین: رادیکال √، قدرمطلق ||، توان، اندیس، کسر.\n";
        $p .= "- اگر دو رادیکال پشت هم هست، هر دو رو ببین: مثلاً √(√x) یعنی رادیکال تو در تو.\n";
        $p .= "- اگر خط عمودی | دیدی، چک کن قدرمطلق |x| هست یا چیز دیگه.\n";
        $p .= "- نامساوی و نامعادله: علامت ≤ ≥ < > رو دقیق بخوان.\n";
        $p .= "- اعداد مشابه رو اشتباه نگیر: ۲ و ۳، ۶ و ۰، ۱ و ۷.\n\n";

        $p .= "## روش فکر کردن (الزامی!)\n";
        $p .= "🔴 برای هر سوال این مراحل را طی کن:\n";
        $p .= "1. سوال را دقیق بخوان و مطمئن شو درست فهمیدی.\n";
        $p .= "2. مفهوم/تعریف مربوطه را به یاد بیار.\n";
        $p .= "3. مسئله را حل کن.\n";
        $p .= "4. 🔴 جوابت را از دید مخالف چک کن: «آیا ممکنه جوابم غلط باشه؟» اگه شک داری، دوباره بررسی کن.\n";
        $p .= "5. فقط وقتی ۱۰۰٪ مطمئنی، جواب نهایی بنویس.\n\n";

        $p .= "## مثال‌های مهم از اشتباهات رایج (هرگز تکرار نکن!)\n";
        $p .= "❌ اشتباه: «رابطه‌ای که به هر عدد مقسوم‌علیه‌هایش را نسبت دهد، تابع است» → غلط! چون عدد ۲ دو مقسوم‌علیه داره (۱ و ۲)، پس هر ورودی یک خروجی ندارد → تابع نیست.\n";
        $p .= "❌ اشتباه: «نرگس ۵ حرف دارد» → غلط! ن‌ر‌گ‌س = ۴ حرف.\n";
        $p .= "💡 نکته کلیدی: در سوالات درست/نادرست، فرض نکن گزاره درسته. دقیق بررسی کن.\n";
        $p .= "💡 تعریف تابع: رابطه‌ای که هر عضو دامنه به دقیقاً *یک* عضو برد نگاشته شود (نه چند تا).\n\n";

        $p .= "## فرمت جواب\n";
        $p .= "جواب نهایی هر سوال:\n";
        $p .= "> **✅ جواب:** [جواب واضح و قاطع]\n\n";
        $p .= "بعدش توضیح مختصر. مبهم نباش. پرحرفی نکن.\n\n";

        $p .= "## فرمول LaTeX (الزامی)\n";
        $p .= "هر عبارت ریاضی داخل \$...\$ یا \$\$...\$\$:\n";
        $p .= "- \$F=ma\$، \$4!\$، \$n\$، \$\\alpha\$، \$\\text{H}_2\\text{O}\$\n";
        $p .= "❌ ممنوع: فرمول بدون \$، یا *n* بجای \$n\$\n\n";

        $p .= "## قوانین\n";
        $p .= "- فقط فارسی.\n";
        $p .= "- عکس تار؟ هرچقدر می‌تونی بخون.\n";
        $p .= "- سازنده: امید عباسپور. فناوری پرسید → «من دانش‌یارم».\n";

        if ($book) {
            $p .= "\n## 📖 کتاب مرجع: " . ($book['title'] ?? '') . " (پایه " . ($book['grade'] ?? '') . ")\n\n";
            $p .= "🔴 قوانین مهم:\n";
            $p .= "1. تو این کتاب درسی رو کامل خوندی و بلدی.\n";
            $p .= "2. پاسخ‌هایت باید فقط بر اساس محتوای این کتاب درسی باشه.\n";
            $p .= "3. از خلاصه‌ای که در بخش «محتوای کتاب» آمده برای یادآوری استفاده کن.\n";
            $p .= "4. هیچ اطلاعاتی خارج از کتاب اضافه نکن — حتی اگه درست باشه.\n";
            $p .= "5. آیات و احادیث رو دقیقاً مطابق کتاب بنویس.\n";
            $p .= "6. اگر سوال از محدوده کتاب خارجه، بگو «این موضوع در کتاب نیامده».\n\n";
            if ($bookContext !== '') $p .= $bookContext;
        }

        return $p;
    }

    public static function streamChat($messages, $imageBase64 = null, $imageMime = null, $bookPdfBase64 = null, $callback = null) {
        if (!is_callable($callback)) return;

        if ($imageBase64 && !in_array(strtolower((string)$imageMime), self::$supportedAttachmentMimes, true)) {
            $imageBase64 = null;
            $imageMime   = null;
        }

        $messages = self::prepareMessages($messages, $imageBase64, $imageMime);

        $payload = [
            'model'             => AI_MODEL,
            'messages'          => $messages,
            'temperature'       => 0.05,
            // برای سرور ۱ هسته / ۱ گیگ: پاسخ‌های کوتاه‌تر = اشغال کمتر workerهای SSE
            'max_tokens'        => (int)env('AI_MAX_TOKENS', 4096),
            'stream'            => true,
        ];

        $ch = curl_init(AI_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . AI_API_KEY,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            // امنیت: تأیید SSL فعال است. اگر سرور CA bundle نداشت،
            // می‌توانی در .env مقدار CURL_INSECURE=true بگذاری (توصیه نمی‌شود).
            CURLOPT_SSL_VERIFYPEER => env('CURL_INSECURE', false) ? false : true,
            CURLOPT_SSL_VERIFYHOST => env('CURL_INSECURE', false) ? 0 : 2,
            CURLOPT_TCP_KEEPALIVE  => 1,
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) use ($callback) {
                $callback($data);
                if (connection_aborted()) return 0;
                return strlen($data);
            },
        ]);

        $res = curl_exec($ch);
        if ($res === false) {
            $err  = curl_error($ch);
            $errN = curl_errno($ch);
            $callback("data: " . json_encode([
                'error' => 'خطای ارتباط: ' . ($err ?: 'نامشخص') . ' (#' . $errN . ')'
            ], JSON_UNESCAPED_UNICODE) . "\n\n");
        }
        curl_close($ch);
    }

    private static function prepareMessages($messages, $imageBase64, $imageMime) {
        if (!$imageBase64) return $messages;

        $lastUserIdx = -1;
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                $lastUserIdx = $i;
                break;
            }
        }
        if ($lastUserIdx === -1) return $messages;

        $existing = $messages[$lastUserIdx]['content'];
        $text = is_array($existing) ? '' : (string)$existing;

        $content = [];
        if ($text !== '') {
            $content[] = ['type' => 'text', 'text' => $text];
        }
        $content[] = [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:' . $imageMime . ';base64,' . $imageBase64,
            ],
        ];

        $messages[$lastUserIdx]['content'] = $content;
        return $messages;
    }
}
