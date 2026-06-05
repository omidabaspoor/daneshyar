<?php
/**
 *  دانش‌یار - پنل مدیریت بروزرسانی و مدیریت فایل‌های سرور
 */
$adminPage = 'updater';
$pageTitle = 'بروزرسانی فایل‌ها';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_header.php';
require_admin();

$subDir = isset($_GET['dir']) ? trim((string)$_GET['dir']) : '';
$subDir = str_replace(['..', "\0"], '', $subDir);
$subDir = ltrim($subDir, '/\\');

$currentPath = ROOT_PATH;
if ($subDir !== '') {
    $currentPath = realpath(ROOT_PATH . '/' . $subDir);
}

if ($currentPath === false || strpos($currentPath, ROOT_PATH) !== 0) {
    $currentPath = ROOT_PATH;
    $subDir = '';
}

$message = null;
$msgType = 'info';

if (isset($_POST['action']) && $_POST['action'] === 'upload_file') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $message = "توکن امنیتی نامعتبر است."; $msgType = "error";
    } else {
        if (!empty($_FILES['file']['name'])) {
            $fileName = basename($_FILES['file']['name']);
            $targetFile = $currentPath . '/' . $fileName;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
                $message = "فایل «{$fileName}» با موفقیت بارگذاری شد."; $msgType = "success";
            } else { $message = "خطا در آپلود فایل."; $msgType = "error"; }
        } else { $message = "فایلی انتخاب نشده."; $msgType = "error"; }
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'upload_zip') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $message = "توکن امنیتی نامعتبر است."; $msgType = "error";
    } else {
        if (!empty($_FILES['zip_file']['name'])) {
            $fileName = $_FILES['zip_file']['name'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($ext !== 'zip') { $message = "فقط ZIP مجاز است."; $msgType = "error"; }
            else {
                $tempZip = $_FILES['zip_file']['tmp_name'];
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive;
                    if ($zip->open($tempZip) === TRUE) {
                        $zip->extractTo($currentPath);
                        $zip->close();
                        $message = "ZIP با موفقیت استخراج شد."; $msgType = "success";
                    } else { $message = "باز کردن ZIP ناموفق بود."; $msgType = "error"; }
                } else { $message = "ZipArchive فعال نیست."; $msgType = "error"; }
            }
        } else { $message = "فایل ZIP انتخاب نشده."; $msgType = "error"; }
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $message = "توکن امنیتی نامعتبر است."; $msgType = "error";
    } else {
        $folderName = trim((string)($_POST['folder_name'] ?? ''));
        $folderName = str_replace(['..', '/', '\\', "\0"], '', $folderName);
        if ($folderName === '') { $message = "نام پوشه نامعتبر است."; $msgType = "error"; }
        else {
            $targetDir = $currentPath . '/' . $folderName;
            if (is_dir($targetDir)) { $message = "پوشه موجود است."; $msgType = "error"; }
            else {
                if (@mkdir($targetDir, 0755, true)) { $message = "پوشه «{$folderName}» ساخته شد."; $msgType = "success"; }
                else { $message = "خطا در ساخت پوشه."; $msgType = "error"; }
            }
        }
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $message = "توکن امنیتی نامعتبر است."; $msgType = "error";
    } else {
        $item = trim((string)($_POST['item'] ?? ''));
        $item = str_replace(['..', "\0"], '', $item);
        $targetItem = $currentPath . '/' . $item;
        if (strpos(realpath($targetItem), ROOT_PATH) === 0) {
            if (is_dir($targetDir = $targetItem)) {
                if (@rmdir($targetItem)) { $message = "پوشه حذف شد."; $msgType = "success"; }
                else { $message = "پوشه خالی نیست."; $msgType = "error"; }
            } else if (is_file($targetItem)) {
                if (@unlink($targetItem)) { $message = "فایل حذف شد."; $msgType = "success"; }
                else { $message = "خطا در حذف."; $msgType = "error"; }
            }
        } else { $message = "مسیر نامعتبر."; $msgType = "error"; }
    }
}

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
        $message = "توکن امنیتی نامعتبر است."; $msgType = "error";
    } else {
        $editItem = trim((string)($_POST['file_name'] ?? ''));
        $editItem = str_replace(['..', "\0"], '', $editItem);
        $editFilePath = $currentPath . '/' . $editItem;
        $content = (string)($_POST['content'] ?? '');
        if (is_file($editFilePath) && strpos(realpath($editFilePath), ROOT_PATH) === 0) {
            if (@file_put_contents($editFilePath, $content) !== false) {
                $message = "تغییرات «{$editItem}» ذخیره شد."; $msgType = "success";
                $editFileName = $editItem;
                $editFileContent = $content;
            } else { $message = "خطا در ذخیره."; $msgType = "error"; }
        }
    }
}

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

usort($dirs, function($a, $b) { return strcmp($a['name'], $b['name']); });
usort($files, function($a, $b) { return strcmp($a['name'], $b['name']); });

$csrf = csrf_token();
?>

<div class="admin-page-header">
  <h2><?= icon('upload') ?> مدیریت و بروزرسانی فایل‌ها</h2>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= $msgType === 'success' ? 'success' : ($msgType === 'error' ? 'error' : 'info') ?>"><?= e($message) ?></div>
<?php endif; ?>

