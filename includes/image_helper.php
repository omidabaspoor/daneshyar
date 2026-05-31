<?php
/**
 * ============================================================
 *  دانش‌یار - کمک‌کار آپلود و نرمال‌سازی تصاویر
 *
 *  هدف: ارسال *همیشه* تصویر سازگار با AI (JPEG/PNG/WEBP)
 *  حتی روی هاست‌های اشتراکی ضعیف بدون Imagick.
 *
 *  ترتیب تلاش:
 *    1. اگر فرمت اصلاً قابل پشتیبانی توسط مدل است (jpg/png/webp/gif)
 *       بدون تغییر استفاده کن.
 *    2. اگر HEIC/HEIF است:
 *       - اگر Imagick در دسترس است → تبدیل به JPEG
 *       - اگر نه → reject کاربرپسند با راهنما
 *    3. اگر تصویر خیلی بزرگ است (>3000px یا >3MB) → resize با GD
 *
 *  این فایل به‌خودی کار می‌کند؛ تابع upload_mime() از functions.php
 *  لازم است.
 * ============================================================
 */

if (!defined('AI_MAX_IMAGE_DIM'))   define('AI_MAX_IMAGE_DIM',   2400);  // حداکثر طول/عرض برای AI
if (!defined('AI_MAX_IMAGE_BYTES')) define('AI_MAX_IMAGE_BYTES', 8 * 1024 * 1024); // 3MB پس از تبدیل
if (!defined('AI_JPEG_QUALITY'))    define('AI_JPEG_QUALITY',    90);

/**
 * فرمت‌هایی که مدل واقعاً می‌خونه. HEIC نه!
 */
function ai_supported_image_mimes() {
    return ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
}

/**
 * چک می‌کنه که یه mime قابل ارسال مستقیم به AI است یا نه.
 */
function is_ai_compatible_image_mime($mime) {
    return in_array(strtolower((string)$mime), ai_supported_image_mimes(), true);
}

/**
 * تشخیص HEIC/HEIF با چک کردن سحرامد فایل (مستقل از mime اعلام‌شده).
 */
function is_heic_file($path) {
    if (!is_file($path)) return false;
    $head = @file_get_contents($path, false, null, 0, 32);
    if ($head === false || strlen($head) < 12) return false;
    // ساختار ISO BMFF: bytes 4-8 == "ftyp"، 8-12 == برند
    if (substr($head, 4, 4) !== 'ftyp') return false;
    $brand = substr($head, 8, 4);
    $heicBrands = ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'hevm', 'hevs', 'mif1', 'msf1', 'heif'];
    return in_array($brand, $heicBrands, true);
}

/**
 * چک امکانات سرور برای پردازش تصویر.
 */
function image_processing_capabilities() {
    return [
        'gd'      => extension_loaded('gd'),
        'imagick' => extension_loaded('imagick'),
        'gd_webp' => extension_loaded('gd') && function_exists('imagewebp'),
        'gd_jpeg' => extension_loaded('gd') && function_exists('imagecreatefromjpeg'),
        'gd_png'  => extension_loaded('gd') && function_exists('imagecreatefrompng'),
    ];
}

/**
 * تبدیل HEIC به JPEG با Imagick (اگر در دسترس باشه).
 * برمی‌گردونه: [bool ok, string|null new_path, string|null error]
 */
