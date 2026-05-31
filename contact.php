<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
$user       = current_user();
$page       = 'contact';
$pageTitle  = 'ارتباط با ما';

$msg        = '';
$msgType    = '';
$activeTab  = $_GET['tab'] ?? 'contact';

// ارسال پیام
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeTab !== 'book-request') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $msg = 'درخواست نامعتبر است.'; $msgType = 'error';
    } else {
        [$ok, $msg] = submit_message($user ? $user['id'] : null, $_POST);
        $msgType = $ok ? 'success' : 'error';
    }
}

// درخواست کتاب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeTab === 'book-request') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $msg = 'درخواست نامعتبر است.'; $msgType = 'error';
    } elseif (!$user) {
        redirect(BASE_URL . '/login.php');
    } else {
        [$ok, $msg] = submit_book_request($user['id'], $_POST);
        $msgType = $ok ? 'success' : 'error';
    }
}

// درخواست‌های قبلی کاربر
$myRequests = [];
if ($user) {
    try {
        $stmt = db()->prepare("SELECT * FROM book_requests WHERE user_id=? ORDER BY id DESC LIMIT 10");
        $stmt->execute([$user['id']]);
        $myRequests = $stmt->fetchAll();
    } catch (Throwable $e) {}
}

include __DIR__ . '/includes/header.php';
?>

