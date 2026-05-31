<?php
/**
 * ============================================================
 *  دانش‌یار - پنل مدیریت بروزرسانی و مدیریت فایل‌های سرور
 * ============================================================
 */
$adminPage = 'updater';
$pageTitle = 'بروزرسانی فایل‌ها';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_header.php';

// بررسی دسترسی ادمین
require_admin();

// دریافت دایرکتوری درخواستی
$subDir = isset($_GET['dir']) ? trim((string)$_GET['dir']) : '';
$subDir = str_replace(['..', "\0"], '', $subDir); // مسدود کردن دایرکتوری کاذب
$subDir = ltrim($subDir, '/\\');

// مسیر فیزیکی فعلی
$currentPath = ROOT_PATH;
if ($subDir !== '') {
    $currentPath = realpath(ROOT_PATH . '/' . $subDir);
}

// چک امنیتی: مسیر نهایی حتماً باید درون ROOT_PATH باشد
if ($currentPath === false || strpos($currentPath, ROOT_PATH) !== 0) {
    $currentPath = ROOT_PATH;
    $subDir = '';
}

$message = null;
$msgType = 'info';

// -------------- عملیات بارگذاری فایل (تکی) --------------
if (isset($_POST['action']) && $_POST['action'] === 'upload_file') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $message = "توکن امنیتی نامعتبر است. مجدداً تلاش کنید.";
        $msgType = "error";
    } else {
        if (!empty($_FILES['file']['name'])) {
            $fileName = basename($_FILES['file']['name']);
            // جلوگیری از اجرای آپلودهای خطرناک خارج از کنترل
            $targetFile = $currentPath . '/' . $fileName;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
                $message = "فایل «{$fileName}» با موفقیت بارگذاری و جایگزین شد.";
                $msgType = "success";
            } else {
                $message = "خطا در آپلود فایل رخ داد.";
                $msgType = "error";
            }
        } else {
            $message = "لطفاً یک فایل برای آپلود انتخاب کنید.";
            $msgType = "error";
        }
    }
}

// -------------- عملیات بارگذاری و استخراج ZIP (بروزرسانی دسته‌ای) --------------
if (isset($_POST['action']) && $_POST['action'] === 'upload_zip') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $message = "توکن امنیتی نامعتبر است. مجدداً تلاش کنید.";
        $msgType = "error";
    } else {
        if (!empty($_FILES['zip_file']['name'])) {
            $fileName = $_FILES['zip_file']['name'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if ($ext !== 'zip') {
                $message = "فقط فایل‌های با فرمت ZIP مجاز هستند.";
                $msgType = "error";
            } else {
                $tempZip = $_FILES['zip_file']['tmp_name'];
                
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive;
                    if ($zip->open($tempZip) === TRUE) {
                        // استخراج در مسیر فعلی
                        $zip->extractTo($currentPath);
                        $zip->close();
                        $message = "بسته بروزرسانی ZIP با موفقیت در این دایرکتوری استخراج و اعمال شد.";
                        $msgType = "success";
                    } else {
                        $message = "باز کردن فایل ZIP ناموفق بود.";
                        $msgType = "error";
                    }
                } else {
                    // روش جایگزین با اجرای دستور سیستم در لینوکس در صورت وجود دسترسی
                    $output = [];
                    $retval = 0;
                    $cmd = "unzip -o " . escapeshellarg($tempZip) . " -d " . escapeshellarg($currentPath);
                    @exec($cmd, $output, $retval);
                    if ($retval === 0) {
                        $message = "بسته ZIP با موفقیت استخراج شد (از طریق unzip سیستم).";
                        $msgType = "success";
                    } else {
                        $message = "کلاس ZipArchive در PHP فعال نیست و فرمان سیستم نیز ناموفق بود.";
                        $msgType = "error";
                    }
                }
            }
        } else {
            $message = "لطفاً فایل ZIP را انتخاب کنید.";
            $msgType = "error";
        }
    }
}

