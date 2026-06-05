<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_login();
$user  = current_user();
$page  = 'profile';
$pageTitle = 'پروفایل';

$sub    = subscription_status($user);
$plan   = $sub['plan'] ?? null;
$notices = user_notifications($user);

// --- ویرایش پروفایل ---
$editMsg  = '';
$editType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $editMsg = 'درخواست نامعتبر.'; $editType = 'error';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $school    = trim($_POST['school'] ?? '');
        $grade     = (int)($_POST['grade'] ?? $user['grade']);
        $major     = normalize_major($_POST['major'] ?? $user['major']);
        $newPass   = (string)($_POST['new_password'] ?? '');
        $curPass   = (string)($_POST['current_password'] ?? '');

        if (!is_valid_person_name($firstName) || !is_valid_person_name($lastName)) {
            $editMsg = 'نام و نام خانوادگی باید واقعی و حداقل ۲ حرفی باشد.'; $editType = 'error';
        } elseif (!is_valid_school_name($school)) {
            $editMsg = 'نام مدرسه الزامی است و باید معتبر وارد شود.'; $editType = 'error';
        } elseif ($grade < 7 || $grade > 12) {
            $editMsg = 'پایه نامعتبر است.'; $editType = 'error';
        } else {
            if ($newPass !== '') {
                if (!verify_user_password($curPass, $user['password'], (int)$user['id'])) {
                    $editMsg = 'رمز عبور فعلی اشتباه است.'; $editType = 'error';
                } elseif (mb_strlen($newPass) < 6) {
                    $editMsg = 'رمز جدید حداقل ۶ کاراکتر باشد.'; $editType = 'error';
                } else {
                    $hash = password_hash($newPass, PASSWORD_BCRYPT);
                    db()->prepare("UPDATE users SET first_name=?, last_name=?, school=?, grade=?, major=?, password=? WHERE id=?")
                        ->execute([$firstName, $lastName, $school, $grade, $major, $hash, $user['id']]);
                    $editMsg = 'اطلاعات و رمز عبور با موفقیت ذخیره شد.'; $editType = 'success';
                }
            } else {
                db()->prepare("UPDATE users SET first_name=?, last_name=?, school=?, grade=?, major=? WHERE id=?")
                    ->execute([$firstName, $lastName, $school, $grade, $major, $user['id']]);
                $editMsg = 'اطلاعات با موفقیت ذخیره شد.'; $editType = 'success';
            }
            // reload
            $stmt = db()->prepare("SELECT * FROM users WHERE id=?");
            $stmt->execute([$user['id']]);
            $user = $stmt->fetch();
        }
    }
}

// --- رسیدهای کاربر ---
$receipts = [];
try {
    $stmt = db()->prepare("SELECT * FROM card_receipts WHERE user_id=? ORDER BY id DESC LIMIT 20");
    $stmt->execute([$user['id']]);
    $receipts = $stmt->fetchAll();
} catch (Throwable $e) {}

include __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['activated'])): ?>
  <div class="alert alert-success" style="max-width:900px; margin:0 auto 14px">
    <?= icon('check') ?> اشتراک با موفقیت فعال شد!
  </div>
<?php endif; ?>

<?php if (isset($_GET['complete'])): ?>
  <div class="alert alert-info" style="max-width:900px; margin:0 auto 14px">
    <?= icon('warning') ?> برای ادامه استفاده، لطفاً نام واقعی و نام مدرسه را تکمیل کن.
  </div>
<?php endif; ?>

<?php if (!empty($notices)): ?>
  <div style="max-width:900px; margin:0 auto 16px; display:grid; gap:8px">
    <?php foreach (array_slice($notices, 0, 3) as $n): ?>
      <div class="dy-notice notice-<?= e($n['type']) ?>">
        <div class="dy-notice-ico"><?= icon($n['icon']) ?></div>
        <div class="dy-notice-body">
          <b><?= e($n['title']) ?></b>
          <small><?= e($n['text']) ?></small>
          <?php if (!empty($n['action'])): ?><a href="<?= e($n['action']) ?>"><?= e($n['action_text']) ?></a><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($editMsg): ?>
  <div class="alert alert-<?= $editType === 'error' ? 'error' : 'success' ?>" style="max-width:900px; margin:0 auto 14px">
    <?= $editType === 'error' ? icon('warning') : icon('check') ?> <?= e($editMsg) ?>
  </div>
<?php endif; ?>

