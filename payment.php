<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_login();

$user      = current_user();
$plan_code = trim($_GET['plan'] ?? '');

$stmt = db()->prepare("SELECT * FROM pricing WHERE plan_code=?");
$stmt->execute([$plan_code]);
$plan = $stmt->fetch();
if (!$plan) redirect(BASE_URL . '/pricing.php');

// تخفیف
$finalPrice  = (int)$plan['price'];
$discountPct = 0;
try {
    $ds = db()->prepare("SELECT discount_percent FROM user_discounts WHERE user_id=? AND plan_code=?");
    $ds->execute([$user['id'], $plan_code]);
    $d = $ds->fetch();
    if ($d && (int)$d['discount_percent'] > 0) {
        $discountPct = (int)$d['discount_percent'];
        $finalPrice  = (int)round($plan['price'] * (1 - $discountPct / 100));
    }
} catch (Throwable $e) {}

$error   = '';
$success = false;
$activateGregorian = null;

// ======== پردازش فرم ========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'خطای امنیتی. صفحه را رفرش کن.';
    } elseif (empty($_FILES['receipt']['tmp_name'])) {
        $error = 'لطفاً عکس رسید پرداخت را آپلود کن.';
    } else {
        // ======== پردازش تاریخ زمان‌بندی ========
        $useSchedule = ($_POST['use_schedule'] ?? '') === '1';
        if ($useSchedule) {
            $jDate = trim(fa_to_en_digits($_POST['jalali_date'] ?? ''));
            $jHour = max(0, min(23, (int)fa_to_en_digits($_POST['hour'] ?? '0')));
            $jMin  = max(0, min(59, (int)fa_to_en_digits($_POST['minute'] ?? '0')));

            if (empty($jDate)) {
                $error = 'تاریخ شروع را انتخاب کن.';
            } elseif (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $jDate)) {
                $error = 'تاریخ نامعتبر است.';
            } else {
                $full = $jDate . ' ' . sprintf('%02d', $jHour) . ':' . sprintf('%02d', $jMin);
                $activateGregorian = jalali_datetime_to_gregorian_str($full);
                if (!$activateGregorian) {
                    $error = 'تاریخ انتخاب‌شده معتبر نیست.';
                } elseif (strtotime($activateGregorian) < time() - 600) {
                    $error = 'تاریخ شروع نمی‌تواند در گذشته باشد.';
                }
            }
        }

        if (!$error) {
            [$ok, $res] = save_receipt_image($_FILES['receipt'], $user['id']);
            if (!$ok) {
                $error = $res;
            } else {
                try {
                    db()->prepare("INSERT INTO card_receipts (user_id, plan_code, plan_title, amount, receipt_image, activate_at) VALUES (?,?,?,?,?,?)")
                        ->execute([$user['id'], $plan_code, $plan['title'], $finalPrice, $res, $activateGregorian]);
                    $success = true;
                } catch (Throwable $e) {
                    $error = 'خطا در ثبت اطلاعات. دوباره تلاش کن.';
                }
            }
        }
    }
}