<!-- ═══ ادیتور ═══ -->
<?php if ($editFileName !== ''): ?>
  <div class="admin-card glass" style="margin-bottom:20px; border-color:var(--border-orange)">
    <div class="admin-card-header">
      <h3><?= icon('edit') ?> ویرایش: <?= e($editFileName) ?></h3>
      <a href="?dir=<?= urlencode($subDir) ?>" class="btn btn-ghost btn-sm"><?= icon('close') ?> بستن ادیتور</a>
    </div>
    <div class="admin-card-body">
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="save_file">
        <input type="hidden" name="file_name" value="<?= e($editFileName) ?>">
        <textarea name="content" class="code-editor"><?= htmlspecialchars($editFileContent) ?></textarea>
        <button type="submit" class="btn btn-primary"><?= icon('check') ?> ذخیره تغییرات</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<!-- ═══ آپلود / ساخت پوشه ═══ -->
<div class="uploader-grid">
  <div class="uploader-box glass">
    <h4><?= icon('flash') ?> بروزرسانی گروهی (ZIP)</h4>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="upload_zip">
      <input type="file" name="zip_file" accept=".zip" class="input" style="font-size:12px;padding:8px" required>
      <button type="submit" class="btn btn-primary btn-block" style="font-size:12px;padding:8px;margin-top:8px">آپلود و اکسترکت ZIP</button>
    </form>
  </div>

  <div class="uploader-box glass">
    <h4><?= icon('plus') ?> آپلود فایل تکی</h4>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="upload_file">
      <input type="file" name="file" class="input" style="font-size:12px;padding:8px" required>
      <button type="submit" class="btn btn-ghost btn-block" style="font-size:12px;padding:8px;margin-top:8px">آپلود / جایگزین</button>
    </form>
  </div>

  <div class="uploader-box glass">
    <h4><?= icon('book') ?> ساخت پوشه جدید</h4>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="create_folder">
      <input type="text" name="folder_name" placeholder="نام پوشه جدید" class="input" style="font-size:12px;padding:8px" required>
      <button type="submit" class="btn btn-block" style="background:var(--glass);color:var(--text);font-size:12px;padding:8px;margin-top:8px;border:1px solid var(--border)">ایجاد پوشه</button>
    </form>
  </div>
</div>

<!-- ═══ مسیر ═══ -->
<div class="admin-breadcrumb">
  <span class="bc-label">مسیر:</span>
  <a href="?dir=" class="bc-root">ROOT</a>
  <?php if ($subDir !== ''):
    $parts = explode('/', $subDir);
    $accumulated = '';
    foreach ($parts as $part):
      if ($part === '') continue;
      $accumulated .= ($accumulated === '' ? '' : '/') . $part;
  ?>
    <span class="bc-sep">/</span>
    <a href="?dir=<?= urlencode($accumulated) ?>"><?= e($part) ?></a>
  <?php endforeach; endif; ?>
</div>

<!-- ═══ لیست فایل‌ها و پوشه‌ها ═══ -->
<div class="admin-card glass">
  <div class="admin-card-body" style="padding:0">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th style="width:50%">نام</th><th style="width:15%">نوع</th><th style="width:15%">حجم</th><th style="width:20%;text-align:center">عملیات</th></tr>
        </thead>
        <tbody>
          <?php if ($subDir !== ''):
            $parentDir = dirname($subDir);
            if ($parentDir === '.' || $parentDir === '/') $parentDir = '';
          ?>
            <tr>
              <td colspan="4">
                <a href="?dir=<?= urlencode($parentDir) ?>" style="color:var(--orange);text-decoration:none;display:flex;align-items:center;gap:8px;font-weight:700">
                  <?= icon('arrow-right') ?> ..
                </a>
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($dirs as $dir): ?>
            <tr>
              <td>
                <a href="?dir=<?= urlencode($dir['path']) ?>" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--text);font-weight:500">
                  <span>📁</span> <?= e($dir['name']) ?>
                </a>
              </td>
              <td><span class="text-muted text-sm">پوشه</span></td>
              <td>-</td>
              <td style="text-align:center">
                <form method="POST" style="display:inline;" onsubmit="return confirm('حذف شود؟');">
                  <input type="hidden" name="csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="item" value="<?= e($dir['name']) ?>">
                  <button type="submit" class="btn-link" style="color:var(--danger);background:none;border:none;cursor:pointer" title="حذف"><?= icon('trash', ['size' => 16]) ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($dirs) && empty($files)): ?>
            <tr><td colspan="4" class="admin-empty">پوشه خالی است.</td></tr>
          <?php endif; ?>

          <?php foreach ($files as $file):
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $canEdit = in_array($ext, ['php', 'css', 'js', 'json', 'env', 'txt', 'html', 'sql', 'xml', 'md'], true);
          ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:8px;color:var(--text)">
                  <span>📄</span> <?= e($file['name']) ?>
                </div>
              </td>
              <td><span class="text-muted text-sm"><?= strtoupper($ext) ?></span></td>
              <td><span class="text-sm"><?= num_fa(round($file['size'] / 1024, 1)) ?> KB</span></td>
              <td style="text-align:center">
                <div style="display:flex;justify-content:center;gap:8px;align-items:center">
                  <?php if ($canEdit): ?>
                    <a href="?dir=<?= urlencode($subDir) ?>&edit=<?= urlencode($file['name']) ?>" style="color:var(--orange);display:inline-flex;align-items:center" title="ویرایش"><?= icon('edit', ['size' => 16]) ?></a>
                  <?php endif; ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('حذف شود؟');">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="item" value="<?= e($file['name']) ?>">
                    <button type="submit" class="btn-link" style="color:var(--danger);background:none;border:none;cursor:pointer" title="حذف"><?= icon('trash', ['size' => 16]) ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>