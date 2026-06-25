<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/book_chunker.php';

// فایل‌های دانلود شده در پوشه پروژه
$booksToInject = [
    [
        'title' => "زیست شناسی دهم (مرجع رسمی ۱۴۰۴)",
        'grade' => 10,
        'subject' => "زیست شناسی",
        'major' => "experimental",
        'path' => 'zist10.pdf'
    ],
    [
        'title' => "هندسه دهم (مرجع رسمی ۱۴۰۴)",
        'grade' => 10,
        'subject' => "هندسه",
        'major' => "math",
        'path' => 'hendese10.pdf'
    ]
];

echo "--- شروع عملیات تزریق دانش از فایل‌های محلی ---\n";

foreach ($booksToInject as $bookInfo) {
    $fullPath = __DIR__ . '/' . $bookInfo['path'];
    echo "\nدر حال پردازش: {$bookInfo['title']}...\n";

    if (!is_file($fullPath)) {
        echo "❌ خطا: فایل {$bookInfo['path']} یافت نشد. رد می‌شویم...\n";
        continue;
    }

    // استخراج فوق‌دقیق با AI (حالت detailed)
    // نکته: تابع extract_book_content_with_ai مسیر فایل را می‌گیرد
    echo "در حال استخراج خط‌به‌خط (این مرحله زمان‌بر است)... ";
    $extraction = extract_book_content_with_ai($fullPath, 'detailed');

    if (!$extraction['ok']) {
        echo "❌ خطا در استخراج: " . $extraction['error'] . "\n";
        continue;
    }
    echo "موفق شد.\n";

    // ثبت کتاب در دیتابیس
    $stmt = db()->prepare("INSERT INTO books (title, grade, subject, major, cached_text) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $bookInfo['title'], 
        $bookInfo['grade'], 
        $bookInfo['subject'], 
        $bookInfo['major'], 
        $extraction['text']
    ]);
    $bookId = (int)db()->lastInsertId();
    echo "✅ کتاب با ID $bookId ثبت شد.\n";

    // تکه تکه کردن و ذخیره Chunkها
    $numChunks = save_book_chunks($bookId, $extraction['text']);
    echo "✅ تعداد $numChunks تکه حافظه ایجاد شد.\n";
}

echo "\n--- تمام عملیات با موفقیت به پایان رسید. زیست و هندسه دهم اکنون در حافظه سیستم هستند. ---\n";
?>
