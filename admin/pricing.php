<?php
$adminPage = 'pricing';
$pageTitle = 'مدیریت قیمت‌ها';
include __DIR__ . '/_header.php';

$msg = '';
$msgType = 'success';

// ─── Toggle فروش (ذخیره در دیتابیس تا فرانت هم اعمال شود) ───
if (isset($_POST['toggle_sales'])) {
    $newOn = !sales_enabled();
    if (set_sales_enabled($newOn)) {
        $msg = $newOn ? '✓ فروش اشتراک فعال شد.' : '✓ فروش اشتراک غیرفعال شد. (صفحه قیمت و پرداخت برای کاربران بسته شد)';
    } else {
        $msg = '❌ ذخیره وضعیت فروش ناموفق بود.';
        $msgType = 'error';
    }
}

$salesOn = sales_enabled();

// ─── تخفیف همگانی ───
if (isset($_POST['save_global_discount'])) {
    $g = (int)($_POST['global_discount_percent'] ?? 0);
    $g = max(0, min(100, $g));
    set_setting('global_discount_percent', (string)$g);
    $msg = $g > 0
        ? '✓ تخفیف همگانی ' . num_fa($g) . '٪ روی همه کاربران اعمال شد.'
        : '✓ تخفیف همگانی غیرفعال شد.';
}
if (isset($_POST['clear_global_discount'])) {
    set_setting('global_discount_percent', '0');
    $msg = '✓ تخفیف همگانی حذف شد. (تخفیف‌های اختصاصی کاربران دست‌نخورده باقی ماند)';
}
// ─── حذف کامل همه تخفیف‌ها (همگانی + همه اختصاصی‌ها) ───
if (isset($_POST['reset_all_discounts'])) {
    set_setting('global_discount_percent', '0');
    $deleted = 0;
    try {
        $deleted = db()->exec("DELETE FROM user_discounts");
    } catch (Throwable $e) {}
    $msg = '✓ همه تخفیف‌ها صفر شد: تخفیف همگانی حذف و ' . num_fa((int)$deleted) . ' تخفیف اختصاصی پاک شد.';
}
$globalDiscount = (int)get_setting('global_discount_percent', '0');
try {
    $personalDiscountCount = (int)db()->query("SELECT COUNT(*) FROM user_discounts WHERE discount_percent > 0")->fetchColumn();
} catch (Throwable $e) { $personalDiscountCount = 0; }

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

<!-- ═══ تخفیف همگانی ═══ -->
<div class="admin-card glass" style="margin-top:16px; border-right:3px solid <?= $globalDiscount > 0 ? 'var(--success)' : 'var(--border)' ?>">
  <div class="admin-card-header">
    <h3><?= icon('sparkle') ?> تخفیف همگانی (همه کاربران)</h3>
  </div>
  <div class="admin-card-body">
    <p class="text-sm text-dim" style="margin-bottom:14px">
      این تخفیف روی <b>همه پلن‌ها برای همه کاربران</b> اعمال می‌شود.
      اگر کاربری <b>تخفیف اختصاصی</b> داشته باشد، تخفیف اختصاصی او اولویت دارد و این تخفیف برای او اعمال نمی‌شود.
      <?php if ($globalDiscount > 0): ?>
        <br><b style="color:var(--success)">هم‌اکنون فعال: <?= num_fa($globalDiscount) ?>٪</b>
      <?php else: ?>
        <br>هم‌اکنون: <b>غیرفعال</b>
      <?php endif; ?>
    </p>
    <form method="post" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap">
      <div class="form-group" style="min-width:140px; margin:0">
        <label class="form-label">درصد تخفیف همگانی (%)</label>
        <input class="input" type="number" name="global_discount_percent" min="0" max="100"
               value="<?= e($globalDiscount) ?>" placeholder="مثلا 20">
      </div>
      <button class="btn btn-primary" name="save_global_discount" value="1"><?= icon('check') ?> اعمال تخفیف همگانی</button>
      <?php if ($globalDiscount > 0): ?>
        <button class="btn btn-danger" name="clear_global_discount" value="1"
                onclick="return confirm('تخفیف همگانی حذف شود؟ (تخفیف‌های اختصاصی دست‌نخورده می‌مانند)')">
          <?= icon('trash') ?> حذف تخفیف همگانی
        </button>
      <?php endif; ?>
    </form>

    <!-- حذف کامل همه تخفیف‌ها -->
    <div style="margin-top:18px; padding-top:16px; border-top:1px dashed var(--border)">
      <p class="text-sm text-dim" style="margin-bottom:10px">
        <b style="color:var(--danger)">حذف کامل همه تخفیف‌ها:</b>
        این دکمه هم تخفیف همگانی و هم <b>همه تخفیف‌های اختصاصی کاربران</b> را یکجا صفر می‌کند.
        <?php if ($personalDiscountCount > 0): ?>
          (الان <?= num_fa($personalDiscountCount) ?> تخفیف اختصاصی فعال است.)
        <?php endif; ?>
      </p>
      <form method="post" onsubmit="return confirm('مطمئنی؟ همه تخفیف‌ها (همگانی و همه اختصاصی‌ها) صفر می‌شوند و قابل بازگشت نیست.')">
        <button class="btn btn-danger" name="reset_all_discounts" value="1">
          <?= icon('trash') ?> صفر کردن همه تخفیف‌ها (همگانی + اختصاصی)
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
