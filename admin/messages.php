<?php
$adminPage = 'messages';
$pageTitle = 'پیام‌ها و درخواست‌ها';
include __DIR__ . '/_header.php';

$tab = $_GET['tab'] ?? 'messages';

// علامت‌گذاری خوانده شده
if (isset($_GET['read']) && (int)$_GET['read'] > 0) {
    db()->prepare("UPDATE messages SET is_read=1 WHERE id=?")->execute([(int)$_GET['read']]);
}

// تایید/رد درخواست کتاب
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

<div class="contact-tabs" style="display:flex;gap:8px;margin-bottom:18px;background:rgba(255,255,255,.05);padding:4px;border-radius:12px;border:1px solid var(--border)">
    <a href="?tab=messages" class="btn btn-sm <?= $tab==='messages'?'btn-primary':'btn-ghost' ?>" style="flex:1"><?= icon('mail') ?> پیام‌ها</a>
    <a href="?tab=book-requests" class="btn btn-sm <?= $tab==='book-requests'?'btn-primary':'btn-ghost' ?>" style="flex:1"><?= icon('book') ?> درخواست کتاب</a>
</div>

<?php if ($tab === 'messages'): ?>
<h2 style="margin-bottom:14px;display:flex;align-items:center;gap:10px"><?= icon('mail') ?> پیام‌های کاربران</h2>

<?php
try {
    $msgs = db()->query("SELECT m.*, u.first_name, u.last_name FROM messages m LEFT JOIN users u ON u.id=m.user_id ORDER BY m.is_read ASC, m.id DESC LIMIT 200")->fetchAll();
} catch (Throwable $e) {
    $msgs = [];
}

if (empty($msgs)): ?>
    <div class="alert alert-info">هنوز پیامی دریافت نشده.</div>
<?php else: ?>
<div style="overflow-x:auto">
<table class="admin-table">
  <thead><tr><th>#</th><th>نام</th><th>کاربر</th><th>موضوع</th><th>پیام</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
  <tbody>
    <?php foreach ($msgs as $m): ?>
      <tr style="<?= $m['is_read']?'':'background:rgba(235,124,42,.06)' ?>">
        <td><?= num_fa($m['id']) ?></td>
        <td><b><?= e($m['name']) ?></b></td>
        <td><?= $m['first_name'] ? e($m['first_name'].' '.$m['last_name']) : '<span style="color:var(--text-muted)">ناشناس</span>' ?></td>
        <td><?= e(mb_substr($m['subject'],0,40)) ?></td>
        <td style="max-width:300px"><?= nl2br(e(mb_substr($m['body'],0,150))) ?><?php if(mb_strlen($m['body'])>150) echo '...' ?></td>
        <td>
          <?php if (!$m['is_read']): ?>
            <a href="?tab=messages&read=<?= $m['id'] ?>" class="btn btn-ghost btn-sm"><?= icon('eye') ?> خواندن</a>
          <?php else: ?>
            <span style="color:var(--success)"><?= icon('check') ?> خوانده شده</span>
          <?php endif; ?>
        </td>
        <td><?= num_fa(date('Y/m/d H:i', strtotime($m['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php else: ?>
<h2 style="margin-bottom:14px;display:flex;align-items:center;gap:10px"><?= icon('book') ?> درخواست‌های کتاب</h2>

<?php
try {
    $reqs = db()->query("SELECT br.*, u.first_name, u.last_name, u.mobile FROM book_requests br LEFT JOIN users u ON u.id=br.user_id ORDER BY br.status='pending' DESC, br.id DESC LIMIT 200")->fetchAll();
} catch (Throwable $e) {
    $reqs = [];
}

$filter = $_GET['status'] ?? '';
if ($filter) {
    try {
        $stmt = db()->prepare("SELECT br.*, u.first_name, u.last_name, u.mobile FROM book_requests br LEFT JOIN users u ON u.id=br.user_id WHERE br.status=? ORDER BY br.id DESC LIMIT 200");
        $stmt->execute([$filter]);
        $reqs = $stmt->fetchAll();
    } catch (Throwable $e) { $reqs = []; }
}
?>

<form method="get" style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap">
    <input type="hidden" name="tab" value="book-requests">
    <button type="submit" class="btn btn-sm <?= !$filter?'btn-primary':'btn-ghost' ?>" formaction="?tab=book-requests">همه</button>
    <button type="submit" class="btn btn-sm <?= $filter==='pending'?'btn-primary':'btn-ghost' ?>" formaction="?tab=book-requests&status=pending">در انتظار</button>
    <button type="submit" class="btn btn-sm <?= $filter==='approved'?'btn-primary':'btn-ghost' ?>" formaction="?tab=book-requests&status=approved">تایید شده</button>
    <button type="submit" class="btn btn-sm <?= $filter==='rejected'?'btn-primary':'btn-ghost' ?>" formaction="?tab=book-requests&status=rejected">رد شده</button>
</form>

<?php if (empty($reqs)): ?>
    <div class="alert alert-info">هنوز درخواستی ثبت نشده.</div>
<?php else: ?>
<div style="overflow-x:auto">
<table class="admin-table">
  <thead><tr><th>#</th><th>عنوان</th><th>پایه</th><th>رشته</th><th>درس</th><th>درخواست‌کننده</th><th>وضعیت</th><th>عملیات</th></tr></thead>
  <tbody>
    <?php foreach ($reqs as $r): ?>
      <tr>
        <td><?= num_fa($r['id']) ?></td>
        <td><b><?= e($r['title']) ?></b><?php if($r['description']): ?><br><small style="color:var(--text-dim)"><?= e(mb_substr($r['description'],0,50)) ?></small><?php endif; ?></td>
        <td>پایه <?= num_fa($r['grade']) ?></td>
        <td><?= e(major_label($r['major'])) ?></td>
        <td><?= e($r['subject']) ?></td>
        <td><?= e(($r['first_name']??'?').' '.($r['last_name']??'')) ?><br><small dir="ltr" style="color:var(--text-dim)"><?= e($r['mobile']??'') ?></small></td>
        <td>
          <?php if ($r['status']==='pending'): ?><span style="color:#ffb86b">در انتظار</span>
          <?php elseif ($r['status']==='approved'): ?><span style="color:#38d9a9">تایید شده</span>
          <?php else: ?><span style="color:#ff5470">رد شده</span><?php endif; ?>
        </td>
        <td>
          <?php if ($r['status']==='pending'): ?>
            <div style="display:flex;gap:4px">
              <form method="post"><input type="hidden" name="req_id" value="<?= $r['id'] ?>"><input type="hidden" name="req_action" value="approved"><button class="btn btn-sm" style="background:rgba(56,217,169,.15);color:#38d9a9;border:1px solid rgba(56,217,169,.3)">تایید</button></form>
              <form method="post"><input type="hidden" name="req_id" value="<?= $r['id'] ?>"><input type="hidden" name="req_action" value="rejected"><button class="btn btn-danger btn-sm">رد</button></form>
            </div>
          <?php else: ?><span style="color:var(--text-muted);font-size:12px">انجام شده</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
