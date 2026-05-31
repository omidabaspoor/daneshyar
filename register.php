<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
if (current_user()) redirect(BASE_URL . '/chat.php');

$error = '';
$old = ['first_name'=>'', 'last_name'=>'', 'mobile'=>'', 'school'=>'', 'grade'=>10, 'major'=>'math'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'درخواست نامعتبر است. لطفاً صفحه را رفرش کن.';
    } else {
        $old['first_name'] = trim($_POST['first_name'] ?? '');
        $old['last_name']  = trim($_POST['last_name'] ?? '');
        $old['mobile']     = fa_to_en_digits(trim($_POST['mobile'] ?? ''));
        $old['mobile']     = preg_replace('/[^0-9]/', '', $old['mobile']);
        $old['school']     = trim($_POST['school'] ?? '');
        $old['grade']      = (int)($_POST['grade'] ?? 10);
        $old['major']      = normalize_major($_POST['major'] ?? 'math');
        $password          = (string)($_POST['password'] ?? '');
        $accept            = isset($_POST['accept']);

        if ($old['first_name']==='' || $old['last_name']==='') {
            $error = 'نام و نام خانوادگی الزامی است.';
        } elseif (!is_valid_mobile($old['mobile'])) {
            $error = 'شماره موبایل باید با ۰۹ شروع شود و ۱۱ رقم باشد.';
        } elseif (mb_strlen($password) < 6) {
            $error = 'رمز عبور حداقل ۶ کاراکتر باشد.';
        } elseif ($old['grade'] < 7 || $old['grade'] > 12) {
            $error = 'پایه تحصیلی نامعتبر است.';
        } elseif (!array_key_exists($old['major'], major_options())) {
            $error = 'رشته تحصیلی نامعتبر است.';
        } elseif (!$accept) {
            $error = 'برای ثبت‌نام باید قوانین را بپذیری.';
        } else {
            $stmt = db()->prepare("SELECT id FROM users WHERE mobile=?");
            $stmt->execute([$old['mobile']]);
            if ($stmt->fetch()) {
                $error = 'این شماره موبایل قبلاً ثبت شده.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                db()->prepare("INSERT INTO users (first_name,last_name,mobile,password,grade,major,school,last_reset_date) VALUES (?,?,?,?,?,?,?,CURDATE())")
                    ->execute([$old['first_name'],$old['last_name'],$old['mobile'],$hash,$old['grade'],$old['major'],$old['school']]);
                $_SESSION['user_id'] = (int)db()->lastInsertId();
                redirect(BASE_URL . '/chat.php');
            }
        }
    }
}

