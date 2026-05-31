<?php
$adminPage = 'pricing';
$pageTitle = 'مدیریت قیمت‌ها';
include __DIR__ . '/_header.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    foreach (($_POST['plans'] ?? []) as $code => $data) {
        $title = trim($data['title'] ?? '');
        $price = (int)($data['price'] ?? 0);
        $dl    = (int)($data['daily_limit'] ?? 0);
        $tl    = (int)($data['total_limit'] ?? 0);
        $dur   = (int)($data['duration_hours'] ?? 0);
        $desc  = trim($data['description'] ?? '');
        db()->prepare("UPDATE pricing SET title=?, price=?, daily_limit=?, total_limit=?, duration_hours=?, description=? WHERE plan_code=?")
            ->execute([$title,$price,$dl,$tl,$dur,$desc,$code]);
    }
    $msg = '✓ قیمت‌ها بروز شد.';
}
$plans = db()->query("SELECT * FROM pricing ORDER BY price")->fetchAll();
?>
<h2 style="margin-bottom:14px">💰 قیمت اشتراک‌ها</h2>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<form method="post">
  <div style="overflow-x:auto">
  <table class="admin-table">
    <thead><tr><th>کد</th><th>عنوان</th><th>قیمت (تومان)</th><th>سقف روزانه</th><th>سقف کل</th><th>مدت (ساعت)</th><th>توضیح</th></tr></thead>
    <tbody>
    <?php foreach ($plans as $p): ?>
      <tr>
        <td><b><?= e($p['plan_code']) ?></b></td>
        <td><input class="input" name="plans[<?= $p['plan_code'] ?>][title]" value="<?= e($p['title']) ?>"></td>
        <td><input class="input" name="plans[<?= $p['plan_code'] ?>][price]" type="number" value="<?= e($p['price']) ?>"></td>
        <td><input class="input" name="plans[<?= $p['plan_code'] ?>][daily_limit]" type="number" value="<?= e($p['daily_limit']) ?>"></td>
        <td><input class="input" name="plans[<?= $p['plan_code'] ?>][total_limit]" type="number" value="<?= e($p['total_limit']) ?>"></td>
        <td><input class="input" name="plans[<?= $p['plan_code'] ?>][duration_hours]" type="number" value="<?= e($p['duration_hours']) ?>"></td>
        <td><input class="input" name="plans[<?= $p['plan_code'] ?>][description]" value="<?= e($p['description']) ?>"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <button class="btn btn-primary" style="margin-top:14px">ذخیره تغییرات</button>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
