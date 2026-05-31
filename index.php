<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
if (current_user()) redirect(BASE_URL . '/chat.php');
$page = 'home';
$pageTitle = 'هوش مصنوعی آموزشی';
$seoTitle = 'دانش‌یار | دستیار هوش مصنوعی درس و کتاب درسی پایه هفتم تا دوازدهم';
$seoDescription = 'دانش‌یار دستیار هوش مصنوعی آموزشی برای دانش‌آموزان پایه هفتم تا دوازدهم است؛ حل سوال با عکس، توضیح گام‌به‌گام و پاسخ کتاب‌محور با بیش از ۷۰ کتاب درسی ثبت‌شده.';
$seoKeywords = 'دانش یار, دانش‌یار, هوش مصنوعی آموزشی, حل سوال درسی, حل تمرین با عکس, کتاب درسی, پایه هفتم, پایه هشتم, پایه نهم, پایه دهم, پایه یازدهم, پایه دوازدهم';
include __DIR__ . '/includes/header.php';
?>

<!-- ============ HERO ============ -->
<section class="hero">
  <div class="hero-text">
    <span class="hero-badge"><?= icon('book') ?> بیش از ۷۰ کتاب درسی ثبت‌شده</span>
    <h1>هر سوال درسی،<br><span class="grad">یک توضیح روشن</span></h1>
    <p class="hero-desc">دانش‌یار پیشرفته‌ترین دستیار هوش مصنوعی آموزشی برای دانش‌آموزان پایه ۷ تا ۱۲ است که با تحلیل دقیق عکس تمرینات و استناد به ۷۰+ کتاب درسی، پاسخ‌های گام‌به‌گام، تشریحی و نمره‌آور ارائه می‌دهد. سریع‌ترین راه برای یادگیری مفاهیم پیچیده درسی.</p>

    <div class="hero-stats">
      <div class="hstat">
        <div class="hstat-v">۲۴/۷</div>
        <div class="hstat-l">همیشه آماده</div>
      </div>
      <div class="hstat">
        <div class="hstat-v">۷ تا ۱۲</div>
        <div class="hstat-l">همراه همه پایه‌ها</div>
      </div>
      <div class="hstat">
        <div class="hstat-v">۷۰+</div>
        <div class="hstat-l">کتاب درسی ثبت‌شده</div>
      </div>
    </div>

    <div class="hero-actions">
      <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-hero">
        <?= icon('rocket') ?> همین حالا شروع کن
      </a>
      <a href="<?= BASE_URL ?>/pricing.php" class="btn btn-ghost btn-hero">
        <?= icon('crown') ?> پلن‌ها
      </a>
    </div>

    <div class="hero-trust">
      <?= icon('shield') ?> <span>شروع سریع، ساده و بدون گیج‌شدن</span>
    </div>
  </div>

  <!-- Mock Chat -->
  <div class="hero-chat-mock">
    <div class="hcm-header">
      <div class="hcm-dots"><span></span><span></span><span></span></div>
      <div class="hcm-title">دانش‌یار · چت زنده</div>
      <div class="hcm-status"><span class="hcm-pulse"></span> آنلاین</div>
    </div>

    <div class="hcm-body">
      <div class="hcm-msg hcm-user">
        <div class="hcm-bub">
          <div class="hcm-img-mock">
            <?= icon('screenshot', ['class'=>'ico-imgmock']) ?>
            <span>عکس تمرین کتاب</span>
          </div>
          <span class="hcm-text">این مسئله رو نمی‌فهمم، توضیح بده 🤔</span>
        </div>
      </div>

      <div class="hcm-msg hcm-ai">
        <div class="hcm-avatar"><img src="<?= BASE_URL ?>/assets/img/logo.png" alt="" width="32" height="32"></div>
        <div class="hcm-bub">
          <div class="hcm-answer">✓ <b>پاسخ: گزینه ۳</b></div>
          <div class="hcm-explain">
            با فرمول دلتا حل می‌کنیم:<br>
            <span class="hcm-formula">Δ = b² - 4ac = 9 - 8 = <b>1</b></span><br>
            چون Δ &gt; 0 پس دو ریشه حقیقی داره ✨
          </div>
        </div>
      </div>

      <div class="hcm-typing">
        <span></span><span></span><span></span>
      </div>
    </div>
  </div>
</section>

<!-- ============ بنر یادگیری (در موبایل بالاتر) ============ -->
<section class="exam-banner glass">
  <div class="exam-bg-shape"></div>
  <div class="exam-content">
    <div class="exam-icon"><?= icon('rocket') ?></div>
    <h2>درس‌خوندن سخت نیست، اگر توضیح درست داشته باشی!</h2>
    <p>با بیش از <b>۷۰ کتاب درسی ثبت‌شده</b>، دانش‌یار کمک می‌کنه پاسخ‌ها فقط یک جواب خشک نباشن؛ توضیح آموزشی، مرحله‌به‌مرحله و قابل فهم بگیری تا واقعاً درس رو یاد بگیری، نه فقط جواب رو حفظ کنی 🚀</p>
    <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-hero">
      <?= icon('flash') ?> همین الان رایگان شروع کن
    </a>
  </div>