function convert_heic_to_jpeg($srcPath) {
    if (!extension_loaded('imagick')) {
        return [false, null, 'پشتیبانی HEIC روی سرور فعال نیست.'];
    }
    try {
        $im = new Imagick();
        $im->setResourceLimit(6, 1); // thread = 1 (هاست‌های ضعیف)
        $im->readImage($srcPath);
        $im->setImageFormat('jpeg');
        $im->setImageCompressionQuality(AI_JPEG_QUALITY);
        // resize اگر لازمه
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();
        if (max($w, $h) > AI_MAX_IMAGE_DIM) {
            $im->resizeImage(
                $w >= $h ? AI_MAX_IMAGE_DIM : 0,
                $w >= $h ? 0 : AI_MAX_IMAGE_DIM,
                Imagick::FILTER_LANCZOS,
                1
            );
        }
        $im->stripImage();
        $dest = preg_replace('/\.(heic|heif)$/i', '.jpg', $srcPath);
        if ($dest === $srcPath) $dest .= '.jpg';
        $im->writeImage($dest);
        $im->clear();
        $im->destroy();
        @chmod($dest, 0644);
        // فایل اصلی HEIC رو پاک می‌کنیم چون دیگه استفاده نمی‌شه
        if ($dest !== $srcPath) @unlink($srcPath);
        return [true, $dest, null];
    } catch (Throwable $e) {
        return [false, null, 'تبدیل HEIC ناموفق بود: ' . $e->getMessage()];
    }
}

/**
 * Resize/optimize یه تصویر JPEG/PNG/WEBP/GIF با GD به محدوده‌ای که AI رو خفه نکنه.
 * اگر تصویر کوچک‌تر از حد است، دست نمی‌زنه.
 * برمی‌گردونه مسیر (یا همون اصلی اگر نیاز نبوده).
 */
function normalize_image_for_ai($srcPath, $mime) {
    if (!is_file($srcPath)) return $srcPath;
    $caps = image_processing_capabilities();

    // اگه فرمت خوبه و حجم/ابعاد کوچیکه، دست نمی‌زنیم
    $size = @filesize($srcPath) ?: 0;
    $info = @getimagesize($srcPath);
    $w = $info[0] ?? 0;
    $h = $info[1] ?? 0;

    $needsResize  = ($w > AI_MAX_IMAGE_DIM || $h > AI_MAX_IMAGE_DIM);
    $needsShrink  = ($size > AI_MAX_IMAGE_BYTES);

    if (!$needsResize && !$needsShrink) return $srcPath;
    if (!$caps['gd']) return $srcPath; // بدون GD کاری نمی‌تونیم بکنیم

    try {
        $img = null;
        switch (strtolower($mime)) {
            case 'image/jpeg':
                if (!$caps['gd_jpeg']) return $srcPath;
                $img = @imagecreatefromjpeg($srcPath);
                break;
            case 'image/png':
                if (!$caps['gd_png']) return $srcPath;
                $img = @imagecreatefrompng($srcPath);
                break;
            case 'image/webp':
                if (!function_exists('imagecreatefromwebp')) return $srcPath;
                $img = @imagecreatefromwebp($srcPath);
                break;
            case 'image/gif':
                if (!function_exists('imagecreatefromgif')) return $srcPath;
                $img = @imagecreatefromgif($srcPath);
                break;
            default:
                return $srcPath;
        }
        if (!$img) return $srcPath;

        // محاسبه ابعاد جدید
        if ($needsResize) {
            $ratio = ($w >= $h) ? (AI_MAX_IMAGE_DIM / $w) : (AI_MAX_IMAGE_DIM / $h);
            $newW = max(1, (int)round($w * $ratio));
            $newH = max(1, (int)round($h * $ratio));
            $resized = imagecreatetruecolor($newW, $newH);
            // حفظ شفافیت برای PNG
            if (strtolower($mime) === 'image/png') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefilledrectangle($resized, 0, 0, $newW, $newH, $transparent);
            }
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }

        // برای کم‌کردن حجم، PNG/GIF بزرگ رو هم به JPEG تبدیل می‌کنیم
        $dest = $srcPath;
        $outMime = strtolower($mime);
        if (($needsShrink || $needsResize) && in_array($outMime, ['image/png', 'image/gif'], true) && $size > AI_MAX_IMAGE_BYTES) {
            // تبدیل به JPEG برای صرفه‌جویی
            $dest = preg_replace('/\.[a-z0-9]+$/i', '.jpg', $srcPath);
            if ($dest === $srcPath) $dest .= '.jpg';
            // pre-fill بک‌گراند سفید چون JPEG شفافیت نداره
            $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefilledrectangle($bg, 0, 0, imagesx($img), imagesy($img), imagecolorallocate($bg, 255, 255, 255));
            imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            imagedestroy($img);
            $img = $bg;
            imagejpeg($img, $dest, AI_JPEG_QUALITY);
            if ($dest !== $srcPath) @unlink($srcPath);
        } else {
            // در همان فرمت ذخیره کن
            switch ($outMime) {
                case 'image/jpeg':
                    imagejpeg($img, $dest, AI_JPEG_QUALITY);
                    break;
                case 'image/png':
                    imagepng($img, $dest, 6);
                    break;
                case 'image/webp':
                    if (function_exists('imagewebp')) imagewebp($img, $dest, AI_JPEG_QUALITY);
                    break;
                case 'image/gif':
                    imagegif($img, $dest);
                    break;
            }
        }
        imagedestroy($img);
        @chmod($dest, 0644);
        return $dest;
    } catch (Throwable $e) {
        return $srcPath; // اگر چیزی شکست، نسخه اصلی
    }
}

