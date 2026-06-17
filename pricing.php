<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$page = 'pricing';
$pageTitle = 'قیمت اشتراک';
$seoTitle = 'قیمت اشتراک دانش‌یار | پلن‌های هوش مصنوعی آموزشی';
$bodyClass = 'page-pricing';
$extraCss = ['pricing.css?v=22'];

$plans = db()->query("SELECT * FROM pricing ORDER BY price ASC")->fetchAll();
$planCount = count($plans);
$user  = current_user();
$sub   = $user ? subscription_status($user) : null;
$salesOn = sales_enabled();

include __DIR__ . '/includes/header.php';
?>

<?php if (!$salesOn): ?>
<section class="pricing-clean-page">
  <div class="pricing-clean-hero">
    <span class="pricing-clean-badge"><?= icon('clock') ?> فروش موقتاً غیرفعال است</span>
    <h1>فروش اشتراک فعلاً بسته است</h1>
    <p>
      در حال حاضر امکان خرید اشتراک وجود ندارد. می‌توانی از پلن رایگان روزانه استفاده کنی.
      به‌زودی فروش دوباره فعال می‌شود؛ ممنون از صبوری‌ات. 🙏
    </p>
    <?php if ($user): ?>
      <a href="<?= BASE_URL ?>/chat.php" class="btn btn-primary" style="margin-top:14px"><?= icon('chat') ?> برگشت به چت</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary" style="margin-top:14px"><?= icon('login') ?> ورود</a>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; return; ?>
<?php endif; ?>

<section class="pricing-clean-page">
  <div class="pricing-clean-hero">
    <span class="pricing-clean-badge"><?= icon('crown') ?> پلن‌های اشتراک دانش‌یار</span>
    <h1>پلن مناسب خودت را انتخاب کن</h1>
    <p>
       
      موقع خرید می‌توانی <b>روز و ساعت شروع اشتراک</b> را خودت انتخاب کنی.
    </p>
  </div>

  <?php if ($user): ?>
    <div class="pricing-clean-summary glass">
      <div>
        <b><?= $sub && $sub['active'] ? 'اشتراک فعال داری' : 'الان روی پلن رایگان هستی' ?></b>
        <p><?= $sub && $sub['active']
            ? 'باقی‌مانده: ' . time_left($user['subscription_end'])
            : 'پیام رایگان امروز: ' . num_fa(max(0, FREE_DAILY_LIMIT - (int)$user['free_used_today'])) . ' از ' . num_fa(FREE_DAILY_LIMIT) ?></p>
      </div>
      <a href="<?= BASE_URL ?>/chat.php" class="btn btn-ghost btn-sm"><?= icon('chat') ?> برگشت به چت</a>
    </div>
  <?php endif; ?>

  <div class="pricing-clean-slider-wrap">
    <div class="pricing-clean-slider-top">
      <div class="pricing-clean-slider-copy">
        <h2>پلن‌ها را مقایسه کن و انتخاب کن</h2>
        
      </div>

      <div class="pricing-clean-slider-tools">
        <div class="pricing-clean-slider-state" id="pricingSliderState">پلن ۱ از <?= num_fa(max(1, $planCount)) ?></div>
        <div class="pricing-clean-slider-nav">
          <button type="button" class="pricing-clean-nav-btn" id="pricingPrevBtn" aria-label="پلن قبلی"><?= icon('arrow-right') ?></button>
          <button type="button" class="pricing-clean-nav-btn" id="pricingNextBtn" aria-label="پلن بعدی"><?= icon('arrow-left') ?></button>
        </div>
      </div>
    </div>

    <div class="pricing-clean-track" id="pricingSlider" aria-label="پلن‌های اشتراک">
      <?php foreach ($plans as $i => $p):
        $featured = $p['plan_code'] === 'weekly';
        $finalPrice  = (int)$p['price'];
        $discountPct = 0;
        $discountType = 'none';

        // تخفیف مؤثر: اختصاصی کاربر اولویت دارد، سپس تخفیف همگانی
        $eff = effective_plan_discount($user ? (int)$user['id'] : 0, $p['plan_code']);
        if ($eff['percent'] > 0) {
            $discountPct  = $eff['percent'];
            $discountType = $eff['type'];
            $finalPrice   = (int)round($p['price'] * (1 - $discountPct / 100));
        }
      ?>
        <article class="pricing-book-card <?= $featured ? 'is-featured' : '' ?>" data-plan-index="<?= $i ?>">
          <?php if ($featured): ?>
            <div class="pricing-book-ribbon">پیشنهاد ویژه</div>
          <?php endif; ?>

          <h3><?= e($p['title']) ?></h3>
          <p class="pricing-book-desc"><?= e($p['description'] ?: 'اشتراک آموزشی دانش‌یار') ?></p>

          <div class="pricing-book-price-row">
            <?php if ($discountPct > 0): ?>
              <span class="pricing-book-old-price"><?= format_price($p['price']) ?></span>
            <?php endif; ?>
            <div class="pricing-book-price">
              <strong><?= format_price($finalPrice) ?></strong>
              <span>تومان</span>
            </div>
          </div>

          <?php if ($discountPct > 0): ?>
            <div class="pricing-book-discount"><?= icon('sparkle') ?> <?= $discountType === 'global' ? 'تخفیف ویژه' : 'تخفیف اختصاصی' ?> <?= num_fa($discountPct) ?>٪</div>
          <?php endif; ?>

          <ul class="pricing-book-list">
            <?php if ($p['total_limit'] > 0): ?><li><?= num_fa($p['total_limit']) ?> پیام در کل دوره</li><?php endif; ?>
            <?php if ($p['daily_limit'] > 0): ?><li><?= num_fa($p['daily_limit']) ?> پیام در روز</li><?php endif; ?>
            <li>مدت اعتبار: <?= num_fa($p['duration_hours']) ?> ساعت</li>
            <li>دسترسی به کتاب‌های پایه و رشته خودت</li>
            <li>تحلیل عکس و PDF</li>
            <li>پاسخ گام‌به‌گام</li>
            <li class="pricing-book-highlight"><?= icon('clock') ?> زمان شروع را خودت انتخاب می‌کنی</li>
          </ul>

          <div class="pricing-book-footer">
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
        </article>
      <?php endforeach; ?>
    </div>

    <div class="pricing-clean-slider-bottom">
      <div class="pricing-clean-swipe-hint"><?= icon('arrow-left') ?> کارت‌ها را بکش تا پلن بعدی را ببینی</div>
      <div class="pricing-clean-dots" id="pricingSliderDots">
        <?php foreach ($plans as $i => $p): ?>
          <button type="button" class="pricing-clean-dot <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>" aria-label="رفتن به پلن <?= num_fa($i + 1) ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="pricing-clean-note glass">
    <div class="pricing-clean-note-icon"><?= icon('clock') ?></div>
    <div class="pricing-clean-note-copy">
      <h3>الان بخر، بعداً فعالش کن</h3>
      <p>
        اگر الان وقت استفاده نداری، موقع ثبت رسید فقط روز و ساعت شروع را مشخص می‌کنی؛
        مثلاً اشتراک از <b>شنبه ساعت ۸ صبح</b> فعال شود.
      </p>
    </div>
  </div>
