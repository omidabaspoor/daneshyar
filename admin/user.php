<?php
$adminPage = 'users';
$pageTitle = 'جزئیات کاربر';
include __DIR__ . '/_header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$viewUser = $stmt->fetch();

if (!$viewUser) {
    echo '<div class="alert alert-error">کاربر یافت نشد.</div>';
    include __DIR__ . '/_footer.php';
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile') {
    $first  = trim($_POST['first_name'] ?? '');
    $last   = trim($_POST['last_name'] ?? '');
    $school = trim($_POST['school'] ?? '');
    $grade  = (int)($_POST['grade'] ?? $viewUser['grade']);
    $major  = normalize_major($_POST['major'] ?? ($viewUser['major'] ?? 'math'));

    if (!is_valid_person_name($first) || !is_valid_person_name($last) || !is_valid_school_name($school) || $grade < 7 || $grade > 12) {
        $msg = 'اطلاعات وارد شده نامعتبر است.';
    } else {
        db()->prepare("UPDATE users SET first_name=?, last_name=?, school=?, grade=?, major=? WHERE id=?")
            ->execute([$first, $last, $school, $grade, $major, $id]);
        $msg = 'اطلاعات کاربر ذخیره شد.';
        $stmt->execute([$id]);
        $viewUser = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reset_password') {
        $newPass = (string)($_POST['new_password'] ?? '');
        if (mb_strlen($newPass, 'UTF-8') < 6) {
            $msg = 'رمز جدید باید حداقل ۶ کاراکتر باشد.';
        } elseif (($viewUser['role'] ?? 'user') === 'admin') {
            $msg = 'تغییر رمز حساب ادمین مجاز نیست.';
        } else {
            db()->prepare("UPDATE users SET password=? WHERE id=? AND role!='admin'")
                ->execute([password_hash($newPass, PASSWORD_BCRYPT), $id]);
            try { db()->prepare("DELETE FROM user_sessions WHERE user_id=?")->execute([$id]); } catch (Throwable $e) {}
            $msg = 'رمز عبور کاربر تغییر کرد.';
            $stmt->execute([$id]);
            $viewUser = $stmt->fetch();
        }
    } elseif ($_POST['action'] === 'save_discount') {
        $plan_code = trim($_POST['plan_code'] ?? '');
        $percent = (int)($_POST['discount_percent'] ?? 0);
        if ($plan_code !== '' && $percent >= 0 && $percent <= 100) {
            db()->prepare("INSERT INTO user_discounts (user_id, plan_code, discount_percent) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE discount_percent = ?")
               ->execute([$id, $plan_code, $percent, $percent]);
            $msg = 'تخفیف با موفقیت ثبت شد.';
        } else { $msg = 'مقادیر تخفیف نامعتبر است.'; }
    } elseif ($_POST['action'] === 'delete_discount') {
        $discount_id = (int)($_POST['discount_id'] ?? 0);
        if ($discount_id > 0) {
            db()->prepare("DELETE FROM user_discounts WHERE id=? AND user_id=?")->execute([$discount_id, $id]);
            $msg = 'تخفیف حذف شد.';
        }
    }
}

$sub = subscription_status($viewUser);
$stats = ['chats' => 0, 'messages' => 0, 'paid' => 0];
$s = db()->prepare("SELECT COUNT(*) FROM chats WHERE user_id=?"); $s->execute([$id]); $stats['chats'] = (int)$s->fetchColumn();
$s = db()->prepare("SELECT COUNT(*) FROM chat_history WHERE user_id=?"); $s->execute([$id]); $stats['messages'] = (int)$s->fetchColumn();
$s = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND status='paid'"); $s->execute([$id]); $stats['paid'] = (int)$s->fetchColumn();

$transactionsStmt = db()->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 50");
$transactionsStmt->execute([$id]);
$transactions = $transactionsStmt->fetchAll();

