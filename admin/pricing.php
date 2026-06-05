<?php
$adminPage = 'pricing';
$pageTitle = 'مدیریت قیمت‌ها';
include __DIR__ . '/_header.php';

$msg = '';
$msgType = 'success';

// ─── Toggle فروش ───
if (isset($_POST['toggle_sales'])) {
    $envPath = ROOT_PATH . '/.env';
    $current = SALES_ENABLED;
    $newVal  = $current ? 'false' : 'true';

    if (is_file($envPath) && is_writable($envPath)) {
        $content = file_get_contents($envPath);
        if (preg_match('/^SALES_ENABLED\s*=/m', $content)) {
            $content = preg_replace('/^SALES_ENABLED\s*=.*/m', 'SALES_ENABLED=' . $newVal, $content);
        } else {
            $content .= "\nSALES_ENABLED=" . $newVal . "\n";
        }
        file_put_contents($envPath, $content);
        putenv("SALES_ENABLED=$newVal");
        $_ENV['SALES_ENABLED'] = $newVal;
        $msg = $newVal === 'true' ? '✓ فروش اشتراک فعال شد.' : '✓ فروش اشتراک غیرفعال شد.';
    } else {
        $msg = '❌ فایل .env قابل نوشتن نیست. دسترسی فایل رو بررسی کن.';
        $msgType = 'error';
    }
}

$salesOn = env('SALES_ENABLED', true);
if (is_string($salesOn)) $salesOn = !in_array(strtolower($salesOn), ['false', '0', 'no', ''], true);

// ─── ذخیره قیمت‌ها ───
if (isset($_POST['plans'])) {
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

<div class="admin-page-header">
  <h2><?= icon('price') ?> مدیریت فروش و قیمت‌ها</h2>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<!-- ═══ کارت وضعیت فروش ═══ -->
<div class="admin-card glass" style="border-right:3px solid <?= $salesOn ? 'var(--success)' : 'var(--danger)' ?>">
  <div class="admin-card-body">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap">
      <div style="display:flex; align-items:center; gap:14px">
        <div style="width:52px;height:52px;border-radius:14px;display:grid;place-items:center;
          background:<?= $salesOn ? 'rgba(56,217,169,.12)' : 'rgba(255,84,112,.12)' ?>;
          color:<?= $salesOn ? 'var(--success)' : 'var(--danger)' ?>">
          <?php if ($salesOn): ?>
            <?= icon('check') ?>
          <?php else: ?>
            <?= icon('close') ?>
          <?php endif; ?>
        </div>
        <div>
          <div style="font-weight:800; font-size:16px; color:<?= $salesOn ? 'var(--success)' : 'var(--danger)' ?>">
            فروش اشتراک <?= $salesOn ? 'فعال' : 'غیرفعال' ?> است
          </div>
          <div class="text-sm text-dim" style="margin-top:4px">
            <?php if ($salesOn): ?>
              کاربران می‌توانند اشتراک بخرند. صفحه قیمت‌ها و پرداخت فعال است.
            <?php else: ?>
              صفحه قیمت‌ها و پرداخت برای کاربران بسته شده. فقط پلن رایگان فعاله.
            <?php endif; ?>
          </div>
        </div>
      </div>
      <form method="post">
        <input type="hidden" name="toggle_sales" value="1">
        <button type="submit" class="btn <?= $salesOn ? 'btn-danger' : 'btn-primary' ?>"
          onclick="return confirm('<?= $salesOn ? 'فروش غیرفعال بشه؟' : 'فروش فعال بشه؟' ?>')">
          <?= icon($salesOn ? 'close' : 'check') ?>
          <?= $salesOn ? 'غیرفعال کردن فروش' : 'فعال کردن فروش' ?>
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ═══ جدول قیمت‌ها ═══ -->
<div class="admin-card glass" style="margin-top:16px">
  <div class="admin-card-header">
    <h3><?= icon('price') ?> پلن‌های اشتراک</h3>
  </div>
  <div class="admin-card-body" style="padding:0">
    <form method="post">
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>کد</th>
              <th>عنوان</th>
              <th>قیمت (تومان)</th>
              <th>سقف روزانه</th>
              <th>سقف کل</th>
              <th>مدت (ساعت)</th>
              <th>توضیح</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($plans as $p): ?>
            <tr>
              <td><b class="font-mono text-sm"><?= e($p['plan_code']) ?></b></td>
              <td><input class="input" name="plans[<?= $p['plan_code'] ?>][title]" value="<?= e($p['title']) ?>" style="padding:7px 10px; font-size:13px"></td>
              <td><input class="input" name="plans[<?= $p['plan_code'] ?>][price]" type="number" value="<?= e($p['price']) ?>" style="padding:7px 10px; font-size:13px; width:110px"></td>
              <td><input class="input" name="plans[<?= $p['plan_code'] ?>][daily_limit]" type="number" value="<?= e($p['daily_limit']) ?>" style="padding:7px 10px; font-size:13px; width:90px"></td>
              <td><input class="input" name="plans[<?= $p['plan_code'] ?>][total_limit]" type="number" value="<?= e($p['total_limit']) ?>" style="padding:7px 10px; font-size:13px; width:90px"></td>
              <td><input class="input" name="plans[<?= $p['plan_code'] ?>][duration_hours]" type="number" value="<?= e($p['duration_hours']) ?>" style="padding:7px 10px; font-size:13px; width:90px"></td>
              <td><input class="input" name="plans[<?= $p['plan_code'] ?>][description]" value="<?= e($p['description']) ?>" style="padding:7px 10px; font-size:13px"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:14px 18px; border-top:1px solid var(--border)">
        <button class="btn btn-primary"><?= icon('check') ?> ذخیره تغییرات</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
