<?php
$adminPage = 'receipts';
$pageTitle  = 'رسیدهای پرداخت';
include __DIR__ . '/_header.php';

$msg     = '';
$msgType = 'success';

/* ===== اقدامات ادمین ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rid    = (int)($_POST['receipt_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($rid > 0) {
        $stmt = db()->prepare("SELECT * FROM card_receipts WHERE id=?");
        $stmt->execute([$rid]);
        $receipt = $stmt->fetch();

        if (!$receipt) {
            $msg = 'رسید یافت نشد.'; $msgType = 'error';

        } elseif ($action === 'approve') {
            // بررسی زمان‌بندی
            $activateAt = $receipt['activate_at'];
            $now = time();

            if ($activateAt && strtotime($activateAt) > $now + 120) {
                // تایید شد ولی زمانش نرسیده → approved_pending
                db()->prepare("UPDATE card_receipts SET status='approved_pending', reviewed_at=NOW(), reviewed_by=? WHERE id=?")
                    ->execute([current_admin()['id'], $rid]);
                $diffMin = round((strtotime($activateAt) - $now) / 60);
                $msg = '✓ تایید شد. اشتراک در ' . num_fa($diffMin) . ' دقیقه دیگر خودکار فعال می‌شه.';
            } else {
                // فعال‌سازی فوری
                $ok = activate_subscription($receipt['user_id'], $receipt['plan_code'], $activateAt ?: null, 'card', 'receipt_' . $rid);
                if ($ok) {
                    db()->prepare("UPDATE card_receipts SET status='approved', reviewed_at=NOW(), reviewed_by=? WHERE id=?")
                        ->execute([current_admin()['id'], $rid]);
                    $msg = '✓ اشتراک کاربر همین الان فعال شد!';
                } else {
                    $msg = 'خطا در فعال‌سازی.'; $msgType = 'error';
                }
            }

        } elseif ($action === 'reject') {
            $note = trim($_POST['admin_note'] ?? '');
            db()->prepare("UPDATE card_receipts SET status='rejected', admin_note=?, reviewed_at=NOW(), reviewed_by=? WHERE id=?")
                ->execute([$note ?: null, current_admin()['id'], $rid]);
            $msg = 'رسید رد شد.';
        }
    }
}

/* ===== فیلتر ===== */
$filter = $_GET['status'] ?? 'pending';
if (!in_array($filter, ['','pending','approved_pending','approved','rejected'])) $filter = 'pending';

$where  = $filter ? "WHERE cr.status=?" : "";
$params = $filter ? [$filter] : [];

