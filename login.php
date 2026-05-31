<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
if (current_user()) redirect(BASE_URL . '/chat.php');

$error = '';
$old_mobile = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'درخواست نامعتبر است. لطفاً صفحه را رفرش کن.';
    } else {
        $mobile   = fa_to_en_digits(trim($_POST['mobile'] ?? ''));
        $mobile   = preg_replace('/[^0-9]/', '', $mobile);
        $password = (string)($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);
        $old_mobile = $mobile;

        if (!is_valid_mobile($mobile)) {
            $error = 'شماره موبایل معتبر نیست.';
        } elseif ($password === '') {
            $error = 'رمز عبور را وارد کن.';
        } else {
            $stmt = db()->prepare("SELECT * FROM users WHERE mobile=?");
            $stmt->execute([$mobile]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'شماره موبایل یا رمز عبور اشتباه است.';
            } else {
                login_user((int)$user['id'], $remember);
                redirect(BASE_URL . '/chat.php');
            }
        }
    }
}

$pageTitle = 'ورود';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card glass">
    <div class="auth-logo">
      <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="دانش‌یار" width="64" height="64">
    </div>
    <h1>خوش برگشتی! 👋</h1>
    <p class="sub">با شماره موبایل و رمز عبورت وارد شو.</p>

    <?php if (isset($_GET['banned'])): ?>
      <div class="alert alert-error"><?= icon('warning') ?> حساب شما مسدود شده است. برای اطلاعات بیشتر با پشتیبانی تماس بگیرید.</div>
    <?php elseif ($error): ?>
      <div class="alert alert-error"><?= icon('warning') ?> <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on" dir="rtl">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

      <div class="form-group">
        <label class="form-label"><?= icon('phone') ?> شماره موبایل</label>
        <input
          class="input input-rtl"
          name="mobile"
          type="tel"
          inputmode="numeric"
          pattern="[0-9]*"
          placeholder="۰۹۱۲۳۴۵۶۷۸۹"
          value="<?= e($old_mobile) ?>"
          maxlength="11"
          autocomplete="tel"
          dir="rtl"
          required
        >
      </div>

      <div class="form-group">
        <label class="form-label"><?= icon('lock') ?> رمز عبور</label>
        <div class="input-icon-wrap">
          <input
            class="input"
            name="password"
            type="password"
            placeholder="رمز عبور خود را وارد کن"
            autocomplete="current-password"
            required
            id="loginPass"
          >
          <button type="button" class="input-eye" onclick="togglePass('loginPass', this)" tabindex="-1">
            <?= icon('eye') ?>
          </button>
        </div>
      </div>

      <div class="check-row" style="margin-bottom:18px">
        <input type="checkbox" name="remember" id="remember" value="1">
        <label for="remember" style="font-size:13px; cursor:pointer">مرا به خاطر بسپار (۳۰ روز)</label>
      </div>

      <button class="btn btn-primary btn-block" type="submit">
        <?= icon('login') ?> ورود به دانش‌یار
      </button>
    </form>

    <div class="switch">حساب نداری؟ <a href="<?= BASE_URL ?>/register.php">ثبت‌نام کن</a></div>
  </div>
</div>

<style>
.auth-logo { text-align:center; margin-bottom:16px; }
.auth-logo img { border-radius:50%; box-shadow:0 6px 24px rgba(235,124,42,.4); }
.input-rtl {
  direction: rtl !important;
  text-align: right !important;
  unicode-bidi: plaintext;
}
.input-icon-wrap { position:relative; }
.input-icon-wrap .input { padding-left: 42px; }
.input-eye {
  position:absolute; left:10px; top:50%; transform:translateY(-50%);
  background:none; border:none; cursor:pointer;
  color:var(--text-dim); padding:4px; border-radius:6px;
}
.input-eye:hover { color:var(--orange); }
.input-eye .ico { width:18px; height:18px; }
</style>

<script>
/* فقط عدد در موبایل + جهت RTL ثابت */
(function(){
  var m = document.querySelector('input[name="mobile"]');
  if (m) {
    m.addEventListener('input', function(){
      var pos = this.selectionStart;
      this.value = this.value.replace(/[^0-9۰-۹٠-٩]/g, '');
      try { this.setSelectionRange(pos, pos); } catch(e) {}
    });
    m.addEventListener('keypress', function(e){
      var ch = e.key;
      if (ch.length === 1 && !/[0-9۰-۹٠-٩]/.test(ch)) e.preventDefault();
    });
    m.addEventListener('paste', function(e){
      e.preventDefault();
      var t = (e.clipboardData||window.clipboardData).getData('text');
      this.value = t.replace(/[^0-9]/g, '');
    });
  }
})();

function togglePass(id, btn) {
  var el = document.getElementById(id);
  if (!el) return;
  if (el.type === 'password') {
    el.type = 'text';
    btn.style.color = 'var(--orange)';
  } else {
    el.type = 'password';
    btn.style.color = '';
  }
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