<div class="profile-grid" style="max-width:900px; margin:0 auto">

  <!-- کارت اشتراک -->
  <div class="glass profile-card profile-sub-card">
    <h3 class="profile-card-title"><?= icon('crown') ?> وضعیت اشتراک</h3>
    <?php if ($sub['active']): ?>
      <div class="sub-status-active">
        <div class="sub-status-icon"><?= icon('crown') ?></div>
        <div>
          <div class="sub-status-name"><?= e($plan['title']) ?></div>
          <div class="sub-status-time"><?= time_left($user['subscription_end']) ?> باقی‌مانده</div>
        </div>
      </div>
      <div class="profile-rows" style="margin-top:14px">
        <?php if ($plan['daily_limit'] > 0): ?>
          <div class="profile-row">
            <span>پیام امروز</span>
            <b><?= num_fa($user['messages_used_today']) ?> / <?= num_fa($plan['daily_limit']) ?></b>
          </div>
          <?php $pct = $plan['daily_limit'] > 0 ? min(100, $user['messages_used_today'] / $plan['daily_limit'] * 100) : 0; ?>
          <div style="height:6px; border-radius:3px; background:rgba(0,0,0,.3); overflow:hidden; margin:-6px 0 6px">
            <div style="width:<?= $pct ?>%; height:100%; background:linear-gradient(90deg,var(--orange),var(--orange-2)); border-radius:3px"></div>
          </div>
        <?php endif; ?>
        <?php if ($plan['total_limit'] > 0): ?>
          <div class="profile-row"><span>پیام کل</span><b><?= num_fa($user['messages_used_total']) ?> / <?= num_fa($plan['total_limit']) ?></b></div>
        <?php endif; ?>
        <div class="profile-row"><span>پایان اشتراک</span><b><?= num_fa(date('Y/m/d', strtotime($user['subscription_end']))) ?></b></div>
      </div>
      <a href="<?= BASE_URL ?>/pricing.php" class="btn btn-ghost btn-block" style="margin-top:14px"><?= icon('refresh') ?> تمدید / ارتقا</a>
    <?php else: ?>
      <div class="alert alert-info" style="margin-bottom:12px"><?= icon('warning') ?> <?= e($sub['reason']) ?></div>
      <p style="color:var(--text-dim); font-size:13px; margin-bottom:12px">
        پیام رایگان امروز: <b style="color:var(--text)"><?= num_fa(FREE_DAILY_LIMIT - (int)$user['free_used_today']) ?> از <?= num_fa(FREE_DAILY_LIMIT) ?></b>
      </p>
      <a href="<?= BASE_URL ?>/pricing.php" class="btn btn-primary btn-block"><?= icon('flash') ?> خرید اشتراک</a>
    <?php endif; ?>
  </div>

  <!-- اطلاعات شخصی -->
  <div class="glass profile-card">
    <h3 class="profile-card-title"><?= icon('user') ?> اطلاعات شخصی</h3>
    <div class="profile-rows">
      <div class="profile-row"><span>نام کامل</span><b><?= e($user['first_name'] . ' ' . $user['last_name']) ?></b></div>
      <div class="profile-row"><span>موبایل</span><b dir="ltr"><?= e($user['mobile']) ?></b></div>
      <div class="profile-row"><span>پایه</span><b>پایه <?= num_fa($user['grade']) ?></b></div>
      <div class="profile-row"><span>رشته</span><b><?= e(major_label($user['major'] ?? 'math')) ?></b></div>
      <div class="profile-row"><span>مدرسه</span><b><?= e($user['school'] ?: '—') ?></b></div>
      <div class="profile-row"><span>عضویت</span><b><?= num_fa(date('Y/m/d', strtotime($user['created_at']))) ?></b></div>
    </div>
    <button class="btn btn-ghost btn-block" style="margin-top:14px" onclick="document.getElementById('editSection').style.display=document.getElementById('editSection').style.display==='none'?'block':'none'">
      <?= icon('edit') ?> ویرایش اطلاعات
    </button>
  </div>
</div>

<!-- فرم ویرایش -->
<div id="editSection" style="display:none; max-width:900px; margin:16px auto">
  <div class="glass profile-card">
    <h3 class="profile-card-title"><?= icon('edit') ?> ویرایش اطلاعات</h3>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="update_profile" value="1">

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px">
        <div class="form-group">
          <label class="form-label">نام</label>
          <input class="input" name="first_name" value="<?= e($user['first_name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">نام خانوادگی</label>
          <input class="input" name="last_name" value="<?= e($user['last_name']) ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">مدرسه</label>
        <input class="input" name="school" value="<?= e($user['school']) ?>" required minlength="2">
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px">
        <div class="form-group">
          <label class="form-label">پایه</label>
          <select class="select" name="grade">
            <?php for ($g = 7; $g <= 12; $g++): ?>
              <option value="<?= $g ?>" <?= (int)$user['grade'] === $g ? 'selected' : '' ?>>پایه <?= num_fa($g) ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">رشته</label>
          <select class="select" name="major">
            <?php foreach (major_options() as $code => $label): ?>
              <option value="<?= e($code) ?>" <?= ($user['major'] ?? 'math') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <hr style="border-color:var(--border); margin:18px 0">
      <p style="color:var(--text-dim); font-size:13px; margin-bottom:12px">تغییر رمز عبور (اختیاری)</p>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px">
        <div class="form-group">
          <label class="form-label">رمز فعلی</label>
          <input class="input" name="current_password" type="password" placeholder="اگر می‌خواهید رمز تغییر کند">
        </div>
        <div class="form-group">
          <label class="form-label">رمز جدید</label>
          <input class="input" name="new_password" type="password" placeholder="حداقل ۶ کاراکتر" minlength="6">
        </div>
      </div>

      <button class="btn btn-primary" type="submit"><?= icon('check') ?> ذخیره تغییرات</button>
    </form>
  </div>
