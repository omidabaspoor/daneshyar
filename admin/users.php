<?php
$adminPage = 'users';
$pageTitle  = 'مدیریت کاربران';
include __DIR__ . '/_header.php';

$msg     = '';
$msgType = 'success';

/* ===== اقدامات ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0) {
        switch ($action) {
            case 'ban':
                $reason = trim($_POST['ban_reason'] ?? '');
                ban_user($id, $reason);
                $msg = 'کاربر مسدود شد.';
                break;
            case 'unban':
                unban_user($id);
                $msg = 'کاربر رفع مسدودی شد.';
                break;
            case 'delete':
                // حذف کامل کاربر و همه داده‌هایش
                try {
                    db()->prepare("DELETE FROM chat_history WHERE user_id=?")->execute([$id]);
                    db()->prepare("DELETE FROM chats WHERE user_id=?")->execute([$id]);
                    db()->prepare("DELETE FROM transactions WHERE user_id=?")->execute([$id]);
                    db()->prepare("DELETE FROM card_receipts WHERE user_id=?")->execute([$id]);
                    db()->prepare("DELETE FROM user_sessions WHERE user_id=?")->execute([$id]);
                    db()->prepare("DELETE FROM users WHERE id=? AND role!='admin'")->execute([$id]);
                    $msg = 'کاربر و تمام داده‌هایش حذف شد.';
                } catch (Throwable $e) {
                    $msg = 'خطا در حذف: ' . $e->getMessage(); $msgType = 'error';
                }
                break;
            case 'activate':
                if (!empty($_POST['plan'])) {
                    activate_subscription($id, $_POST['plan'], null, 'manual', 'admin_grant');
                    $msg = 'اشتراک دستی فعال شد.';
                }
                break;
            case 'cancel_sub':
                // قطع فوری اشتراک
                db()->prepare("UPDATE users SET subscription_type='none', subscription_start=NULL, subscription_end=NOW() WHERE id=?")
                    ->execute([$id]);
                $msg = 'اشتراک کاربر قطع شد.';
                break;
            case 'reset_sub':
                // ریست کامل (مصرف + اشتراک)
                db()->prepare("UPDATE users SET subscription_type='none', subscription_start=NULL, subscription_end=NULL, messages_used_total=0, messages_used_today=0, free_used_today=0 WHERE id=?")
                    ->execute([$id]);
                $msg = 'اشتراک و مصرف کاربر ریست شد.';
                break;
        }
    }
}

/* ===== جستجو و فیلتر ===== */
$q          = trim($_GET['q'] ?? '');
$filterSub  = $_GET['sub'] ?? '';
$filterBan  = $_GET['ban'] ?? '';
$page       = max(1, (int)($_GET['p'] ?? 1));
$perPage    = 30;
$offset     = ($page - 1) * $perPage;

$wheres = ["u.role != 'admin'"];
$params = [];

if ($q !== '') {
    $wheres[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.mobile LIKE ? OR u.school LIKE ?)";
    $w = "%$q%"; $params = array_merge($params, [$w,$w,$w,$w]);
}
if ($filterSub === 'active') {
    $wheres[] = "u.subscription_end > NOW()";
} elseif ($filterSub === 'none') {
    $wheres[] = "(u.subscription_type='none' OR u.subscription_end IS NULL OR u.subscription_end < NOW())";
}
if ($filterBan === '1') {
    $wheres[] = "u.status='banned'";
} elseif ($filterBan === '0') {
    $wheres[] = "(u.status='active' OR u.status IS NULL)";
}

$whereStr = "WHERE " . implode(' AND ', $wheres);

// شمارش کل
$totalCount = (int)db()->prepare("SELECT COUNT(*) FROM users u $whereStr")->execute($params) ?
    db()->prepare("SELECT COUNT(*) FROM users u $whereStr")->execute($params) && true ? 0 : 0 : 0;