// -------------- ایجاد دایرکتوری جدید --------------
if (isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $message = "توکن امنیتی نامعتبر است.";
        $msgType = "error";
    } else {
        $folderName = trim((string)($_POST['folder_name'] ?? ''));
        $folderName = str_replace(['..', '/', '\\', "\0"], '', $folderName);
        
        if ($folderName === '') {
            $message = "نام پوشه نامعتبر است.";
            $msgType = "error";
        } else {
            $targetDir = $currentPath . '/' . $folderName;
            if (is_dir($targetDir)) {
                $message = "پوشه‌ای با این نام از قبل وجود دارد.";
                $msgType = "error";
            } else {
                if (@mkdir($targetDir, 0755, true)) {
                    $message = "پوشه «{$folderName}» با موفقیت ساخته شد.";
                    $msgType = "success";
                } else {
                    $message = "خطا در ساخت پوشه. دسترسی‌های سرور را بررسی کنید.";
                    $msgType = "error";
                }
            }
        }
    }
}

// -------------- حذف فایل یا پوشه --------------
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $message = "توکن امنیتی نامعتبر است.";
        $msgType = "error";
    } else {
        $item = trim((string)($_POST['item'] ?? ''));
        $item = str_replace(['..', "\0"], '', $item);
        $targetItem = $currentPath . '/' . $item;
        
        // مطمئن شویم همچنان داخل پروژه هستیم
        if (strpos(realpath($targetItem), ROOT_PATH) === 0) {
            if (is_dir($targetItem)) {
                // حذف بازگشتی یا ساده
                if (@rmdir($targetItem)) {
                    $message = "پوشه با موفقیت حذف شد.";
                    $msgType = "success";
                } else {
                    // اگر پوشه پر باشد
                    $message = "پوشه خالی نیست یا خطای دسترسی رخ داده است.";
                    $msgType = "error";
                }
            } else if (is_file($targetItem)) {
                if (@unlink($targetItem)) {
                    $message = "فایل با موفقیت حذف شد.";
                    $msgType = "success";
                } else {
                    $message = "خطا در حذف فایل.";
                    $msgType = "error";
                }
            }
        } else {
            $message = "مسیر نامعتبر است.";
            $msgType = "error";
        }
    }
}

// -------------- ویرایش و ذخیره فایل متنی --------------
$editFileContent = '';
$editFileName = '';
if (isset($_GET['edit'])) {
    $editItem = trim((string)$_GET['edit']);
    $editItem = str_replace(['..', "\0"], '', $editItem);
    $editFilePath = $currentPath . '/' . $editItem;
    
    if (is_file($editFilePath) && strpos(realpath($editFilePath), ROOT_PATH) === 0) {
        $editFileName = $editItem;
        $editFileContent = file_get_contents($editFilePath);
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'save_file') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $message = "توکن امنیتی نامعتبر است.";
        $msgType = "error";
    } else {
        $editItem = trim((string)($_POST['file_name'] ?? ''));
        $editItem = str_replace(['..', "\0"], '', $editItem);
        $editFilePath = $currentPath . '/' . $editItem;
        $content = (string)($_POST['content'] ?? '');
        
        if (is_file($editFilePath) && strpos(realpath($editFilePath), ROOT_PATH) === 0) {
            if (@file_put_contents($editFilePath, $content) !== false) {
                $message = "تغییرات فایل «{$editItem}» با موفقیت ذخیره شد.";
                $msgType = "success";
                // رفرش محتوای ادیتور
                $editFileName = $editItem;
                $editFileContent = $content;
            } else {
                $message = "خطا در ذخیره تغییرات. دسترسی فایل را بررسی کنید.";
                $msgType = "error";
            }
        }
    }
}

// خواندن لیست فایل‌ها و پوشه‌ها
$files = [];
$dirs = [];

if (is_dir($currentPath)) {
    $items = scandir($currentPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $full = $currentPath . '/' . $item;
        $stat = stat($full);
        
        if (is_dir($full)) {
            $dirs[] = [
                'name' => $item,
                'path' => ($subDir !== '' ? $subDir . '/' : '') . $item,
                'mtime' => $stat['mtime']
            ];
        } else {
            $files[] = [
                'name' => $item,
                'path' => ($subDir !== '' ? $subDir . '/' : '') . $item,
                'size' => $stat['size'],
                'mtime' => $stat['mtime']
            ];
        }
    }
}

// مرتب‌سازی بر اساس حروف الفبا
usort($dirs, function($a, $b) { return strcmp($a['name'], $b['name']); });
usort($files, function($a, $b) { return strcmp($a['name'], $b['name']); });

$csrf = csrf_token();
?>