</div>

<!-- رسیدهای پرداخت -->
<?php if (!empty($receipts)): ?>
<div id="receipts" style="max-width:900px; margin:16px auto">
  <div class="glass profile-card">
    <h3 class="profile-card-title"><?= icon('wallet') ?> رسیدهای پرداخت من</h3>
    <div style="display:flex; flex-direction:column; gap:10px">
      <?php foreach ($receipts as $r):
        $sc = ['pending'=>'#ffb86b','approved_pending'=>'#4dabf7','approved'=>'#38d9a9','rejected'=>'#ff5470'][$r['status']] ?? '#aaa';
        $sl = [
            'pending'          => '🕐 در انتظار بررسی',
            'approved_pending' => '✅ تایید شد – در انتظار زمان شروع',
            'approved'         => '✅ فعال شد',
            'rejected'         => '❌ رد شده',
        ][$r['status']] ?? $r['status'];
      ?>
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 14px; background:rgba(255,255,255,.03); border:1px solid var(--border); border-right:3px solid <?= $sc ?>; border-radius:10px; flex-wrap:wrap">
          <div>
            <div style="font-weight:700"><?= e($r['plan_title'] ?: $r['plan_code']) ?></div>
            <div style="font-size:12px; color:var(--text-dim)"><?= num_fa(date('Y/m/d H:i', strtotime($r['created_at']))) ?></div>
            <?php if ($r['status'] === 'approved_pending' && $r['activate_at']): ?>
              <div style="font-size:12px; color:#4dabf7; margin-top:4px; display:flex; align-items:center; gap:4px">
                <?= icon('clock') ?> فعال‌سازی خودکار در <?= num_fa(date('Y/m/d – H:i', strtotime($r['activate_at']))) ?>
              </div>
            <?php elseif ($r['activate_at'] && $r['status'] === 'pending'): ?>
              <div style="font-size:11px; color:var(--text-dim); margin-top:3px">زمان دلخواه: <?= num_fa(date('Y/m/d H:i', strtotime($r['activate_at']))) ?></div>
            <?php endif; ?>
          </div>
          <div style="text-align:left">
            <div style="font-weight:800; color:var(--orange)"><?= format_price($r['amount']) ?> ت</div>
            <span style="font-size:12px; font-weight:700; color:<?= $sc ?>"><?= e($sl) ?></span>
          </div>
        </div>
        <?php if ($r['status'] === 'rejected' && $r['admin_note']): ?>
          <div style="font-size:12px; color:var(--danger); padding:4px 14px">دلیل رد: <?= e($r['admin_note']) ?></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div style="text-align:center; margin-top:24px; max-width:900px; margin-left:auto; margin-right:auto">
  <a href="<?= BASE_URL ?>/chat.php" class="btn btn-primary"><?= icon('chat') ?> بازگشت به چت</a>
</div>

<style>
.profile-grid { display:grid; grid-template-columns:1fr; gap:16px; }
@media (min-width: 720px) { .profile-grid { grid-template-columns:1fr 1fr; } }
.profile-card { padding:24px; }
.profile-card-title {
  margin-bottom:16px; color:var(--orange);
  display:flex; align-items:center; gap:8px;
  font-size:16px;
}
.profile-card-title .ico { width:20px; height:20px; }
.profile-rows { display:flex; flex-direction:column; gap:8px; }
.profile-row {
  display:flex; justify-content:space-between; align-items:center;
  padding:8px 12px;
  background:rgba(255,255,255,.03); border:1px solid var(--border);
  border-radius:10px; font-size:13.5px;
}
.profile-row span { color:var(--text-dim); }
.sub-status-active {
  display:flex; align-items:center; gap:14px;
  padding:14px; border-radius:14px;
  background:rgba(56,217,169,.08); border:1px solid rgba(56,217,169,.25);
}
.sub-status-icon {
  width:44px; height:44px; border-radius:12px; flex-shrink:0;
  background:linear-gradient(135deg,#38d9a9,#20c997);
  display:grid; place-items:center; color:#1a0e05;
}
.sub-status-icon .ico { width:22px; height:22px; }
.sub-status-name { font-weight:800; font-size:15px; }
.sub-status-time { font-size:12px; color:var(--text-dim); margin-top:3px; }
</style>

<script>
// اگه از طریق anchor به receipts هدایت شده باشیم
if (window.location.hash === '#receipts') {
  var el = document.getElementById('receipts');
  if (el) { el.scrollIntoView({behavior:'smooth'}); el.style.outline='2px solid var(--orange)'; setTimeout(function(){ el.style.outline=''; }, 2000); }
}
// اگه editMsg وجود داشت، بخش ویرایش باز باشه
<?php if ($editMsg || isset($_GET['complete'])): ?>
document.getElementById('editSection').style.display = 'block';
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
