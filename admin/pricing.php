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
        // بروزرسانی runtime
        putenv("SALES_ENABLED=$newVal");
        $_ENV['SALES_ENABLED'] = $newVal;
        $msg = $newVal === 'true' ? '✓ فروش اشتراک فعال شد.' : '✓ فروش اشتراک غیرفعال شد.';
    } else {
        $msg = '❌ فایل .env قابل نوشتن نیست. دسترسی فایل رو بررسی کن.';
        $msgType = 'error';
    }
}

// وضعیت فعلی (بعد از toggle احتمالی)
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

<h2 style="margin-bottom:18px">💰 مدیریت فروش و قیمت‌ها</h2>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><?= $msg ?></div>
<?php endif; ?>

<!-- ═══════ کارت وضعیت فروش ═══════ -->
<div class="sales-toggle-card <?= $salesOn ? 'sales-on' : 'sales-off' ?>">
  <div class="stc-content">
    <div class="stc-icon">
      <?php if ($salesOn): ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12l3 3 5-5"/></svg>
      <?php else: ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
      <?php endif; ?>
    </div>
    <div class="stc-info">
      <div class="stc-status"><?= $salesOn ? 'فروش اشتراک فعال است' : 'فروش اشتراک غیرفعال است' ?></div>
      <div class="stc-desc">
        <?php if ($salesOn): ?>
          کاربران می‌توانند اشتراک بخرند. صفحه قیمت‌ها و پرداخت فعال است.
        <?php else: ?>
          صفحه قیمت‌ها و پرداخت برای کاربران بسته شده. فقط پلن رایگان فعاله.
        <?php endif; ?>
      </div>
    </div>
    <form method="post" class="stc-form">
      <input type="hidden" name="toggle_sales" value="1">
      <button type="submit" class="stc-btn <?= $salesOn ? 'stc-btn-off' : 'stc-btn-on' ?>" onclick="return confirm('<?= $salesOn ? 'فروش غیرفعال بشه؟' : 'فروش فعال بشه؟' ?>')">
        <span class="stc-btn-dot"></span>
        <span><?= $salesOn ? 'غیرفعال کردن' : 'فعال کردن' ?></span>
      </button>
    </form>
  </div>
  <div class="stc-bar">
    <div class="stc-bar-fill"></div>
  </div>
</div>

<style>
/* ─── کارت وضعیت فروش ─── */
.sales-toggle-card {
  border-radius: 16px;
  padding: 0;
  margin-bottom: 24px;
  overflow: hidden;
  transition: all .3s;
}
.sales-toggle-card.sales-on {
  background: linear-gradient(135deg, rgba(56,217,169,.08), rgba(56,217,169,.02));
  border: 1px solid rgba(56,217,169,.25);
}
.sales-toggle-card.sales-off {
  background: linear-gradient(135deg, rgba(255,84,112,.08), rgba(255,84,112,.02));
  border: 1px solid rgba(255,84,112,.25);
}
.stc-content {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 24px;
  flex-wrap: wrap;
}
.stc-icon {
  width: 48px; height: 48px;
  border-radius: 14px;
  display: grid; place-items: center;
  flex-shrink: 0;
}
.stc-icon svg { width: 26px; height: 26px; }
.sales-on .stc-icon {
  background: rgba(56,217,169,.15);
  color: #38d9a9;
  box-shadow: 0 4px 16px rgba(56,217,169,.2);
}
.sales-off .stc-icon {
  background: rgba(255,84,112,.15);
  color: #ff5470;
  box-shadow: 0 4px 16px rgba(255,84,112,.2);
}
.stc-info { flex: 1; min-width: 200px; }
.stc-status {
  font-size: 16px; font-weight: 800;
  margin-bottom: 4px;
}
.sales-on .stc-status { color: #38d9a9; }
.sales-off .stc-status { color: #ff5470; }
.stc-desc {
  font-size: 13px; color: var(--text-dim);
  line-height: 1.6;
}
.stc-form { flex-shrink: 0; }
.stc-btn {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 22px;
  border-radius: 12px;
  border: none;
  font-size: 14px; font-weight: 700;
  cursor: pointer;
  transition: all .2s;
  font-family: inherit;
}
.stc-btn:hover { transform: scale(1.03); }
.stc-btn:active { transform: scale(.97); }
.stc-btn-off {
  background: rgba(255,84,112,.12);
  color: #ff5470;
  border: 1px solid rgba(255,84,112,.3);
}
.stc-btn-off:hover { background: rgba(255,84,112,.2); }
.stc-btn-on {
  background: rgba(56,217,169,.12);
  color: #38d9a9;
  border: 1px solid rgba(56,217,169,.3);
}
.stc-btn-on:hover { background: rgba(56,217,169,.2); }
.stc-btn-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  animation: stcPulse 2s infinite;
}
.stc-btn-off .stc-btn-dot { background: #ff5470; }
.stc-btn-on .stc-btn-dot { background: #38d9a9; }
@keyframes stcPulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: .5; transform: scale(.8); }
}
.stc-bar {
  height: 4px;
  background: rgba(255,255,255,.05);
}
.stc-bar-fill {
  height: 100%;
  transition: width .5s;
}
.sales-on .stc-bar-fill {
  width: 100%;
  background: linear-gradient(90deg, #38d9a9, #20c997);
}
.sales-off .stc-bar-fill {
  width: 100%;
  background: linear-gradient(90deg, #ff5470, #e03e52);
}
</style>

<!-- ═══════ جدول قیمت‌ها ═══════ -->
<div class="glass" style="padding:20px">
  <h3 style="color:var(--orange); margin-bottom:14px"><?= icon('price') ?> پلن‌های اشتراک</h3>
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
    <button class="btn btn-primary" style="margin-top:14px"><?= icon('check') ?> ذخیره تغییرات</button>
  </form>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