$cntStmt = db()->prepare("SELECT COUNT(*) FROM users u $whereStr");
$cntStmt->execute($params);
$totalCount = (int)$cntStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = db()->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM chats c WHERE c.user_id=u.id) AS chats_count,
           (SELECT COUNT(*) FROM chat_history h WHERE h.user_id=u.id) AS msg_count
    FROM users u
    $whereStr
    ORDER BY u.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// آمار کلی
$stats = [
    'total'   => (int)db()->query("SELECT COUNT(*) FROM users WHERE role!='admin'")->fetchColumn(),
    'active'  => (int)db()->query("SELECT COUNT(*) FROM users WHERE role!='admin' AND subscription_end > NOW()")->fetchColumn(),
    'banned'  => (int)db()->query("SELECT COUNT(*) FROM users WHERE status='banned'")->fetchColumn(),
    'today'   => (int)db()->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
];

$plans = db()->query("SELECT plan_code, title FROM pricing ORDER BY price")->fetchAll();
?>

<!-- آمار سریع -->
<div class="stat-grid" style="margin-bottom:20px">
  <div class="stat-card glass"><div class="s-icon"><?= icon('users') ?></div><div><div class="l">کل کاربران</div><div class="v"><?= num_fa($stats['total']) ?></div></div></div>
  <div class="stat-card glass"><div class="s-icon"><?= icon('crown') ?></div><div><div class="l">اشتراک فعال</div><div class="v"><?= num_fa($stats['active']) ?></div></div></div>
  <div class="stat-card glass"><div class="s-icon"><?= icon('warning') ?></div><div><div class="l">مسدود</div><div class="v" style="color:var(--danger)"><?= num_fa($stats['banned']) ?></div></div></div>
  <div class="stat-card glass"><div class="s-icon"><?= icon('sparkle') ?></div><div><div class="l">امروز ثبت‌نام</div><div class="v"><?= num_fa($stats['today']) ?></div></div></div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>" style="margin-bottom:16px"><?= e($msg) ?></div>
<?php endif; ?>

<!-- جستجو و فیلتر -->
<div class="glass" style="padding:14px; margin-bottom:16px; border-radius:14px">
  <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center">
    <div style="flex:1; min-width:200px; position:relative">
      <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--text-dim); pointer-events:none"><?= icon('search') ?></span>
      <input class="input" name="q" placeholder="نام، موبایل، مدرسه..." value="<?= e($q) ?>" style="padding-right:38px">
    </div>
    <select class="select" name="sub" style="min-width:140px">
      <option value="" <?= !$filterSub?'selected':'' ?>>همه اشتراک‌ها</option>
      <option value="active" <?= $filterSub==='active'?'selected':'' ?>>اشتراک فعال</option>
      <option value="none" <?= $filterSub==='none'?'selected':'' ?>>بدون اشتراک</option>
    </select>
    <select class="select" name="ban" style="min-width:120px">
      <option value="" <?= $filterBan===''?'selected':'' ?>>همه وضعیت‌ها</option>
      <option value="0" <?= $filterBan==='0'?'selected':'' ?>>فعال</option>
      <option value="1" <?= $filterBan==='1'?'selected':'' ?>>مسدود</option>
    </select>
    <button class="btn btn-primary" type="submit"><?= icon('search') ?> جستجو</button>
    <?php if ($q || $filterSub || $filterBan): ?>
      <a href="?" class="btn btn-ghost">پاک کردن</a>
    <?php endif; ?>
  </form>
</div>

