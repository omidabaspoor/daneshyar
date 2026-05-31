<?php
/**
 * ============================================================
 *  دانش‌یار - آپلود ضمیمه (تصویر یا PDF) برای چت
 *
 *  - HEIC رو در همین لحظه به JPEG تبدیل می‌کنه (اگه Imagick هست).
 *  - تصاویر بزرگ رو resize می‌کنه (با GD).
 *  - PDF فقط validate می‌شه و ذخیره می‌شه.
 *  - روی هاست‌های ضعیف هم کار می‌کنه (fallback graceful).
 * ============================================================
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/image_helper.php';

// محدودیت‌های کمتر برای آپلود (هاست‌های اشتراکی)
@set_time_limit(120);
@ini_set('memory_limit', '256M');

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) json_response(['ok'=>false,'error'=>'ابتدا وارد شو.'], 401);

if (!csrf_check($_POST['csrf'] ?? '')) {
    json_response(['ok'=>false,'error'=>'توکن امنیتی نامعتبر است.'], 403);
}

if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
    json_response(['ok'=>false,'error'=>'فایلی ارسال نشد.']);
}

// چک خطاهای آپلود PHP (مثل INI_SIZE)
$uploadErr = (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadErr !== UPLOAD_ERR_OK) {
    json_response(['ok'=>false, 'error'=>upload_error_message($uploadErr)]);
}

$f = $_FILES['file'];

// تشخیص mime واقعی از روی محتوا
$mime = upload_mime($f['tmp_name']);

// تشخیص HEIC از روی سحرامد (حتی اگه مرورگر mime نگفته باشه)
if (is_heic_file($f['tmp_name'])) {
    $mime = 'image/heic';
}

// fallback از روی پسوند اگر mime معتبر نبود
if (!$mime || $mime === 'application/octet-stream') {
    $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
    $extMap = [
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png',  'webp' => 'image/webp',
        'gif'  => 'image/gif',  'heic' => 'image/heic',
        'heif' => 'image/heif', 'pdf'  => 'application/pdf',
    ];
    $mime = $extMap[$ext] ?? $mime;
}

// ============== مسیر PDF ==============
if ($mime === 'application/pdf' || $mime === 'application/x-pdf') {
    [$ok, $res] = validate_chat_pdf_upload($f);
    if (!$ok) json_response(['ok'=>false,'error'=>$res]);

    if (!is_dir(UPLOADS_PATH)) @mkdir(UPLOADS_PATH, 0755, true);
    if (!is_writable(UPLOADS_PATH)) json_response(['ok'=>false,'error'=>'پوشه uploads قابل نوشتن نیست.']);

    $name = 'pdf_' . (int)$user['id'] . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.pdf';
    $dest = UPLOADS_PATH . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        json_response(['ok'=>false,'error'=>'ذخیره PDF ناموفق بود.']);
    }
    @chmod($dest, 0644);
    json_response(['ok'=>true, 'path'=>'uploads/'.$name, 'type'=>'pdf']);
}

// ============== مسیر تصویر ==============
$isImage = is_string($mime) && strpos($mime, 'image/') === 0;
if (!$isImage) {
    json_response(['ok'=>false,'error'=>'فقط فایل عکس یا PDF قابل ارسال است.']);
}

// چک حجم
$maxBytes = (defined('MAX_UPLOAD_MB') ? MAX_UPLOAD_MB : 15) * 1024 * 1024;
if ((int)$f['size'] <= 0 || (int)$f['size'] > $maxBytes) {
    json_response(['ok'=>false, 'error'=>'حجم تصویر بیش از حد مجاز است.']);
}

// ابتدا فایل رو در uploads ذخیره کن، بعد پردازش
if (!is_dir(UPLOADS_PATH)) @mkdir(UPLOADS_PATH, 0755, true);
if (!is_writable(UPLOADS_PATH)) json_response(['ok'=>false,'error'=>'پوشه uploads قابل نوشتن نیست.']);

// انتخاب پسوند موقت بر اساس mime
$extMap = [
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
    'image/gif'  => 'gif', 'image/heic' => 'heic', 'image/heif' => 'heic',
];
$tmpExt = $extMap[$mime] ?? 'bin';
$tmpName = 'img_' . (int)$user['id'] . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $tmpExt;
$tmpDest = UPLOADS_PATH . '/' . $tmpName;

if (!move_uploaded_file($f['tmp_name'], $tmpDest)) {
    json_response(['ok'=>false, 'error'=>'ذخیره موقت تصویر ناموفق بود.']);
}
@chmod($tmpDest, 0644);

// تبدیل HEIC و resize
$prep = prepare_image_for_ai($tmpDest, $mime);
if (!$prep['ok']) {
    @unlink($tmpDest);
    json_response(['ok'=>false, 'error'=>$prep['error']]);
}

$finalPath = $prep['path'];
$finalMime = $prep['mime'];
$relative  = 'uploads/' . basename($finalPath);

// چک نهایی ابعاد (محافظت برابر تصاویر عجیب)
$info = @getimagesize($finalPath);
if ($info && ($info[0] < 10 || $info[1] < 10)) {
    @unlink($finalPath);
    json_response(['ok'=>false, 'error'=>'تصویر معتبر نیست.']);
}

json_response([
    'ok'   => true,
    'path' => $relative,
    'type' => 'image',
    'mime' => $finalMime,
]);


/* ===================== helpers محلی ===================== */
function upload_error_message($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'حجم فایل بیشتر از حد مجاز سرور است.';
        case UPLOAD_ERR_PARTIAL:
            return 'آپلود ناقص بود. لطفاً دوباره تلاش کن.';
        case UPLOAD_ERR_NO_FILE:
            return 'فایلی ارسال نشد.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'مشکل سرور: پوشه موقت برای آپلود وجود ندارد.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'مشکل سرور: نوشتن روی دیسک ناموفق بود.';
        case UPLOAD_ERR_EXTENSION:
            return 'یکی از افزونه‌های PHP آپلود را متوقف کرده است.';
        default:
            return 'آپلود ناموفق بود (کد ' . (int)$code . ').';
    }
}
