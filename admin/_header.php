<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';
require_admin();
$adminPage = $adminPage ?? '';

// شمارش رسیدهای در انتظار
$pendingReceipts = 0;
try {
    $pendingReceipts = (int)db()->query("SELECT COUNT(*) FROM card_receipts WHERE status='pending'")->fetchColumn();
} catch (Throwable $e) {}
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'پنل مدیریت') ?> | دانش‌یار</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fonts/vazirmatn.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=3">
<style>
  .admin-layout { display: grid; grid-template-columns: 1fr; gap: 14px; margin-top: 16px; }
  @media (min-width: 900px) {
    .admin-layout { grid-template-columns: 240px 1fr; min-height: calc(100vh - 100px); }
  }
  .admin-sidebar { padding: 16px; }
  .admin-sidebar h3 { font-size: 12px; color: var(--text-dim); margin-bottom: 12px; padding: 0 4px; }
  .admin-sidebar a {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 14px; border-radius: 11px;
    color: var(--text-dim); margin-bottom: 4px; font-size: 13.5px;
    transition: .2s; text-decoration: none; position: relative;
  }
  .admin-sidebar a .ico { width: 17px; height: 17px; }
  .admin-sidebar a:hover { background: var(--glass); color: var(--text); }
  .admin-sidebar a.active {
    background: var(--orange-soft); color: var(--orange);
    border: 1px solid rgba(235,124,42,.25);
  }
  .admin-main { padding: 22px; min-height: 500px; }

  .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 22px; }
  @media (min-width: 720px) { .stat-grid { grid-template-columns: repeat(4,1fr); } }
  .stat-card {
    padding: 18px 16px;
    display: flex; align-items: center; gap: 14px;
  }
  .stat-card .s-icon {
    width: 46px; height: 46px; border-radius: 12px;
    background: var(--orange-soft); border: 1px solid var(--border-orange);
    display: grid; place-items: center; color: var(--orange); flex-shrink: 0;
  }
  .stat-card .s-icon .ico { width: 22px; height: 22px; }
  .stat-card .v { font-size: 22px; font-weight: 800; color: var(--text); }
  .stat-card .l { font-size: 11px; color: var(--text-dim); }

  table.admin-table { width: 100%; border-collapse: collapse; }
  table.admin-table th, table.admin-table td {
    padding: 11px 12px; text-align: right;
    border-bottom: 1px solid var(--border); font-size: 13px;
  }
  table.admin-table th {
    color: var(--orange); font-weight: 700;
    background: rgba(0,0,0,.25); position: sticky; top: 0;
  }
  table.admin-table tr:hover { background: var(--glass); }

  .badge-pending {
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--danger); color: #fff; border-radius: 50%;
    width: 18px; height: 18px; font-size: 10px; font-weight: 800;
    position: absolute; top: 6px; left: 6px; line-height: 1;
  }
</style>
</head>
<body>
<div class="container">
  <nav class="navbar glass">
    <a href="<?= BASE_URL ?>/admin/" class="brand">
      <div class="brand-logo" style="display:grid;place-items:center;background:var(--orange-soft)"><?= icon('shield') ?></div>
      <span>پنل مدیریت دانش‌یار</span>
    </a>
    <div class="nav-links">
      <a href="<?= BASE_URL ?>/"><?= icon('home') ?><span>سایت</span></a>
      <a href="<?= BASE_URL ?>/admin/logout.php"><?= icon('logout') ?><span>خروج</span></a>
    </div>
  </nav>

  <div class="admin-layout">
    <aside class="admin-sidebar glass">
      <h3>منو</h3>
      <a href="<?= BASE_URL ?>/admin/" class="<?= $adminPage==='dashboard'?'active':'' ?>"><?= icon('graph') ?> داشبورد</a>
      <a href="<?= BASE_URL ?>/admin/users.php" class="<?= $adminPage==='users'?'active':'' ?>"><?= icon('users') ?> کاربران</a>
      <a href="<?= BASE_URL ?>/admin/books.php" class="<?= $adminPage==='books'?'active':'' ?>"><?= icon('book') ?> کتاب‌ها</a>
      <a href="<?= BASE_URL ?>/admin/receipts.php" class="<?= $adminPage==='receipts'?'active':'' ?>" style="position:relative">
        <?= icon('wallet') ?> رسیدهای پرداخت
        <?php if ($pendingReceipts > 0): ?>
          <span class="badge-pending"><?= $pendingReceipts > 9 ? '9+' : $pendingReceipts ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>/admin/messages.php" class="<?= $adminPage==='messages'?'active':'' ?>"><?= icon('mail') ?> پیام‌ها</a>
      <a href="<?= BASE_URL ?>/admin/pricing.php" class="<?= $adminPage==='pricing'?'active':'' ?>"><?= icon('price') ?> قیمت‌ها</a>
      <a href="<?= BASE_URL ?>/admin/transactions.php" class="<?= $adminPage==='trx'?'active':'' ?>"><?= icon('graph') ?> تراکنش‌ها</a>
      <a href="<?= BASE_URL ?>/admin/updater.php" class="<?= $adminPage==='updater'?'active':'' ?>"><?= icon('upload') ?> بروزرسانی فایل‌ها</a>
    </aside>

    <main class="admin-main glass">
