<?php
$adminPage = 'trx';
$pageTitle = 'تراکنش‌ها';
include __DIR__ . '/_header.php';

$trx = db()->query("SELECT t.*, u.first_name, u.last_name, u.mobile FROM transactions t LEFT JOIN users u ON u.id=t.user_id ORDER BY t.id DESC LIMIT 200")->fetchAll();
$total = (int)db()->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='paid'")->fetchColumn();
?>
<h2 style="margin-bottom:14px">💳 تراکنش‌ها</h2>
<div class="glass" style="padding:16px; margin-bottom:14px">
  <b>مجموع درآمد:</b> <span style="color:var(--orange); font-size:18px; font-weight:800"><?= format_price($total) ?></span> تومان
</div>
<div style="overflow-x:auto">
<table class="admin-table">
  <thead><tr><th>#</th><th>کاربر</th><th>موبایل</th><th>پلن</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
  <tbody>
  <?php foreach ($trx as $t): ?>
    <tr>
      <td><?= num_fa($t['id']) ?></td>
      <td><?= e(($t['first_name']??'').' '.($t['last_name']??'')) ?></td>
      <td dir="ltr"><?= e($t['mobile']??'-') ?></td>
      <td><?= e($t['plan_code']) ?></td>
      <td><?= format_price($t['amount']) ?></td>
      <td><?= e($t['status']) ?></td>
      <td><?= num_fa(date('Y/m/d H:i', strtotime($t['created_at']))) ?></td>
    </tr>
  <?php endforeach; if (!$trx): ?>
    <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-dim)">هنوز تراکنشی ثبت نشده.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