$stmt = db()->prepare("
    SELECT cr.*, u.first_name, u.last_name, u.mobile
    FROM card_receipts cr
    LEFT JOIN users u ON u.id=cr.user_id
    $where
    ORDER BY cr.id DESC
    LIMIT 200
");
$stmt->execute($params);
$receipts = $stmt->fetchAll();

// آمار سریع
$counts = [];
foreach (['pending','approved_pending','approved','rejected'] as $s) {
    $counts[$s] = (int)db()->query("SELECT COUNT(*) FROM card_receipts WHERE status='$s'")->fetchColumn();
}
$pendingAll = $counts['pending'] + $counts['approved_pending'];

$filterLabels = [
    ''                 => 'همه',
    'pending'          => 'در انتظار بررسی',
    'approved_pending' => 'تایید شده – زمان‌بندی',
    'approved'         => 'فعال شده',
    'rejected'         => 'رد شده',
];
?>

<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:10px">
  <h2 style="display:flex; align-items:center; gap:10px; margin:0">
    <?= icon('wallet') ?> رسیدهای پرداخت
    <?php if ($pendingAll > 0): ?>
      <span style="background:var(--danger); color:#fff; font-size:11px; padding:2px 8px; border-radius:20px; font-weight:800"><?= num_fa($pendingAll) ?> نیاز به بررسی</span>
    <?php endif; ?>
  </h2>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>" style="margin-bottom:16px"><?= e($msg) ?></div>
<?php endif; ?>

<!-- فیلتر تب‌ها -->
<div style="display:flex; gap:6px; margin-bottom:18px; flex-wrap:wrap">
  <?php foreach ($filterLabels as $f => $label): ?>
    <a href="?status=<?= e($f) ?>" class="btn btn-sm <?= $filter === $f ? 'btn-primary' : 'btn-ghost' ?>">
      <?= e($label) ?>
      <?php $cnt = ($f === '' ? array_sum($counts) : ($counts[$f] ?? 0));
            if ($cnt > 0): ?>
        <span style="background:rgba(255,255,255,.25); border-radius:8px; padding:0 6px; font-size:10px; margin-right:2px"><?= num_fa($cnt) ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (empty($receipts)): ?>
  <div class="glass" style="padding:40px; text-align:center; color:var(--text-dim)">
    <?= icon('check') ?> موردی برای نمایش وجود ندارد.
  </div>
<?php else: ?>

<div style="display:grid; gap:12px">
<?php foreach ($receipts as $r):
  $statusMap = [
    'pending'          => ['🕐', '#ffb86b', 'در انتظار بررسی'],
    'approved_pending' => ['⏰', '#4dabf7', 'تایید شده – زمان‌بندی'],
    'approved'         => ['✅', '#38d9a9', 'فعال شده'],
    'rejected'         => ['❌', '#ff5470', 'رد شده'],
  ];
  [$sIco, $sColor, $sLabel] = $statusMap[$r['status']] ?? ['?', '#fff', $r['status']];
  $isPending = $r['status'] === 'pending';
?>
  <div class="glass" style="border-right:3px solid <?= $sColor ?>; <?= $isPending ? 'border:1px solid rgba(235,124,42,.3); background:rgba(235,124,42,.04)' : '' ?>; border-radius:14px; overflow:hidden">

    <!-- هدر رسید -->
    <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid var(--border); flex-wrap:wrap; gap:8px">
      <div style="display:flex; align-items:center; gap:10px">
        <span style="font-size:20px"><?= $sIco ?></span>
        <div>
          <div style="font-weight:800; font-size:15px"><?= e(($r['first_name']??'?') . ' ' . ($r['last_name']??'')) ?></div>
          <div style="font-size:12px; color:var(--text-dim)" dir="ltr"><?= e($r['mobile']??'') ?></div>
        </div>
        <span style="font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px; background:<?= $sColor ?>22; color:<?= $sColor ?>"><?= e($sLabel) ?></span>
      </div>
      <div style="font-size:11px; color:var(--text-dim)"><?= num_fa(date('Y/m/d H:i', strtotime($r['created_at']))) ?></div>
    </div>

    <!-- بدنه -->
    <div style="display:grid; grid-template-columns:1fr auto; gap:16px; padding:14px 16px; align-items:start">
      <div>
        <!-- اطلاعات -->
        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px">
          <span class="info-chip"><?= icon('crown') ?> <?= e($r['plan_title'] ?: $r['plan_code']) ?></span>
          <span class="info-chip" style="color:var(--orange); font-weight:800"><?= icon('wallet') ?> <?= format_price($r['amount']) ?> ت</span>
          <?php if ($r['activate_at']): ?>
            <span class="info-chip" style="color:var(--info)"><?= icon('clock') ?> شروع: <?= num_fa(date('Y/m/d H:i', strtotime($r['activate_at']))) ?></span>
          <?php else: ?>
            <span class="info-chip">بدون زمان‌بندی</span>
          <?php endif; ?>
        </div>

        <?php if ($r['admin_note']): ?>
          <div style="font-size:12px; color:var(--danger); margin-bottom:10px"><?= icon('warning') ?> <?= e($r['admin_note']) ?></div>
        <?php endif; ?>

        <!-- دکمه‌های اقدام -->
        <?php if ($isPending): ?>
          <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">
            <form method="post" style="display:inline">
              <input type="hidden" name="receipt_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button class="btn btn-sm" style="background:rgba(56,217,169,.15); color:#38d9a9; border:1px solid rgba(56,217,169,.4); display:flex; align-items:center; gap:5px"
                onclick="return confirm('تایید و فعال‌سازی شود؟')">
                <?= icon('check') ?>
                <?php if ($r['activate_at'] && strtotime($r['activate_at']) > time() + 120): ?>
                  تایید – زمان‌بندی می‌شه
                <?php else: ?>
                  تایید – فعال‌سازی فوری
                <?php endif; ?>
              </button>
            </form>

            <button type="button" class="btn btn-danger btn-sm" onclick="toggleReject(<?= $r['id'] ?>)" style="display:flex; align-items:center; gap:5px">
              <?= icon('close') ?> رد
            </button>

            <div id="rejectForm<?= $r['id'] ?>" style="display:none; width:100%; margin-top:8px">
              <form method="post" style="display:flex; gap:8px; flex-wrap:wrap">
                <input type="hidden" name="receipt_id" value="<?= $r['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <input class="input" name="admin_note" placeholder="دلیل رد (اختیاری)" style="flex:1; min-width:180px">
                <button class="btn btn-danger btn-sm" onclick="return confirm('رد شود؟')">تایید رد</button>
              </form>
            </div>
          </div>

        <?php elseif ($r['status'] === 'approved_pending'): ?>
          <div style="font-size:12px; color:var(--info); display:flex; align-items:center; gap:6px">
            <?= icon('clock') ?> سیستم در <?= num_fa(date('Y/m/d H:i', strtotime($r['activate_at']))) ?> این اشتراک را خودکار فعال می‌کند.
          </div>
        <?php endif; ?>
      </div>

      <!-- تصویر رسید -->
      <div>
        <a href="<?= e(BASE_URL . '/' . $r['receipt_image']) ?>" target="_blank">
          <img src="<?= e(BASE_URL . '/' . $r['receipt_image']) ?>" alt="رسید"
               style="width:100px; height:80px; object-fit:cover; border-radius:8px; border:1px solid var(--border); display:block; cursor:zoom-in"
               onerror="this.style.display='none'">
        </a>
        <a href="<?= e(BASE_URL . '/' . $r['receipt_image']) ?>" target="_blank" style="font-size:10px; color:var(--orange); display:block; text-align:center; margin-top:3px">بزرگ‌تر</a>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.info-chip { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; background:rgba(255,255,255,.05); border:1px solid var(--border); border-radius:8px; font-size:12px; }
.info-chip .ico { width:13px; height:13px; }
</style>

<script>
function toggleReject(id) {
  var el = document.getElementById('rejectForm' + id);
  if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/_footer.php'; ?>