<!-- جدول کاربران -->
<div class="glass" style="border-radius:14px; overflow:hidden; border:1px solid var(--border)">
  <div style="overflow-x:auto">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>کاربر</th>
        <th>موبایل</th>
        <th>پایه / رشته</th>
        <th>اشتراک</th>
        <th>فعالیت</th>
        <th>وضعیت</th>
        <th>عملیات</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($users)): ?>
      <tr><td colspan="8" style="text-align:center; padding:40px; color:var(--text-dim)">کاربری یافت نشد.</td></tr>
    <?php endif; ?>
    <?php foreach ($users as $u):
      $isBanned = ($u['status'] ?? 'active') === 'banned';
      $sub = subscription_status($u);
    ?>
      <tr style="<?= $isBanned ? 'opacity:.6; background:rgba(255,84,112,.04)' : '' ?>">

        <td style="color:var(--text-dim); font-size:12px"><?= num_fa($u['id']) ?></td>

        <td>
          <a href="<?= BASE_URL ?>/admin/user.php?id=<?= $u['id'] ?>" style="font-weight:700; color:var(--orange); text-decoration:none">
            <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
          </a>
          <?php if ($u['school']): ?>
            <br><small style="color:var(--text-dim); font-size:11px"><?= e($u['school']) ?></small>
          <?php endif; ?>
        </td>

        <td dir="ltr" style="font-family:monospace; font-size:13px"><?= e($u['mobile']) ?></td>

        <td>
          <span style="font-size:13px">پایه <?= num_fa($u['grade']) ?></span>
          <br><small style="color:var(--text-dim); font-size:11px"><?= e(major_label($u['major'] ?? 'math')) ?></small>
        </td>

        <td>
          <?php if ($sub['active']): ?>
            <span style="color:var(--success); font-size:12px; font-weight:700">✅ <?= e($sub['plan']['title'] ?? '') ?></span>
            <br><small style="color:var(--text-dim); font-size:10px"><?= time_left($u['subscription_end']) ?></small>
          <?php else: ?>
            <span style="color:var(--text-muted); font-size:12px">بدون اشتراک</span>
          <?php endif; ?>
        </td>

        <td style="font-size:12px">
          <div><?= icon('chat') ?> <?= num_fa($u['chats_count'] ?? 0) ?> چت</div>
          <div style="color:var(--text-dim)"><?= icon('send') ?> <?= num_fa($u['msg_count'] ?? 0) ?> پیام</div>
        </td>

        <td>
          <?php if ($isBanned): ?>
            <span style="background:rgba(255,84,112,.15); color:#ff5470; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700">🚫 مسدود</span>
            <?php if ($u['ban_reason']): ?>
              <br><small style="color:var(--text-dim); font-size:10px"><?= e(mb_substr($u['ban_reason'], 0, 30)) ?></small>
            <?php endif; ?>
          <?php else: ?>
            <span style="background:rgba(56,217,169,.1); color:#38d9a9; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700">✓ فعال</span>
          <?php endif; ?>
        </td>

        <td>
          <div style="display:flex; gap:5px; flex-wrap:wrap">
            <!-- لینک جزئیات -->
            <a href="<?= BASE_URL ?>/admin/user.php?id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm" title="جزئیات"><?= icon('eye') ?></a>

            <!-- مسدود / رفع مسدودی -->
            <?php if ($isBanned): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <input type="hidden" name="action" value="unban">
                <button class="btn btn-sm" style="background:rgba(56,217,169,.15); color:#38d9a9; border:1px solid rgba(56,217,169,.3)" title="رفع مسدودی" onclick="return confirm('رفع مسدودی شود؟')"><?= icon('check') ?></button>
              </form>
            <?php else: ?>
              <button class="btn btn-danger btn-sm" title="مسدود کردن" onclick="showBanForm(<?= $u['id'] ?>)"><?= icon('lock') ?></button>
            <?php endif; ?>

            <!-- فعال‌سازی اشتراک دستی -->
            <button type="button" class="btn btn-ghost btn-sm" title="فعال‌سازی / ویرایش اشتراک" onclick="showActivateForm(<?= $u['id'] ?>)"><?= icon('crown') ?></button>

            <!-- حذف -->
            <form method="post" style="display:inline">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button class="btn btn-danger btn-sm" title="حذف کاربر"
                onclick="return confirm('کاربر و تمام داده‌هایش حذف شود؟ این عمل برگشت‌پذیر نیست!')"><?= icon('trash') ?></button>
            </form>
          </div>

          <!-- فرم مسدود کردن -->
          <div id="banForm<?= $u['id'] ?>" style="display:none; margin-top:8px">
            <form method="post" style="display:flex; flex-direction:column; gap:5px">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="ban">
              <input class="input" name="ban_reason" placeholder="دلیل مسدودی (اختیاری)" style="font-size:12px; padding:6px 10px">
              <button class="btn btn-danger btn-sm" onclick="return confirm('مسدود شود؟')">تایید مسدودی</button>
            </form>
          </div>

          <!-- فرم مدیریت اشتراک -->
          <div id="activateForm<?= $u['id'] ?>" style="display:none; margin-top:8px; padding:10px; background:rgba(255,255,255,.04); border:1px solid var(--border); border-radius:10px">
            <div style="font-size:11px; color:var(--text-dim); margin-bottom:8px; font-weight:700">مدیریت اشتراک</div>
            <!-- فعال‌سازی -->
            <form method="post" style="display:flex; gap:5px; margin-bottom:6px; flex-wrap:wrap">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="activate">
              <select name="plan" class="select" style="font-size:12px; padding:5px 8px; flex:1; min-width:100px">
                <?php foreach ($plans as $pl): ?>
                  <option value="<?= e($pl['plan_code']) ?>"><?= e($pl['title']) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-primary btn-sm" onclick="return confirm('اشتراک فعال شود؟')" style="white-space:nowrap"><?= icon('check') ?> فعال</button>
            </form>
            <?php if ($sub['active']): ?>
            <!-- قطع اشتراک -->
            <form method="post" style="display:inline">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="cancel_sub">
              <button class="btn btn-sm" style="background:rgba(255,84,112,.12); color:#ff5470; border:1px solid rgba(255,84,112,.3); font-size:11px" onclick="return confirm('اشتراک قطع شود؟')"><?= icon('close') ?> قطع اشتراک</button>
            </form>
            <?php endif; ?>
            <!-- ریست مصرف -->
            <form method="post" style="display:inline; margin-right:4px">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="reset_sub">
              <button class="btn btn-sm" style="background:rgba(255,184,107,.1); color:#ffb86b; border:1px solid rgba(255,184,107,.3); font-size:11px" onclick="return confirm('اشتراک و مصرف ریست شود؟')"><?= icon('refresh') ?> ریست کامل</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- صفحه‌بندی -->