</section>

<script>
(function () {
  const slider = document.getElementById('pricingSlider');
  const state = document.getElementById('pricingSliderState');
  const dots = Array.from(document.querySelectorAll('.pricing-clean-dot'));
  const prevBtn = document.getElementById('pricingPrevBtn');
  const nextBtn = document.getElementById('pricingNextBtn');
  if (!slider) return;

  const cards = Array.from(slider.querySelectorAll('.pricing-book-card'));
  const total = cards.length;
  let currentIndex = 0;

  function toFaDigits(value) {
    const fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return String(value).replace(/[0-9]/g, d => fa[d]);
  }

  function updateState(index) {
    currentIndex = index;
    if (state) state.textContent = 'پلن ' + toFaDigits(index + 1) + ' از ' + toFaDigits(total);
    dots.forEach((dot, i) => dot.classList.toggle('active', i === index));
    if (prevBtn) prevBtn.disabled = index <= 0;
    if (nextBtn) nextBtn.disabled = index >= total - 1;
  }

  function nearestIndex() {
    const center = slider.scrollLeft + (slider.clientWidth / 2);
    let bestIndex = 0;
    let bestDistance = Infinity;

    cards.forEach((card, index) => {
      const cardCenter = card.offsetLeft + (card.offsetWidth / 2);
      const distance = Math.abs(center - cardCenter);
      if (distance < bestDistance) {
        bestDistance = distance;
        bestIndex = index;
      }
    });

    return bestIndex;
  }

  function goTo(index) {
    const card = cards[index];
    if (!card) return;
    card.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    updateState(index);
  }

  let scrollTimer = null;
  slider.addEventListener('scroll', function () {
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(function () {
      updateState(nearestIndex());
    }, 60);
  }, { passive: true });

  dots.forEach((dot) => {
    dot.addEventListener('click', function () {
      goTo(parseInt(this.getAttribute('data-index'), 10) || 0);
    });
  });

  prevBtn && prevBtn.addEventListener('click', function () {
    goTo(Math.max(0, currentIndex - 1));
  });

  nextBtn && nextBtn.addEventListener('click', function () {
    goTo(Math.min(total - 1, currentIndex + 1));
  });

  updateState(0);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
