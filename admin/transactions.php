<?php
$adminPage = 'trx';
$pageTitle = 'تراکنش‌ها';
include __DIR__ . '/_header.php';

$trx = db()->query("SELECT t.*, u.first_name, u.last_name, u.mobile FROM transactions t LEFT JOIN users u ON u.id=t.user_id ORDER BY t.id DESC LIMIT 200")->fetchAll();
$total = (int)db()->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='paid'")->fetchColumn();
?>

<div class="admin-page-header">
  <h2><?= icon('wallet') ?> تراکنش‌ها</h2>
</div>

<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))">
  <div class="stat-card glass">
    <div class="s-icon" style="background:rgba(56,217,169,.12);border-color:rgba(56,217,169,.2);color:var(--success)"><?= icon('wallet') ?></div>
    <div>
      <div class="l">مجموع درآمد</div>
      <div class="v" style="color:var(--orange)"><?= format_price($total) ?></div>
    </div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('graph') ?></div>
    <div>
      <div class="l">تعداد تراکنش</div>
      <div class="v"><?= num_fa(count($trx)) ?></div>
    </div>
  </div>
</div>

<div class="admin-card glass">
  <div class="admin-card-body" style="padding:0">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>#</th><th>کاربر</th><th>موبایل</th><th>پلن</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
        <tbody>
        <?php foreach ($trx as $t): ?>
          <tr>
            <td class="text-muted"><?= num_fa($t['id']) ?></td>
            <td><?= e(($t['first_name']??'').' '.($t['last_name']??'')) ?></td>
            <td dir="ltr" class="font-mono text-sm"><?= e($t['mobile']??'-') ?></td>
            <td class="font-mono text-sm"><?= e($t['plan_code']) ?></td>
            <td style="font-weight:700"><?= format_price($t['amount']) ?></td>
            <td><span class="status-badge <?= $t['status']==='paid'?'status-approved':'status-pending' ?>"><?= e($t['status']) ?></span></td>
            <td class="text-sm text-dim"><?= num_fa(date('Y/m/d H:i', strtotime($t['created_at']))) ?></td>
          </tr>
        <?php endforeach; if (!$trx): ?>
          <tr><td colspan="7" class="admin-empty">هنوز تراکنشی ثبت نشده.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/_footer.php'; ?>