$chatsStmt = db()->prepare("SELECT c.*, b.title AS book_title FROM chats c LEFT JOIN books b ON b.id=c.book_id WHERE c.user_id=? ORDER BY c.updated_at DESC LIMIT 50");
$chatsStmt->execute([$id]);
$chats = $chatsStmt->fetchAll();

$messagesStmt = db()->prepare("SELECT h.*, c.title AS chat_title, b.title AS book_title FROM chat_history h LEFT JOIN chats c ON c.id=h.chat_id LEFT JOIN books b ON b.id=h.book_id WHERE h.user_id=? ORDER BY h.id DESC LIMIT 40");
$messagesStmt->execute([$id]);
$messages = $messagesStmt->fetchAll();
?>

<div class="admin-page-header">
  <h2><?= icon('user') ?> جزئیات کاربر #<?= num_fa($viewUser['id']) ?></h2>
  <a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>/admin/users.php">← بازگشت به کاربران</a>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success"><?= icon('check') ?> <?= e($msg) ?></div>
<?php endif; ?>

<!-- ═══ آمار کاربر ═══ -->
<div class="stat-grid">
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('chat') ?></div>
    <div><div class="l">چت‌ها</div><div class="v"><?= num_fa($stats['chats']) ?></div></div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('graph') ?></div>
    <div><div class="l">کل پیام‌ها</div><div class="v"><?= num_fa($stats['messages']) ?></div></div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('wallet') ?></div>
    <div><div class="l">پرداختی</div><div class="v"><?= format_price($stats['paid']) ?></div></div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon" style="<?= $sub['active'] ? 'background:rgba(56,217,169,.12);border-color:rgba(56,217,169,.2);color:var(--success)' : 'background:rgba(255,84,112,.12);border-color:rgba(255,84,112,.2);color:var(--danger)' ?>">
      <?= icon('crown') ?>
    </div>
    <div><div class="l">اشتراک</div><div class="v <?= $sub['active'] ? 'text-success' : 'text-danger' ?>"><?= $sub['active'] ? 'فعال' : 'غیرفعال' ?></div></div>
  </div>
</div>

<!-- ═══ اطلاعات شخصی + اشتراک ═══ -->
<div class="dash-grid">
  <div class="admin-card glass">
    <div class="admin-card-header"><h3><?= icon('user') ?> اطلاعات شخصی</h3></div>
    <div class="admin-card-body">
      <form method="post">
        <input type="hidden" name="action" value="save_profile">
        <div class="admin-form-grid">
          <div class="form-group"><label class="form-label">نام</label><input class="input" name="first_name" value="<?= e($viewUser['first_name']) ?>" required></div>
          <div class="form-group"><label class="form-label">نام خانوادگی</label><input class="input" name="last_name" value="<?= e($viewUser['last_name']) ?>" required></div>
          <div class="form-group full-width"><label class="form-label">موبایل/شناسه</label><input class="input" value="<?= e($viewUser['mobile']) ?>" dir="ltr" disabled></div>
          <div class="form-group"><label class="form-label">پایه</label>
            <select class="select" name="grade"><?php for($g=7;$g<=12;$g++): ?><option value="<?= $g ?>" <?= (int)$viewUser['grade']===$g?'selected':'' ?>>پایه <?= num_fa($g) ?></option><?php endfor; ?></select>
          </div>
          <div class="form-group"><label class="form-label">رشته/شاخه</label>
            <select class="select" name="major"><?php foreach(major_options() as $code=>$label): ?><option value="<?= e($code) ?>" <?= ($viewUser['major'] ?? 'math')===$code?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
          </div>
          <div class="form-group full-width"><label class="form-label">مدرسه</label><input class="input" name="school" value="<?= e($viewUser['school']) ?>" required minlength="2"></div>
        </div>
        <button class="btn btn-primary" style="margin-top:8px">ذخیره اطلاعات</button>
      </form>
    </div>
  </div>

  <div class="admin-card glass">
    <div class="admin-card-header"><h3><?= icon('crown') ?> اشتراک و مصرف</h3></div>
    <div class="admin-card-body" style="padding:0">
      <div class="admin-table-wrap">
        <table class="admin-table detail-list">
          <tr><th>نقش</th><td><?= e($viewUser['role']) ?></td></tr>
          <tr><th>وضعیت</th><td><?= $sub['active'] ? '<span class="status-badge status-active">فعال</span>' : '<span class="status-badge status-banned">'.e($sub['reason']).'</span>' ?></td></tr>
          <tr><th>نوع پلن</th><td><?= e($viewUser['subscription_type']) ?></td></tr>
          <tr><th>شروع</th><td><?= e($viewUser['subscription_start'] ?: '—') ?></td></tr>
          <tr><th>پایان</th><td><?= e($viewUser['subscription_end'] ?: '—') ?></td></tr>
          <tr><th>پیام امروز</th><td><?= num_fa($viewUser['messages_used_today']) ?></td></tr>
          <tr><th>رایگان امروز</th><td><?= num_fa($viewUser['free_used_today']) ?></td></tr>
          <tr><th>کل پیام</th><td><?= num_fa($viewUser['messages_used_total']) ?></td></tr>
          <tr><th>عضویت</th><td><?= e($viewUser['created_at']) ?></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ═══ مدیریت رمز ═══ -->
