<?php
/**
 * ============================================================
 *  دانش‌یار – مدیریت کتاب‌ها v3
 *  سازگار با cPanel (فقط PHP)
 *  آپلود چند فایل + انتخاب چند رشته
 * ============================================================
 */
$adminPage = 'books';
$pageTitle = 'مدیریت کتاب‌ها';
include __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/book_chunker.php';
ensure_book_chunks_schema();
require_once __DIR__ . '/../includes/icons.php';

@set_time_limit(600);
@ini_set('memory_limit', '512M');
@ini_set('post_max_size', '200M');
@ini_set('upload_max_filesize', '50M');
@ini_set('max_file_uploads', '20');

// اطمینان از ستون‌های جدید
try {
    $col = db()->query("SHOW COLUMNS FROM books LIKE 'majors'")->fetch();
    if (!$col) {
        db()->exec("ALTER TABLE books ADD COLUMN `majors` VARCHAR(200) NOT NULL DEFAULT 'all' AFTER `major`");
        db()->exec("UPDATE books SET majors = major WHERE majors = 'all' AND major != 'all'");
    }
} catch (Throwable $e) {}

try {
    $col = db()->query("SHOW COLUMNS FROM books LIKE 'file_names'")->fetch();
    if (!$col) {
        db()->exec("ALTER TABLE books ADD COLUMN `file_names` TEXT DEFAULT NULL AFTER `file_name`");
        db()->exec("UPDATE books SET file_names = file_name WHERE file_names IS NULL");
    }
} catch (Throwable $e) {}

// تبدیل مقادیر PHP مثل "50M" به بایت
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch ($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

$msg = '';
$msgType = 'success';

// ======== تشخیص خطای آپلود بزرگ ========
// وقتی حجم فایل از post_max_size بیشتره، PHP کل $_POST و $_FILES رو خالی می‌کنه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $maxPost = ini_get('post_max_size');
    $maxUpload = ini_get('upload_max_filesize');
    $msg = "❌ حجم فایل‌ها بیش از حد مجاز سرور است.<br>"
         . "محدودیت فعلی: post_max_size=<b>{$maxPost}</b> | upload_max_filesize=<b>{$maxUpload}</b><br>"
         . "برای رفع: در cPanel → PHP Settings مقادیر رو افزایش بده، یا فایل <code>.user.ini</code> بساز.";
    $msgType = 'error';
}

