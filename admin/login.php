<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';
if (current_admin()) redirect(BASE_URL . '/admin/');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');

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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود ادمین | دانش‌یار</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fonts/vazirmatn.css">
<style>
  :root {
    --orange: #eb7c2a;
    --orange-2: #ff9a3d;
    --orange-3: #ffb86b;
    --bg: #07070d;
    --glass: rgba(255,255,255,0.04);
    --glass-2: rgba(255,255,255,0.07);
    --glass-3: rgba(255,255,255,0.10);
    --border: rgba(255,255,255,0.08);
    --border-orange: rgba(235,124,42,.35);
    --text: #f4f4f7;
    --text-dim: #9ea0b3;
    --danger: #ff5470;
    --radius: 18px;
    --radius-sm: 12px;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Vazirmatn', 'Tahoma', system-ui, sans-serif;
    background: var(--bg);
    color: var(--text);
    direction: rtl;
    min-height: 100dvh;
    display: grid;
    place-items: center;
    padding: 20px 14px;
    -webkit-font-smoothing: antialiased;
    overflow-x: hidden;
  }

  body::before {
    content: "";
    position: fixed;
    inset: 0;
    z-index: -1;
    background:
      radial-gradient(ellipse 700px 500px at 20% 10%, rgba(235,124,42,.18), transparent 60%),
      radial-gradient(ellipse 500px 400px at 85% 85%, rgba(255,154,61,.10), transparent 55%),
      linear-gradient(160deg, #07070d 0%, #0c0c16 50%, #0a0a14 100%);
  }

  .login-container {
    width: 100%;
    max-width: 440px;
  }

  /* Brand Header */
  .login-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-bottom: 32px;
  }

  .login-brand-logo {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, var(--orange), var(--orange-2));
    color: #1a0e05;
    box-shadow: 0 8px 28px rgba(235,124,42,.4);
  }

  .login-brand-logo svg { width: 28px; height: 28px; }

  .login-brand-text {
    display: flex;
    flex-direction: column;
  }

  .login-brand-text b {
    font-size: 17px;
    font-weight: 800;
    color: var(--text);
  }

  .login-brand-text small {
    font-size: 12px;
    color: var(--text-dim);
    margin-top: 2px;
  }

  /* Card */
  .login-card {
    background: linear-gradient(160deg, rgba(255,255,255,.08), rgba(255,255,255,.015) 60%);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    box-shadow: 0 8px 32px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.08);
    padding: 36px 28px 32px;
    position: relative;
    overflow: hidden;
  }

  .login-card::before {
    content: "";
    position: absolute;
    top: 0; right: 0; left: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.28), transparent);
    pointer-events: none;
  }

  /* Icon */
  .login-icon {
    width: 72px;
    height: 72px;
    border-radius: 20px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, var(--orange), var(--orange-2));
    color: #1a0e05;
    margin: 0 auto 20px;
    box-shadow: 0 12px 40px rgba(235,124,42,.35);
  }

  .login-icon svg { width: 36px; height: 36px; }

  /* Title */
  .login-title {
    text-align: center;
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 6px;
    background: linear-gradient(135deg, var(--orange), var(--orange-2));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
  }

  .login-subtitle {
    text-align: center;
    color: var(--text-dim);
    font-size: 13.5px;
    margin-bottom: 28px;
    line-height: 1.7;
  }

  /* Alert */
  .alert {
    padding: 12px 14px;
    border-radius: var(--radius-sm);
    margin-bottom: 18px;
    font-size: 13px;
    border: 1px solid;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .alert-error {
    background: rgba(255,84,112,.10);
    border-color: rgba(255,84,112,.3);
    color: #ffc1cc;
  }

  /* Form */
  .login-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .form-label {
    font-size: 13px;
    color: var(--text-dim);
    font-weight: 500;
  }

  .form-input {
    width: 100%;
    padding: 13px 16px;
    background: rgba(0,0,0,.3);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: inherit;
    font-size: 15px;
    transition: all .2s;
    direction: ltr;
    text-align: left;
  }

  .form-input::placeholder {
    color: var(--text-dim);
    opacity: .5;
  }

  .form-input:focus {
    outline: none;
    border-color: var(--orange);
    box-shadow: 0 0 0 3px rgba(235,124,42,.15);
    background: rgba(0,0,0,.4);
  }

  /* Submit Button */
  .btn-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 14px 24px;
    border-radius: var(--radius-sm);
    border: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 15px;
    font-weight: 700;
    background: linear-gradient(135deg, var(--orange), var(--orange-2));
    color: #1a0e05;
    box-shadow: 0 8px 24px rgba(235,124,42,.4), inset 0 1px 0 rgba(255,255,255,.3);
    transition: all .2s;
    margin-top: 4px;
  }

  .btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(235,124,42,.55), inset 0 1px 0 rgba(255,255,255,.3);
  }

  .btn-submit:active {
    transform: translateY(0);
  }

  .btn-submit svg {
    width: 18px;
    height: 18px;
  }

  /* Back Link */
  .login-back {
    text-align: center;
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1px solid var(--border);
  }

  .login-back a {
    color: var(--text-dim);
    font-size: 13px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: .15s;
  }

  .login-back a:hover {
    color: var(--orange);
  }

  .login-back a svg {
    width: 14px;
    height: 14px;
  }

  /* Responsive */
  @media (max-width: 480px) {
    body { padding: 16px 10px; }
    .login-card { padding: 28px 20px 24px; }
    .login-title { font-size: 20px; }
    .login-icon { width: 60px; height: 60px; border-radius: 16px; }
    .login-icon svg { width: 28px; height: 28px; }
    .login-brand { margin-bottom: 24px; }
    .login-brand-logo { width: 44px; height: 44px; border-radius: 14px; }
    .login-brand-text b { font-size: 15px; }
  }
</style>
</head>
<body>

<div class="login-container">

  <!-- Brand -->
  <div class="login-brand">
    <div class="login-brand-logo">
      <?= icon('shield') ?>
    </div>
    <div class="login-brand-text">
      <b>پنل مدیریت</b>
      <small>دانش‌یار</small>
    </div>
  </div>

  <!-- Card -->
  <div class="login-card">
    <div class="login-icon"><?= icon('lock') ?></div>
    <h1 class="login-title">ورود مدیر سیستم</h1>
    <p class="login-subtitle">این بخش از حساب کاربری دانش‌آموزان جداست.</p>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="flex-shrink:0">
          <circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6" stroke-linecap="round"/>
        </svg>
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="login-form">
      <div class="form-group">
        <label class="form-label" for="username">نام کاربری</label>
        <input class="form-input" id="username" name="username" required autocomplete="username" placeholder="username">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">رمز عبور</label>
        <input class="form-input" id="password" name="password" type="password" required autocomplete="current-password" placeholder="password">
      </div>
      <button type="submit" class="btn-submit">
        <?= icon('login') ?>
        ورود به پنل مدیریت
      </button>
    </form>

    <div class="login-back">
      <a href="<?= BASE_URL ?>/">
        <?= icon('home') ?>
        بازگشت به سایت اصلی
      </a>
    </div>
  </div>

</div>

</body>
</html>