/**
 * ورودی: مسیر فایل و mime اعلام‌شده
 * خروجی: ['ok'=>bool, 'path'=>string, 'mime'=>string, 'error'=>?string]
 *
 * این تابع جامع کار آماده‌سازی یه تصویر برای ارسال به AI رو انجام می‌ده:
 * - اگه HEIC بود → تبدیل به JPEG (یا reject اگه Imagick نباشه)
 * - اگه بزرگ بود → resize/compress
 * - اگه فرمت ناسازگار بود → reject
 */
function prepare_image_for_ai($path, $mime = null) {
    if (!is_file($path)) {
        return ['ok' => false, 'error' => 'فایل تصویر یافت نشد.', 'path' => $path, 'mime' => $mime];
    }

    // تشخیص mime واقعی از روی محتوا (نه فقط extension)
    if (function_exists('upload_mime')) {
        $realMime = upload_mime($path);
        if ($realMime && $realMime !== 'application/octet-stream') {
            $mime = $realMime;
        }
    }

    // تشخیص قطعی HEIC از روی سحرامد فایل
    if (is_heic_file($path)) {
        $mime = 'image/heic';
    }

    $mime = strtolower((string)$mime);

    // HEIC → تبدیل اجباری
    if (in_array($mime, ['image/heic', 'image/heif'], true)) {
        if (!extension_loaded('imagick')) {
            return [
                'ok' => false,
                'error' => 'فرمت HEIC روی این سرور پشتیبانی نمی‌شود. لطفاً عکس را به JPG یا PNG تبدیل کن یا از گوشی یه اسکرین‌شات بگیر و بفرست.',
                'path' => $path,
                'mime' => $mime
            ];
        }
        [$ok, $newPath, $err] = convert_heic_to_jpeg($path);
        if (!$ok) {
            return ['ok' => false, 'error' => $err, 'path' => $path, 'mime' => $mime];
        }
        $path = $newPath;
        $mime = 'image/jpeg';
    }

    // فرمت سازگار؟
    if (!is_ai_compatible_image_mime($mime)) {
        return [
            'ok' => false,
            'error' => 'فرمت این تصویر پشتیبانی نمی‌شود. فقط JPG/PNG/WEBP/GIF قابل ارسال است.',
            'path' => $path,
            'mime' => $mime
        ];
    }

    // resize/compress اگر لازم
    $normalized = normalize_image_for_ai($path, $mime);
    if ($normalized !== $path) {
        // mime ممکنه تغییر کرده باشه (مثلاً PNG → JPEG)
        $newExt = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
        if ($newExt === 'jpg' || $newExt === 'jpeg') $mime = 'image/jpeg';
        $path = $normalized;
    }

    return ['ok' => true, 'path' => $path, 'mime' => $mime, 'error' => null];
}
