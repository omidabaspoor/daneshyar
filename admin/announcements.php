<?php
$adminPage = 'announcements';
$pageTitle = 'اعلان همگانی';
include __DIR__ . '/_header.php';

$msg = '';
$msgType = 'success';

// ─── انتشار / به‌روزرسانی اعلان ───
if (isset($_POST['save_announcement'])) {
    $aTitle = trim($_POST['announcement_title'] ?? '');
    $aText  = trim($_POST['announcement_text'] ?? '');
    if ($aText === '') {
        $msg = '❌ متن اعلان نمی‌تواند خالی باشد.';
        $msgType = 'error';
    } else {
        set_setting('announcement_title', $aTitle !== '' ? $aTitle : 'اطلاعیه');
        set_setting('announcement_text', $aText);
        set_setting('announcement_enabled', '1');
        // شناسه جدید تا برای همه کاربران (حتی کسانی که اعلان قبلی را بسته‌اند) دوباره نمایش داده شود
        set_setting('announcement_id', (string)time());
        $msg = '✓ اعلان فعال شد و به همه کاربرانِ واردشده نمایش داده می‌شود.';
    }
}

// ─── غیرفعال کردن اعلان ───
if (isset($_POST['disable_announcement'])) {
    set_setting('announcement_enabled', '0');
    $msg = '✓ اعلان غیرفعال شد و دیگر به کاربران نمایش داده نمی‌شود.';
}

$annEnabled = !in_array(strtolower((string)get_setting('announcement_enabled', '0')), ['0','false','no','off',''], true);
$annTitle   = (string)get_setting('announcement_title', '');
$annText    = (string)get_setting('announcement_text', '');
?>

<div class="admin-page-header">
  <h2><?= icon('mail') ?> اعلان همگانی به کاربران</h2>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<!-- ═══ کارت وضعیت ═══ -->
<div class="admin-card glass" style="border-right:3px solid <?= $annEnabled ? 'var(--success)' : 'var(--border)' ?>">
  <div class="admin-card-body">
    <div style="display:flex; align-items:center; gap:14px">
      <div style="width:52px;height:52px;border-radius:14px;display:grid;place-items:center;
        background:<?= $annEnabled ? 'rgba(56,217,169,.12)' : 'rgba(150,150,160,.12)' ?>;
        color:<?= $annEnabled ? 'var(--success)' : 'var(--text-dim)' ?>">
        <?= icon($annEnabled ? 'check' : 'mail') ?>
      </div>
      <div>
        <div style="font-weight:800; font-size:16px; color:<?= $annEnabled ? 'var(--success)' : 'inherit' ?>">
          اعلان <?= $annEnabled ? 'فعال' : 'غیرفعال' ?> است
        </div>
        <div class="text-sm text-dim" style="margin-top:4px">
          پیام اینجا به‌صورت پاپ‌آپ وسط صفحه به همه کاربرانِ واردشده (سایت و چت) نشان داده می‌شود.
          هر کاربر می‌تواند آن را ببندد؛ تا انتشار پیام جدید دوباره نمایش داده نمی‌شود.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ فرم اعلان ═══ -->
<div class="admin-card glass" style="margin-top:16px">
  <div class="admin-card-header">
    <h3><?= icon('edit') ?> متن اعلان</h3>
  </div>
  <div class="admin-card-body">
    <form method="post">
      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label">عنوان اعلان</label>
        <input class="input" type="text" name="announcement_title" maxlength="100"
               value="<?= e($annTitle) ?>" placeholder="مثلا: راهنمای مهم">
      </div>
      <div class="form-group" style="margin-bottom:16px">
        <label class="form-label">متن اعلان</label>
        <textarea class="input" name="announcement_text" rows="5" maxlength="1000"
                  placeholder="مثلا: دانش‌آموزان عزیز دقت کنید که حتما کتاب را از قسمت بالای صفحه چت انتخاب کنید."><?= e($annText) ?></textarea>
        <small class="text-dim">می‌توانی چند خط بنویسی؛ خطوط حفظ می‌شوند.</small>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap">
        <button class="btn btn-primary" name="save_announcement" value="1">
          <?= icon('check') ?> <?= $annEnabled ? 'به‌روزرسانی و انتشار مجدد' : 'انتشار اعلان' ?>
        </button>
        <?php if ($annEnabled): ?>
          <button class="btn btn-danger" name="disable_announcement" value="1"
                  onclick="return confirm('اعلان غیرفعال شود؟')">
            <?= icon('close') ?> غیرفعال کردن اعلان
          </button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- ═══ پیش‌نمایش ═══ -->
<?php if ($annEnabled && trim($annText) !== ''): ?>
<div class="admin-card glass" style="margin-top:16px">
  <div class="admin-card-header"><h3><?= icon('sparkle') ?> پیش‌نمایش پاپ‌آپ کاربر</h3></div>
  <div class="admin-card-body">
    <div style="max-width:420px;margin:0 auto;background:linear-gradient(160deg,#16161f,#0f0f17);
      border:1px solid rgba(235,124,42,.35);border-radius:18px;padding:24px;text-align:center">
      <div style="width:54px;height:54px;margin:0 auto 12px;border-radius:50%;display:grid;place-items:center;
        background:rgba(235,124,42,.14);color:#eb7c2a"><?= icon('sparkle') ?></div>
      <div style="font-size:18px;font-weight:800;color:#fff;margin-bottom:8px"><?= e($annTitle ?: 'اطلاعیه') ?></div>
      <div style="color:#cfcfda;font-size:14px;line-height:2;white-space:pre-line"><?= nl2br(e($annText)) ?></div>
      <div style="margin-top:18px;display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:12px;
        font-weight:800;color:#fff;background:linear-gradient(135deg,#eb7c2a,#f0a050)"><?= icon('check') ?> متوجه شدم</div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
