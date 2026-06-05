<?php
$adminPage = 'messages';
$pageTitle = 'پیام‌ها و درخواست‌ها';
include __DIR__ . '/_header.php';

$tab = $_GET['tab'] ?? 'messages';

if (isset($_GET['read']) && (int)$_GET['read'] > 0) {
    db()->prepare("UPDATE messages SET is_read=1 WHERE id=?")->execute([(int)$_GET['read']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['req_action'])) {
    $id = (int)($_POST['req_id'] ?? 0);
    $act = $_POST['req_action'] ?? '';
    if ($id > 0 && in_array($act, ['approved','rejected'])) {
        try {
            db()->prepare("UPDATE book_requests SET status=?, reviewed_at=NOW() WHERE id=?")->execute([$act, $id]);
        } catch (Throwable $e) {}
    }
}
?>

<div class="admin-page-header">
  <h2><?= icon('mail') ?> <?= $tab === 'messages' ? 'پیام‌های کاربران' : 'درخواست‌های کتاب' ?></h2>
</div>

<!-- ═══ تب‌ها ═══ -->
<div class="admin-tabs">
  <a href="?tab=messages" class="btn btn-sm <?= $tab==='messages'?'btn-primary':'btn-ghost' ?>"><?= icon('mail') ?> پیام‌ها</a>
  <a href="?tab=book-requests" class="btn btn-sm <?= $tab==='book-requests'?'btn-primary':'btn-ghost' ?>"><?= icon('book') ?> درخواست کتاب</a>
</div>

<?php if ($tab === 'messages'): ?>

<?php
try {
    $msgs = db()->query("SELECT m.*, u.first_name, u.last_name FROM messages m LEFT JOIN users u ON u.id=m.user_id ORDER BY m.is_read ASC, m.id DESC LIMIT 200")->fetchAll();
} catch (Throwable $e) {
    $msgs = [];
}
?>

<div class="admin-card glass">
  <div class="admin-card-body" style="padding:0">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>#</th><th>نام</th><th>کاربر</th><th>موضوع</th><th>پیام</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
        <tbody>
        <?php if (empty($msgs)): ?>
          <tr><td colspan="7" class="admin-empty">هنوز پیامی دریافت نشده.</td></tr>
        <?php else: foreach ($msgs as $m): ?>
          <tr style="<?= $m['is_read']?'':'background:rgba(235,124,42,.04)' ?>">
            <td class="text-muted"><?= num_fa($m['id']) ?></td>
            <td style="font-weight:700"><?= e($m['name']) ?></td>
            <td><?= $m['first_name'] ? e($m['first_name'].' '.$m['last_name']) : '<span class="text-muted">ناشناس</span>' ?></td>
            <td><?= e(mb_substr($m['subject'],0,40)) ?></td>
            <td style="max-width:300px; white-space:normal"><?= nl2br(e(mb_substr($m['body'],0,150))) ?><?php if(mb_strlen($m['body'])>150) echo '...' ?></td>
            <td>
              <?php if (!$m['is_read']): ?>
                <a href="?tab=messages&read=<?= $m['id'] ?>" class="btn btn-ghost btn-sm"><?= icon('eye') ?> خواندن</a>
              <?php else: ?>
                <span class="status-badge status-approved">خوانده شده</span>
              <?php endif; ?>
            </td>
            <td class="text-dim text-sm"><?= num_fa(date('Y/m/d H:i', strtotime($m['created_at']))) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php else: ?>

<?php
$filter = $_GET['status'] ?? '';
try {
    if ($filter) {
        $stmt = db()->prepare("SELECT br.*, u.first_name, u.last_name, u.mobile FROM book_requests br LEFT JOIN users u ON u.id=br.user_id WHERE br.status=? ORDER BY br.id DESC LIMIT 200");
        $stmt->execute([$filter]);
        $reqs = $stmt->fetchAll();
    } else {
        $reqs = db()->query("SELECT br.*, u.first_name, u.last_name, u.mobile FROM book_requests br LEFT JOIN users u ON u.id=br.user_id ORDER BY br.status='pending' DESC, br.id DESC LIMIT 200")->fetchAll();
    }
} catch (Throwable $e) {
    $reqs = [];
}
?>

<div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap">
  <a href="?tab=book-requests" class="btn btn-sm <?= !$filter?'btn-primary':'btn-ghost' ?>">همه</a>
  <a href="?tab=book-requests&status=pending" class="btn btn-sm <?= $filter==='pending'?'btn-primary':'btn-ghost' ?>">در انتظار</a>
  <a href="?tab=book-requests&status=approved" class="btn btn-sm <?= $filter==='approved'?'btn-primary':'btn-ghost' ?>">تایید شده</a>
  <a href="?tab=book-requests&status=rejected" class="btn btn-sm <?= $filter==='rejected'?'btn-primary':'btn-ghost' ?>">رد شده</a>
</div>

<div class="admin-card glass">
  <div class="admin-card-body" style="padding:0">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>#</th><th>عنوان</th><th>پایه</th><th>رشته</th><th>درس</th><th>درخواست‌کننده</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php if (empty($reqs)): ?>
          <tr><td colspan="8" class="admin-empty">هنوز درخواستی ثبت نشده.</td></tr>
        <?php else: foreach ($reqs as $r): ?>
          <tr>
            <td class="text-muted"><?= num_fa($r['id']) ?></td>
            <td>
              <b><?= e($r['title']) ?></b>
              <?php if($r['description']): ?><br><small class="text-dim"><?= e(mb_substr($r['description'],0,50)) ?></small><?php endif; ?>
            </td>
            <td>پایه <?= num_fa($r['grade']) ?></td>
            <td><?= e(major_label($r['major'])) ?></td>
            <td><?= e($r['subject']) ?></td>
            <td>
              <?= e(($r['first_name']??'?').' '.($r['last_name']??'')) ?>
              <br><small dir="ltr" class="text-dim"><?= e($r['mobile']??'') ?></small>
            </td>
            <td>
              <?php if ($r['status']==='pending'): ?>
                <span class="status-badge status-pending">در انتظار</span>
              <?php elseif ($r['status']==='approved'): ?>
                <span class="status-badge status-approved">تایید شده</span>
              <?php else: ?>
                <span class="status-badge status-rejected">رد شده</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['status']==='pending'): ?>
                <div style="display:flex;gap:4px">
                  <form method="post">
                    <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="req_action" value="approved">
                    <button class="btn btn-sm" style="background:rgba(56,217,169,.12);color:#38d9a9;border:1px solid rgba(56,217,169,.25)">تایید</button>
                  </form>
                  <form method="post">
                    <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="req_action" value="rejected">
                    <button class="btn btn-danger btn-sm">رد</button>
                  </form>
                </div>
              <?php else: ?>
                <span class="text-muted text-sm">انجام شده</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