<div class="contact-page">
  <div class="contact-header">
    <div class="contact-icon"><?= icon('mail') ?></div>
    <h1>ارتباط با ما</h1>
    <p class="contact-desc">سوالی دارید؟ کتابی نیاز دارید؟ یا پیشنهادی؟ با ما در ارتباط باشید.</p>
  </div>

  <!-- اطلاعات تماس مستقیم -->
  <div class="contact-info-cards">
    <a href="tel:<?= CONTACT_PHONE ?>" class="contact-info-card glass">
      <?= icon('phone') ?>
      <div>
        <div class="ci-label">تماس مستقیم</div>
        <div class="ci-value" dir="ltr"><?= e(CONTACT_PHONE) ?></div>
      </div>
    </a>
    <a href="https://t.me/<?= e(CONTACT_TELEGRAM) ?>" target="_blank" rel="noopener" class="contact-info-card glass">
      <?= icon('send') ?>
      <div>
        <div class="ci-label">تلگرام</div>
        <div class="ci-value" dir="ltr">@<?= e(CONTACT_TELEGRAM) ?></div>
      </div>
    </a>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= $msgType === 'success' ? icon('check') : icon('warning') ?> <?= e($msg) ?></div>
  <?php endif; ?>

  <!-- تب‌ها -->
  <div class="contact-tabs">
    <a href="?tab=contact" class="contact-tab <?= $activeTab !== 'book-request' ? 'active' : '' ?>">
      <?= icon('mail') ?> ارسال پیام
    </a>
    <a href="?tab=book-request" class="contact-tab <?= $activeTab === 'book-request' ? 'active' : '' ?>">
      <?= icon('book') ?> درخواست کتاب
    </a>
  </div>

  <!-- تب ارسال پیام -->
  <?php if ($activeTab !== 'book-request'): ?>
  <div class="glass contact-card">
    <h3 class="contact-card-title"><?= icon('mail') ?> ارسال پیام</h3>
    <form method="post" novalidate>
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

      <div class="form-group">
        <label class="form-label">نام و نام خانوادگی</label>
        <input class="input" name="name" required placeholder="نام شما"
               value="<?= $user ? e($user['first_name'].' '.$user['last_name']) : '' ?>">
      </div>
      <div class="form-group">
        <label class="form-label">موضوع</label>
        <input class="input" name="subject" required placeholder="موضوع پیام شما">
      </div>
      <div class="form-group">
        <label class="form-label">متن پیام</label>
        <textarea class="input textarea" name="body" rows="5" required placeholder="پیام خود را اینجا بنویسید..."></textarea>
      </div>
      <button class="btn btn-primary btn-block" type="submit">
        <?= icon('send') ?> ارسال پیام
      </button>
    </form>
  </div>

  <!-- تب درخواست کتاب -->
  <?php else: ?>

  <?php if (!$user): ?>
    <div class="glass contact-card">
      <div class="alert alert-info">
        <?= icon('login') ?> برای درخواست کتاب ابتدا <a href="<?= BASE_URL ?>/login.php">وارد شوید</a> یا <a href="<?= BASE_URL ?>/register.php">ثبت‌نام کنید</a>.
      </div>
    </div>
  <?php else: ?>
  <div class="contact-grid">
    <div class="glass contact-card">
      <h3 class="contact-card-title"><?= icon('book') ?> درخواست اضافه کردن کتاب درسی</h3>
      <p style="color:var(--text-dim);font-size:13px;margin-bottom:16px">اگر کتاب درسی پایه و رشته خودت رو تو سیستم پیدا نکردی، اینجا درخواست بده.</p>
      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="form-group">
          <label class="form-label">عنوان کتاب</label>
          <input class="input" name="title" required placeholder="مثلاً: ریاضی پایه دهم">
        </div>
        <div class="contact-row-2">
          <div class="form-group">
            <label class="form-label">پایه</label>
            <select class="select" name="grade" required>
              <?php for ($g = 7; $g <= 12; $g++): ?>
                <option value="<?= $g ?>" <?= $user['grade'] == $g ? 'selected' : '' ?>>پایه <?= num_fa($g) ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">رشته</label>
            <select class="select" name="major" required>
              <?php foreach (major_options() as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= ($user['major'] ?? 'math') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">نام درس</label>
          <input class="input" name="subject" required placeholder="مثلاً: ریاضی، فیزیک، شیمی...">
        </div>
        <div class="form-group">
          <label class="form-label">توضیحات <small style="color:var(--text-muted)">(اختیاری)</small></label>
          <textarea class="input textarea" name="description" rows="3" placeholder="نام نویسنده، انتشارات یا توضیح اضافی..."></textarea>
        </div>
        <button class="btn btn-primary btn-block" type="submit">
          <?= icon('send') ?> ارسال درخواست کتاب
        </button>
      </form>
    </div>

    <?php if (!empty($myRequests)): ?>
    <div class="glass contact-card">
      <h3 class="contact-card-title"><?= icon('clipboard') ?> درخواست‌های شما</h3>
      <div class="contact-requests">
        <?php foreach ($myRequests as $r): ?>
          <div class="cr-item cr-<?= e($r['status']) ?>">
            <div class="cr-main">
              <b><?= e($r['title']) ?></b>
              <small>پایه <?= num_fa($r['grade']) ?> · <?= e($r['subject']) ?></small>
            </div>
            <span class="cr-badge cr-badge-<?= e($r['status']) ?>">
              <?php if ($r['status']==='pending'): ?>در انتظار
              <?php elseif ($r['status']==='approved'): ?>تایید شده
              <?php else: ?>رد شده<?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<style>
.contact-page { max-width: 700px; margin: 0 auto; padding: 20px 0 40px; }
.contact-header { text-align: center; margin-bottom: 24px; }
.contact-icon { width:56px; height:56px; margin:0 auto 14px; border-radius:16px; display:grid; place-items:center; background:linear-gradient(135deg,var(--orange),var(--orange-2)); color:#1a0e05; box-shadow:0 8px 24px rgba(235,124,42,.45); }
.contact-icon .ico { width:26px; height:26px; }
.contact-header h1 { font-size:24px; font-weight:800; margin-bottom:8px; }
.contact-desc { color:var(--text-dim); font-size:14px; max-width:480px; margin:0 auto; line-height:1.7; }

/* اطلاعات تماس */
.contact-info-cards { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:20px; }
.contact-info-card { display:flex; align-items:center; gap:12px; padding:14px 16px; text-decoration:none; color:var(--text); transition:.2s; }
.contact-info-card:hover { border-color:var(--border-orange); transform:translateY(-2px); }
.contact-info-card .ico { width:22px; height:22px; color:var(--orange); flex-shrink:0; }
.ci-label { font-size:11px; color:var(--text-dim); }
.ci-value { font-size:14px; font-weight:700; direction:ltr; text-align:right; }

.contact-tabs { display:flex; gap:8px; margin-bottom:20px; background:var(--glass-2); border-radius:14px; padding:4px; border:1px solid var(--border); }
.contact-tab { flex:1; display:flex; align-items:center; justify-content:center; gap:7px; padding:12px; border-radius:11px; font-size:14px; font-weight:600; color:var(--text-dim); transition:.15s; text-decoration:none; }
.contact-tab .ico { width:17px; height:17px; }
.contact-tab:hover { color:var(--text); background:var(--glass); }
.contact-tab.active { background:linear-gradient(135deg,var(--orange),var(--orange-2)); color:#1a0e05; box-shadow:0 4px 14px rgba(235,124,42,.4); }

.contact-card { padding:22px; }
.contact-card-title { display:flex; align-items:center; gap:8px; color:var(--orange); font-size:16px; margin-bottom:16px; }
.contact-card-title .ico { width:20px; height:20px; }

.contact-grid { display:grid; grid-template-columns:1fr; gap:16px; }
.contact-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

.contact-requests { display:flex; flex-direction:column; gap:8px; }
.cr-item { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-radius:10px; background:rgba(255,255,255,.03); border:1px solid var(--border); }
.cr-item.cr-approved { border-color:rgba(56,217,169,.25); background:rgba(56,217,169,.05); }
.cr-item.cr-rejected { border-color:rgba(255,84,112,.2); background:rgba(255,84,112,.04); }
.cr-main b { display:block; font-size:13px; }
.cr-main small { color:var(--text-dim); font-size:11px; }
.cr-badge { padding:3px 10px; border-radius:8px; font-size:11px; font-weight:700; white-space:nowrap; }
.cr-badge-pending { background:rgba(255,184,107,.12); color:#ffb86b; }
.cr-badge-approved { background:rgba(56,217,169,.12); color:#38d9a9; }
.cr-badge-rejected { background:rgba(255,84,112,.10); color:#ff5470; }

@media(max-width:480px){ .contact-row-2,.contact-info-cards { grid-template-columns:1fr; } }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
