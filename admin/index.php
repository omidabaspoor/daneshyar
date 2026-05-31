<?php
$adminPage = 'dashboard';
$pageTitle = 'داشبورد مدیریت';
include __DIR__ . '/_header.php';

$stats = [
    'users'       => (int)db()->query("SELECT COUNT(*) FROM users WHERE role!='admin'")->fetchColumn(),
    'messages'    => (int)db()->query("SELECT COUNT(*) FROM chat_history")->fetchColumn(),
    'active_subs' => (int)db()->query("SELECT COUNT(*) FROM users WHERE subscription_end > NOW()")->fetchColumn(),
    'revenue'     => (int)db()->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='paid'")->fetchColumn(),
    'books'       => (int)db()->query("SELECT COUNT(*) FROM books")->fetchColumn(),
];

$recentUsers = db()->query("SELECT id, first_name, last_name, mobile, grade, major, created_at FROM users WHERE role!='admin' ORDER BY id DESC LIMIT 8")->fetchAll();
$recentTrx   = db()->query("SELECT t.*, u.first_name, u.last_name FROM transactions t LEFT JOIN users u ON u.id=t.user_id ORDER BY t.id DESC LIMIT 8")->fetchAll();
?>

<h2 style="margin-bottom:18px; display:flex; align-items:center; gap:10px">
  <?= icon('graph') ?> خلاصه آمار
</h2>

<div class="stat-grid">
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('users') ?></div>
    <div><div class="l">کاربران</div><div class="v"><?= num_fa($stats['users']) ?></div></div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('chat') ?></div>
    <div><div class="l">پیام‌ها</div><div class="v"><?= num_fa($stats['messages']) ?></div></div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('crown') ?></div>
    <div><div class="l">اشتراک فعال</div><div class="v"><?= num_fa($stats['active_subs']) ?></div></div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('wallet') ?></div>
    <div><div class="l">درآمد</div><div class="v"><?= format_price($stats['revenue']) ?></div></div>
  </div>
</div>

<div style="display:grid; grid-template-columns:1fr; gap:16px">
  <style>
    @media (min-width:900px) { .dash-cols { display:grid; grid-template-columns:1fr 1fr; gap:16px; } }
  </style>
  <div class="dash-cols">
    <div class="glass" style="padding:18px">
      <h3 style="color:var(--orange); margin-bottom:12px; display:flex; align-items:center; gap:8px"><?= icon('users') ?> آخرین کاربران</h3>
      <table class="admin-table">
        <thead><tr><th>نام</th><th>موبایل</th><th>پایه/رشته</th></tr></thead>
        <tbody>
        <?php foreach ($recentUsers as $u): ?>
          <tr>
            <td><?= e($u['first_name'].' '.$u['last_name']) ?></td>
            <td dir="ltr"><?= e($u['mobile']) ?></td>
            <td><?= num_fa($u['grade']) ?><br><small style="color:var(--text-dim)"><?= e(major_label($u['major'] ?? 'math')) ?></small></td>
          </tr>
        <?php endforeach; if (!$recentUsers): ?><tr><td colspan="3" style="text-align:center;color:var(--text-dim)">هنوز کاربری ثبت نشده</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="glass" style="padding:18px">
      <h3 style="color:var(--orange); margin-bottom:12px; display:flex; align-items:center; gap:8px"><?= icon('wallet') ?> آخرین تراکنش‌ها</h3>
      <table class="admin-table">
        <thead><tr><th>کاربر</th><th>پلن</th><th>مبلغ</th></tr></thead>
        <tbody>
        <?php foreach ($recentTrx as $t): ?>
          <tr>
            <td><?= e(($t['first_name']??'') . ' ' . ($t['last_name']??'')) ?></td>
            <td><?= e($t['plan_code']) ?></td>
            <td><?= format_price($t['amount']) ?></td>
          </tr>
        <?php endforeach; if (!$recentTrx): ?><tr><td colspan="3" style="text-align:center;color:var(--text-dim)">هنوز تراکنشی ثبت نشده</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="glass" style="padding:18px">
    <h3 style="color:var(--orange); margin-bottom:12px; display:flex; align-items:center; gap:8px"><?= icon('book') ?> وضعیت محتوا</h3>
    <p style="color:var(--text-dim); font-size:13px">تعداد کتاب‌های فعال در سیستم: <b style="color:var(--text); font-size:16px"><?= num_fa($stats['books']) ?></b> کتاب</p>
    <a href="<?= BASE_URL ?>/admin/books.php" class="btn btn-primary btn-sm" style="margin-top:10px"><?= icon('plus') ?> افزودن کتاب جدید</a>
  </div>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
