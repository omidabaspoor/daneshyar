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

<div class="admin-page-header">
  <h2><?= icon('graph') ?> خلاصه آمار</h2>
  <div class="admin-breadcrumb">
    <a href="<?= BASE_URL ?>/admin/">خانه</a>
    <span class="sep">/</span>
    <span>داشبورد</span>
  </div>
</div>

<!-- ═══ Stat Cards ═══ -->
<div class="stat-grid">
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('users') ?></div>
    <div>
      <div class="v"><?= num_fa($stats['users']) ?></div>
      <div class="l">کاربران</div>
    </div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('chat') ?></div>
    <div>
      <div class="v"><?= num_fa($stats['messages']) ?></div>
      <div class="l">پیام‌ها</div>
    </div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('crown') ?></div>
    <div>
      <div class="v"><?= num_fa($stats['active_subs']) ?></div>
      <div class="l">اشتراک فعال</div>
    </div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('wallet') ?></div>
    <div>
      <div class="v"><?= format_price($stats['revenue']) ?></div>
      <div class="l">درآمد (تومان)</div>
    </div>
  </div>
</div>

<!-- ═══ Dashboard Grid ═══ -->
<div class="dash-grid">

  <!-- آخرین کاربران -->
  <div class="admin-card glass">
    <div class="admin-card-header">
      <h3><?= icon('users') ?> آخرین کاربران</h3>
      <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-ghost btn-sm">مشاهده همه</a>
    </div>
    <div class="admin-card-body" style="padding:0">
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>#</th><th>نام</th><th>موبایل</th><th>پایه/رشته</th></tr>
          </thead>
          <tbody>
          <?php foreach ($recentUsers as $u): ?>
            <tr>
              <td style="color:var(--text-muted)"><?= num_fa($u['id']) ?></td>
              <td>
                <a href="<?= BASE_URL ?>/admin/user.php?id=<?= $u['id'] ?>" style="font-weight:700; color:var(--text); text-decoration:none"><?= e($u['first_name'].' '.$u['last_name']) ?></a>
              </td>
              <td dir="ltr" class="font-mono text-sm"><?= e($u['mobile']) ?></td>
              <td>
                <span class="text-sm">پایه <?= num_fa($u['grade']) ?></span>
                <br><small class="text-dim"><?= e(major_label($u['major'] ?? 'math')) ?></small>
              </td>
            </tr>
          <?php endforeach; if (!$recentUsers): ?>
            <tr><td colspan="4" class="admin-empty">هنوز کاربری ثبت نشده</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- آخرین تراکنش‌ها -->
  <div class="admin-card glass">
    <div class="admin-card-header">
      <h3><?= icon('wallet') ?> آخرین تراکنش‌ها</h3>
      <a href="<?= BASE_URL ?>/admin/transactions.php" class="btn btn-ghost btn-sm">مشاهده همه</a>
    </div>
    <div class="admin-card-body" style="padding:0">
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>کاربر</th><th>پلن</th><th>مبلغ</th><th>وضعیت</th></tr>
          </thead>
          <tbody>
          <?php foreach ($recentTrx as $t): ?>
            <tr>
              <td><?= e(($t['first_name']??'') . ' ' . ($t['last_name']??'')) ?></td>
              <td class="font-mono text-sm"><?= e($t['plan_code']) ?></td>
              <td style="font-weight:700"><?= format_price($t['amount']) ?></td>
              <td>
                <?php if ($t['status'] === 'paid'): ?>
                  <span class="status-badge status-approved">پرداخت شده</span>
                <?php else: ?>
                  <span class="status-badge status-pending"><?= e($t['status']) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; if (!$recentTrx): ?>
            <tr><td colspan="4" class="admin-empty">هنوز تراکنشی ثبت نشده</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- وضعیت محتوا -->
  <div class="admin-card glass dash-full">
    <div class="admin-card-header">
      <h3><?= icon('book') ?> وضعیت محتوا</h3>
    </div>
    <div class="admin-card-body">
      <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap">
        <div>
          <p class="text-dim text-sm" style="margin-bottom:4px">تعداد کتاب‌های فعال در سیستم</p>
          <p style="font-size:28px; font-weight:800; color:var(--orange)"><?= num_fa($stats['books']) ?> <span class="text-sm text-dim" style="font-weight:400">کتاب</span></p>
        </div>
        <a href="<?= BASE_URL ?>/admin/books.php" class="btn btn-primary"><?= icon('plus') ?> افزودن کتاب جدید</a>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/_footer.php'; ?>