</section>

<!-- ============ ویژگی‌ها ============ -->
<section class="features-wrap">
  <h2 class="section-title">چرا <span class="grad">دانش‌یار</span>؟</h2>
  <p class="section-sub">ساده، سریع، کتاب‌محور و همیشه در دسترس 🎯</p>

  <div class="features">
    <div class="feature glass">
      <div class="feat-icon"><?= icon('screenshot') ?></div>
      <div class="feat-body">
        <h3>عکس بفرست، حل تشریحی بگیر</h3>
        <p>از تمرین، جزوه یا صفحه کتاب عکس بفرست؛ دانش‌یار سوال را می‌خواند و راه‌حل قابل فهم می‌دهد.</p>
      </div>
    </div>
    <div class="feature glass">
      <div class="feat-icon"><?= icon('flash') ?></div>
      <div class="feat-body">
        <h3>جواب سریع، اما آموزشی</h3>
        <p>فقط گزینه نهایی نمی‌گیری؛ دلیل، فرمول و نکته اصلی را هم می‌فهمی.</p>
      </div>
    </div>
    <div class="feature glass">
      <div class="feat-icon"><?= icon('book') ?></div>
      <div class="feat-body">
        <h3>کتاب‌محور و منظم</h3>
        <p>کتاب مرجع را انتخاب کن تا پاسخ‌ها با فضای کتاب درسی و سطح پایه‌ات هماهنگ‌تر شوند.</p>
      </div>
    </div>
    <div class="feature glass">
      <div class="feat-icon"><?= icon('brain') ?></div>
      <div class="feat-body">
        <h3>بیش از ۷۰ کتاب درسی</h3>
        <p>از پایه هفتم تا دوازدهم، کتاب‌ها دسته‌بندی شده‌اند تا سریع‌تر به منبع درست برسی.</p>
      </div>
    </div>
    <div class="feature glass">
      <div class="feat-icon"><?= icon('trophy') ?></div>
      <div class="feat-body">
        <h3>مناسب شب امتحان و تمرین روزانه</h3>
        <p>چه مرور سریع بخوای، چه حل قدم‌به‌قدم، دانش‌یار مثل همراه درسی کنارت می‌مونه.</p>
      </div>
    </div>
    <div class="feature glass">
      <div class="feat-icon"><?= icon('lock') ?></div>
      <div class="feat-body">
        <h3>کاملاً خصوصی</h3>
        <p>چت‌های شما خصوصی‌اند و فقط برای خودت قابل دسترسی هستند.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============ ۳ قدم ============ -->
<section class="steps-wrap">
  <h2 class="section-title">فقط در ۳ قدم</h2>
  <p class="section-sub">بدون پیچیدگی؛ انتخاب کتاب، ارسال سوال، یادگیری</p>

  <div class="steps">
    <div class="step glass">
      <div class="step-num">1</div>
      <div class="step-icon"><?= icon('camera') ?></div>
      <h3>کتاب یا حالت عمومی را انتخاب کن</h3>
      <p>اگر سوالت از کتاب خاصی است، اول کتاب مرجع را انتخاب کن</p>
    </div>
    <div class="step-arrow"><?= icon('arrow-left') ?></div>
    <div class="step glass">
      <div class="step-num">2</div>
      <div class="step-icon"><?= icon('send') ?></div>
      <h3>سوالت را بفرست</h3>
      <p>متن سوال را بنویس یا از تمرین و جزوه عکس بفرست</p>
    </div>
    <div class="step-arrow"><?= icon('arrow-left') ?></div>
    <div class="step glass">
      <div class="step-num">3</div>
      <div class="step-icon"><?= icon('check') ?></div>
      <h3>واقعاً بفهم</h3>
      <p>جواب، راه‌حل و نکته آموزشی را یکجا دریافت کن</p>
    </div>
  </div>
</section>


<!-- ============ سوالات پرتکرار ============ -->
<section class="faq-wrap">
  <h2 class="section-title">سوالات پرتکرار</h2>
  <p class="section-sub">هر چیزی که قبل از شروع باید بدونی</p>
  <div class="faq-grid">
    <div class="faq-item glass">
      <h3>دانش‌یار دقیقاً چه کاری انجام می‌دهد؟</h3>
      <p>سوال درسی را از متن یا عکس می‌گیرد و به‌جای جواب کوتاه، راه‌حل آموزشی و مرحله‌به‌مرحله می‌دهد.</p>
    </div>
    <div class="faq-item glass">
      <h3>آیا پاسخ‌ها طبق کتاب درسی هستند؟</h3>
      <p>بله؛ می‌توانی کتاب مرجع را انتخاب کنی تا پاسخ‌ها با پایه، رشته و کتاب درسی هماهنگ‌تر باشند.</p>
    </div>
    <div class="faq-item glass">
      <h3>چه پایه‌هایی پشتیبانی می‌شوند؟</h3>
      <p>دانش‌یار برای پایه هفتم تا دوازدهم طراحی شده و بیش از ۷۰ کتاب درسی در سیستم قابل ثبت و استفاده است.</p>
    </div>
    <div class="faq-item glass">
      <h3>می‌توانم عکس تمرین بفرستم؟</h3>
      <p>بله؛ از تمرین، تست یا صفحه کتاب عکس بفرست تا دانش‌یار آن را بخواند و توضیح قابل فهم بدهد.</p>
    </div>
  </div>