<div class="card glass">
  <div style="display:flex;justify-content:between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:15px;flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="color:var(--orange);margin:0;display:flex;align-items:center;gap:8px">
            <?= icon('upload') ?> مدیریت و بروزرسانی فایل‌های سرور
        </h2>
        <p class="sub" style="margin:5px 0 0 0">از این بخش می‌توانی سورس کدها یا فایل‌های پروژه را به طور مستقیم بروزرسانی، ویرایش یا دلیت کنی.</p>
    </div>
    
    <div>
        <a href="?dir=<?= urlencode($subDir) ?>" class="btn btn-outline" style="font-size:12px;padding:6px 12px">
            <?= icon('refresh') ?> بارگذاری مجدد
        </a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?= $msgType === 'success' ? 'success' : ($msgType === 'error' ? 'error' : 'info') ?>" style="margin-bottom: 20px;">
        <?= e($message) ?>
    </div>
  <?php endif; ?>

  <!-- فرم‌های مربوط به بارگذاری و پوشه جدید -->
  <div style="display:grid;grid-template-columns:1fr;gap:20px;margin-bottom:25px;" id="actions-grid">
    <style>
        @media(min-width: 768px) {
            #actions-grid { grid-template-columns: 1fr 1fr 1fr !important; }
        }
        .upload-box {
            padding:15px; border-radius:12px; background: rgba(0,0,0,0.2); border: 1px dashed var(--border);
        }
        .upload-box h4 { margin:0 0 10px 0; color:var(--text); font-size:13.5px; display:flex; align-items:center; gap:6px; }
        .upload-box .form-control { background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius:8px; padding:6px 10px; width:100%; color:#fff; font-size:12px; margin-bottom:8px;}
    </style>

    <!-- بخش ۱: بروزرسانی با فایل ZIP -->
    <div class="upload-box">
        <h4><?= icon('flash') ?> بروزرسانی گروهی (ZIP)</h4>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="upload_zip">
            <input type="file" name="zip_file" accept=".zip" class="form-control" required>
            <button type="submit" class="btn btn-primary btn-block" style="font-size:12px;padding:6px 10px">آپلود و اکسترکت ZIP</button>
        </form>
    </div>

    <!-- بخش ۲: آپلود فایل تکی -->
    <div class="upload-box">
        <h4><?= icon('plus') ?> آپلود فایل تکی</h4>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="upload_file">
            <input type="file" name="file" class="form-control" required>
            <button type="submit" class="btn btn-outline btn-block" style="font-size:12px;padding:6px 10px">آپلود فایل جدید / جایگزین</button>
        </form>
    </div>

    <!-- بخش ۳: ایجاد پوشه جدید -->
    <div class="upload-box">
        <h4><?= icon('book') ?> ساخت پوشه جدید</h4>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="create_folder">
            <input type="text" name="folder_name" placeholder="نام پوشه جدید به انگلیسی" class="form-control" required>
            <button type="submit" class="btn btn-block" style="background:var(--glass);color:#fff;font-size:12px;padding:6px 10px;border:1px solid var(--border)">ایجاد دایرکتوری</button>
        </form>
    </div>
  </div>

  <!-- ادیتور فایل متنی (در صورت درخواست ویرایش) -->
  <?php if ($editFileName !== ''): ?>
    <div class="upload-box" style="margin-bottom:25px; border-color:var(--orange)">
        <h4 style="color:var(--orange)"><?= icon('edit') ?> ویرایشگر متنی فایل: <?= e($editFileName) ?></h4>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="save_file">
            <input type="hidden" name="file_name" value="<?= e($editFileName) ?>">
            <textarea name="content" style="width:100%; height:300px; background:#1e1e1e; color:#d4d4d4; font-family:monospace; font-size:12.5px; padding:12px; border-radius:8px; border:1px solid var(--border); direction:ltr; text-align:left; line-height:1.5; resize:vertical; margin-bottom:12px;"><?= htmlspecialchars($editFileContent) ?></textarea>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary" style="font-size:12px;padding:8px 16px">ذخیره تغییرات فایل</button>
                <a href="?dir=<?= urlencode($subDir) ?>" class="btn btn-outline" style="font-size:12px;padding:8px 16px">بستن ادیتور</a>
            </div>
        </form>
    </div>
  <?php endif; ?>

  <!-- مسیر فعلی دایرکتوری (Breadcrumb) -->
  <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);padding:10px 14px;border-radius:10px;margin-bottom:15px;font-size:13px;display:flex;align-items:center;gap:8px">
    <span style="color:var(--text-dim)">مسیر فعلی:</span>
    <a href="?dir=" style="color:var(--orange);font-weight:bold;text-decoration:none">ROOT</a>
    <?php
    if ($subDir !== '') {
        $parts = explode('/', $subDir);
        $accumulated = '';
        foreach ($parts as $part) {
            if ($part === '') continue;
            $accumulated .= ($accumulated === '' ? '' : '/') . $part;
            echo ' <span style="color:var(--text-dim)">/</span> ';
            echo '<a href="?dir=' . urlencode($accumulated) . '" style="color:#fff;text-decoration:none">' . e($part) . '</a>';
        }
    }
    ?>
  </div>

  <!-- لیست پوشه‌ها و فایل‌ها -->
  <div style="overflow-x:auto">
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width:50%">نام</th>
                <th style="width:15%">نوع</th>
                <th style="width:15%">حجم</th>
                <th style="width:20%;text-align:center">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <!-- برگشت به پوشه قبلی -->
            <?php if ($subDir !== ''): ?>
                <?php
                $parentDir = dirname($subDir);
                if ($parentDir === '.' || $parentDir === '/') $parentDir = '';
                ?>
                <tr>
                    <td colspan="4">
                        <a href="?dir=<?= urlencode($parentDir) ?>" style="color:var(--orange);text-decoration:none;display:flex;align-items:center;gap:8px;font-weight:bold">
                            <?= icon('arrow-right') ?> رفتن به پوشه قبلی (..)
                        </a>
                    </td>
                </tr>
            <?php endif; ?>

            <!-- پوشه‌ها -->
            <?php foreach ($dirs as $dir): ?>
                <tr>
                    <td>
                        <a href="?dir=<?= urlencode($dir['path']) ?>" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:#fff;font-weight:500">
                            <span style="color:#e0a96d">📁</span> <?= e($dir['name']) ?>
                        </a>
                    </td>
                    <td><span style="color:var(--text-dim);font-size:11.5px">پوشه</span></td>
                    <td>-</td>
                    <td style="text-align:center">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('آیا از حذف این پوشه مطمئن هستید؟ این کار ممکن است سورس کد را خراب کند.');">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="item" value="<?= e($dir['name']) ?>">
                            <button type="submit" class="btn-link" style="color:var(--danger);border:none;background:none;cursor:pointer;" title="حذف دایرکتوری">
                                <?= icon('trash', ['size' => 16]) ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>

            <!-- فایل‌ها -->
            <?php if (empty($dirs) && empty($files)): ?>
                <tr>
                    <td colspan="4" style="text-align:center;padding:30px;color:var(--text-dim)">پوشه خالی است.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($files as $file): ?>
                <?php 
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $canEdit = in_array($ext, ['php', 'css', 'js', 'json', 'env', 'txt', 'html', 'sql', 'xml', 'md'], true);
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;color:#fff">
                            <span>📄</span> <?= e($file['name']) ?>
                        </div>
                    </td>
                    <td><span style="color:var(--text-dim);font-size:11.5px"><?= strtoupper($ext) ?></span></td>
                    <td><span style="font-size:12px"><?= num_fa(round($file['size'] / 1024, 1)) ?> KB</span></td>
                    <td style="text-align:center">
                        <div style="display:flex;justify-content:center;gap:12px;align-items:center">
                            <?php if ($canEdit): ?>
                                <a href="?dir=<?= urlencode($subDir) ?>&edit=<?= urlencode($file['name']) ?>" style="color:var(--orange);display:inline-flex;align-items:center;" title="ویرایش کد">
                                    <?= icon('edit', ['size' => 16]) ?>
                                </a>
                            <?php endif; ?>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirm('آیا از حذف این فایل مطمئن هستید؟');">
                                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="item" value="<?= e($file['name']) ?>">
                                <button type="submit" class="btn-link" style="color:var(--danger);border:none;background:none;cursor:pointer;display:inline-flex;align-items:center" title="حذف فایل">
                                    <?= icon('trash', ['size' => 16]) ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