<?php if (($viewUser['role'] ?? 'user') !== 'admin'): ?>
<div class="admin-card glass" style="margin-top:16px">
  <div class="admin-card-header"><h3><?= icon('lock') ?> مدیریت رمز عبور</h3></div>
  <div class="admin-card-body">
    <div class="alert alert-info" style="margin-bottom:14px">
      رمز فعلی به‌صورت هش ذخیره شده و قابل مشاهده نیست. رمز جدید تعیین کنید.
    </div>
    <form method="post" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap">
      <input type="hidden" name="action" value="reset_password">
      <div class="form-group" style="flex:1; min-width:200px; margin:0">
        <label class="form-label">رمز جدید</label>
        <input class="input" type="text" name="new_password" minlength="6" required autocomplete="off" placeholder="حداقل ۶ کاراکتر">
      </div>
      <button class="btn btn-primary" onclick="return confirm('رمز تغییر کند؟')"><?= icon('lock') ?> تغییر رمز</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ═══ تخفیف‌ها ═══ -->
<div class="admin-card glass" style="margin-top:16px">
  <div class="admin-card-header"><h3><?= icon('star') ?> تخفیف‌های اختصاصی</h3></div>
  <div class="admin-card-body">
    <form method="post" style="display:flex; gap:10px; align-items:flex-end; margin-bottom:16px; flex-wrap:wrap">
      <input type="hidden" name="action" value="save_discount">
      <div class="form-group" style="flex:1; min-width:120px; margin:0">
        <label class="form-label">انتخاب پلن</label>
        <select class="select" name="plan_code">
          <option value="3h">۳ ساعته</option>
          <option value="weekly">هفتگی</option>
          <option value="monthly">ماهانه</option>
        </select>
      </div>
      <div class="form-group" style="min-width:100px; margin:0">
        <label class="form-label">درصد تخفیف (%)</label>
        <input class="input" type="number" name="discount_percent" min="0" max="100" placeholder="مثلا 30" required>
      </div>
      <button class="btn btn-primary">ثبت تخفیف</button>
    </form>

    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>پلن</th><th>تخفیف</th><th>تاریخ ثبت</th><th>عملیات</th></tr></thead>
        <tbody>
          <?php
          $discounts = db()->prepare("SELECT * FROM user_discounts WHERE user_id=?");
          $discounts->execute([$id]);
          $discRows = $discounts->fetchAll();
          foreach($discRows as $d):
            $pLabel = ['3h'=>'۳ ساعته','weekly'=>'هفتگی','monthly'=>'ماهانه'][$d['plan_code']] ?? $d['plan_code'];
          ?>
            <tr>
              <td><?= e($pLabel) ?></td>
              <td style="color:var(--success); font-weight:800"><?= num_fa($d['discount_percent']) ?>%</td>
              <td><?= e($d['created_at']) ?></td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="delete_discount">
                  <input type="hidden" name="discount_id" value="<?= $d['id'] ?>">
                  <button class="btn btn-danger btn-sm" onclick="return confirm('حذف شود؟')"><?= icon('trash') ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; if(!$discRows): ?>
            <tr><td colspan="4" class="admin-empty">تخفیفی تعریف نشده است.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══ چت‌ها ═══ -->