$pageTitle = 'ثبت‌نام';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap" style="min-height:auto; padding:20px 0 60px">
  <div class="auth-card glass">
    <h1>ثبت‌نام در دانش‌یار</h1>
    <p class="sub">فقط در ۳ مرحله کوتاه؛ بعدش مستقیم وارد چت می‌شی.</p>

    <?php if ($error): ?><div class="alert alert-error"><?= icon('warning') ?> <?= e($error) ?></div><?php endif; ?>

    <div class="register-progress" id="registerProgress">
      <span class="active"></span><span></span><span></span>
    </div>

    <form method="post" autocomplete="off" id="registerForm">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

      <section class="reg-step active" data-step="1">
        <div class="reg-step-title"><?= icon('user') ?> مرحله ۱: اطلاعات ورود</div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px">
          <div class="form-group">
            <label class="form-label">نام</label>
            <input class="input" name="first_name" required value="<?= e($old['first_name']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">نام خانوادگی</label>
            <input class="input" name="last_name" required value="<?= e($old['last_name']) ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">شماره موبایل</label>
          <input class="input input-tel" name="mobile" type="tel" inputmode="numeric" pattern="[0-9]*" required  placeholder="09123456789" value="<?= e($old['mobile']) ?>" maxlength="11" autocomplete="tel">
        </div>
        <div class="form-group">
          <label class="form-label">رمز عبور</label>
          <input class="input" name="password" type="password" required minlength="6" placeholder="حداقل ۶ کاراکتر">
        </div>
        <div class="reg-actions">
          <button class="btn btn-primary btn-block" type="button" data-next>ادامه <?= icon('arrow-left') ?></button>
        </div>
      </section>

      <section class="reg-step" data-step="2">
        <div class="reg-step-title"><?= icon('book') ?> مرحله ۲: پایه و رشته</div>
        <div class="form-group">
          <label class="form-label">پایه تحصیلی</label>
          <div class="grade-picker">
            <?php for ($g=7; $g<=12; $g++): ?>
              <label>
                <input type="radio" name="grade" value="<?= $g ?>" <?= $old['grade']==$g?'checked':'' ?>>
                <span><?= num_fa($g) ?></span>
              </label>
            <?php endfor; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">رشته/شاخه تحصیلی</label>
          <div class="major-picker-v2">
            <?php foreach (major_options() as $code => $label):
              $isMain = in_array($code, ['math','experimental','humanities'], true);
              $desc = $isMain ? 'رشته نظری' : 'شاخه غیرنظری / عمومی';
            ?>
              <label class="major-card">
                <input type="radio" name="major" value="<?= e($code) ?>" <?= $old['major']===$code?'checked':'' ?>>
                <span class="major-card-body">
                  <span class="major-card-main">
                    <b><?= e($label) ?></b>
                    <small><?= e($desc) ?></small>
                  </span>
                  <span class="major-badge"><?= $isMain ? 'تمرکز اصلی' : 'پشتیبانی' ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
          <small style="display:block; margin-top:8px; color:var(--text-dim)">کتاب‌ها براساس پایه و رشته نمایش داده می‌شن. کتاب‌های مشترک برای همه شاخه‌های همان پایه قابل نمایش‌اند.</small>
        </div>

        <div class="form-group">
          <label class="form-label">نام مدرسه <small style="color:var(--text-muted)">(اختیاری)</small></label>
          <input class="input" name="school" value="<?= e($old['school']) ?>">
        </div>
        <div class="reg-actions">
          <button class="btn btn-ghost" type="button" data-prev>برگشت</button>
          <button class="btn btn-primary" type="button" data-next>ادامه <?= icon('arrow-left') ?></button>
        </div>
      </section>

      <section class="reg-step" data-step="3">
        <div class="reg-step-title"><?= icon('shield') ?> مرحله ۳: قوانین و شروع</div>
        <div class="rules-box">
          <b>قوانین استفاده از دانش‌یار:</b><br>
          ۱. این سرویس فقط برای استفاده آموزشی دانش‌آموزان پایه ۷ تا ۱۲ است.<br>
          ۲. کاربر متعهد می‌شود از سرویس برای تقلب در امتحانات رسمی استفاده نکند.<br>
          ۳. اطلاعات شخصی کاربر محرمانه نگه‌داشته می‌شود و فروخته نمی‌شود.<br>
          ۴. هرگونه ارسال محتوای نامناسب، غیر اخلاقی یا غیر درسی منجر به مسدودسازی حساب می‌شود.<br>
          ۵. پاسخ‌های هوش مصنوعی ممکن است خطا داشته باشند؛ مسئولیت نهایی با کاربر است.<br>
          ۶. اشتراک خریداری‌شده قابل برگشت نیست مگر در شرایط خاص.<br>
          ۷. کاربر اجازه ندارد حساب خود را به دیگران اجاره یا واگذار کند.<br>
          ۸. ما حق توقف خدمات یا تغییر قوانین را در آینده محفوظ می‌داریم.
        </div>
        <div class="check-row">
          <input type="checkbox" name="accept" id="accept" required>
          <label for="accept" style="font-size:13px">قوانین بالا را خوانده‌ام و می‌پذیرم.</label>
        </div>
        <div class="reg-actions">
          <button class="btn btn-ghost" type="button" data-prev>برگشت</button>
          <button class="btn btn-primary" type="submit"><?= icon('sparkle') ?> ثبت‌نام و ورود</button>
        </div>
      </section>
    </form>

    <div class="switch">قبلاً ثبت‌نام کرده‌ای؟ <a href="<?= BASE_URL ?>/login.php">وارد شو</a></div>
  </div>
</div>
<script>
(function(){
  var form = document.getElementById('registerForm');
  var steps = Array.prototype.slice.call(document.querySelectorAll('.reg-step'));
  var bars = Array.prototype.slice.call(document.querySelectorAll('#registerProgress span'));
  var current = 0;
  function show(i){
    current = Math.max(0, Math.min(i, steps.length - 1));
    steps.forEach(function(s, idx){ s.classList.toggle('active', idx === current); });
    bars.forEach(function(b, idx){ b.classList.toggle('active', idx <= current); });
    window.scrollTo({top: 0, behavior: 'smooth'});
  }
  function validateStep(){
    var fields = steps[current].querySelectorAll('input[required]');
    for (var i=0; i<fields.length; i++) {
      if (!fields[i].checkValidity()) { fields[i].reportValidity(); return false; }
    }
    return true;
  }
  form.querySelectorAll('[data-next]').forEach(function(btn){ btn.addEventListener('click', function(){ if(validateStep()) show(current + 1); }); });
  form.querySelectorAll('[data-prev]').forEach(function(btn){ btn.addEventListener('click', function(){ show(current - 1); }); });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
