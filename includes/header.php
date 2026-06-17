<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/icons.php';
maybe_run_scheduler(); // فعال‌سازی خودکار اشتراک‌های scheduled
$user = current_user();
$page = $page ?? '';

// ساختار جدید:
// - صفحات عمومی سایت Header/Menu دارند.
// - فضای اپلیکیشن مثل chat.php با hideSiteChrome از سایت جدا می‌شود.
$hideSiteChrome = !empty($hideSiteChrome);
$fullWidthMain  = !empty($fullWidthMain);
$bodyClass      = trim((string)($bodyClass ?? ''));
$brandHref      = $user ? (BASE_URL . '/chat.php') : (BASE_URL . '/');
$mainClass      = $fullWidthMain ? 'app-main' : 'container';
$seoTitle       = $seoTitle ?? (($pageTitle ?? SITE_NAME) . ' | ' . SITE_NAME);
$seoDescription = $seoDescription ?? 'دانش‌یار، دستیار هوش مصنوعی آموزشی برای حل سوالات درسی، توضیح گام‌به‌گام، تحلیل عکس تمرین و پاسخ کتاب‌محور از پایه هفتم تا دوازدهم.';
$seoKeywords    = $seoKeywords ?? 'دانش‌یار, هوش مصنوعی آموزشی, حل سوال درسی, حل تمرین با عکس, کتاب درسی, آموزش آنلاین';
$seoDomain      = rtrim((string)env('PUBLIC_ORIGIN', BASE_URL), '/');
$pathOnly       = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$canonicalUrl   = $canonicalUrl ?? $seoDomain . $pathOnly;
$seoRobots      = $seoRobots ?? ($hideSiteChrome ? 'noindex,nofollow' : 'index,follow,max-image-preview:large');
$ogImage        = $ogImage ?? ($seoDomain . '/assets/img/logo.png');
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($seoTitle) ?></title>
<meta name="theme-color" content="#0a0a10">
<meta name="description" content="<?= e($seoDescription) ?>">
<meta name="keywords" content="<?= e($seoKeywords) ?>">
<meta name="robots" content="<?= e($seoRobots) ?>">
<meta name="author" content="دانش‌یار">
<meta name="language" content="fa">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<meta property="og:locale" content="fa_IR">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($seoTitle) ?>">
<meta property="og:description" content="<?= e($seoDescription) ?>">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($seoTitle) ?>">
<meta name="twitter:description" content="<?= e($seoDescription) ?>">

<link rel="preload" href="<?= BASE_URL ?>/assets/vendor/fonts/Vazirmatn-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fonts/vazirmatn.css">

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=11">
<?php if (!empty($extraCss)) foreach ($extraCss as $c):
  $cssHref = BASE_URL . '/assets/css/' . ltrim((string)$c, '/');
  if (strpos((string)$c, '?') === false) $cssHref .= '?v=16';
?>
<link rel="stylesheet" href="<?= e($cssHref) ?>">
<?php endforeach; ?>

<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/img/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/assets/img/favicon-16x16.png">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/apple-touch-icon.png">
<link rel="manifest" href="<?= BASE_URL ?>/manifest.json">

<!-- PWA Meta Tags برای iOS -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="دانش‌یار">
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="دانش‌یار">

