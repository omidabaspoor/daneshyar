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
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
<title><?= e($pageTitle ?? 'پنل مدیریت') ?> | دانش‌یار</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fonts/vazirmatn.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=3">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css?v=1">
</head>
<body>

<!-- ═══ Sidebar Overlay (Mobile) ═══ -->
<div class="admin-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ═══ Sidebar Drawer (Mobile) ═══ -->
<aside class="admin-sidebar-drawer" id="sidebarDrawer">
  <div class="admin-drawer-head">
    <a href="<?= BASE_URL ?>/admin/" class="admin-brand">
      <div class="admin-brand-logo"><?= icon('shield') ?></div>
      <div class="admin-brand-text">
        <span>پنل مدیریت</span>
        <small>دانش‌یار</small>
      </div>
    </a>
    <button class="admin-drawer-close" onclick="closeSidebar()" aria-label="بستن منو">
      <?= icon('close') ?>
    </button>
  </div>

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
  <a href="<?= BASE_URL ?>/admin/announcements.php" class="<?= $adminPage==='announcements'?'active':'' ?>"><?= icon('info') ?> اعلان همگانی</a>
  <a href="<?= BASE_URL ?>/admin/transactions.php" class="<?= $adminPage==='trx'?'active':'' ?>"><?= icon('graph') ?> تراکنش‌ها</a>
  <a href="<?= BASE_URL ?>/admin/updater.php" class="<?= $adminPage==='updater'?'active':'' ?>"><?= icon('upload') ?> بروزرسانی فایل‌ها</a>
  <a href="<?= BASE_URL ?>/admin/test.php" class="<?= $adminPage==='test'?'active':'' ?>"><?= icon('check-circle') ?> تست سیستم</a>

  <div style="margin-top:auto; padding-top:16px; border-top:1px solid var(--border); display:flex; flex-direction:column; gap:6px;">
    <a href="<?= BASE_URL ?>/"><?= icon('home') ?> بازگشت به سایت</a>
    <a href="<?= BASE_URL ?>/admin/logout.php" style="color:var(--danger)"><?= icon('logout') ?> خروج</a>
  </div>
</aside>

<!-- ═══ Main Container ═══ -->
<div class="container">

  <!-- ═══ Navbar ═══ -->
  <header class="admin-navbar">
    <nav class="navbar">
      <div style="display:flex; align-items:center; gap:12px;">
        <button class="admin-sidebar-toggle" onclick="openSidebar()" aria-label="منو" style="display:none" id="sidebarToggle">
          <?= icon('menu') ?>
        </button>
        <a href="<?= BASE_URL ?>/admin/" class="admin-brand">
          <div class="admin-brand-logo"><?= icon('shield') ?></div>
          <div class="admin-brand-text">
            <span>پنل مدیریت دانش‌یار</span>
            <small>مدیریت و نظارت بر سیستم</small>
          </div>
        </a>
      </div>
      <div class="admin-nav-actions">
        <a href="<?= BASE_URL ?>/"><?= icon('home') ?><span>سایت</span></a>
        <a href="<?= BASE_URL ?>/admin/logout.php" style="color:var(--danger)"><?= icon('logout') ?><span>خروج</span></a>
      </div>
    </nav>
  </header>

  <!-- ═══ Layout ═══ -->
  <div class="admin-layout">

    <!-- ═══ Desktop Sidebar ═══ -->
    <aside class="admin-sidebar glass">
      <h3>منوی مدیریت</h3>
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
      <a href="<?= BASE_URL ?>/admin/announcements.php" class="<?= $adminPage==='announcements'?'active':'' ?>"><?= icon('info') ?> اعلان همگانی</a>
      <a href="<?= BASE_URL ?>/admin/transactions.php" class="<?= $adminPage==='trx'?'active':'' ?>"><?= icon('graph') ?> تراکنش‌ها</a>
      <a href="<?= BASE_URL ?>/admin/updater.php" class="<?= $adminPage==='updater'?'active':'' ?>"><?= icon('upload') ?> بروزرسانی فایل‌ها</a>
      <a href="<?= BASE_URL ?>/admin/test.php" class="<?= $adminPage==='test'?'active':'' ?>"><?= icon('check-circle') ?> تست سیستم</a>
    </aside>

    <!-- ═══ Main Content ═══ -->
    <main class="admin-main glass">

<style>
  @media (max-width: 899px) {
    #sidebarToggle { display: grid !important; }
  }
</style>

<script>
function openSidebar() {
  document.getElementById('sidebarDrawer').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  document.getElementById('sidebarDrawer').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('show');
  document.body.style.overflow = '';
}
</script>