<div class="admin-card glass" style="margin-top:16px">
  <div class="admin-card-header"><h3><?= icon('chat') ?> آخرین چت‌ها</h3></div>
  <div class="admin-card-body" style="padding:0">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>#</th><th>عنوان</th><th>کتاب</th><th>سنجاق</th><th>ایجاد</th><th>بروزرسانی</th></tr></thead>
        <tbody>
        <?php foreach($chats as $c): ?>
          <tr>
            <td><?= num_fa($c['id']) ?></td>
            <td><?= e($c['title']) ?></td>
            <td><?= e($c['book_title'] ?: 'عمومی') ?></td>
            <td><?= $c['is_pinned']?'بله':'خیر' ?></td>
            <td><?= e($c['created_at']) ?></td>
            <td><?= e($c['updated_at']) ?></td>
          </tr>
        <?php endforeach; if(!$chats): ?>
          <tr><td colspan="6" class="admin-empty">چتی وجود ندارد.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══ پیام‌ها ═══ -->
<div class="admin-card glass" style="margin-top:16px">
  <div class="admin-card-header"><h3><?= icon('send') ?> آخرین پیام‌ها</h3></div>
  <div class="admin-card-body" style="padding:0">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>#</th><th>نقش</th><th>چت</th><th>کتاب</th><th>متن</th><th>پیوست</th><th>تاریخ</th></tr></thead>
        <tbody>
        <?php foreach($messages as $m): ?>
          <tr>
            <td><?= num_fa($m['id']) ?></td>
            <td><?= e($m['role']) ?></td>
            <td><?= e($m['chat_title'] ?: '—') ?></td>
            <td><?= e($m['book_title'] ?: '—') ?></td>
            <td style="max-width:320px; white-space:normal"><?= e(mb_substr($m['content'], 0, 180)) ?><?= mb_strlen($m['content'])>180?'…':'' ?></td>
            <td><?= e($m['attachment'] ?: '—') ?></td>
            <td><?= e($m['created_at']) ?></td>
          </tr>
        <?php endforeach; if(!$messages): ?>
          <tr><td colspan="7" class="admin-empty">پیامی وجود ندارد.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══ تراکنش‌ها ═══ -->
<div class="admin-card glass" style="margin-top:16px">
  <div class="admin-card-header"><h3><?= icon('wallet') ?> تراکنش‌ها</h3></div>
  <div class="admin-card-body" style="padding:0">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>#</th><th>پلن</th><th>مبلغ</th><th>وضعیت</th><th>Ref</th><th>تاریخ</th></tr></thead>
        <tbody>
        <?php foreach($transactions as $t): ?>
          <tr>
            <td><?= num_fa($t['id']) ?></td>
            <td class="font-mono text-sm"><?= e($t['plan_code']) ?></td>
            <td style="font-weight:700"><?= format_price($t['amount']) ?></td>
            <td><span class="status-badge <?= $t['status']==='paid'?'status-approved':'status-pending' ?>"><?= e($t['status']) ?></span></td>
            <td class="font-mono text-sm"><?= e($t['ref_id'] ?: '—') ?></td>
            <td><?= e($t['created_at']) ?></td>
          </tr>
        <?php endforeach; if(!$transactions): ?>
          <tr><td colspan="6" class="admin-empty">تراکنشی وجود ندارد.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
