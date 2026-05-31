<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
$page = 'pricing';
$pageTitle = 'قیمت اشتراک';
$seoTitle = 'قیمت اشتراک دانش‌یار | پلن‌های هوش مصنوعی آموزشی';

$plans = db()->query("SELECT * FROM pricing ORDER BY price ASC")->fetchAll();
$user  = current_user();
$sub   = $user ? subscription_status($user) : null;

include __DIR__ . '/includes/header.php';
?>

<div style="text-align:center; padding:30px 0 10px">
  <span class="hero-badge" style="margin-bottom:14px"><?= icon('crown') ?> پلن‌های ویژه</span>
  <h1 style="font-size:30px; margin-bottom:10px">پلن مناسب خودت رو انتخاب کن</h1>
  <p style="color:var(--text-dim)">پلن مناسب را انتخاب کن تا وسط درس و حل تمرین محدود نشی.</p>
</div>

<?php if ($user): ?>
  <div class="glass subscription-summary">
    <div>
      <b><?= $sub && $sub['active'] ? 'اشتراک فعال داری' : 'الان روی پلن رایگان هستی' ?></b>
      <p><?= $sub && $sub['active']
          ? 'باقی‌مانده: ' . time_left($user['subscription_end'])
          : 'پیام رایگان امروز: ' . num_fa(max(0, FREE_DAILY_LIMIT - (int)$user['free_used_today'])) . ' از ' . num_fa(FREE_DAILY_LIMIT) ?></p>
    </div>
    <a href="<?= BASE_URL ?>/chat.php" class="btn btn-ghost btn-sm"><?= icon('chat') ?> برگشت به چت</a>
  </div>
<?php endif; ?>

<div class="pricing-grid">
  <?php foreach ($plans as $p):
    $featured = $p['plan_code'] === 'weekly';

    // تخفیف اختصاصی
    $finalPrice  = $p['price'];
    $discountPct = 0;
    if ($user) {
        try {
            $ds = db()->prepare("SELECT discount_percent FROM user_discounts WHERE user_id=? AND plan_code=?");
            $ds->execute([$user['id'], $p['plan_code']]);
            $d = $ds->fetch();
            if ($d) {
                $discountPct = (int)$d['discount_percent'];
                $finalPrice  = (int)round($p['price'] * (1 - $discountPct / 100));
            }
        } catch (Throwable $e) {}
    }
  ?>
    <div class="price-card glass <?= $featured ? 'featured' : '' ?>">
      <?php if ($featured): ?><div class="badge">پیشنهاد ویژه</div><?php endif; ?>
      <h3><?= e($p['title']) ?></h3>

      <div class="price">
        <?php if ($discountPct > 0): ?>
          <span style="text-decoration:line-through; color:var(--text-dim); font-size:0.7em; margin-left:10px"><?= format_price($p['price']) ?></span>
          <span style="color:var(--success)"><?= format_price($finalPrice) ?></span>
        <?php else: ?>
          <?= format_price($finalPrice) ?>
        <?php endif; ?>
        <small>تومان</small>
      </div>

      <?php if ($discountPct > 0): ?>
        <div style="text-align:center; color:var(--success); font-size:11px; font-weight:800; margin-bottom:10px">
          <?= icon('sparkle') ?> تخفیف اختصاصی <?= num_fa($discountPct) ?>%
        </div>
      <?php endif; ?>

      <ul>
        <?php if ($p['total_limit'] > 0): ?><li><?= num_fa($p['total_limit']) ?> پیام در کل دوره</li><?php endif; ?>
        <?php if ($p['daily_limit'] > 0): ?><li><?= num_fa($p['daily_limit']) ?> پیام در روز</li><?php endif; ?>
        <li>مدت اعتبار: <?= num_fa($p['duration_hours']) ?> ساعت</li>
        <li>دسترسی به کتاب‌های پایه و رشته خودت</li>
        <li>تحلیل عکس و PDF</li>
        <li>پاسخ گام‌به‌گام</li>
        <li class="plan-feat-highlight"><?= icon('clock') ?> زمان شروع دلخواه</li>
      </ul>

      <?php if ($user): ?>
        <a href="<?= BASE_URL ?>/payment.php?plan=<?= e($p['plan_code']) ?>"
           class="btn <?= $featured ? 'btn-primary' : 'btn-ghost' ?> btn-block">
          <?= icon('wallet') ?>
          <?= ($sub && $sub['active'] && $user['subscription_type'] === $p['plan_code']) ? 'تمدید همین پلن' : 'خرید اشتراک' ?>
        </a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-ghost btn-block">
          <?= icon('login') ?> ابتدا وارد شو
        </a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===== قابلیت زمان‌بندی ===== -->
<div class="glass schedule-info-card" style="max-width:760px; margin:0 auto 30px; padding:24px 22px; position:relative; overflow:visible;">
  <div class="sched-info-badge"><?= icon('clock') ?> زمان شروع دلخواه</div>
  <h3 style="font-size:17px; font-weight:800; margin-bottom:8px; margin-top:14px;">می‌خوای اشتراکت رو بعداً شروع کنی؟</h3>
  <p style="color:var(--text-dim); font-size:13.5px; line-height:1.9; margin-bottom:16px;">
    وقتی خرید می‌کنی، می‌تونی <b style="color:var(--text)">دقیقاً مشخص کنی اشتراکت از چه روز و ساعتی فعال بشه</b>. مثلاً الان بخری و بگی «از شنبه صبح ساعت ۸ شروع شه». 
    اینجوری اگه الان وقت نداری یا مثلاً وسط هفته‌ای و می‌خوای از اول هفته شروع کنی، راحت می‌تونی تنظیمش کنی!
  </p>
  <div class="sched-info-steps">
    <div class="sched-info-step">
      <div class="sis-num">1</div>
      <div class="sis-text">پلن رو انتخاب کن</div>
    </div>
    <div class="sis-arrow"><?= icon('arrow-left') ?></div>
    <div class="sched-info-step">
      <div class="sis-num">2</div>
      <div class="sis-text">رسید کارت‌به‌کارت آپلود کن</div>
    </div>
    <div class="sis-arrow"><?= icon('arrow-left') ?></div>
    <div class="sched-info-step">
      <div class="sis-num">3</div>
      <div class="sis-text">روز و ساعت شروع رو انتخاب کن</div>
    </div>
    <div class="sis-arrow"><?= icon('arrow-left') ?></div>
    <div class="sched-info-step">
      <div class="sis-num">4</div>
      <div class="sis-text">سیستم خودکار فعالش می‌کنه ✅</div>
    </div>
  </div>
</div>

<div class="glass" style="padding:18px 20px; max-width:760px; margin:0 auto 30px; text-align:center">
  <p style="color:var(--text-dim); font-size:13px; display:flex; align-items:center; justify-content:center; gap:8px">
    <?= icon('shield') ?>
    پرداخت از طریق کارت به کارت – پس از تایید ادمین اشتراک فعال می‌شه. درگاه آنلاین به زودی اضافه می‌شه.
  </p>
</div>



<?php include __DIR__ . '/includes/footer.php'; ?>