// ======== پردازش فرم ========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {

    // --- حذف ---
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $r = db()->prepare("SELECT file_name, file_names FROM books WHERE id=?"); $r->execute([$id]); $row = $r->fetch();
        if ($row) {
            $files = array_filter(explode(',', $row['file_names'] ?? $row['file_name']));
            foreach ($files as $fn) @unlink(BOOKS_PATH . '/' . trim($fn));
            try { db()->prepare("DELETE FROM book_chunks WHERE book_id=?")->execute([$id]); } catch (Throwable $e) {}
            db()->prepare("DELETE FROM books WHERE id=?")->execute([$id]);
            $msg = '✓ کتاب حذف شد.';
        }

    // --- بازسازی chunk‌ها ---
    } elseif (isset($_POST['rechunk_id'])) {
        $id = (int)$_POST['rechunk_id'];
        $r = db()->prepare("SELECT * FROM books WHERE id=?"); $r->execute([$id]); $row = $r->fetch();
        if ($row && mb_strlen($row['cached_text'] ?? '', 'UTF-8') > 100) {
            $cnt = save_book_chunks($id, $row['cached_text']);
            $msg = '✓ «' . e($row['title']) . '» → ' . num_fa($cnt) . ' بخش.';
        } else { $msg = '⚠ ابتدا محتوا را استخراج کنید.'; $msgType = 'info'; }

    // --- بازسازی همه ---
    } elseif (isset($_POST['rechunk_all'])) {
        $all = db()->query("SELECT id, cached_text FROM books WHERE LENGTH(COALESCE(cached_text,'')) > 100")->fetchAll();
        $t = 0; foreach ($all as $a) $t += save_book_chunks((int)$a['id'], $a['cached_text']);
        $msg = '✓ ' . num_fa(count($all)) . ' کتاب → ' . num_fa($t) . ' بخش.';

    // --- استخراج مجدد با AI ---
    } elseif (isset($_POST['extract_id'])) {
        $id = (int)$_POST['extract_id'];
        $r = db()->prepare("SELECT * FROM books WHERE id=?"); $r->execute([$id]); $row = $r->fetch();
        if ($row) {
            $files = array_filter(explode(',', $row['file_names'] ?? $row['file_name']));
            $allText = '';
            $errors = [];

            foreach ($files as $fn) {
                $pdf = BOOKS_PATH . '/' . trim($fn);
                if (!is_file($pdf)) { $errors[] = trim($fn) . ' یافت نشد'; continue; }
                $res = extract_book_content_with_ai($pdf);
                if ($res['ok']) {
                    $allText .= "\n\n---\n\n" . $res['text'];
                } else {
                    $errors[] = trim($fn) . ': ' . $res['error'];
                }
            }

            if (mb_strlen(trim($allText), 'UTF-8') > 100) {
                $allText = trim($allText);
                if (function_exists('sanitize_utf8')) $allText = sanitize_utf8($allText);
                db()->prepare("UPDATE books SET cached_text=? WHERE id=?")->execute([$allText, $id]);
                $cnt = save_book_chunks($id, $allText);
                $msg = '✓ «' . e($row['title']) . '»: ' . num_fa(number_format(mb_strlen($allText,'UTF-8'))) . ' کاراکتر → ' . num_fa($cnt) . ' بخش';
                if (!empty($errors)) $msg .= '<br>⚠ ' . implode(' | ', $errors);
            } else {
                $msg = '❌ استخراج ناموفق. ' . implode(' | ', $errors); $msgType = 'error';
            }
        }

    // --- افزودن فایل به کتاب موجود ---
    } elseif (isset($_POST['append_to_id'])) {
        $id = (int)$_POST['append_to_id'];
        $r = db()->prepare("SELECT * FROM books WHERE id=?"); $r->execute([$id]); $row = $r->fetch();

        if (!$row) { $msg = '❌ کتاب یافت نشد.'; $msgType = 'error'; }
        elseif (empty($_FILES['append_files']['tmp_name'][0])) { $msg = '❌ فایلی انتخاب نشده.'; $msgType = 'error'; }
        else {
            if (!is_dir(BOOKS_PATH)) mkdir(BOOKS_PATH, 0755, true);
            $existingFiles = array_filter(explode(',', $row['file_names'] ?? $row['file_name']));
            $newText = '';
            $newFiles = [];
            $errors = [];

            foreach ($_FILES['append_files']['tmp_name'] as $i => $tmp) {
                if (empty($tmp)) continue;
                $origName = $_FILES['append_files']['name'][$i] ?? 'file.pdf';
                if (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'pdf') { $errors[] = $origName . ': فقط PDF'; continue; }
                if ($_FILES['append_files']['size'][$i] > 30 * 1024 * 1024) { $errors[] = $origName . ': بیش از ۳۰ مگ'; continue; }

                $fn = 'book_' . time() . '_' . bin2hex(random_bytes(3)) . '_p' . ($i+1) . '.pdf';
                move_uploaded_file($tmp, BOOKS_PATH . '/' . $fn);
                $newFiles[] = $fn;

                $res = extract_book_content_with_ai(BOOKS_PATH . '/' . $fn);
                if ($res['ok']) $newText .= "\n\n---\n\n" . $res['text'];
                else $errors[] = $origName . ': ' . $res['error'];
            }

            if (!empty($newFiles)) {
                $allFiles = array_merge($existingFiles, $newFiles);
                db()->prepare("UPDATE books SET file_names=? WHERE id=?")->execute([implode(',', $allFiles), $id]);
                if (mb_strlen(trim($newText), 'UTF-8') > 50) {
                    $cnt = append_book_content($id, trim($newText));
                    $msg = '✓ ' . num_fa(count($newFiles)) . ' فایل اضافه شد → ' . num_fa($cnt) . ' بخش.';
                    if (!empty($errors)) $msg .= '<br>⚠ ' . implode(' | ', $errors);
                } else {
                    $msg = '⚠ فایل‌ها ذخیره شدند ولی استخراج ناموفق.'; $msgType = 'info';
                }
            } else { $msg = '❌ آپلود ناموفق.'; $msgType = 'error'; }
        }

    // --- کتاب جدید ---
    } else {
        $title   = trim($_POST['title'] ?? '');
        $grade   = (int)($_POST['grade'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');

        // Debug: اگه فیلدها خالی بودن، نشون بده چرا
        if (!$title && !$grade && !$subject && DEV_MODE) {
            $debugInfo = 'POST keys: ' . implode(', ', array_keys($_POST));
            $debugInfo .= ' | FILES keys: ' . implode(', ', array_keys($_FILES ?? []));
            $debugInfo .= ' | Content-Length: ' . ($_SERVER['CONTENT_LENGTH'] ?? 'N/A');
            $debugInfo .= ' | post_max_size: ' . ini_get('post_max_size');
            $debugInfo .= ' | upload_max_filesize: ' . ini_get('upload_max_filesize');
            error_log('[Daneshyar Books] Empty form submit debug: ' . $debugInfo);
        }
        $selectedMajors = $_POST['majors'] ?? [];
        if (empty($selectedMajors)) $selectedMajors = ['all'];
        $validMajors = array_keys(book_major_options());
        $selectedMajors = array_filter($selectedMajors, fn($m) => in_array($m, $validMajors, true));
        if (empty($selectedMajors)) $selectedMajors = ['all'];
        $majorsStr = implode(',', $selectedMajors);
        $primaryMajor = in_array('all', $selectedMajors) ? 'all' : $selectedMajors[0];

        if (!$title || !$grade || !$subject) {
            // تشخیص علت: آیا به دلیل محدودیت حجم آپلود بوده؟
            $contentLen = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
            $postMax = return_bytes(ini_get('post_max_size'));
            if ($contentLen > 0 && $postMax > 0 && $contentLen > $postMax) {
                $msg = "❌ حجم درخواست ({$contentLen} بایت) از محدودیت سرور (" . ini_get('post_max_size') . ") بیشتر است.<br>"
                     . "📋 <b>راه‌حل:</b> در cPanel → Select PHP Version → Options → مقدار <code>post_max_size</code> رو به <b>200M</b> و <code>upload_max_filesize</code> رو به <b>50M</b> تغییر بده.";
            } else {
                $msg = '❌ عنوان، پایه و درس الزامی هستند. لطفاً همه فیلدها رو پر کن.';
            }
            $msgType = 'error';
        }
        elseif (empty($_FILES['files']['tmp_name'][0])) { $msg = '❌ حداقل یک فایل PDF.'; $msgType = 'error'; }
        else {
            if (!is_dir(BOOKS_PATH)) mkdir(BOOKS_PATH, 0755, true);
            $fileNames = [];
            $allText = '';
            $errors = [];

            foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
                if (empty($tmp)) continue;
                $origName = $_FILES['files']['name'][$i] ?? 'file.pdf';
                if (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'pdf') { $errors[] = $origName . ': فقط PDF'; continue; }
                if ($_FILES['files']['size'][$i] > 30 * 1024 * 1024) { $errors[] = $origName . ': بیش از ۳۰ مگ'; continue; }

                $fn = 'book_' . time() . '_' . bin2hex(random_bytes(3)) . '_p' . ($i+1) . '.pdf';
                move_uploaded_file($tmp, BOOKS_PATH . '/' . $fn);
                $fileNames[] = $fn;

                $res = extract_book_content_with_ai(BOOKS_PATH . '/' . $fn);
                if ($res['ok']) $allText .= "\n\n---\n\n" . $res['text'];
                else $errors[] = $origName . ': ' . $res['error'];
            }

            if (empty($fileNames)) { $msg = '❌ فایل معتبری آپلود نشد.'; $msgType = 'error'; }
            else {
                $allText = trim($allText);
                if (function_exists('sanitize_utf8') && $allText) $allText = sanitize_utf8($allText);

                db()->prepare("INSERT INTO books (title,grade,subject,major,majors,file_name,file_names,cached_text) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$title, $grade, $subject, $primaryMajor, $majorsStr, $fileNames[0], implode(',', $fileNames), $allText]);
                $bid = (int)db()->lastInsertId();

                $cc = 0;
                if (mb_strlen($allText, 'UTF-8') >= 100) $cc = save_book_chunks($bid, $allText);

                $tl = mb_strlen($allText, 'UTF-8');
                if ($tl > 100 && $cc > 0) {
                    $msg = '✓ کتاب اضافه شد! ' . num_fa(count($fileNames)) . ' فایل → ' . num_fa(number_format($tl)) . ' کاراکتر → ' . num_fa($cc) . ' بخش';
                } elseif ($tl > 100) {
                    $msg = '✓ کتاب ذخیره شد (' . num_fa(number_format($tl)) . ' کاراکتر).'; $msgType = 'info';
                } else {
                    $msg = '⚠ فایل‌ها ذخیره شدند ولی استخراج ناموفق. دکمه «استخراج» رو بزنید.'; $msgType = 'info';
                }
                if (!empty($errors)) $msg .= '<br>⚠ ' . implode(' | ', $errors);
            }
        }
    }
}

$books = db()->query("SELECT id, title, grade, subject, major, majors, file_name, file_names, LENGTH(COALESCE(cached_text,'')) as text_len, COALESCE(chunks_count,0) as chunks_count, created_at FROM books ORDER BY grade, subject")->fetchAll();
?>

<h2 style="margin-bottom:14px"><?= icon('book') ?> مدیریت کتاب‌ها</h2>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType==='error'?'error':($msgType==='info'?'info':'success') ?>"><?= $msg ?></div>
<?php endif; ?>

<!-- فرم افزودن -->
<div class="glass" style="padding:20px; margin-bottom:20px">
  <h3 style="color:var(--orange); margin-bottom:14px"><?= icon('plus') ?> افزودن کتاب جدید</h3>
  <form method="post" enctype="multipart/form-data" id="bf">
    <div class="book-form-grid">
      <div class="form-group" style="margin:0">
        <label class="form-label">عنوان</label>
        <input class="input" name="title" required placeholder="مثلاً: دین و زندگی دهم">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">پایه</label>
        <select class="select" name="grade" required>
          <?php for($g=7;$g<=12;$g++):?><option value="<?=$g?>">پایه <?=num_fa($g)?></option><?php endfor;?>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">درس</label>
        <input class="input" name="subject" required placeholder="مثلاً: دین و زندگی">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">رشته‌ها</label>
        <div class="major-checks">
          <?php foreach(book_major_options() as $c => $l): ?>
          <label class="mck"><input type="checkbox" name="majors[]" value="<?=e($c)?>" <?= $c==='all'?'checked':'' ?>><span><?=e($l)?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">PDF <small>(چندتایی)</small></label>
        <input class="input" name="files[]" type="file" accept=".pdf" multiple required>
      </div>
      <div style="display:flex;align-items:end">
        <button class="btn btn-primary" type="submit" id="ub"><?= icon('upload') ?> آپلود</button>
      </div>
    </div>
  </form>
  <p style="margin-top:10px;font-size:12px;color:var(--text-dim)">
    <?= icon('sparkle') ?> PDF به AI فرستاده می‌شه و خلاصه ساختاریافته (سرفصل‌ها + نکات + آیات) ذخیره می‌شه. ممکنه ۱-۳ دقیقه طول بکشه.
  </p>
</div>

<style>
.book-form-grid{display:grid;grid-template-columns:1.5fr .7fr 1fr 1.5fr 1.5fr auto;gap:12px;align-items:start}
@media(max-width:1200px){.book-form-grid{grid-template-columns:1fr 1fr}}
@media(max-width:700px){.book-form-grid{grid-template-columns:1fr}}
.major-checks{display:flex;flex-wrap:wrap;gap:5px}
.mck{display:flex;align-items:center;gap:3px;padding:3px 8px;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:7px;font-size:12px;cursor:pointer}
.mck:has(input:checked){background:rgba(235,124,42,.12);border-color:var(--orange);color:var(--orange)}
.mck input{width:13px;height:13px}
.major-tag{padding:1px 6px;background:rgba(235,124,42,.1);border-radius:5px;font-size:11px;color:var(--orange);font-weight:600}
.file-tags{display:flex;flex-wrap:wrap;gap:3px;margin-top:4px}
.file-tag{padding:1px 6px;background:rgba(75,171,247,.08);border:1px solid rgba(75,171,247,.15);border-radius:5px;font-size:10px;color:#4dabf7}
</style>

<!-- بازسازی همه -->
<div style="display:flex;justify-content:flex-end;margin-bottom:10px">
  <form method="post"><input type="hidden" name="rechunk_all" value="1"><button class="btn btn-ghost btn-sm" onclick="return confirm('بازسازی بخش‌ها برای همه؟')"><?= icon('refresh') ?> بازسازی همه</button></form>
</div>

<!-- لیست -->
<div style="overflow-x:auto"><table class="admin-table">
<thead><tr><th>#</th><th>عنوان</th><th>پایه</th><th>رشته</th><th>درس</th><th>فایل</th><th>محتوا</th><th>بخش</th><th>عملیات</th></tr></thead>
<tbody>
<?php foreach($books as $b):
  $tl=(int)$b['text_len']; $cc=(int)($b['chunks_count']??0);
  $files=array_filter(explode(',', $b['file_names']??$b['file_name']));
  $majors=array_filter(explode(',', $b['majors']??$b['major']??'all'));
?>
<tr>
  <td><?=num_fa($b['id'])?></td>
  <td><b><?=e($b['title'])?></b></td>
  <td>پایه <?=num_fa($b['grade'])?></td>
  <td><div style="display:flex;flex-wrap:wrap;gap:2px"><?php foreach($majors as $m):?><span class="major-tag"><?=e(major_label(trim($m)))?></span><?php endforeach;?></div></td>
  <td><?=e($b['subject'])?></td>
  <td><b><?=num_fa(count($files))?></b></td>
  <td><?php if($tl>100):?><span style="color:var(--success)">✓ <?=num_fa(number_format($tl))?></span><?php else:?><span style="color:var(--danger)">✗</span><?php endif;?></td>
  <td><?php if($cc>0):?><span style="color:var(--success);font-weight:700"><?=num_fa($cc)?></span><?php else:?>—<?php endif;?></td>
  <td>
    <div style="display:flex;gap:3px;flex-wrap:wrap">
      <form method="post" style="display:inline" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='⏳'">
        <input type="hidden" name="extract_id" value="<?=$b['id']?>">
        <button class="btn btn-sm" onclick="return confirm('استخراج خلاصه با AI؟ (۱-۳ دقیقه)')" style="background:rgba(75,171,247,.15);color:#4dabf7;border:1px solid rgba(75,171,247,.3);font-size:11px"><?=icon('sparkle')?> AI</button>
      </form>
      <form method="post" style="display:inline"><input type="hidden" name="rechunk_id" value="<?=$b['id']?>"><button class="btn btn-ghost btn-sm" onclick="return confirm('بازسازی؟')" style="font-size:11px"><?=icon('refresh')?></button></form>
      <a href="<?=BASE_URL?>/books/<?=e(trim($files[0]??''))?>" target="_blank" class="btn btn-ghost btn-sm" style="font-size:11px"><?=icon('pdf')?></a>
      <form method="post" style="display:inline"><input type="hidden" name="delete_id" value="<?=$b['id']?>"><button class="btn btn-danger btn-sm" onclick="return confirm('حذف؟')" style="font-size:11px"><?=icon('trash')?></button></form>
    </div>
    <details style="margin-top:5px"><summary style="font-size:11px;color:var(--text-dim);cursor:pointer"><?=icon('plus')?> افزودن فایل</summary>
      <form method="post" enctype="multipart/form-data" style="margin-top:5px;display:flex;gap:5px;align-items:end" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('button[type=submit]').innerHTML='⏳'">
        <input type="hidden" name="append_to_id" value="<?=$b['id']?>">
        <input type="file" name="append_files[]" accept=".pdf" multiple style="font-size:11px;max-width:180px" required>
        <button type="submit" class="btn btn-sm" style="font-size:11px;background:rgba(56,217,169,.12);color:#38d9a9;border:1px solid rgba(56,217,169,.25)"><?=icon('upload')?></button>
      </form>
    </details>
    <?php if(count($files)>1):?><div class="file-tags"><?php foreach($files as $fi=>$fn):?><span class="file-tag">بخش <?=num_fa($fi+1)?></span><?php endforeach;?></div><?php endif;?>
  </td>
</tr>
<?php endforeach; if(!$books):?><tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-dim)">هنوز کتابی اضافه نشده.</td></tr><?php endif;?>
</tbody></table></div>

<script>
document.getElementById('bf')?.addEventListener('submit',function(){var b=document.getElementById('ub');if(b){b.disabled=true;b.innerHTML='⏳ در حال پردازش...';}});
document.querySelectorAll('input[name="majors[]"]').forEach(function(cb){
  cb.addEventListener('change',function(){
    if(this.value==='all'&&this.checked){document.querySelectorAll('input[name="majors[]"]').forEach(function(o){if(o.value!=='all')o.checked=false;});}
    else if(this.value!=='all'&&this.checked){var a=document.querySelector('input[name="majors[]"][value="all"]');if(a)a.checked=false;}
    if(!Array.from(document.querySelectorAll('input[name="majors[]"]')).some(c=>c.checked)){var a=document.querySelector('input[name="majors[]"][value="all"]');if(a)a.checked=true;}
  });
});
</script>
<?php include __DIR__ . '/_footer.php'; ?>