// ======== تاریخ امروز شمسی ========
function jalali_to_gregorian_local($jy,$jm,$jd){
    $jy=(int)$jy;$jm=(int)$jm;$jd=(int)$jd;
    if($jy>979){$gy=1600;$jy-=979;}else{$gy=621;}
    $j_d_no=365*$jy+(int)($jy/33)*8+(int)(($jy%33+3)/4);
    $ml=[31,31,31,31,31,31,30,30,30,30,30,29];
    for($i=0;$i<$jm-1;$i++) $j_d_no+=$ml[$i];
    $j_d_no+=$jd-1;
    $g_d_no=$j_d_no+79;
    $gy+=400*(int)($g_d_no/146097);$g_d_no%=146097;
    if($g_d_no>=36525){$g_d_no--;$gy+=100*(int)($g_d_no/36524);$g_d_no%=36524;if($g_d_no>=365)$g_d_no++;}
    $gy+=4*(int)($g_d_no/1461);$g_d_no%=1461;
    if($g_d_no>=366){$gy+=(int)(($g_d_no-1)/365);$g_d_no=($g_d_no-1)%365;}
    $leap=($gy%4===0&&$gy%100!==0)||$gy%400===0;
    $gdm=[31,$leap?29:28,31,30,31,30,31,31,30,31,30,31];
    $gm=1;$gd=$g_d_no;
    foreach($gdm as $i=>$dim){if($gd<$dim){$gm=$i+1;$gd=$gd+1;break;}$gd-=$dim;}
    return[$gy,$gm,$gd];
}
function g2j($gy, $gm, $gd) {
    // تبدیل میلادی به شمسی - با iterate روی سال‌های مجاور
    $gy=(int)$gy;$gm=(int)$gm;$gd=(int)$gd;
    $jy_est = $gy - 621;
    $target_ts = mktime(0,0,0,$gm,$gd,$gy);
    for($jy=$jy_est-1;$jy<=$jy_est+1;$jy++){
        [$gy1,$gm1,$gd1]=jalali_to_gregorian_local($jy,1,1);
        [$gy2,$gm2,$gd2]=jalali_to_gregorian_local($jy+1,1,1);
        $ts1=mktime(0,0,0,$gm1,$gd1,$gy1);
        $ts2=mktime(0,0,0,$gm2,$gd2,$gy2);
        if($target_ts>=$ts1 && $target_ts<$ts2){
            $delta=(int)(($target_ts-$ts1)/86400);
            $ml=[31,31,31,31,31,31,30,30,30,30,30,29];
            $jm=1;
            foreach($ml as $i=>$dim){
                if($delta<$dim){$jm=$i+1;$jd=$delta+1;break;}
                $delta-=$dim;
            }
            return[$jy,$jm,$jd];
        }
    }
    return[0,0,0];
}
[$jy0, $jm0, $jd0] = g2j((int)date('Y'), (int)date('n'), (int)date('j'));
$jMonths = ['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];

// ساخت لیست ۳۰ روز آینده
$futureDays = [];
for ($i = 0; $i <= 30; $i++) {
    $ts = mktime(0,0,0, date('n'), date('j') + $i, date('Y'));
    [$jyi,$jmi,$jdi] = g2j((int)date('Y',$ts),(int)date('n',$ts),(int)date('j',$ts));
    $val   = sprintf('%d/%02d/%02d', $jyi, $jmi, $jdi);
    $label = ($i===0?'امروز ':($i===1?'فردا ':'')) . num_fa($jdi) . ' ' . $jMonths[$jmi];
    $futureDays[] = ['val'=>$val,'label'=>$label,'dayname'=>$i];
}

$pageTitle = 'پرداخت اشتراک ' . $plan['title'];
include __DIR__ . '/includes/header.php';
?>

<div class="pay-page">

<?php if ($success): ?>
<!-- ===== صفحه موفقیت ===== -->
<div class="pay-success-screen">
  <div class="success-ring">
    <div class="success-icon"><?= icon('check') ?></div>
  </div>
  <h2>رسید ثبت شد! 🎉</h2>
  <p>رسیدت رو دریافت کردیم.<br>بعد از تایید ادمین<?= $activateGregorian ? ' در زمان انتخابی' : '' ?> اشتراکت فعال می‌شه.</p>
  <?php if ($activateGregorian): ?>
  <div class="success-schedule">
    <?= icon('clock') ?>
    <span>اشتراک در <b><?= num_fa(date('Y/m/d H:i', strtotime($activateGregorian))) ?></b> خودکار فعال می‌شه</span>
  </div>
  <?php endif; ?>
  <div class="success-actions">
    <a href="<?= BASE_URL ?>/profile.php#receipts" class="btn btn-primary"><?= icon('eye') ?> وضعیت رسید</a>
    <a href="<?= BASE_URL ?>/chat.php" class="btn btn-ghost"><?= icon('chat') ?> بازگشت به چت</a>
  </div>
</div>

<?php else: ?>
<!-- ===== صفحه اصلی پرداخت ===== -->

<!-- هدر -->
<div class="pay-hero">
  <div class="pay-hero-badge"><?= icon('wallet') ?> پرداخت امن</div>
  <h1>خرید اشتراک <span class="grad"><?= e($plan['title']) ?></span></h1>
</div>

<!-- خطا -->
<?php if ($error): ?>
<div class="alert alert-error" style="margin-bottom:16px"><?= icon('warning') ?> <?= e($error) ?></div>
<?php endif; ?>

<div class="pay-layout">

  <!-- ===== ستون چپ: فرم ===== -->
  <div class="pay-main">

    <!-- کارت بانکی -->
    <div class="pay-card-section">
      <div class="pay-section-title"><?= icon('info') ?> واریز به این کارت</div>

      <div class="bank-card-3d" id="bankCard">
        <div class="bc-glow"></div>
        <div class="bc-shimmer"></div>

        <div class="bc-top">
          <div class="bc-bank"><?= e(CARD_BANK) ?></div>
          <div class="bc-chip">
            <div class="bc-chip-line"></div>
            <div class="bc-chip-line"></div>
          </div>
        </div>

        <!-- شماره کارت با چهار بلوک جدا برای خوانایی بهتر -->
        <div class="bc-number-row">
          <?php
            $cn = preg_replace('/\s+/', '', CARD_NUMBER);
            $groups = str_split($cn, 4);
          ?>
          <div class="bc-number" id="cardNumber" dir="ltr">
            <?php foreach ($groups as $g): ?>
              <span class="bc-num-group"><?= e($g) ?></span>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="bc-bottom">
          <div class="bc-holder">
            <div class="bc-label">صاحب کارت</div>
            <div class="bc-name"><?= e(CARD_HOLDER) ?></div>
          </div>
        </div>

        <!-- دکمه کپی به صورت overlay زیر کارت -->
        <button type="button" class="bc-copy-big" onclick="copyCardNum()" id="copyBtn">
          <?= icon('copy') ?>
          <span class="bc-copy-text">کپی شماره کارت</span>
          <span class="bc-copy-done">✓ کپی شد!</span>
        </button>
      </div>

      <!-- مبلغ -->
      <div class="pay-amount-display">
        <?php if ($discountPct > 0): ?>
          <div class="pay-original"><?= format_price($plan['price']) ?> تومان</div>
        <?php endif; ?>
        <div class="pay-final-wrap">
          <div class="pay-final-num"><?= format_price($finalPrice) ?></div>
          <div class="pay-final-label">تومان</div>
          <?php if ($discountPct > 0): ?>
            <div class="pay-disc-tag">-<?= num_fa($discountPct) ?>%</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- فرم ارسال رسید -->
    <form method="post" enctype="multipart/form-data" id="payForm" novalidate>
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="use_schedule" id="useScheduleInput" value="0">

      <!-- آپلود رسید -->
      <div class="pay-section-title" style="margin-top:22px"><?= icon('upload') ?> آپلود رسید پرداخت</div>

      <div class="upload-box" id="uploadBox" onclick="triggerUpload()">
        <div class="upload-default" id="uploadDefault">
          <div class="upload-ico-wrap"><?= icon('upload') ?></div>
          <div class="upload-text">کلیک کن یا عکس رو اینجا بنداز</div>
          <div class="upload-hint">اسکرین‌شات یا عکس رسید — JPG / PNG — حداکثر ۱۰ مگ</div>
        </div>
        <div class="upload-preview-wrap" id="uploadPreviewWrap" style="display:none">
          <img id="uploadPreview" src="" alt="رسید">
          <div class="upload-preview-overlay">
            <span><?= icon('edit') ?> تغییر عکس</span>
          </div>
        </div>
      </div>
      <input type="file" id="fileInput" name="receipt" accept="image/*" style="display:none" required>

      <!-- زمان‌بندی -->
      <div class="schedule-toggle-btn" id="schedToggle" onclick="toggleSchedule()">
        <div class="stb-left">
          <?= icon('clock') ?>
          <div>
            <div class="stb-title">می‌خوای اشتراک بعداً شروع شه؟</div>
            <div class="stb-sub" id="schedSubText">همین لحظه تایید فعال می‌شه</div>
          </div>
        </div>
        <div class="stb-switch" id="schedSwitch">
          <div class="stb-knob"></div>
        </div>
      </div>

      <!-- پنل زمان‌بندی -->
      <div class="schedule-panel" id="schedPanel">
        <div class="sched-grid">

          <div class="sched-col sched-col-date">
            <label class="sched-label">روز شروع</label>
            <div class="sched-days" id="schedDays">
              <?php foreach ($futureDays as $i => $day): ?>
              <button type="button"
                class="sched-day-btn <?= $i===0?'selected':'' ?>"
                data-val="<?= e($day['val']) ?>"
                onclick="selectDay(this, '<?= e($day['val']) ?>', '<?= e($day['label']) ?>')">
                <?= e($day['label']) ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="sched-col sched-col-time">
            <label class="sched-label">ساعت شروع</label>
            <div class="time-picker">
              <div class="time-drum" id="hourDrum">
                <?php for($h=0;$h<24;$h++): ?>
                  <div class="td-item <?= $h==8?'active':'' ?>" data-val="<?= $h ?>" onclick="selectHour(<?= $h ?>)">
                    <?= num_fa(sprintf('%02d',$h)) ?>
                  </div>
                <?php endfor; ?>
              </div>
              <div class="time-sep">:</div>
              <div class="time-drum" id="minDrum">
                <?php foreach([0,15,30,45] as $m): ?>
                  <div class="td-item <?= $m==0?'active':'' ?>" data-val="<?= $m ?>" onclick="selectMin(<?= $m ?>)">
                    <?= num_fa(sprintf('%02d',$m)) ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="sched-result" id="schedResult">
          <?= icon('sparkle') ?> اشتراک <b id="schedResultText"></b> شروع می‌شه و سیستم خودکار فعالش می‌کنه
        </div>

        <input type="hidden" name="jalali_date" id="jalaliDate" value="<?= e($futureDays[0]['val']) ?>">
        <input type="hidden" name="hour"        id="hiddenHour" value="8">
        <input type="hidden" name="minute"      id="hiddenMin"  value="0">
      </div>

      <!-- دکمه ارسال -->
      <button type="submit" class="btn-pay" id="submitBtn">
        <span id="submitText"><?= icon('send') ?> ارسال رسید و ثبت درخواست</span>
        <span id="submitLoading" style="display:none">در حال ارسال...</span>
      </button>

      <div class="pay-trust">
        <?= icon('shield') ?> بعد از تایید ادمین اشتراک فعال می‌شه · معمولاً زیر ۱ ساعت
      </div>
    </form>

  </div>

  <!-- ===== ستون راست: خلاصه ===== -->
  <div class="pay-sidebar">

    <!-- کارت پلن -->
    <div class="plan-card">
      <div class="plan-card-head">
        <div class="plan-card-icon"><?= icon('crown') ?></div>
        <div>
          <div class="plan-card-name"><?= e($plan['title']) ?></div>
          <div class="plan-card-desc">اشتراک دانش‌یار</div>
        </div>
      </div>
      <div class="plan-features">
        <div class="plan-feat"><?= icon('clock') ?> <?= num_fa($plan['duration_hours']) ?> ساعت اعتبار</div>
        <?php if ($plan['daily_limit'] > 0): ?>
        <div class="plan-feat"><?= icon('chat') ?> <?= num_fa($plan['daily_limit']) ?> پیام روزانه</div>
        <?php else: ?>
        <div class="plan-feat"><?= icon('chat') ?> پیام نامحدود روزانه</div>
        <?php endif; ?>
        <?php if ($plan['total_limit'] > 0): ?>
        <div class="plan-feat"><?= icon('graph') ?> سقف <?= num_fa($plan['total_limit']) ?> پیام کل</div>
        <?php endif; ?>
        <div class="plan-feat"><?= icon('book') ?> دسترسی به کتاب‌های درسی</div>
        <div class="plan-feat"><?= icon('screenshot') ?> تحلیل عکس و PDF</div>
      </div>
      <div class="plan-price-wrap">
        <?php if ($discountPct > 0): ?>
          <div class="plan-old-price"><?= format_price($plan['price']) ?> تومان</div>
        <?php endif; ?>
        <div class="plan-price">
          <span class="plan-price-num"><?= format_price($finalPrice) ?></span>
          <span class="plan-price-cur">تومان</span>
        </div>
        <?php if ($discountPct > 0): ?>
          <div class="plan-disc-note"><?= icon('sparkle') ?> تخفیف اختصاصی <?= num_fa($discountPct) ?>٪</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- درگاه آنلاین -->
    <div class="online-card">
      <?= icon('globe') ?>
      <div>
        <div class="oc-title">درگاه پرداخت آنلاین</div>
        <div class="oc-sub">به زودی فعال می‌شه</div>
      </div>
      <span class="oc-badge">زود</span>
    </div>

    <a href="<?= BASE_URL ?>/pricing.php" class="btn btn-ghost btn-block"><?= icon('arrow-right') ?> بازگشت</a>
  </div>

</div><!-- .pay-layout -->
<?php endif; ?>
</div><!-- .pay-page -->

<style>
/* ================================================================
   صفحه پرداخت – طراحی حرفه‌ای
   ================================================================ */
.pay-page { max-width:1000px; margin:0 auto; padding:20px 0 60px; }

/* موفقیت */
.pay-success-screen { text-align:center; padding:60px 20px; max-width:500px; margin:0 auto; }
.success-ring { width:90px; height:90px; margin:0 auto 24px; border-radius:50%; background:linear-gradient(135deg,rgba(56,217,169,.2),rgba(56,217,169,.05)); border:2px solid rgba(56,217,169,.4); display:grid; place-items:center; animation:ring-pulse 2s infinite; }
@keyframes ring-pulse { 0%,100%{box-shadow:0 0 0 0 rgba(56,217,169,.3)} 50%{box-shadow:0 0 0 16px rgba(56,217,169,0)} }
.success-icon { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,#38d9a9,#20c997); display:grid; place-items:center; color:#fff; }
.success-icon .ico { width:26px; height:26px; }
.pay-success-screen h2 { font-size:26px; font-weight:900; margin-bottom:12px; }
.pay-success-screen p { color:var(--text-dim); line-height:1.8; margin-bottom:20px; }
.success-schedule { display:flex; align-items:center; gap:8px; background:rgba(77,171,247,.1); border:1px solid rgba(77,171,247,.3); border-radius:12px; padding:12px 16px; font-size:13px; color:var(--info); margin-bottom:24px; }
.success-schedule .ico { width:16px; height:16px; flex-shrink:0; }
.success-actions { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }

/* هدر */
.pay-hero { text-align:center; padding:16px 0 24px; }
.pay-hero-badge { display:inline-flex; align-items:center; gap:6px; background:var(--orange-soft); color:var(--orange); border:1px solid var(--border-orange); padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; margin-bottom:12px; }
.pay-hero-badge .ico { width:14px; height:14px; }
.pay-hero h1 { font-size:26px; font-weight:900; }

/* لایه اصلی */
.pay-layout { display:grid; grid-template-columns:1fr 320px; gap:20px; align-items:start; }
@media(max-width:800px) { .pay-layout { grid-template-columns:1fr; } .pay-sidebar { order:-1; } }

/* ===== ستون فرم ===== */
.pay-main { display:flex; flex-direction:column; gap:0; }
.pay-section-title { display:flex; align-items:center; gap:7px; font-size:13px; font-weight:700; color:var(--text-dim); margin-bottom:10px; }
.pay-section-title .ico { width:15px; height:15px; }

/* کارت بانکی ۳D */
.pay-card-section { background:linear-gradient(160deg,rgba(255,255,255,.06),rgba(255,255,255,.02)); border:1px solid var(--border); border-radius:18px; padding:22px; margin-bottom:16px; }
/* ============ کارت بانکی - طراحی جدید موبایل-فرست ============ */
.bank-card-3d {
  position: relative;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 35%, #0f3460 75%, #162447 100%);
  border-radius: 18px;
  padding: 20px;
  margin: 0 0 14px;
  overflow: hidden;
  box-shadow:
    0 12px 40px rgba(0, 0, 0, .45),
    inset 0 1px 0 rgba(255, 255, 255, .08),
    inset 0 0 0 1px rgba(255, 255, 255, .04);
  aspect-ratio: 1.586 / 1; /* نسبت دقیق کارت بانکی واقعی */
  max-width: 420px;
  width: 100%;
  margin-inline: auto;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  user-select: none;
}
.bc-glow {
  position: absolute; top: -80px; right: -80px;
  width: 220px; height: 220px; border-radius: 50%;
  background: radial-gradient(circle, rgba(235, 124, 42, .4), transparent 70%);
  pointer-events: none; z-index: 0;
}
.bc-shimmer {
  position: absolute; inset: 0;
  background: linear-gradient(110deg, transparent 30%, rgba(255,255,255,.06) 50%, transparent 70%);
  background-size: 200% 100%;
  animation: bcShimmer 6s ease-in-out infinite;
  pointer-events: none; z-index: 0;
}
@keyframes bcShimmer {
  0%, 100% { background-position: 200% 0; }
  50%      { background-position: -100% 0; }
}

.bc-top {
  display: flex; align-items: center; justify-content: space-between;
  position: relative; z-index: 1;
}
.bc-bank {
  font-size: 13px; font-weight: 800; color: rgba(255, 255, 255, .92);
  letter-spacing: .5px;
}
.bc-chip {
  width: 42px; height: 32px; border-radius: 6px;
  background: linear-gradient(135deg, #e0b850 0%, #c49527 50%, #b3851a 100%);
  display: flex; flex-direction: column; justify-content: center; gap: 5px;
  padding: 5px 7px;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, .4), 0 1px 3px rgba(0, 0, 0, .3);
}
.bc-chip-line {
  height: 1.5px; background: rgba(0, 0, 0, .35); border-radius: 1px;
}

/* شماره کارت - چهار بلوک */
.bc-number-row {
  position: relative; z-index: 1;
  margin: 0;
  padding: 8px 0;
}
.bc-number {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 4px;
  direction: ltr;
  text-align: center;
}
.bc-num-group {
  flex: 1;
  color: #fff;
  font-family: 'Courier New', 'Consolas', monospace;
  font-weight: 800;
  font-size: clamp(16px, 5.2vw, 22px); /* ریسپانسیو خودکار */
  letter-spacing: 2px;
  text-shadow: 0 2px 10px rgba(0, 0, 0, .55);
  white-space: nowrap;
}

.bc-bottom {
  display: flex; align-items: flex-end; justify-content: space-between;
  position: relative; z-index: 1; gap: 12px;
}
.bc-label {
  font-size: 10px; color: rgba(255, 255, 255, .55);
  letter-spacing: .5px; margin-bottom: 4px;
  text-transform: uppercase;
}
.bc-name {
  font-size: 15px; font-weight: 800; color: #fff;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  max-width: 100%;
}

/* دکمه کپی - حالا یه دکمه بزرگ زیر کارت */
.bc-copy-big {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%; max-width: 420px;
  margin: 12px auto 0;
  padding: 13px 16px;
  background: linear-gradient(135deg, var(--orange) 0%, #d96a1e 100%);
  border: none;
  color: #fff;
  border-radius: 12px;
  font-size: 14px; font-weight: 800; font-family: inherit;
  cursor: pointer;
  transition: transform .15s ease, box-shadow .2s ease;
  box-shadow: 0 4px 14px rgba(235, 124, 42, .35);
  position: relative;
  overflow: hidden;
}
.bc-copy-big:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(235, 124, 42, .45); }
.bc-copy-big:active { transform: translateY(0); }
.bc-copy-big .ico { width: 17px; height: 17px; flex-shrink: 0; }
.bc-copy-big .bc-copy-done { display: none; }
.bc-copy-big.copied {
  background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
  box-shadow: 0 4px 14px rgba(22, 163, 74, .35);
}
.bc-copy-big.copied .bc-copy-text,
.bc-copy-big.copied .ico { display: none; }
.bc-copy-big.copied .bc-copy-done { display: inline; }

/* تنظیمات کوچک‌تر شدن برای موبایل */
@media (max-width: 480px) {
  .bank-card-3d {
    padding: 18px;
    border-radius: 16px;
  }
  .bc-bank { font-size: 12px; }
  .bc-chip { width: 36px; height: 28px; }
  .bc-num-group {
    letter-spacing: 1.5px;
  }
  .bc-name { font-size: 14px; }
}

/* خیلی کوچک (گوشی‌های قدیمی) */
@media (max-width: 360px) {
  .bank-card-3d { padding: 14px; aspect-ratio: 1.55 / 1; }
  .bc-num-group { letter-spacing: 1px; }
}

/* مبلغ */
.pay-amount-display { text-align:center; }
.pay-original { font-size:14px; color:var(--text-dim); text-decoration:line-through; margin-bottom:4px; }
.pay-final-wrap { display:inline-flex; align-items:baseline; gap:6px; }
.pay-final-num { font-size:34px; font-weight:900; color:var(--orange); }
.pay-final-label { font-size:15px; color:var(--text-dim); }
.pay-disc-tag { background:rgba(56,217,169,.15); color:#38d9a9; border:1px solid rgba(56,217,169,.3); padding:3px 10px; border-radius:8px; font-size:12px; font-weight:800; margin-right:6px; }

/* آپلود */
.upload-box { border:2px dashed rgba(235,124,42,.4); border-radius:16px; padding:0; cursor:pointer; transition:.2s; margin-bottom:14px; overflow:hidden; min-height:130px; display:flex; align-items:center; justify-content:center; background:rgba(235,124,42,.03); }
.upload-box:hover { border-color:var(--orange); background:rgba(235,124,42,.07); }
.upload-box.has-file { border-color:#38d9a9; border-style:solid; background:rgba(56,217,169,.04); }
.upload-default { display:flex; flex-direction:column; align-items:center; gap:8px; padding:30px 20px; }
.upload-ico-wrap { width:52px; height:52px; border-radius:14px; background:var(--orange-soft); border:1px solid var(--border-orange); display:grid; place-items:center; color:var(--orange); }
.upload-ico-wrap .ico { width:24px; height:24px; }
.upload-text { font-size:14px; font-weight:700; }
.upload-hint { font-size:12px; color:var(--text-dim); }
.upload-preview-wrap { position:relative; width:100%; }
.upload-preview-wrap img { width:100%; max-height:220px; object-fit:contain; display:block; }
.upload-preview-overlay { position:absolute; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; opacity:0; transition:.2s; color:#fff; font-size:14px; font-weight:700; gap:6px; }
.upload-preview-wrap:hover .upload-preview-overlay { opacity:1; }
.upload-preview-overlay .ico { width:16px; height:16px; }

/* toggle زمان‌بندی */
.schedule-toggle-btn { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; background:var(--glass-2); border:1px solid var(--border); border-radius:14px; cursor:pointer; margin-bottom:0; transition:.2s; user-select:none; }
.schedule-toggle-btn:hover { border-color:var(--border-orange); }
.schedule-toggle-btn.active { border-color:var(--border-orange); background:rgba(235,124,42,.06); border-bottom-left-radius:0; border-bottom-right-radius:0; }
.stb-left { display:flex; align-items:center; gap:12px; }
.stb-left .ico { width:18px; height:18px; color:var(--orange); flex-shrink:0; }
.stb-title { font-size:14px; font-weight:700; }
.stb-sub { font-size:11px; color:var(--text-dim); margin-top:2px; }
.stb-switch { width:44px; height:24px; border-radius:12px; background:rgba(255,255,255,.1); border:1px solid var(--border); position:relative; transition:.25s; flex-shrink:0; }
.stb-switch.on { background:linear-gradient(135deg,var(--orange),var(--orange-2)); border-color:var(--orange); }
.stb-knob { width:18px; height:18px; border-radius:50%; background:#fff; position:absolute; top:2px; right:3px; transition:.25s; box-shadow:0 1px 4px rgba(0,0,0,.3); }
.stb-switch.on .stb-knob { right:23px; }

/* پنل زمان‌بندی */
.schedule-panel { display:none; background:rgba(235,124,42,.04); border:1px solid var(--border-orange); border-top:none; border-bottom-left-radius:14px; border-bottom-right-radius:14px; padding:18px; margin-bottom:14px; }
.schedule-panel.open { display:block; }
.sched-grid { display:grid; grid-template-columns:1fr auto; gap:16px; align-items:start; margin-bottom:12px; }
@media(max-width:480px) { .sched-grid { grid-template-columns:1fr; } }
.sched-label { font-size:11px; font-weight:700; color:var(--text-dim); margin-bottom:8px; display:block; }

/* روزها */
.sched-days { display:flex; flex-wrap:wrap; gap:6px; max-height:160px; overflow-y:auto; }
.sched-days::-webkit-scrollbar { width:3px; }
.sched-days::-webkit-scrollbar-thumb { background:var(--border-orange); border-radius:2px; }
.sched-day-btn { padding:6px 10px; border-radius:8px; border:1px solid var(--border); background:rgba(255,255,255,.04); color:var(--text-dim); font-size:12px; font-weight:600; cursor:pointer; font-family:inherit; transition:.15s; white-space:nowrap; }
.sched-day-btn:hover { border-color:var(--border-orange); color:var(--orange); background:var(--orange-soft); }
.sched-day-btn.selected { background:linear-gradient(135deg,var(--orange),var(--orange-2)); color:#1a0e05; border-color:transparent; font-weight:800; }

/* ساعت - drum picker */
.time-picker { display:flex; align-items:center; gap:8px; }
.time-sep { font-size:20px; font-weight:900; color:var(--orange); }
.time-drum { display:flex; flex-direction:column; gap:4px; max-height:140px; overflow-y:auto; width:56px; scrollbar-width:none; }
.time-drum::-webkit-scrollbar { display:none; }
.td-item { padding:7px 0; text-align:center; border-radius:8px; border:1px solid transparent; font-size:14px; font-weight:700; cursor:pointer; color:var(--text-dim); transition:.1s; }
.td-item:hover { background:var(--orange-soft); color:var(--orange); }
.td-item.active { background:linear-gradient(135deg,var(--orange),var(--orange-2)); color:#1a0e05; border-color:transparent; }

/* نتیجه */
.sched-result { display:flex; align-items:center; gap:8px; background:rgba(56,217,169,.08); border:1px solid rgba(56,217,169,.25); border-radius:10px; padding:10px 14px; font-size:13px; color:#38d9a9; }
.sched-result .ico { width:15px; height:15px; flex-shrink:0; }

/* دکمه ارسال */
.btn-pay { width:100%; padding:16px; margin-top:16px; border-radius:14px; border:none; background:linear-gradient(135deg,var(--orange),var(--orange-2)); color:#1a0e05; font-size:16px; font-weight:900; cursor:pointer; font-family:inherit; display:flex; align-items:center; justify-content:center; gap:8px; transition:.2s; box-shadow:0 8px 24px rgba(235,124,42,.4); }
.btn-pay:hover { transform:translateY(-2px); box-shadow:0 12px 30px rgba(235,124,42,.5); }
.btn-pay:active { transform:translateY(0); }
.btn-pay .ico { width:20px; height:20px; }
.btn-pay:disabled { opacity:.6; cursor:not-allowed; transform:none; }
.pay-trust { display:flex; align-items:center; justify-content:center; gap:6px; font-size:11px; color:var(--text-muted); margin-top:10px; }
.pay-trust .ico { width:13px; height:13px; }

/* ===== ستون سایدبار ===== */
.pay-sidebar { display:flex; flex-direction:column; gap:12px; position:sticky; top:80px; }

/* کارت پلن */
.plan-card { background:linear-gradient(160deg,rgba(255,255,255,.07),rgba(255,255,255,.02)); border:1px solid var(--border); border-radius:20px; padding:22px; overflow:hidden; position:relative; }
.plan-card::before { content:""; position:absolute; top:-40px; left:-40px; width:140px; height:140px; border-radius:50%; background:radial-gradient(circle,rgba(235,124,42,.18),transparent 70%); pointer-events:none; }
.plan-card-head { display:flex; align-items:center; gap:12px; margin-bottom:18px; position:relative; }
.plan-card-icon { width:46px; height:46px; border-radius:14px; background:linear-gradient(135deg,var(--orange),var(--orange-2)); display:grid; place-items:center; color:#1a0e05; flex-shrink:0; box-shadow:0 6px 18px rgba(235,124,42,.4); }
.plan-card-icon .ico { width:22px; height:22px; }
.plan-card-name { font-size:18px; font-weight:900; }
.plan-card-desc { font-size:12px; color:var(--text-dim); }
.plan-features { display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
.plan-feat { display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text-dim); }
.plan-feat .ico { width:15px; height:15px; color:var(--orange); flex-shrink:0; }
.plan-price-wrap { border-top:1px solid var(--border); padding-top:14px; }
.plan-old-price { font-size:13px; color:var(--text-muted); text-decoration:line-through; margin-bottom:4px; }
.plan-price { display:flex; align-items:baseline; gap:6px; }
.plan-price-num { font-size:28px; font-weight:900; color:var(--orange); }
.plan-price-cur { font-size:14px; color:var(--text-dim); }
.plan-disc-note { display:flex; align-items:center; gap:5px; font-size:12px; color:#38d9a9; margin-top:6px; }
.plan-disc-note .ico { width:13px; height:13px; }

/* درگاه آنلاین */
.online-card { display:flex; align-items:center; gap:12px; padding:14px 16px; background:rgba(255,255,255,.03); border:1px solid var(--border); border-radius:14px; opacity:.7; }
.online-card .ico { width:18px; height:18px; color:var(--text-dim); flex-shrink:0; }
.oc-title { font-size:13px; font-weight:700; }
.oc-sub { font-size:11px; color:var(--text-dim); }
.oc-badge { margin-right:auto; background:rgba(235,124,42,.15); color:var(--orange); font-size:10px; padding:2px 7px; border-radius:6px; font-weight:700; white-space:nowrap; }

/* آیکون info */
.ico-info-inline { color:var(--text-dim); }
</style>

<script>
/* ========== کپی کارت ========== */
function copyCardNum() {
  // شماره کارت بدون فاصله برای کپی
  var n = ('<?= CARD_NUMBER ?>').replace(/\s+/g, '');
  var btn = document.getElementById('copyBtn');

  function showCopied() {
    if (!btn) return;
    btn.classList.add('copied');
    // ویبره کوتاه روی موبایل
    if (navigator.vibrate) { try { navigator.vibrate(40); } catch(e){} }
    setTimeout(function() { btn.classList.remove('copied'); }, 2000);
  }

  function fallbackCopy() {
    var t = document.createElement('textarea');
    t.value = n; t.style.position = 'fixed'; t.style.opacity = '0';
    document.body.appendChild(t);
    t.focus(); t.select();
    try { document.execCommand('copy'); showCopied(); }
    catch(e) { alert('کپی نشد. شماره: ' + n); }
    document.body.removeChild(t);
  }

  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(n).then(showCopied).catch(fallbackCopy);
  } else {
    fallbackCopy();
  }
}

/* ========== آپلود ========== */
function triggerUpload() { document.getElementById('fileInput').click(); }

document.getElementById('fileInput').addEventListener('change', function(){
  var f = this.files[0];
  if (!f) return;
  if (f.size > 10*1024*1024) { alert('حجم عکس بیش از ۱۰ مگابایت است.'); this.value=''; return; }
  var reader = new FileReader();
  reader.onload = function(e){
    document.getElementById('uploadDefault').style.display = 'none';
    var p = document.getElementById('uploadPreview');
    p.src = e.target.result;
    document.getElementById('uploadPreviewWrap').style.display = 'block';
    document.getElementById('uploadBox').classList.add('has-file');
  };
  reader.readAsDataURL(f);
});

/* drag & drop */
var ub = document.getElementById('uploadBox');
['dragenter','dragover'].forEach(function(e){
  ub.addEventListener(e, function(ev){ ev.preventDefault(); ub.style.borderColor='var(--orange)'; });
});
ub.addEventListener('dragleave', function(){ ub.style.borderColor=''; });
ub.addEventListener('drop', function(e){
  e.preventDefault(); ub.style.borderColor='';
  var f = e.dataTransfer.files[0];
  if (f && f.type.startsWith('image/')) {
    var dt = new DataTransfer(); dt.items.add(f);
    document.getElementById('fileInput').files = dt.files;
    document.getElementById('fileInput').dispatchEvent(new Event('change'));
  }
});

/* paste */
document.addEventListener('paste', function(e){
  if (e.clipboardData) {
    for (var i=0; i<e.clipboardData.items.length; i++) {
      var it = e.clipboardData.items[i];
      if (it.type.startsWith('image/')) {
        e.preventDefault();
        var f = it.getAsFile();
        var dt = new DataTransfer(); dt.items.add(f);
        document.getElementById('fileInput').files = dt.files;
        document.getElementById('fileInput').dispatchEvent(new Event('change'));
        break;
      }
    }
  }
});

/* ========== زمان‌بندی ========== */
var schedActive = false;
var selectedDay = '<?= e($futureDays[0]['val']) ?>';
var selectedDayLabel = '<?= e($futureDays[0]['label']) ?>';
var selectedHour = 8;
var selectedMin  = 0;

var jMonths = ['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
var faDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
function toFa(n){ return String(n).replace(/[0-9]/g, function(c){ return faDigits[c]; }); }
function pad2(n){ return (n<10?'0':'')+n; }

function toggleSchedule() {
  schedActive = !schedActive;
  var sw = document.getElementById('schedSwitch');
  var panel = document.getElementById('schedPanel');
  var btn = document.getElementById('schedToggle');
  var inp = document.getElementById('useScheduleInput');

  sw.classList.toggle('on', schedActive);
  panel.classList.toggle('open', schedActive);
  btn.classList.toggle('active', schedActive);
  inp.value = schedActive ? '1' : '0';

  if (schedActive) {
    updateSchedResult();
    document.getElementById('stb-sub') && (document.getElementById('stb-sub').textContent='زمان شروع انتخاب کن');
  } else {
    document.getElementById('schedSubText').textContent = 'همین لحظه تایید فعال می‌شه';
  }
}

function selectDay(btn, val, label) {
  document.querySelectorAll('.sched-day-btn').forEach(function(b){ b.classList.remove('selected'); });
  btn.classList.add('selected');
  selectedDay   = val;
  selectedDayLabel = label;
  document.getElementById('jalaliDate').value = val;
  updateSchedResult();
}

function selectHour(h) {
  selectedHour = h;
  document.querySelectorAll('#hourDrum .td-item').forEach(function(el){
    el.classList.toggle('active', parseInt(el.dataset.val) === h);
  });
  document.getElementById('hiddenHour').value = h;
  // scroll به مرکز
  var active = document.querySelector('#hourDrum .td-item.active');
  if (active) active.scrollIntoView({block:'center',behavior:'smooth'});
  updateSchedResult();
}

function selectMin(m) {
  selectedMin = m;
  document.querySelectorAll('#minDrum .td-item').forEach(function(el){
    el.classList.toggle('active', parseInt(el.dataset.val) === m);
  });
  document.getElementById('hiddenMin').value = m;
  updateSchedResult();
}

function updateSchedResult() {
  var txt = selectedDayLabel + ' ساعت ' + toFa(pad2(selectedHour)) + ':' + toFa(pad2(selectedMin));
  document.getElementById('schedResultText').textContent = txt;
  document.getElementById('schedSubText').textContent = txt;
}

// scroll ساعت پیش‌فرض
setTimeout(function(){
  var def = document.querySelector('#hourDrum .td-item.active');
  if (def) def.scrollIntoView({block:'center'});
}, 100);

/* ========== ولیدیشن ========== */
document.getElementById('payForm').addEventListener('submit', function(e){
  var fi = document.getElementById('fileInput');
  if (!fi.files || !fi.files[0]) {
    e.preventDefault();
    alert('لطفاً عکس رسید پرداخت را انتخاب کن.');
    return;
  }
  if (schedActive && !selectedDay) {
    e.preventDefault();
    alert('لطفاً تاریخ شروع اشتراک را انتخاب کن.');
    return;
  }
  document.getElementById('submitBtn').disabled = true;
  document.getElementById('submitText').style.display = 'none';
  document.getElementById('submitLoading').style.display = 'block';
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