<?php if (!$hideSiteChrome): ?>
<script type="application/ld+json">
<?= json_encode([
  '@context' => 'https://schema.org',
  '@graph' => [
    [
      '@type' => 'SoftwareApplication',
      'name' => SITE_NAME,
      'applicationCategory' => 'EducationalApplication',
      'operatingSystem' => 'Web',
      'url' => $seoDomain,
      'description' => $seoDescription,
      'inLanguage' => 'fa-IR',
      'author' => [
        '@type' => 'Person',
        'name' => 'امید عباسپور'
      ],
      'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'IRR'],
      'aggregateRating' => [
        '@type' => 'AggregateRating',
        'ratingValue' => '4.9',
        'reviewCount' => '1200'
      ]
    ],
    [
      '@type' => 'Organization',
      'name' => SITE_NAME,
      'url' => $seoDomain,
      'logo' => $seoDomain . '/assets/img/logo.png'
    ]
  ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>

</script>
<?php endif; ?>
</head>
<body<?= $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '' ?>>

<?php if (!$hideSiteChrome): ?>
<div class="dy-menu-backdrop" id="dyMenuBackdrop"></div>

<div class="container dy-header-wrap">
  <nav class="navbar dy-header glass">
    <a href="<?= e($brandHref) ?>" class="brand dy-brand">
      <span class="brand-logo">
        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="دانش‌یار" width="40" height="40">
      </span>
      <span><?= e(SITE_NAME) ?></span>
    </a>

    <div class="dy-desktop-menu">
      <?php if ($user): ?>
        <a href="<?= BASE_URL ?>/chat.php" class="<?= $page==='chat'?'active dy-nav-primary':'dy-nav-primary' ?>"><?= icon('chat') ?><span>ورود به چت</span></a>
        <a href="<?= BASE_URL ?>/pricing.php" class="<?= $page==='pricing'?'active':'' ?>"><?= icon('price') ?><span>اشتراک</span></a>
        <a href="<?= BASE_URL ?>/contact.php" class="<?= $page==='contact'?'active':'' ?>"><?= icon('mail') ?><span>ارتباط با ما</span></a>
        <a href="<?= BASE_URL ?>/profile.php" class="<?= $page==='profile'?'active':'' ?>"><?= icon('user') ?><span>پروفایل</span></a>
        <a href="<?= BASE_URL ?>/logout.php"><?= icon('logout') ?><span>خروج</span></a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/" class="<?= $page==='home'?'active':'' ?>"><?= icon('home') ?><span>خانه</span></a>
        <a href="<?= BASE_URL ?>/pricing.php" class="<?= $page==='pricing'?'active':'' ?>"><?= icon('price') ?><span>قیمت‌ها</span></a>
        <a href="<?= BASE_URL ?>/contact.php" class="<?= $page==='contact'?'active':'' ?>"><?= icon('mail') ?><span>ارتباط با ما</span></a>
        <a href="<?= BASE_URL ?>/login.php"><?= icon('login') ?><span>ورود</span></a>
        <a href="<?= BASE_URL ?>/register.php" class="dy-nav-primary"><?= icon('rocket') ?><span>شروع رایگان</span></a>
      <?php endif; ?>
    </div>

    <button class="dy-burger" id="dyMenuOpen" aria-label="باز کردن منو" type="button" aria-expanded="false">
      <?= icon('menu') ?>
    </button>
  </nav>
</div>

<aside class="dy-mobile-menu" id="dyMobileMenu" aria-hidden="true">
  <div class="dy-mobile-head">
    <a href="<?= e($brandHref) ?>" class="brand dy-brand">
      <span class="brand-logo">
        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="" width="38" height="38">
      </span>
      <span><?= e(SITE_NAME) ?></span>
    </a>
    <button class="dy-menu-close" id="dyMenuClose" aria-label="بستن منو" type="button"><?= icon('close') ?></button>
  </div>

  <?php if ($user): ?>
    <div class="dy-mobile-user">
      <div class="dy-mobile-avatar"><?= e(mb_substr($user['first_name'], 0, 1)) ?></div>
      <div>
        <b><?= e($user['first_name']) ?></b>
        <small>پایه <?= num_fa($user['grade']) ?><?= isset($user['major']) ? ' · ' . e(major_label($user['major'])) : '' ?></small>
      </div>
    </div>
  <?php endif; ?>

  <div class="dy-mobile-links">
    <?php if ($user): ?>
      <a href="<?= BASE_URL ?>/chat.php" class="<?= $page==='chat'?'active dy-nav-primary':'dy-nav-primary' ?>"><?= icon('chat') ?><span>ورود به چت</span></a>
      <a href="<?= BASE_URL ?>/pricing.php" class="<?= $page==='pricing'?'active':'' ?>"><?= icon('price') ?><span>اشتراک</span></a>
      <a href="<?= BASE_URL ?>/contact.php" class="<?= $page==='contact'?'active':'' ?>"><?= icon('mail') ?><span>ارتباط با ما</span></a>
      <a href="<?= BASE_URL ?>/profile.php" class="<?= $page==='profile'?'active':'' ?>"><?= icon('user') ?><span>پروفایل</span></a>
      <a href="<?= BASE_URL ?>/logout.php"><?= icon('logout') ?><span>خروج</span></a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/" class="<?= $page==='home'?'active':'' ?>"><?= icon('home') ?><span>خانه</span></a>
      <a href="<?= BASE_URL ?>/pricing.php" class="<?= $page==='pricing'?'active':'' ?>"><?= icon('price') ?><span>قیمت‌ها</span></a>
      <a href="<?= BASE_URL ?>/contact.php" class="<?= $page==='contact'?'active':'' ?>"><?= icon('mail') ?><span>ارتباط با ما</span></a>
      <a href="<?= BASE_URL ?>/login.php"><?= icon('login') ?><span>ورود</span></a>
      <a href="<?= BASE_URL ?>/register.php" class="dy-nav-primary"><?= icon('rocket') ?><span>شروع رایگان</span></a>
    <?php endif; ?>
  </div>
</aside>

<script>
(function(){
  var openBtn = document.getElementById('dyMenuOpen');
  var closeBtn = document.getElementById('dyMenuClose');
  var menu = document.getElementById('dyMobileMenu');
  var backdrop = document.getElementById('dyMenuBackdrop');
  if (!openBtn || !menu || !backdrop) return;

  function openMenu(){
    document.body.classList.add('dy-menu-open');
    menu.classList.add('open');
    backdrop.classList.add('show');
    menu.setAttribute('aria-hidden', 'false');
    openBtn.setAttribute('aria-expanded', 'true');
  }
  function closeMenu(){
    document.body.classList.remove('dy-menu-open');
    menu.classList.remove('open');
    backdrop.classList.remove('show');
    menu.setAttribute('aria-hidden', 'true');
    openBtn.setAttribute('aria-expanded', 'false');
  }
  openBtn.addEventListener('click', function(e){ e.preventDefault(); menu.classList.contains('open') ? closeMenu() : openMenu(); });
  closeBtn && closeBtn.addEventListener('click', closeMenu);
  backdrop.addEventListener('click', closeMenu);
  menu.querySelectorAll('a').forEach(function(a){ a.addEventListener('click', closeMenu); });
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeMenu(); });
  window.addEventListener('resize', function(){ if(window.innerWidth > 820) closeMenu(); });
})();
</script>
<?php endif; ?>

<?php
// ───── اعلان همگانی (پاپ‌آپ وسط صفحه برای کاربران لاگین‌شده) ─────
$dyAnnouncement = $user ? active_announcement() : null;
if ($dyAnnouncement):
?>
<div class="dy-ann-overlay" id="dyAnnOverlay" data-ann-id="<?= e($dyAnnouncement['id']) ?>" role="dialog" aria-modal="true" aria-labelledby="dyAnnTitle">
  <div class="dy-ann-modal">
    <div class="dy-ann-icon" aria-hidden="true"><?= icon('sparkle') ?></div>
    <h3 class="dy-ann-title" id="dyAnnTitle"><?= e($dyAnnouncement['title']) ?></h3>
    <div class="dy-ann-text"><?= nl2br(e($dyAnnouncement['text'])) ?></div>
    <button type="button" class="dy-ann-btn" id="dyAnnClose"><?= icon('check') ?> متوجه شدم</button>
  </div>
</div>
<style>
.dy-ann-overlay{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;
  padding:20px;background:rgba(8,8,14,.72);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
.dy-ann-overlay.show{display:flex;animation:dyAnnFade .2s ease}
@keyframes dyAnnFade{from{opacity:0}to{opacity:1}}
.dy-ann-modal{width:100%;max-width:440px;background:linear-gradient(160deg,#16161f,#0f0f17);
  border:1px solid rgba(235,124,42,.35);border-radius:20px;padding:28px 24px;text-align:center;
  box-shadow:0 24px 70px rgba(0,0,0,.6);animation:dyAnnPop .25s cubic-bezier(.2,.9,.3,1.2)}
@keyframes dyAnnPop{from{transform:translateY(16px) scale(.96);opacity:0}to{transform:none;opacity:1}}
.dy-ann-icon{width:60px;height:60px;margin:0 auto 14px;border-radius:50%;display:grid;place-items:center;
  background:rgba(235,124,42,.14);color:#eb7c2a}
.dy-ann-icon svg{width:30px;height:30px}
.dy-ann-title{margin:0 0 10px;font-size:19px;font-weight:800;color:#fff}
.dy-ann-text{color:#cfcfda;font-size:15px;line-height:2;margin-bottom:22px;white-space:pre-line;
  max-height:46vh;overflow:auto}
.dy-ann-btn{display:inline-flex;align-items:center;gap:8px;justify-content:center;width:100%;
  padding:13px 18px;border:none;border-radius:13px;cursor:pointer;font-family:inherit;font-size:15px;
  font-weight:800;color:#fff;background:linear-gradient(135deg,#eb7c2a,#f0a050)}
.dy-ann-btn svg{width:18px;height:18px}
.dy-ann-btn:active{transform:scale(.98)}
</style>
<script>
(function(){
  var ov = document.getElementById('dyAnnOverlay');
  if(!ov) return;
  var id = ov.getAttribute('data-ann-id') || '0';
  var key = 'dy_ann_dismissed';
  var seen = '';
  try { seen = localStorage.getItem(key) || ''; } catch(e) {}
  if (seen === id) return; // این اعلان قبلاً بسته شده
  ov.classList.add('show');
  document.body.style.overflow = 'hidden';
  function dismiss(){
    ov.classList.remove('show');
    document.body.style.overflow = '';
    try { localStorage.setItem(key, id); } catch(e) {}
  }
  var btn = document.getElementById('dyAnnClose');
  btn && btn.addEventListener('click', dismiss);
  ov.addEventListener('click', function(e){ if(e.target === ov) dismiss(); });
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape') dismiss(); });
})();
</script>
<?php endif; ?>

<main class="<?= e($mainClass) ?>">