</section>

<script type="application/ld+json">
<?= json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'FAQPage',
  'mainEntity' => [
    ['@type'=>'Question','name'=>'دانش‌یار دقیقاً چه کاری انجام می‌دهد؟','acceptedAnswer'=>['@type'=>'Answer','text'=>'دانش‌یار سوال درسی را از متن یا عکس دریافت می‌کند و راه‌حل آموزشی، مرحله‌به‌مرحله و قابل فهم ارائه می‌دهد.']],
    ['@type'=>'Question','name'=>'آیا پاسخ‌ها طبق کتاب درسی هستند؟','acceptedAnswer'=>['@type'=>'Answer','text'=>'بله، کاربر می‌تواند کتاب مرجع را انتخاب کند تا پاسخ‌ها با پایه، رشته و کتاب درسی هماهنگ‌تر باشند.']],
    ['@type'=>'Question','name'=>'چه پایه‌هایی پشتیبانی می‌شوند؟','acceptedAnswer'=>['@type'=>'Answer','text'=>'دانش‌یار برای پایه هفتم تا دوازدهم طراحی شده و بیش از ۷۰ کتاب درسی در سیستم قابل ثبت و استفاده است.']],
    ['@type'=>'Question','name'=>'می‌توانم عکس تمرین بفرستم؟','acceptedAnswer'=>['@type'=>'Answer','text'=>'بله، کاربر می‌تواند از تمرین، تست یا صفحه کتاب عکس ارسال کند و توضیح آموزشی دریافت کند.']],
  ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>

<!-- ============ CTA نهایی ============ -->
<section class="final-cta">
  <div class="glass glass-orange final-cta-card">
    <h2>از همین امروز باهوش‌تر درس بخون 🚀</h2>
    <p>دانش‌یار کمک می‌کنه کمتر سردرگم بشی، سریع‌تر جواب بگیری و عمیق‌تر یاد بگیری. وقتشه درس‌خوندن رو ساده‌تر کنی.</p>
    <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-hero">
      <?= icon('sparkle') ?> شروع یادگیری با دانش‌یار
    </a>
    <small>💎 شروع رایگان روزانه، سریع و بدون دردسر</small>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>


<script>
(function(){
  const mockChat = document.querySelector('.hcm-body');
  if(!mockChat) return;

  const messages = [
    { role: 'user', text: 'این مسئله رو نمی‌فهمم، توضیح بده 🤔', type: 'text' },
    { role: 'ai', text: '✓ پاسخ: گزینه ۳\n\nبا فرمول دلتا حل می‌کنیم:\nΔ = b² - 4ac = 9 - 8 = 1\nچون Δ > 0 پس دو ریشه حقیقی داره ✨', type: 'full' }
  ];

  const hcmBody = mockChat;
  hcmBody.innerHTML = ''; // Clear initial static content

  async function typeText(element, text, speed = 30) {
    for (let i = 0; i < text.length; i++) {
      element.innerHTML += text.charAt(i);
      await new Promise(r => setTimeout(r, speed));
    }
  }

  async function runSimulation() {
    for (const msg of messages) {
      const msgDiv = document.createElement('div');
      msgDiv.className = 'hcm-msg ' + (msg.role === 'user' ? 'hcm-user' : 'hcm-ai');
      
      if (msg.role === 'user') {
        msgDiv.innerHTML = `<div class="hcm-bub"><div class="hcm-img-mock"><svg class="ico-imgmock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg><span>عکس تمرین کتاب</span></div><span class="hcm-text"></span></div>`;
        hcmBody.appendChild(msgDiv);
        await typeText(msgDiv.querySelector('.hcm-text'), msg.text, 40);
      } else {
        msgDiv.innerHTML = `<div class="hcm-avatar"><img src="${window.DANESHYAR?.logoUrl || '/assets/img/logo.png'}" alt="" width="32" height="32"></div><div class="hcm-bub"><div class="hcm-content"></div></div>`;
        hcmBody.appendChild(msgDiv);
        const contentDiv = msgDiv.querySelector('.hcm-content');
        
        // AI specific formatting for the simulation
        const lines = msg.text.split('\n');
        for (const line of lines) {
          const lineDiv = document.createElement('div');
          lineDiv.style.marginBottom = '5px';
          if (line.startsWith('✓')) lineDiv.style.fontWeight = '800';
          contentDiv.appendChild(lineDiv);
          await typeText(lineDiv, line, 20);
        }
      }
      await new Promise(r => setTimeout(r, 1000));
    }
    
    // Add typing indicator at the end
    const typing = document.createElement('div');
    typing.className = 'hcm-typing';
    typing.innerHTML = '<span></span><span></span><span></span>';
    hcmBody.appendChild(typing);
  }

  runSimulation();
})();
</script>
