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
            $activateAt = $receipt['activate_at'];
            $now = time();
            if ($activateAt && strtotime($activateAt) > $now + 120) {
                db()->prepare("UPDATE card_receipts SET status='approved_pending', reviewed_at=NOW(), reviewed_by=? WHERE id=?")
                    ->execute([current_admin()['id'], $rid]);
                $diffMin = round((strtotime($activateAt) - $now) / 60);
                $msg = '✓ تایید شد. اشتراک در ' . num_fa($diffMin) . ' دقیقه دیگر خودکار فعال می‌شه.';
            } else {
                $ok = activate_subscription($receipt['user_id'], $receipt['plan_code'], $activateAt ?: null, 'card', 'receipt_' . $rid);
                if ($ok) {
                    db()->prepare("UPDATE card_receipts SET status='approved', reviewed_at=NOW(), reviewed_by=? WHERE id=?")
                        ->execute([current_admin()['id'], $rid]);
                    $msg = '✓ اشتراک کاربر همین الان فعال شد!';
                } else { $msg = 'خطا در فعال‌سازی.'; $msgType = 'error'; }
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

$counts = [];
foreach (['pending','approved_pending','approved','rejected'] as $s) {
    $counts[$s] = (int)db()->query("SELECT COUNT(*) FROM card_receipts WHERE status='$s'")->fetchColumn();
}
$pendingAll = $counts['pending'] + $counts['approved_pending'];

$filterLabels = [
    ''                 => 'همه',
    'pending'          => 'در انتظار',
    'approved_pending' => 'تایید زمان‌دار',
    'approved'         => 'فعال شده',
    'rejected'         => 'رد شده',
];
?>

<div class="admin-page-header">
  <h2>
    <?= icon('wallet') ?> رسیدهای پرداخت
    <?php if ($pendingAll > 0): ?>
      <span class="status-badge status-pending" style="font-size:12px; margin-right:8px"><?= num_fa($pendingAll) ?> نیاز به بررسی</span>
    <?php endif; ?>
  </h2>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<!-- ═══ تب‌های فیلتر ═══ -->
<div class="admin-tabs">
  <?php foreach ($filterLabels as $f => $label): ?>
    <a href="?status=<?= e($f) ?>" class="btn btn-sm <?= $filter === $f ? 'btn-primary' : 'btn-ghost' ?>">
      <?= e($label) ?>
      <?php $cnt = ($f === '' ? array_sum($counts) : ($counts[$f] ?? 0)); if ($cnt > 0): ?>
        <span style="background:rgba(255,255,255,.2); border-radius:8px; padding:0 6px; font-size:10px; margin-right:2px"><?= num_fa($cnt) ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (empty($receipts)): ?>
  <div class="admin-card glass">
    <div class="admin-card-body">
      <div class="admin-empty"><?= icon('check') ?> موردی برای نمایش وجود ندارد.</div>
    </div>
  </div>
<?php else: ?>

<div style="display:grid; gap:14px">
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
  <div class="admin-card receipt-card glass" style="border-right-color:<?= $sColor ?>">

    <div class="receipt-header">
      <div style="display:flex; align-items:center; gap:12px">
        <span style="font-size:22px"><?= $sIco ?></span>
        <div>
          <div style="font-weight:800; font-size:15px"><?= e(($r['first_name']??'?') . ' ' . ($r['last_name']??'')) ?></div>
          <div class="text-sm text-dim" dir="ltr"><?= e($r['mobile']??'') ?></div>
        </div>
        <span class="status-badge" style="background:<?= $sColor ?>18; color:<?= $sColor ?>; border-color:<?= $sColor ?>33"><?= e($sLabel) ?></span>
      </div>
      <div class="text-sm text-dim"><?= num_fa(date('Y/m/d H:i', strtotime($r['created_at']))) ?></div>
    </div>

    <div class="receipt-body">
      <div>
        <div class="receipt-chips">
          <span class="receipt-chip"><?= icon('crown') ?> <?= e($r['plan_title'] ?: $r['plan_code']) ?></span>
          <span class="receipt-chip" style="color:var(--orange); font-weight:800"><?= icon('wallet') ?> <?= format_price($r['amount']) ?> ت</span>
          <?php if ($r['activate_at']): ?>
            <span class="receipt-chip" style="color:var(--info)"><?= icon('clock') ?> <?= num_fa(date('Y/m/d H:i', strtotime($r['activate_at']))) ?></span>
          <?php endif; ?>
        </div>

        <?php if ($r['admin_note']): ?>
          <div class="text-sm" style="color:var(--danger); margin-bottom:10px"><?= icon('warning') ?> <?= e($r['admin_note']) ?></div>
        <?php endif; ?>

        <?php if ($isPending): ?>
          <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:8px">
            <form method="post" style="display:inline">
              <input type="hidden" name="receipt_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button class="btn btn-sm" style="background:rgba(56,217,169,.12); color:#38d9a9; border:1px solid rgba(56,217,169,.3)"
                onclick="return confirm('تایید و فعال‌سازی شود؟')">
                <?= icon('check') ?>
                <?php if ($r['activate_at'] && strtotime($r['activate_at']) > time() + 120): ?>
                  تایید زمان‌دار
                <?php else: ?>
                  فعال‌سازی فوری
                <?php endif; ?>
              </button>
            </form>

            <button type="button" class="btn btn-danger btn-sm" onclick="toggleReject(<?= $r['id'] ?>)"><?= icon('close') ?> رد</button>

            <div id="rejectForm<?= $r['id'] ?>" style="display:none; width:100%; margin-top:8px">
              <form method="post" style="display:flex; gap:8px; flex-wrap:wrap">
                <input type="hidden" name="receipt_id" value="<?= $r['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <input class="input" name="admin_note" placeholder="دلیل رد (اختیاری)" style="flex:1; min-width:160px; font-size:12px; padding:7px 10px">
                <button class="btn btn-danger btn-sm" onclick="return confirm('رد شود؟')">تایید رد</button>
              </form>
            </div>
          </div>

        <?php elseif ($r['status'] === 'approved_pending'): ?>
          <div class="text-sm" style="color:var(--info); display:flex; align-items:center; gap:6px; margin-top:4px">
            <?= icon('clock') ?> فعال‌سازی خودکار در <?= num_fa(date('Y/m/d H:i', strtotime($r['activate_at']))) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="receipt-image-box">
        <a href="<?= e(BASE_URL . '/' . $r['receipt_image']) ?>" target="_blank">
          <img src="<?= e(BASE_URL . '/' . $r['receipt_image']) ?>" alt="رسید"
               onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
          <span class="text-muted text-sm" style="display:none; padding:20px; text-align:center">تصویر یافت نشد</span>
        </a>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function toggleReject(id) {
  var el = document.getElementById('rejectForm' + id);
  if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/_footer.php'; ?>
