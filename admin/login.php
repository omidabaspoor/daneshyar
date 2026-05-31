<?php
require_once __DIR__ . '/../includes/functions.php';
if (current_admin()) redirect(BASE_URL . '/admin/');

$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');

    // ورود ادمین جدا از حساب کاربری سایت است و فقط admin_id را در session ذخیره می‌کند.
    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $row = db()->prepare("SELECT * FROM users WHERE mobile=? OR (role='admin' AND first_name='وب' AND last_name='مانیا') LIMIT 1");
        $row->execute([ADMIN_USER]);
        $admin = $row->fetch();
        if ($admin) {
            $_SESSION['admin_id'] = (int)$admin['id'];
            redirect(BASE_URL . '/admin/');
        } else {
            $hash = password_hash(ADMIN_PASS, PASSWORD_BCRYPT);
            db()->prepare("INSERT INTO users (first_name,last_name,mobile,password,grade,school,role) VALUES ('وب','مانیا',?,?,12,'مدیریت','admin')")
                ->execute([ADMIN_USER, $hash]);
            $_SESSION['admin_id'] = (int)db()->lastInsertId();
            redirect(BASE_URL . '/admin/');
        }
    } else {
        $error = 'یوزرنیم یا پسورد اشتباه است.';
    }
}
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>ورود ادمین | دانش‌یار</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fonts/vazirmatn.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=8">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card glass">
    <h1>🔐 ورود مدیر</h1>
    <p class="sub">این بخش از حساب کاربری دانش‌آموزان جداست.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label class="form-label">یوزرنیم ادمین</label>
        <input class="input" name="username" required dir="ltr">
      </div>
      <div class="form-group">
        <label class="form-label">پسورد ادمین</label>
        <input class="input" name="password" type="password" required dir="ltr">
      </div>
      <button class="btn btn-primary btn-block">ورود به پنل مدیریت</button>
    </form>
    <div class="switch"><a href="<?= BASE_URL ?>/">بازگشت به سایت</a></div>
  </div>
</div>
</body>
</html>