<?php if ($totalPages > 1): ?>
<div style="display:flex; justify-content:center; gap:6px; margin-top:16px; flex-wrap:wrap">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="?q=<?= e($q) ?>&sub=<?= e($filterSub) ?>&ban=<?= e($filterBan) ?>&p=<?= $p ?>"
       class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>"><?= num_fa($p) ?></a>
  <?php endfor; ?>
</div>
<div style="text-align:center; margin-top:8px; color:var(--text-dim); font-size:12px">
  <?= num_fa($totalCount) ?> کاربر · صفحه <?= num_fa($page) ?> از <?= num_fa($totalPages) ?>
</div>
<?php endif; ?>

<script>
function showBanForm(id) {
  var el = document.getElementById('banForm' + id);
  if (el) { el.style.display = el.style.display === 'none' ? 'block' : 'none'; }
  // پنهان کردن فرم‌های دیگر
  document.querySelectorAll('[id^="activateForm"]').forEach(function(e){ e.style.display='none'; });
}
function showActivateForm(id) {
  var el = document.getElementById('activateForm' + id);
  if (el) { el.style.display = el.style.display === 'none' ? 'block' : 'none'; }
  document.querySelectorAll('[id^="banForm"]').forEach(function(e){ e.style.display='none'; });
}
</script>

<?php include __DIR__ . '/_footer.php'; ?>
