<?php
/**
 *  دانش‌یار – مدیریت کتاب‌ها v4
 *  - دو حالت استخراج: دقیق (نزدیک به متن کتاب) | خلاصه (سبک)
 *  - ویرایش اطلاعات کتاب (عنوان، پایه، درس، رشته‌ها)
 *  - چیدمان مرتب‌تر
 *  سازگار با cPanel (فقط PHP)
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

try {
    $col = db()->query("SHOW COLUMNS FROM books LIKE 'extract_mode'")->fetch();
    if (!$col) db()->exec("ALTER TABLE books ADD COLUMN `extract_mode` VARCHAR(12) NOT NULL DEFAULT 'summary'");
} catch (Throwable $e) {}

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

function normalize_extract_mode($m) {
    return ($m === 'detailed') ? 'detailed' : 'summary';
}

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $maxPost = ini_get('post_max_size');
    $maxUpload = ini_get('upload_max_filesize');
    $msg = "❌ حجم فایل‌ها بیش از حد مجاز سرور است.<br>محدودیت فعلی: post_max_size=<b>{$maxPost}</b> | upload_max_filesize=<b>{$maxUpload}</b>";
    $msgType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {

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

    } elseif (isset($_POST['edit_id'])) {
        // ─── ویرایش اطلاعات کتاب ───
        $id = (int)$_POST['edit_id'];
        $title   = trim($_POST['title'] ?? '');
        $grade   = (int)($_POST['grade'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $selectedMajors = $_POST['majors'] ?? [];
        if (empty($selectedMajors)) $selectedMajors = ['all'];
        $validMajors = array_keys(book_major_options());
        $selectedMajors = array_filter($selectedMajors, fn($m) => in_array($m, $validMajors, true));
        if (empty($selectedMajors)) $selectedMajors = ['all'];
        $majorsStr = implode(',', $selectedMajors);
        $primaryMajor = in_array('all', $selectedMajors) ? 'all' : $selectedMajors[0];

        if (!$title || $grade < 7 || $grade > 12 || !$subject) {
            $msg = '❌ عنوان، پایه و درس را درست وارد کن.'; $msgType = 'error';
        } else {
            db()->prepare("UPDATE books SET title=?, grade=?, subject=?, major=?, majors=? WHERE id=?")
                ->execute([$title, $grade, $subject, $primaryMajor, $majorsStr, $id]);
            $msg = '✓ اطلاعات کتاب ویرایش شد.';
        }

    } elseif (isset($_POST['rechunk_id'])) {
        $id = (int)$_POST['rechunk_id'];
        $r = db()->prepare("SELECT * FROM books WHERE id=?"); $r->execute([$id]); $row = $r->fetch();
        if ($row && mb_strlen($row['cached_text'] ?? '', 'UTF-8') > 100) {
            $cnt = save_book_chunks($id, $row['cached_text']);
            $msg = '✓ «' . e($row['title']) . '» → ' . num_fa($cnt) . ' بخش.';
        } else { $msg = '⚠ ابتدا محتوا را استخراج کنید.'; $msgType = 'info'; }

    } elseif (isset($_POST['rechunk_all'])) {
        $all = db()->query("SELECT id, cached_text FROM books WHERE LENGTH(COALESCE(cached_text,'')) > 100")->fetchAll();
        $t = 0; foreach ($all as $a) $t += save_book_chunks((int)$a['id'], $a['cached_text']);
        $msg = '✓ ' . num_fa(count($all)) . ' کتاب → ' . num_fa($t) . ' بخش.';

    } elseif (isset($_POST['extract_id'])) {
        $id = (int)$_POST['extract_id'];
        $extractMode = normalize_extract_mode($_POST['extract_mode'] ?? '');
        $r = db()->prepare("SELECT * FROM books WHERE id=?"); $r->execute([$id]); $row = $r->fetch();
        if ($row) {
            // اگر حالت در فرم نیامده، از حالت ذخیره‌شده کتاب استفاده کن
            if (empty($_POST['extract_mode'])) $extractMode = normalize_extract_mode($row['extract_mode'] ?? 'summary');
            $files = array_filter(explode(',', $row['file_names'] ?? $row['file_name']));
            $allText = '';
            $errors = [];
            foreach ($files as $fn) {
                $pdf = BOOKS_PATH . '/' . trim($fn);
                if (!is_file($pdf)) { $errors[] = trim($fn) . ' یافت نشد'; continue; }
                $res = extract_book_content_with_ai($pdf, $extractMode);
                if ($res['ok']) {
                    $allText .= "\n\n---\n\n" . $res['text'];
                } else {
                    $errors[] = trim($fn) . ': ' . $res['error'];
                }
            }
            if (mb_strlen(trim($allText), 'UTF-8') > 100) {
                $allText = trim($allText);
                if (function_exists('sanitize_utf8')) $allText = sanitize_utf8($allText);
                db()->prepare("UPDATE books SET cached_text=?, extract_mode=? WHERE id=?")->execute([$allText, $extractMode, $id]);
                $cnt = save_book_chunks($id, $allText);
                $modeLabel = $extractMode === 'detailed' ? 'دقیق' : 'خلاصه';
                $msg = '✓ «' . e($row['title']) . '» (حالت ' . $modeLabel . '): ' . num_fa(number_format(mb_strlen($allText,'UTF-8'))) . ' کاراکتر → ' . num_fa($cnt) . ' بخش';
                if (!empty($errors)) $msg .= '<br>⚠ ' . implode(' | ', $errors);
            } else {
                $msg = '❌ استخراج ناموفق. ' . implode(' | ', $errors); $msgType = 'error';
            }
        }

    } elseif (isset($_POST['append_to_id'])) {
        $id = (int)$_POST['append_to_id'];
        $r = db()->prepare("SELECT * FROM books WHERE id=?"); $r->execute([$id]); $row = $r->fetch();

        if (!$row) { $msg = '❌ کتاب یافت نشد.'; $msgType = 'error'; }
        elseif (empty($_FILES['append_files']['tmp_name'][0])) { $msg = '❌ فایلی انتخاب نشده.'; $msgType = 'error'; }
        else {
            $extractMode = normalize_extract_mode($row['extract_mode'] ?? 'summary');
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

                $res = extract_book_content_with_ai(BOOKS_PATH . '/' . $fn, $extractMode);
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

    } else {
        // ─── افزودن کتاب جدید ───
        $title   = trim($_POST['title'] ?? '');
        $grade   = (int)($_POST['grade'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $extractMode = normalize_extract_mode($_POST['extract_mode'] ?? 'summary');
        $selectedMajors = $_POST['majors'] ?? [];
        if (empty($selectedMajors)) $selectedMajors = ['all'];
        $validMajors = array_keys(book_major_options());
        $selectedMajors = array_filter($selectedMajors, fn($m) => in_array($m, $validMajors, true));
        if (empty($selectedMajors)) $selectedMajors = ['all'];
        $majorsStr = implode(',', $selectedMajors);
        $primaryMajor = in_array('all', $selectedMajors) ? 'all' : $selectedMajors[0];

        if (!$title || !$grade || !$subject) {
            $contentLen = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
            $postMax = return_bytes(ini_get('post_max_size'));
            if ($contentLen > 0 && $postMax > 0 && $contentLen > $postMax) {
                $msg = "❌ حجم درخواست ({$contentLen} بایت) از محدودیت سرور ({$postMax}) بیشتر است.";
            } else {
                $msg = '❌ عنوان، پایه و درس الزامی هستند.';
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

                $res = extract_book_content_with_ai(BOOKS_PATH . '/' . $fn, $extractMode);
                if ($res['ok']) $allText .= "\n\n---\n\n" . $res['text'];
                else $errors[] = $origName . ': ' . $res['error'];
            }

            if (empty($fileNames)) { $msg = '❌ فایل معتبری آپلود نشد.'; $msgType = 'error'; }
            else {
                $allText = trim($allText);
                if (function_exists('sanitize_utf8') && $allText) $allText = sanitize_utf8($allText);

                db()->prepare("INSERT INTO books (title,grade,subject,major,majors,file_name,file_names,cached_text,extract_mode) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$title, $grade, $subject, $primaryMajor, $majorsStr, $fileNames[0], implode(',', $fileNames), $allText, $extractMode]);
                $bid = (int)db()->lastInsertId();

                $cc = 0;
                if (mb_strlen($allText, 'UTF-8') >= 100) $cc = save_book_chunks($bid, $allText);

                $tl = mb_strlen($allText, 'UTF-8');
                $modeLabel = $extractMode === 'detailed' ? 'دقیق' : 'خلاصه';
                if ($tl > 100 && $cc > 0) {
                    $msg = '✓ کتاب اضافه شد (حالت ' . $modeLabel . ')! ' . num_fa(count($fileNames)) . ' فایل → ' . num_fa(number_format($tl)) . ' کاراکتر → ' . num_fa($cc) . ' بخش';
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

$books = db()->query("SELECT id, title, grade, subject, major, majors, file_name, file_names, COALESCE(extract_mode,'summary') as extract_mode, LENGTH(COALESCE(cached_text,'')) as text_len, COALESCE(chunks_count,0) as chunks_count, created_at FROM books ORDER BY grade, subject")->fetchAll();

// گروه‌بندی بر اساس پایه برای نمایش مرتب
$booksByGrade = [];
foreach ($books as $b) $booksByGrade[(int)$b['grade']][] = $b;
ksort($booksByGrade);
$majorOptions = book_major_options();
?>

<style>
  .bk-modes{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
  .bk-mode{flex:1;min-width:200px;display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border:1px solid var(--border);
    border-radius:12px;cursor:pointer;background:rgba(255,255,255,.02);transition:.15s}
  .bk-mode:hover{border-color:var(--orange)}
  .bk-mode input{margin-top:3px;accent-color:var(--orange);width:16px;height:16px}
  .bk-mode b{display:block;font-size:13px;margin-bottom:3px}
  .bk-mode small{color:var(--text-dim);font-size:11px;line-height:1.6}
  .bk-mode.is-on{border-color:var(--orange);background:rgba(235,124,42,.07)}
  .bk-grade-head{display:flex;align-items:center;gap:8px;margin:22px 0 10px;font-weight:800;font-size:15px}
  .bk-grade-head .bk-count{background:rgba(235,124,42,.12);color:var(--orange);font-size:11px;padding:2px 9px;border-radius:20px}
  .bk-mode-badge{padding:2px 8px;border-radius:5px;font-size:10px;font-weight:700}
  .bk-mode-badge.detailed{background:rgba(56,217,169,.12);color:#38d9a9}
  .bk-mode-badge.summary{background:rgba(75,171,247,.12);color:#4dabf7}
  .mck-item.is-on{border-color:var(--orange)!important;background:rgba(235,124,42,.08)!important}
</style>

<div class="admin-page-header">
  <h2><?= icon('book') ?> مدیریت کتاب‌ها</h2>
  <form method="post" style="margin:0">
    <input type="hidden" name="rechunk_all" value="1">
    <button class="btn btn-ghost btn-sm" onclick="return confirm('بازسازی بخش‌ها برای همه؟')">
      <?= icon('refresh') ?> بازسازی همه بخش‌ها
    </button>
  </form>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType==='error'?'error':($msgType==='info'?'info':'success') ?>"><?= $msg ?></div>
<?php endif; ?>

<!-- ═══ فرم افزودن ═══ -->
<div class="admin-card glass" style="margin-bottom:20px">
  <div class="admin-card-header">
    <h3><?= icon('plus') ?> افزودن کتاب جدید</h3>
  </div>
  <div class="admin-card-body">
    <form method="post" enctype="multipart/form-data" id="bf">
      <div class="admin-form-grid">
        <div class="form-group" style="margin:0">
          <label class="form-label">عنوان</label>
          <input class="input" name="title" required placeholder="مثلاً: جغرافیای ایران دهم">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">پایه</label>
          <select class="select" name="grade" required>
            <?php for($g=7;$g<=12;$g++):?><option value="<?=$g?>">پایه <?=num_fa($g)?></option><?php endfor;?>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">درس</label>
          <input class="input" name="subject" required placeholder="مثلاً: جغرافیا">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">رشته‌ها</label>
          <div style="display:flex;flex-wrap:wrap;gap:5px">
            <?php foreach($majorOptions as $c => $l): ?>
            <label class="mck-item <?= $c==='all'?'is-on':'' ?>" style="display:flex;align-items:center;gap:4px;padding:4px 10px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;font-size:12px;cursor:pointer;transition:.2s">
              <input type="checkbox" name="majors[]" value="<?=e($c)?>" <?= $c==='all'?'checked':'' ?> style="width:14px;height:14px;accent-color:var(--orange)">
              <span><?=e($l)?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">PDF <small>(چندتایی)</small></label>
          <input class="input" name="files[]" type="file" accept=".pdf" multiple required>
        </div>
        <div style="display:flex;align-items:end">
          <button class="btn btn-primary" type="submit" id="ub" style="width:100%"><?= icon('upload') ?> آپلود و استخراج</button>
        </div>
      </div>

      <!-- انتخاب حالت استخراج -->
      <div class="form-group" style="margin:16px 0 0">
        <label class="form-label">حالت استخراج محتوا</label>
        <div class="bk-modes">
          <label class="bk-mode" data-mode-card>
            <input type="radio" name="extract_mode" value="summary" checked>
            <span>
              <b><?= icon('flash') ?> خلاصه (پیش‌فرض)</b>
              <small>خلاصهٔ ساختاریافته و سبک از کتاب. سریع‌تر، حجم کمتر، مناسب اکثر امتحان‌ها و هاست‌های ضعیف.</small>
            </span>
          </label>
          <label class="bk-mode" data-mode-card>
            <input type="radio" name="extract_mode" value="detailed">
            <span>
              <b><?= icon('star') ?> دقیق (نزدیک به متن کتاب)</b>
              <small>متن کامل و وفادار به خودِ کتاب؛ جواب‌ها تقریباً عینِ کتاب می‌شوند. دقیق‌تر ولی سنگین‌تر و کندتر.</small>
            </span>
          </label>
        </div>
      </div>
    </form>
    <p style="margin-top:12px;font-size:12px;color:var(--text-dim)">
      <?= icon('sparkle') ?> PDF به هوش مصنوعی فرستاده می‌شه و محتوای آن ذخیره می‌شه. ممکنه ۱ تا ۳ دقیقه طول بکشه (حالت دقیق بیشتر).
    </p>
    <p style="margin-top:8px;font-size:12px;color:#f0a050;background:rgba(235,124,42,.08);border:1px solid rgba(235,124,42,.2);border-radius:8px;padding:8px 12px">
      <?= icon('warning') ?> <b>برای حالت «دقیق»:</b> اگر کتاب بزرگ است، آن را تکه‌تکه آپلود کن (مثلاً هر فصل یا هر ۲ تا ۳ درس یک PDF جدا). چون اگر کل کتاب در یک فایل باشد، هوش مصنوعی درس‌های آخر را خلاصه می‌کند و جزئیات از دست می‌رود. هر فایل جداگانه با دقت کامل استخراج می‌شود.
    </p>
  </div>
</div>

<!-- ═══ لیست کتاب‌ها (گروه‌بندی بر اساس پایه) ═══ -->
<?php if (!$books): ?>
  <div class="admin-card glass"><div class="admin-card-body admin-empty">هنوز کتابی اضافه نشده.</div></div>
<?php else: foreach ($booksByGrade as $grade => $gradeBooks): ?>
  <div class="bk-grade-head">
    <?= icon('book') ?> پایه <?= num_fa($grade) ?>
    <span class="bk-count"><?= num_fa(count($gradeBooks)) ?> کتاب</span>
  </div>
  <div class="admin-card glass" style="margin-bottom:8px">
    <div class="admin-card-body" style="padding:0">
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>#</th><th>عنوان</th><th>درس</th><th>رشته</th><th>حالت</th><th>فایل</th><th>محتوا</th><th>بخش</th><th>عملیات</th></tr>
          </thead>
          <tbody>
          <?php foreach($gradeBooks as $b):
            $tl=(int)$b['text_len']; $cc=(int)($b['chunks_count']??0);
            $files=array_filter(explode(',', $b['file_names']??$b['file_name']));
            $majors=array_filter(explode(',', $b['majors']??$b['major']??'all'));
            $bmode = ($b['extract_mode']==='detailed')?'detailed':'summary';
          ?>
          <tr>
            <td class="text-muted"><?=num_fa($b['id'])?></td>
            <td><b><?=e($b['title'])?></b></td>
            <td><?=e($b['subject'])?></td>
            <td>
              <div style="display:flex;flex-wrap:wrap;gap:3px">
                <?php foreach($majors as $m):?>
                <span style="padding:2px 8px;background:rgba(235,124,42,.08);border-radius:5px;font-size:10px;color:var(--orange);font-weight:600"><?=e(major_label(trim($m)))?></span>
                <?php endforeach;?>
              </div>
            </td>
            <td>
              <span class="bk-mode-badge <?=$bmode?>"><?= $bmode==='detailed'?'دقیق':'خلاصه' ?></span>
            </td>
            <td><b><?=num_fa(count($files))?></b></td>
            <td>
              <?php if($tl>100):?><span class="text-success" style="font-weight:700">✓ <?=num_fa(number_format($tl))?></span><?php else:?><span class="text-danger">✗</span><?php endif;?>
            </td>
            <td>
              <?php if($cc>0):?><span class="text-success" style="font-weight:700"><?=num_fa($cc)?></span><?php else:?><span class="text-muted">—</span><?php endif;?>
            </td>
            <td>
              <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
                <button type="button" class="btn btn-ghost btn-sm" onclick="bkToggleEdit(<?=$b['id']?>)" title="ویرایش اطلاعات"><?=icon('edit')?></button>

                <!-- استخراج خلاصه (یک‌مرحله‌ای، سبک) -->
                <form method="post" style="display:inline-flex" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('button[type=submit]').innerHTML='⏳'">
                  <input type="hidden" name="extract_id" value="<?=$b['id']?>">
                  <input type="hidden" name="extract_mode" value="summary">
                  <button type="submit" class="btn btn-sm" onclick="return confirm('استخراج خلاصه با هوش مصنوعی؟ (۱-۳ دقیقه)')" style="background:rgba(75,171,247,.12);color:#4dabf7;border:1px solid rgba(75,171,247,.25)"><?=icon('flash')?> خلاصه</button>
                </form>

                <!-- استخراج دقیقِ تکه‌تکه (هوشمند، گام‌به‌گام) -->
                <button type="button" class="btn btn-sm bk-precise-btn"
                        data-book-id="<?=$b['id']?>"
                        style="background:rgba(56,217,169,.12);color:#38d9a9;border:1px solid rgba(56,217,169,.25)"
                        title="استخراج دقیق و کامل، تکه‌تکه (هیچ درسی خلاصه نمی‌شود)"><?=icon('star')?> دقیق (تکه‌تکه)</button>

                <form method="post" style="display:inline">
                  <input type="hidden" name="rechunk_id" value="<?=$b['id']?>">
                  <button class="btn btn-ghost btn-sm" onclick="return confirm('بازسازی بخش‌ها؟')" title="بازسازی بخش‌ها"><?=icon('refresh')?></button>
                </form>
                <a href="<?=BASE_URL?>/books/<?=e(trim($files[0]??''))?>" target="_blank" class="btn btn-ghost btn-sm" title="مشاهده PDF"><?=icon('pdf')?></a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="delete_id" value="<?=$b['id']?>">
                  <button class="btn btn-sm" style="background:rgba(255,84,112,.08);color:var(--danger);border:1px solid rgba(255,84,112,.2)" onclick="return confirm('حذف کتاب؟')" title="حذف"><?=icon('trash')?></button>
                </form>
              </div>

              <!-- فرم ویرایش (مخفی) -->
              <div id="bkEdit<?=$b['id']?>" style="display:none;margin-top:10px;padding:12px;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:10px">
                <form method="post" style="display:flex;flex-direction:column;gap:8px">
                  <input type="hidden" name="edit_id" value="<?=$b['id']?>">
                  <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <div class="form-group" style="margin:0;flex:1;min-width:140px">
                      <label class="form-label" style="font-size:11px">عنوان</label>
                      <input class="input" name="title" value="<?=e($b['title'])?>" required style="font-size:12px">
                    </div>
                    <div class="form-group" style="margin:0;width:110px">
                      <label class="form-label" style="font-size:11px">پایه</label>
                      <select class="select" name="grade" style="font-size:12px">
                        <?php for($g=7;$g<=12;$g++):?><option value="<?=$g?>" <?=$g==(int)$b['grade']?'selected':''?>>پایه <?=num_fa($g)?></option><?php endfor;?>
                      </select>
                    </div>
                    <div class="form-group" style="margin:0;flex:1;min-width:140px">
                      <label class="form-label" style="font-size:11px">درس</label>
                      <input class="input" name="subject" value="<?=e($b['subject'])?>" required style="font-size:12px">
                    </div>
                  </div>
                  <div>
                    <label class="form-label" style="font-size:11px">رشته‌ها</label>
                    <div style="display:flex;flex-wrap:wrap;gap:5px">
                      <?php foreach($majorOptions as $c => $l): $on = in_array($c, array_map('trim',$majors), true); ?>
                      <label class="mck-item <?=$on?'is-on':''?>" style="display:flex;align-items:center;gap:4px;padding:4px 10px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;font-size:11px;cursor:pointer">
                        <input type="checkbox" name="majors[]" value="<?=e($c)?>" <?=$on?'checked':''?> style="width:13px;height:13px;accent-color:var(--orange)">
                        <span><?=e($l)?></span>
                      </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <div style="display:flex;gap:6px">
                    <button type="submit" class="btn btn-primary btn-sm"><?=icon('check')?> ذخیره ویرایش</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="bkToggleEdit(<?=$b['id']?>)">انصراف</button>
                  </div>
                </form>
              </div>

              <details style="margin-top:8px">
                <summary style="font-size:12px;color:var(--text-dim);cursor:pointer;padding:4px 0"><?=icon('plus') ?> افزودن فایل به این کتاب</summary>
                <form method="post" enctype="multipart/form-data" style="margin-top:8px;display:flex;gap:6px;align-items:end;flex-wrap:wrap" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('button[type=submit]').innerHTML='⏳'">
                  <input type="hidden" name="append_to_id" value="<?=$b['id']?>">
                  <input type="file" name="append_files[]" accept=".pdf" multiple style="font-size:12px;max-width:200px" required>
                  <button type="submit" class="btn btn-sm" style="background:rgba(56,217,169,.12);color:#38d9a9;border:1px solid rgba(56,217,169,.25)"><?=icon('upload')?> افزودن</button>
                  <small style="color:var(--text-dim);width:100%">با همان حالت «<?= $bmode==='detailed'?'دقیق':'خلاصه' ?>» استخراج می‌شود.</small>
                </form>
              </details>

              <?php if(count($files)>1):?>
              <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:6px">
                <?php foreach($files as $fi=>$fn):?>
                <span style="padding:1px 7px;background:rgba(75,171,247,.06);border:1px solid rgba(75,171,247,.15);border-radius:5px;font-size:10px;color:#4dabf7">بخش <?=num_fa($fi+1)?></span>
                <?php endforeach;?>
              </div>
              <?php endif;?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endforeach; endif; ?>

<script>
document.getElementById('bf')?.addEventListener('submit',function(){var b=document.getElementById('ub');if(b){b.disabled=true;b.innerHTML='⏳ در حال پردازش...';}});

// تیک رشته‌ها (همه/تکی)
function bindMajorChecks(scope){
  (scope||document).querySelectorAll('input[name="majors[]"]').forEach(function(cb){
    cb.addEventListener('change',function(){
      var group=this.closest('form')||document;
      var boxes=group.querySelectorAll('input[name="majors[]"]');
      if(this.value==='all'&&this.checked){boxes.forEach(function(o){if(o.value!=='all')o.checked=false;});}
      else if(this.value!=='all'&&this.checked){var a=group.querySelector('input[name="majors[]"][value="all"]');if(a)a.checked=false;}
      if(!Array.from(boxes).some(c=>c.checked)){var a=group.querySelector('input[name="majors[]"][value="all"]');if(a)a.checked=true;}
      boxes.forEach(function(o){o.closest('.mck-item')&&o.closest('.mck-item').classList.toggle('is-on',o.checked);});
    });
  });
}
bindMajorChecks(document);

// کارت حالت استخراج
document.querySelectorAll('[data-mode-card] input').forEach(function(r){
  r.addEventListener('change',function(){
    document.querySelectorAll('[data-mode-card]').forEach(function(c){c.classList.toggle('is-on',c.querySelector('input').checked);});
  });
});
document.querySelector('[data-mode-card] input:checked')?.closest('[data-mode-card]')?.classList.add('is-on');

// باز/بستن فرم ویرایش
function bkToggleEdit(id){
  var el=document.getElementById('bkEdit'+id);
  if(el) el.style.display = (el.style.display==='none'||!el.style.display) ? 'block' : 'none';
}

// ───── استخراج دقیقِ تکه‌تکه (گام‌به‌گام) ─────
(function(){
  var CSRF = '<?= csrf_token() ?>';
  var ENDPOINT = '<?= BASE_URL ?>/admin/book_extract.php';
  var overlay = document.getElementById('bkPreciseOverlay');
  var titleEl = document.getElementById('bkPreciseTitle');
  var logEl   = document.getElementById('bkPreciseLog');
  var barEl   = document.getElementById('bkPreciseBar');
  var pctEl   = document.getElementById('bkPrecisePct');
  var closeBtn= document.getElementById('bkPreciseClose');
  var busy = false;

  function show(){ overlay.classList.add('show'); }
  function hide(){ overlay.classList.remove('show'); }
  function log(msg, cls){
    var d=document.createElement('div');
    d.className='bk-log-line'+(cls?(' '+cls):'');
    d.textContent=msg;
    logEl.appendChild(d);
    logEl.scrollTop=logEl.scrollHeight;
  }
  function setBar(step,total){
    var pct = total>0 ? Math.round(step/total*100) : 0;
    barEl.style.width = pct+'%';
    pctEl.textContent = pct+'٪ ('+toFa(step)+' از '+toFa(total)+')';
  }
  function toFa(n){var f=['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];return String(n).replace(/[0-9]/g,function(d){return f[d];});}

  function post(data){
    var body = new URLSearchParams();
    body.append('csrf', CSRF);
    for (var k in data) body.append(k, data[k]);
    return fetch(ENDPOINT, {method:'POST', body:body, credentials:'same-origin'})
      .then(function(r){ return r.text(); })
      .then(function(t){
        try { return JSON.parse(t); }
        catch(e){ throw new Error('پاسخ نامعتبر سرور: ' + t.slice(0,200)); }
      });
  }

  function runStep(bookId, total){
    return post({action:'step', book_id:bookId}).then(function(res){
      if(!res.ok) throw new Error(res.error||'خطا در یک مرحله');
      setBar(res.step, total);
      if(res.skipped) log('• رد شد: '+(res.note||''),'warn');
      else log('✓ '+(res.label||('مرحله '+toFa(res.step)))+(res.note?(' — '+res.note):''), res.note?'warn':'ok');
      if(res.done) return true;
      return runStep(bookId, total);
    });
  }

  document.querySelectorAll('.bk-precise-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      if(busy) return;
      if(!confirm('استخراج دقیقِ تکه‌تکه شروع شود؟ این روش دقیق‌ترین است و بسته به حجم کتاب چند دقیقه طول می‌کشد. صفحه را نبند.')) return;
      busy = true;
      var bookId = this.getAttribute('data-book-id');
      logEl.innerHTML=''; setBar(0,1); titleEl.textContent='در حال آماده‌سازی...';
      closeBtn.style.display='none';
      show();
      log('در حال تشخیص فهرست درس‌ها...');
      post({action:'start', book_id:bookId}).then(function(res){
        if(!res.ok) throw new Error(res.error||'شروع ناموفق بود');
        (res.warnings||[]).forEach(function(w){ log('• '+w,'warn'); });
        var total = res.total;
        titleEl.textContent='استخراج دقیق — '+toFa(total)+' مرحله';
        log('فهرست آماده شد. شروع استخراج تکه‌تکه ('+toFa(total)+' مرحله)...','ok');
        setBar(0,total);
        return runStep(bookId, total).then(function(){
          log('در حال ذخیره و تکه‌بندی نهایی...');
          return post({action:'finish', book_id:bookId});
        });
      }).then(function(res){
        if(res){
          if(!res.ok) throw new Error(res.error||'پایان ناموفق');
          log('✅ تمام شد: '+toFa(res.chars)+' کاراکتر → '+toFa(res.chunks)+' بخش.','ok');
          titleEl.textContent='استخراج با موفقیت کامل شد ✅';
        }
      }).catch(function(err){
        log('❌ '+(err.message||err),'err');
        titleEl.textContent='خطا در استخراج';
      }).finally(function(){
        busy=false;
        closeBtn.style.display='inline-flex';
      });
    });
  });

  closeBtn.addEventListener('click', function(){ hide(); location.reload(); });
})();
</script>

<!-- مودال پیشرفت استخراج دقیق -->
<div class="bk-precise-overlay" id="bkPreciseOverlay">
  <div class="bk-precise-modal">
    <h3 id="bkPreciseTitle">استخراج دقیق</h3>
    <div class="bk-precise-barwrap"><div class="bk-precise-bar" id="bkPreciseBar"></div></div>
    <div class="bk-precise-pct" id="bkPrecisePct">۰٪</div>
    <div class="bk-precise-log" id="bkPreciseLog"></div>
    <button type="button" class="btn btn-primary" id="bkPreciseClose" style="display:none;margin-top:14px"><?=icon('check')?> بستن و تازه‌سازی</button>
  </div>
</div>

<style>
.bk-precise-overlay{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(8,8,14,.74);backdrop-filter:blur(5px)}
.bk-precise-overlay.show{display:flex}
.bk-precise-modal{width:100%;max-width:520px;background:linear-gradient(160deg,#16161f,#0f0f17);border:1px solid rgba(56,217,169,.3);border-radius:18px;padding:22px}
.bk-precise-modal h3{margin:0 0 14px;font-size:16px;color:#fff}
.bk-precise-barwrap{height:10px;background:rgba(255,255,255,.07);border-radius:20px;overflow:hidden}
.bk-precise-bar{height:100%;width:0;background:linear-gradient(90deg,#38d9a9,#4dabf7);transition:width .3s}
.bk-precise-pct{font-size:12px;color:var(--text-dim);margin:8px 0 12px;text-align:center}
.bk-precise-log{max-height:46vh;overflow:auto;background:rgba(0,0,0,.25);border:1px solid var(--border);border-radius:10px;padding:10px;font-size:12px;line-height:2}
.bk-log-line{color:#cfcfda}
.bk-log-line.ok{color:#38d9a9}
.bk-log-line.warn{color:#f0a050}
.bk-log-line.err{color:#ff5470}
</style>

<?php include __DIR__ . '/_footer.php'; ?>
